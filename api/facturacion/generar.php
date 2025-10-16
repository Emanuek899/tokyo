<?php
declare(strict_types=1);

/**
 * API: Generación de Facturas
 * Endpoint: POST /backend/api/facturacion/generar.php
 * 
 * Genera una factura a partir de un ticket existente e integra
 * opcionalmente con Facturama para timbrado automático.
 */

$BASE = dirname(__DIR__, 2);

// Rutas robustas para config y utils (soporta estructura con /backend)
$CFG = is_file($BASE . '/config/db.php') ? ($BASE . '/config/db.php') : ($BASE . '/backend/config/db.php');
$UTL = is_file($BASE . '/utils/response.php') ? ($BASE . '/utils/response.php') : ($BASE . '/backend/utils/response.php');
$FCT = is_file($BASE . '/config/facturama.php') ? ($BASE . '/config/facturama.php') : ($BASE . '/backend/config/facturama.php');

require_once $CFG;
require_once $UTL;
if (is_file($FCT)) {
    require_once $FCT;
}

/**
 * Resuelve el ID real del ticket a partir de ticket_id o folio
 * 
 * Maneja tres casos:
 * 1. Si ticket_id existe como ID en BD, lo retorna
 * 2. Si ticket_id >= 1000 (parece folio), busca por folio
 * 3. Si viene folio explícito, busca por folio
 * 
 * @param PDO $pdo Conexión a base de datos
 * @param int $ticketId ID del ticket (puede ser folio)
 * @param int $folioIn Folio explícito del ticket
 * @return int ID real del ticket o 0 si no existe
 */
function getTicketId(PDO $pdo, int $ticketId, int $folioIn): int
{
    // Si viene un ticketId válido y existe por ID
    if ($ticketId > 0) {
        $q = $pdo->prepare('SELECT id FROM tickets WHERE id = ? LIMIT 1');
        $q->execute([$ticketId]);
        if ($q->fetchColumn()) {
            return $ticketId;
        }

        // Si NO existe por ID, pero el número se ve como FOLIO (típicamente >= 1000),
        //    intenta mapearlo por folio
        if ($ticketId >= 1000) {
            $q = $pdo->prepare('SELECT id FROM tickets WHERE folio = ? LIMIT 1');
            $q->execute([$ticketId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return (int)$row['id'];
            }
        }
    }

    // Si vino folio explícito, resolver a id
    if ($folioIn > 0) {
        $q = $pdo->prepare('SELECT id FROM tickets WHERE folio = ? LIMIT 1');
        $q->execute([$folioIn]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int)$row['id'];
        }
    }

    return 0;
}

