<?php
declare(strict_types=1);

/**
 * API: Timbrado de Facturas con Facturama
 * Endpoint: POST /backend/api/facturacion/timbrar.php
 * 
 * Genera y timbra una factura electrónica (CFDI 4.0) a partir de un ticket
 * existente, integrándose con el servicio de Facturama.
 * 
 * Características:
 * - Validación de datos fiscales según formato SAT
 * - Idempotencia: evita timbrados duplicados
 * - Cálculo automático de IVA
 * - Descarga y almacenamiento de XML y PDF
 * - Rate limiting para prevenir abuso
 */

require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/config/facturama.php';
require_once dirname(__DIR__, 3) . '/utils/response.php';
require_once dirname(__DIR__, 3) . '/utils/security.php';

/**
 * Construye los items (conceptos) del CFDI a partir de los detalles del ticket
 * Calcula automáticamente el IVA (16%) para cada concepto
 * 
 * @param PDO $pdo Conexión a base de datos
 * @param int $ticketId ID del ticket
 * @return array Lista de items en formato Facturama
 */
function build_items(PDO $pdo, int $ticketId): array
{
    $q = $pdo->prepare('
        SELECT 
            td.id, 
            td.producto_id, 
            p.nombre AS descripcion, 
            td.cantidad, 
            td.precio_unitario
        FROM ticket_detalles td
        LEFT JOIN productos p ON p.id = td.producto_id
        WHERE td.ticket_id = ?
    ');
    $q->execute([$ticketId]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    
    $items = [];
    
    foreach ($rows as $r) {
        $qty = (float)$r['cantidad'];
        $pu  = (float)$r['precio_unitario'];
        $subtotal = round($qty * $pu, 2);
        
        // Cálculo de IVA (16%)
        $ivaBase = $subtotal;
        $iva = round($ivaBase * 0.16, 2);
        $total = round($subtotal + $iva, 2);
        
        $items[] = [
            'ProductCode' => '01010101', // Clave producto SAT genérica
            'IdentificationNumber' => (string)$r['producto_id'],
            'Description' => $r['descripcion'] ?: 'Producto sin descripción',
            'Unit' => 'Unidad',
            'UnitCode' => 'H87', // Clave unidad SAT: Pieza
            'UnitPrice' => $pu,
            'Quantity' => $qty,
            'Subtotal' => $subtotal,
            'Taxes' => [[
                'Name' => 'IVA',
                'Rate' => 0.16,
                'IsRetention' => false,
                'Base' => $ivaBase,
                'Total' => $iva,
            ]],
            'Total' => $total,
        ];
    }
    
    return $items;
}

try {
    // ============================================
    // VALIDACIÓN INICIAL
    // ============================================
    
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error(['Método no permitido', 405]);
    }

    // Rate limiting: máximo 30 peticiones en 10 minutos
    require_rate_limit('timbrar', 30, 600);
    
    // Validación CSRF
    require_csrf_token();

    // Verificar configuración de Facturama
    if (!Facturama::isConfigured()) {
        json_error(['Configuración de Facturama no disponible', 500]);
    }

    // ============================================
    // PARSEAR Y VALIDAR PARÁMETROS
    // ============================================
    
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    
    $ticketId = (int)($body['ticket_id'] ?? 0);
    $rfc = strtoupper(trim((string)($body['rfc'] ?? '')));
    $razon = trim((string)($body['razon_social'] ?? ''));
    $regimen = trim((string)($body['regimen'] ?? ''));
    $cp = trim((string)($body['cp'] ?? ''));
    $uso = trim((string)($body['uso_cfdi'] ?? ''));
    $correo = trim((string)($body['correo'] ?? ''));
    $paymentMethod = trim((string)($body['payment_method'] ?? 'PUE')); // Pago en una exhibición
    $paymentForm = trim((string)($body['payment_form'] ?? '03')); // Transferencia electrónica
    $observaciones = trim((string)($body['observaciones'] ?? ''));

    // Validaciones
    if ($ticketId <= 0) {
        json_error(['ticket_id requerido', 422]);
    }
    
    // Validar formato RFC según reglas del SAT
    if (!preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/u', $rfc)) {
        json_error(['RFC inválido. Formato: 3-4 letras, 6 dígitos, 3 caracteres', 422]);
    }
    
    // Validar campos requeridos
    if ($razon === '' || $regimen === '' || $cp === '' || $uso === '') {
        json_error(['Datos fiscales incompletos (razón social, régimen, CP, uso CFDI)', 422]);
    }

    // ============================================
    // INICIAR TRANSACCIÓN
    // ============================================
    
    $pdo = DB::get();
    $pdo->beginTransaction();

    // ============================================
    // IDEMPOTENCIA: Verificar factura existente
    // ============================================
    
    $q = $pdo->prepare('
        SELECT id, facturama_id, uuid, xml_path, pdf_path 
        FROM facturas 
        WHERE ticket_id = ? AND estado = "generada" 
        ORDER BY id DESC 
        LIMIT 1
    ');
    $q->execute([$ticketId]);
    $existente = $q->fetch(PDO::FETCH_ASSOC);
    
    if ($existente && !empty($existente['uuid'])) {
        $pdo->commit();
        
        json_response([
            'ok' => true,
            'factura_id' => (int)$existente['id'],
            'uuid' => $existente['uuid'],
            'xml_url' => '/tokyo/api/public/facturacion/descargar.php?uuid=' . urlencode($existente['uuid']) . '&tipo=xml',
            'pdf_url' => '/tokyo/api/public/facturacion/descargar.php?uuid=' . urlencode($existente['uuid']) . '&tipo=pdf',
            'idempotent' => true
        ]);
    }

    // ============================================
    // UPSERT CLIENTE FISCAL
    // ============================================
    
    $csel = $pdo->prepare('SELECT id FROM clientes_facturacion WHERE rfc = ? LIMIT 1');
    $csel->execute([$rfc]);
    $clienteId = (int)($csel->fetchColumn() ?: 0);
    
    if ($clienteId > 0) {
        // Actualizar datos existentes
        $up = $pdo->prepare('
            UPDATE clientes_facturacion 
            SET razon_social = ?, regimen = ?, cp = ?, uso_cfdi = ?, correo = ? 
            WHERE id = ?
        ');
        $up->execute([$razon, $regimen, $cp, $uso, $correo, $clienteId]);
    } else {
        // Insertar nuevo cliente
        $ins = $pdo->prepare('
            INSERT INTO clientes_facturacion (rfc, razon_social, regimen, cp, uso_cfdi, correo) 
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $ins->execute([$rfc, $razon, $regimen, $cp, $uso, $correo]);
        $clienteId = (int)$pdo->lastInsertId();
    }

    // ============================================
    // CARGAR TICKET Y CONSTRUIR CONCEPTOS
    // ============================================
    
    $t = $pdo->prepare('SELECT id, folio, total, fecha FROM tickets WHERE id = ? LIMIT 1');
    $t->execute([$ticketId]);
    $ticket = $t->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        $pdo->rollBack();
        json_error(['Ticket no encontrado', 404]);
    }

    // Construir items del CFDI
    $items = build_items($pdo, $ticketId);
    
    if (empty($items)) {
        $pdo->rollBack();
        json_error(['El ticket no tiene productos/servicios', 422]);
    }
    
    // Calcular totales
    $subtotal = array_reduce($items, fn($acc, $item) => $acc + (float)$item['Subtotal'], 0.0);
    $iva = 0.0;
    
    foreach ($items as $item) {
        foreach ($item['Taxes'] as $tax) {
            $iva += (float)$tax['Total'];
        }
    }
    
    $total = round($subtotal + $iva, 2);

    // ============================================
    // CONSTRUIR PAYLOAD CFDI PARA FACTURAMA
    // ============================================
    
    $cfdi = [
        'Serie' => Facturama::serie(),
        'Currency' => 'MXN',
        'ExpeditionPlace' => Facturama::expeditionPlace(),
        'CfdiType' => 'I', // Ingreso
        'PaymentMethod' => $paymentMethod,
        'PaymentForm' => $paymentForm,
        'Receiver' => [
            'Rfc' => $rfc,
            'Name' => $razon,
            'FiscalRegime' => $regimen,
            'TaxZipCode' => $cp,
            'CfdiUse' => $uso,
        ],
        'Items' => $items,
    ];
    
    // Agregar email si se proporcionó
    if ($correo) {
        $cfdi['Receiver']['Email'] = $correo;
    }
    
    // Agregar observaciones si se proporcionaron
    if ($observaciones) {
        $cfdi['Observations'] = $observaciones;
    }

    // ============================================
    // TIMBRAR CON FACTURAMA
    // ============================================
    
    try {
        $resultado = Facturama::request('POST', '/api/3/cfdis', $cfdi);
    } catch (Throwable $e) {
        error_log("[Facturama] Error al conectar: " . $e->getMessage());
        $pdo->rollBack();
        json_error(['Error de conexión con Facturama', 500]);
    }

    // Validar respuesta de Facturama
    $status = $resultado['status'] ?? 0;
    
    if ($status < 200 || $status >= 300) {
        $mensaje = $resultado['json']['Message'] ?? ($resultado['json']['message'] ?? 'Error desconocido de Facturama');
        $detalle = $resultado['json']['ModelState'] ?? ($resultado['json']['detail'] ?? null);
        
        error_log("[Facturama] Error de timbrado: {$mensaje}");
        error_log("[Facturama] Detalles: " . json_encode($detalle));
        
        $pdo->rollBack();
        
        json_response([
            'ok' => false,
            'code' => 'TIMBRADO_ERROR',
            'message' => $mensaje,
            'detail' => $detalle,
            'status' => $status
        ], 422);
    }

    // Extraer datos de la respuesta
    $json = $resultado['json'] ?? [];
    $facturamaId = $json['Id'] ?? ($json['id'] ?? null);
    $uuid = $json['FolioFiscal'] ?? ($json['Uuid'] ?? null);
    $serie = $json['Serie'] ?? Facturama::serie();
    $folio = $json['Folio'] ?? null;
    
    if (!$facturamaId || !$uuid) {
        $pdo->rollBack();
        json_error(['Respuesta incompleta de Facturama (falta ID o UUID)', 502, $json]);
    }

    // ============================================
    // GUARDAR FACTURA EN BD LOCAL
    // ============================================
    
    $insF = $pdo->prepare('
        INSERT INTO facturas (
            facturama_id, 
            ticket_id, 
            cliente_id, 
            folio, 
            uuid, 
            subtotal, 
            impuestos, 
            total, 
            fecha_emision, 
            estado, 
            serie, 
            metodo_pago, 
            forma_pago, 
            uso_cfdi
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), "generada", ?, ?, ?, ?)
    ');
    
    $insF->execute([
        (string)$facturamaId,
        (int)$ticket['id'],
        (int)$clienteId,
        (string)($folio ?? ('F-' . $ticket['folio'])),
        (string)$uuid,
        (float)$subtotal,
        (float)$iva,
        (float)$total,
        (string)$serie,
        (string)$paymentMethod,
        (string)$paymentForm,
        (string)$uso
    ]);
    
    $facturaId = (int)$pdo->lastInsertId();

    // ============================================
    // GUARDAR DETALLES DE LA FACTURA
    // ============================================
    
    $insD = $pdo->prepare('
        INSERT INTO factura_detalles (
            factura_id, 
            ticket_detalle_id, 
            producto_id, 
            descripcion, 
            cantidad, 
            precio_unitario, 
            importe
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    
    foreach ($items as $item) {
        $insD->execute([
            $facturaId,
            null, // ticket_detalle_id no disponible en este contexto
            (int)$item['IdentificationNumber'],
            $item['Description'],
            (int)$item['Quantity'],
            (float)$item['UnitPrice'],
            (float)$item['Subtotal']
        ]);
    }

    // ============================================
    // DESCARGAR XML Y PDF DE FACTURAMA
    // ============================================
    
    $baseDir = dirname(__DIR__, 3) . '/tokyo/vistas/upload/facturas/' . date('Y') . '/' . date('m');
    $xmlPath = $baseDir . '/' . $uuid . '.xml';
    $pdfPath = $baseDir . '/' . $uuid . '.pdf';

    // Descargar XML
    try {
        $xml = Facturama::request(
            'GET', 
            '/api/Cfdi/xml/issued/' . urlencode((string)$facturamaId),
            null,
            ['Accept: application/xml']
        );
        
        if (($xml['status'] ?? 0) >= 200 && ($xml['status'] ?? 0) < 300 && !empty($xml['body'])) {
            Facturama::saveFile($xmlPath, $xml['body']);
        } else {
            error_log("[Facturama] No se pudo descargar XML. Status: " . ($xml['status'] ?? 'unknown'));
        }
    } catch (Throwable $e) {
        error_log("[Facturama] Error al descargar XML: " . $e->getMessage());
    }

    // Descargar PDF
    try {
        $pdf = Facturama::request(
            'GET', 
            '/api/Cfdi/pdf/issued/' . urlencode((string)$facturamaId),
            null,
            ['Accept: application/pdf']
        );
        
        if (($pdf['status'] ?? 0) >= 200 && ($pdf['status'] ?? 0) < 300 && !empty($pdf['body'])) {
            Facturama::saveFile($pdfPath, $pdf['body']);
        } else {
            error_log("[Facturama] No se pudo descargar PDF. Status: " . ($pdf['status'] ?? 'unknown'));
        }
    } catch (Throwable $e) {
        error_log("[Facturama] Error al descargar PDF: " . $e->getMessage());
    }

    // Actualizar rutas en la BD
    $upd = $pdo->prepare('UPDATE facturas SET xml_path = ?, pdf_path = ? WHERE id = ?');
    $upd->execute([
        str_replace(dirname(__DIR__, 3), '', $xmlPath),
        str_replace(dirname(__DIR__, 3), '', $pdfPath),
        $facturaId
    ]);

    // ============================================
    // COMMIT Y RESPUESTA EXITOSA
    // ============================================
    
    $pdo->commit();
    
    json_response([
        'ok' => true,
        'factura_id' => $facturaId,
        'uuid' => $uuid,
        'folio' => $folio ?? ('F-' . $ticket['folio']),
        'serie' => $serie,
        'total' => $total,
        'xml_url' => '/tokyo/api/public/facturacion/descargar.php?uuid=' . urlencode($uuid) . '&tipo=xml',
        'pdf_url' => '/tokyo/api/public/facturacion/descargar.php?uuid=' . urlencode($uuid) . '&tipo=pdf'
    ]);
    
} catch (Throwable $e) {
    // ============================================
    // MANEJO DE ERRORES GLOBAL
    // ============================================
    
    error_log("[timbrar.php] Error: " . $e->getMessage());
    error_log("[timbrar.php] Trace: " . $e->getTraceAsString());
    
    try {
        DB::get()->rollBack();
    } catch (Throwable $e2) {
        // Ignorar si la transacción ya fue cerrada
    }
    
    // Determinar tipo de error y responder apropiadamente
    if ($e instanceof PDOException) {
        json_error(['Error de base de datos al procesar la factura', 500]);
    } else if (strpos($e->getMessage(), 'cURL') !== false || strpos($e->getMessage(), 'conexión') !== false) {
        json_error(['Error de conexión con Facturama', 500]);
    } else {
        json_error(['Error al generar la factura: ' . $e->getMessage(), 500]);
    }
}