try {
    // Validar método HTTP
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error(['Método no permitido', 405]);
    }

    // Parsear body JSON
    $body = json_decode(file_get_contents('php://input'), true) ?: [];

    // Extraer y validar parámetros
    $ticketIdRaw = isset($body['ticket_id']) ? (int)$body['ticket_id'] : 0;
    $folioIn     = isset($body['folio']) ? (int)$body['folio'] : 0;
    $clienteId   = isset($body['cliente_id']) ? (int)$body['cliente_id'] : 0;

    if (($ticketIdRaw <= 0 && $folioIn <= 0) || $clienteId <= 0) {
        json_error(['Se requiere ticket_id o folio, y cliente_id', 422, ['body' => $body]]);
    }

    // Iniciar transacción
    $pdo = DB::get();
    $pdo->beginTransaction();

    // Log de diagnóstico
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    error_log("[facturacion/generar] DB={$dbName} in.ticket_id={$ticketIdRaw} in.folio={$folioIn} cliente_id={$clienteId}");

    // Resolver ticket_id válido (acepta que envíen el folio en ticket_id)
    $ticketId = getTicketId($pdo, $ticketIdRaw, $folioIn);
    
    if ($ticketId <= 0) {
        $pdo->rollBack();
        json_error([
            'Ticket no encontrado',
            404,
            [
                'ticket_id' => $ticketIdRaw,
                'folio' => $folioIn,
                'db' => $dbName
            ]
        ]);
    }

    // ============================================
    // IDEMPOTENCIA: Verificar si ya existe factura
    // ============================================
    $q = $pdo->prepare('SELECT id FROM facturas WHERE ticket_id = ? LIMIT 1');
    $q->execute([$ticketId]);
    $existingFacturaId = (int)($q->fetchColumn() ?: 0);
    
    if ($existingFacturaId > 0) {
        // Ya existe factura, retornarla sin crear duplicado
        $f  = $pdo->prepare('SELECT * FROM vista_facturas WHERE factura_id = ?');
        $fd = $pdo->prepare('SELECT * FROM vista_factura_detalles WHERE factura_id = ?');
        $f->execute([$existingFacturaId]);
        $fd->execute([$existingFacturaId]);
        $pdo->commit();
        
        json_response([
            'factura' => $f->fetch(PDO::FETCH_ASSOC),
            'detalles' => $fd->fetchAll(PDO::FETCH_ASSOC),
            'idempotent' => true
        ]);
    }

    // ============================================
    // RECUPERAR DATOS DEL TICKET
    // ============================================
    $t = $pdo->prepare('SELECT id, folio, total, fecha FROM tickets WHERE id = ? LIMIT 1');
    $t->execute([$ticketId]);
    $ticket = $t->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        $pdo->rollBack();
        json_error([
            'Ticket no encontrado',
            404,
            [
                'ticket_id' => $ticketId,
                'db' => $dbName
            ]
        ]);
    }

    // Recuperar detalles del ticket
    $d = $pdo->prepare('
        SELECT 
            td.id, 
            td.producto_id, 
            td.cantidad, 
            td.precio_unitario, 
            p.nombre AS producto
        FROM ticket_detalles td
        LEFT JOIN productos p ON p.id = td.producto_id
        WHERE td.ticket_id = ?
    ');
    $d->execute([$ticketId]);
    $detalles = $d->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // CALCULAR TOTALES
    // ============================================
    $subtotal = 0.0;
    foreach ($detalles as $row) {
        $subtotal += ((float)$row['precio_unitario']) * ((int)$row['cantidad']);
    }
    
    $impuestos = 0.0; // Ajustar si se requiere IVA u otros impuestos
    $total = (float)$ticket['total'];

    // ============================================
    // CREAR FACTURA LOCAL
    // ============================================
    $folioFactura = 'F-' . $ticket['folio'];
    $uuid = bin2hex(random_bytes(16)); // UUID temporal (se actualiza con Facturama)
    
    $insF = $pdo->prepare('
        INSERT INTO facturas (
            ticket_id, 
            cliente_id, 
            folio, 
            uuid, 
            subtotal, 
            impuestos, 
            total, 
            fecha_emision, 
            estado
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), "generada")
    ');
    
    $insF->execute([
        (int)$ticket['id'],
        $clienteId,
        $folioFactura,
        $uuid,
        $subtotal,
        $impuestos,
        $total
    ]);
    
    $facturaId = (int)$pdo->lastInsertId();

    // ============================================
    // INSERTAR DETALLES DE LA FACTURA
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
    
    foreach ($detalles as $row) {
        $cant = (int)$row['cantidad'];
        $pu   = (float)$row['precio_unitario'];
        
        $insD->execute([
            $facturaId,
            (int)$row['id'],
            (int)$row['producto_id'],
            $row['producto'],
            $cant,
            $pu,
            $cant * $pu
        ]);
    }

    // ============================================
    // INTEGRACIÓN CON FACTURAMA (TIMBRADO)
    // ============================================
    if (function_exists('facturama_create_cfdi')) {
        // Recuperar datos fiscales del cliente
        $clQ = $pdo->prepare('SELECT * FROM clientes_facturacion WHERE id = ? LIMIT 1');
        $clQ->execute([$clienteId]);
        $cli = $clQ->fetch(PDO::FETCH_ASSOC) ?: [];

        // Valores por defecto para facturación electrónica
        $formaPago = '03'; // Transferencia electrónica de fondos
        $metodoPago = 'PUE'; // Pago en una sola exhibición
        $usoCfdi = (string)($cli['uso_cfdi'] ?? 'G03'); // Gastos en general

        // Construir payload para Facturama (formato form-data)
        $fields = [
            'CfdiType' => 'I', // Tipo: Ingreso
            'ExpeditionPlace' => function_exists('FacturamaCfg\expeditionPlace') 
                ? Facturama::expeditionPlace() 
                : (getenv('FACTURAMA_EXPEDITION_PLACE') ?: '34217'),
            'PaymentForm' => $formaPago,
            'PaymentMethod' => $metodoPago,
            'Currency' => 'MXN',
            
            // Datos del receptor (cliente)
            'Receiver[Rfc]' => (string)($cli['rfc'] ?? ''),
            'Receiver[Name]' => (string)($cli['razon_social'] ?? ''),
            'Receiver[CfdiUse]' => $usoCfdi,
            
            // Totales
            'SubTotal' => number_format($subtotal, 2, '.', ''),
            'Total' => number_format($total, 2, '.', ''),
        ];

        // Agregar conceptos (productos/servicios)
        $idx = 0;
        foreach ($detalles as $row) {
            $cant = (int)$row['cantidad'];
            $pu   = (float)$row['precio_unitario'];
            $desc = (string)($row['producto'] ?? ('Producto #' . (int)$row['producto_id']));
            
            $fields["Items[$idx][ProductCode]"] = '01010101'; // Clave genérica SAT
            $fields["Items[$idx][IdentificationNumber]"] = (string)((int)$row['producto_id']);
            $fields["Items[$idx][Description]"] = $desc;
            $fields["Items[$idx][Unit]"] = 'Pieza';
            $fields["Items[$idx][UnitCode]"] = 'H87'; // Clave unidad SAT
            $fields["Items[$idx][UnitPrice]"] = number_format($pu, 2, '.', '');
            $fields["Items[$idx][Quantity]"] = $cant;
            $fields["Items[$idx][Subtotal]"] = number_format($cant * $pu, 2, '.', '');
            $fields["Items[$idx][TaxObject]"] = '01'; // No objeto de impuesto
            $idx++;
        }

        try {
            // Llamar a Facturama para timbrar
            $resp = facturama_create_cfdi($fields);
            
            // Extraer datos de la respuesta
            $facturamaId = (string)($resp['Id'] ?? ($resp['id'] ?? ''));
            $uuidResp    = (string)($resp['Uuid'] ?? ($resp['uuid'] ?? ''));
            $serieResp   = (string)($resp['Serie'] ?? '');
            $folioResp   = isset($resp['Folio']) 
                ? (string)$resp['Folio'] 
                : ((string)($resp['FolioInt'] ?? ''));

            // Extraer URLs de XML y PDF
            $pdfUrl = '';
            $xmlUrl = '';
            if (isset($resp['Links']) && is_array($resp['Links'])) {
                $pdfUrl = (string)($resp['Links']['Pdf'] ?? ($resp['Links']['PdfUrl'] ?? ''));
                $xmlUrl = (string)($resp['Links']['Xml'] ?? ($resp['Links']['XmlUrl'] ?? ''));
            }

            // Actualizar factura local con datos de Facturama
            $up = $pdo->prepare('
                UPDATE facturas 
                SET 
                    facturama_id = ?, 
                    uuid = ?, 
                    serie = ?, 
                    folio = ?, 
                    metodo_pago = ?, 
                    forma_pago = ?, 
                    uso_cfdi = ?, 
                    xml_path = ?, 
                    pdf_path = ?
                WHERE id = ?
            ');
            
            $up->execute([
                $facturamaId ?: null,
                $uuidResp ?: null,
                $serieResp ?: null,
                $folioResp ?: $folioFactura,
                $metodoPago,
                $formaPago,
                $usoCfdi,
                $xmlUrl ?: null,
                $pdfUrl ?: null,
                $facturaId
            ]);
            
        } catch (Throwable $fe) {
            // Si falla Facturama, revertir toda la transacción
            $pdo->rollBack();
            error_log("[facturacion/generar] Error Facturama: " . $fe->getMessage());
            json_error([
                'Error al timbrar con Facturama',
                502,
                $fe->getMessage()
            ]);
        }
    }

    // ============================================
    // RESPUESTA FINAL
    // ============================================
    // Recuperar factura completa desde las vistas
    $f  = $pdo->prepare('SELECT * FROM vista_facturas WHERE factura_id = ?');
    $fd = $pdo->prepare('SELECT * FROM vista_factura_detalles WHERE factura_id = ?');
    $f->execute([$facturaId]);
    $fd->execute([$facturaId]);

    $pdo->commit();
    
    json_response([
        'factura' => $f->fetch(PDO::FETCH_ASSOC),
        'detalles' => $fd->fetchAll(PDO::FETCH_ASSOC)
    ]);
    
} catch (Throwable $e) {
    // Manejo de errores global
    try {
        DB::get()->rollBack();
    } catch (Throwable $e2) {
        // Ignorar si la transacción ya fue cerrada
    }
    
    error_log("[facturacion/generar] Error: " . $e->getMessage());
    json_error([
        'Error al generar factura',
        500,
        $e->getMessage()
    ]);
}