<?php
declare(strict_types=1);

$BASE = dirname(__DIR__, 2);
// Rutas robustas para config y utils (soporta estructura con /backend)
$CFG = is_file($BASE . '/config/db.php') ? ($BASE . '/config/db.php') : ($BASE . '/backend/config/db.php');
$UTL = is_file($BASE . '/utils/response.php') ? ($BASE . '/utils/response.php') : ($BASE . '/backend/utils/response.php');
$FCT = is_file($BASE . '/config/facturama.php') ? ($BASE . '/config/facturama.php') : ($BASE . '/backend/config/facturama.php');
require_once $CFG;
require_once $UTL;
if (is_file($FCT)) require_once $FCT;

require_once __DIR__ . '/../../components/FacturacionRepo.php';

function getTicketId(PDO $pdo, int $ticketId, int $folioIn, FacturacionRepo $repo): int {
    // a) Si viene un ticketId válido y existe por ID, úsalo.
    if ($ticketId > 0) {
        $sql = 'SELECT id FROM tickets WHERE id = ? LIMIT 1';
        $q = $repo->select($sql, [$ticketId], 'column');
        if (!empty($q)) return $ticketId;

        // b) Si NO existe por ID, pero el número se ve como FOLIO (típicamente >= 1000),
        //    intenta mapearlo por folio.
        if ($ticketId >= 1000) {
            $sql = 'SELECT id FROM tickets WHERE folio = ? LIMIT 1';
            $q = $repo->select($sql, [$ticketId], 'fetch');
            $row = $q;
            if ($row) return (int)$row['id'];
        }
    }

    // c) Si vino folio explícito, resolver a id
    if ($folioIn > 0) {
        $sql = 'SELECT id FROM tickets WHERE folio = ? LIMIT 1';
        $q = $repo->select($sql, [$folioIn], 'fetch');
        $row = $q;
        if ($row) return (int)$row['id'];
    }

    return 0;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error(['Método no permitido'], 405);
    }
    $body = json_decode(file_get_contents('php://input'), true) ?: [];

    $ticketIdRaw = isset($body['ticket_id']) ? (int)$body['ticket_id'] : 0;
    $folioIn     = isset($body['folio'])     ? (int)$body['folio']     : 0;
    $clienteId   = isset($body['cliente_id'])? (int)$body['cliente_id']: 0;

    if (($ticketIdRaw <= 0 && $folioIn <= 0) || $clienteId <= 0) {
        json_error(['Se requiere ticket_id o folio, y cliente_id'], 422, ['body'=>$body]);
    }

    $pdo = DB::get();
    $repo = new FacturacionRepo($pdo);
    $pdo->beginTransaction();

    // Log mínimo para diagnosticar ambientes
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    error_log("[facturacion/generar] DB={$dbName} in.ticket_id={$ticketIdRaw} in.folio={$folioIn} cliente_id={$clienteId}");

    // Resolver ticket_id válido (acepta que te manden el folio en ticket_id)
    $ticketId = getTicketId($pdo, $ticketIdRaw, $folioIn, $repo);
    if ($ticketId <= 0) {
        $pdo->rollBack();
        json_error(['Ticket no encontrado'], 404, ['ticket_id'=>$ticketIdRaw, 'folio'=>$folioIn, 'db'=>$dbName]);
    }

    // Idempotencia: si ya existe factura para ese ticket, regrésala
    $sql = 'SELECT id FROM facturas WHERE ticket_id = ? LIMIT 1';
    $q = $repo->select($sql, [$ticketId], 'column');
    $existingFacturaId = (int)($q ?: 0);
    if ($existingFacturaId > 0) {
        $f = $repo->select(
            'SELECT * FROM vista_facturas WHERE factura_id = ?',
            [$existingFacturaId],
            'fetch'
        );
        $fd = $repo->select(
            'SELECT * FROM vista_factura_detalles WHERE factura_id = ?',
            [$existingFacturaId]
        );
        $pdo->commit();
        json_response(['factura' => $f, 'detalles' => $fd, 'idempotent'=>true]);
    }

    // Traer ticket
    $t = $repo->select(
        'SELECT id, folio, total, fecha FROM tickets WHERE id = ? LIMIT 1',
        [$ticketId],
        'fetch'
    );
    $ticket = $t;
    if (!$ticket) {
        $pdo->rollBack();
        json_error(['Ticket no encontrado'], 404, ['ticket_id'=>$ticketId, 'db'=>$dbName]);
    }

    // Detalles del ticket
    $d = $repo->select('
        SELECT td.id, td.producto_id, td.cantidad, td.precio_unitario, p.nombre AS producto
        FROM ticket_detalles td
        LEFT JOIN productos p ON p.id = td.producto_id
        WHERE td.ticket_id = ?',
        [$ticketId],
    );
    $detalles = $d;
    // Totales
    $subtotal = 0.0;
    foreach ($detalles as $row) {
        $subtotal += ((float)$row['precio_unitario']) * ((int)$row['cantidad']);
    }
    $impuestos = 0.0; // Ajusta si requieres IVA
    $total = (float)$ticket['total'];

    // Insertar factura (temporal; se actualizará con datos de Facturama)
    $folioFactura = 'F-' . $ticket['folio'];
    $uuid = bin2hex(random_bytes(8));
    $insF = $repo->insert('
        INSERT INTO facturas (ticket_id, cliente_id, folio, uuid, subtotal, impuestos, total, fecha_emision, estado)
        VALUES (?,?,?,?,?,?,?,NOW(),"generada")',
        [(int)$ticket['id'], $clienteId, $folioFactura, $uuid, $subtotal, $impuestos, $total]
    );
    $facturaId = (int)$insF;

    // Insertar detalles
    foreach ($detalles as $row) {
        $cant = (int)$row['cantidad'];
        $pu   = (float)$row['precio_unitario'];
    }
    $insD = $repo->insert('
        INSERT INTO factura_detalles
        (factura_id, ticket_detalle_id, producto_id, descripcion, cantidad, precio_unitario, importe)
        VALUES (?,?,?,?,?,?,?)',
        [$facturaId, (int)$row['id'], (int)$row['producto_id'], $row['producto'], $cant, $pu, $cant * $pu]
    );


    // Si existe integración Facturama, intentar timbrar ahora
    if (function_exists('facturama_create_cfdi')) {
        // Traer datos del cliente para receptor
        $clQ = $repo->select('
            SELECT * FROM clientes_facturacion WHERE id = ? LIMIT 1',
            [$clienteId],
            'fetch'
        );
        $cli = $clQ ?: [];

        // Valores por defecto de forma/método de pago
        $formaPago = '03'; // Transferencia
        $metodoPago = 'PUE';
        $usoCfdi = (string)($cli['uso_cfdi'] ?? 'G03');

        // Construir payload como arreglo (no JSON)
        $fields = [
            'CfdiType' => 'I',
            'ExpeditionPlace' => function_exists('FacturamaCfg\expeditionPlace') ? FacturamaCfg::expeditionPlace() : (getenv('FACTURAMA_EXPEDITION_PLACE') ?: '34217'),
            'PaymentForm' => $formaPago,
            'PaymentMethod' => $metodoPago,
            'Currency' => 'MXN',
            // Emisor se toma de la cuenta; receptor desde los campos abajo
            'Receiver[Rfc]' => (string)($cli['rfc'] ?? ''),
            'Receiver[Name]' => (string)($cli['razon_social'] ?? ''),
            'Receiver[CfdiUse]' => $usoCfdi,
            // Totales (el servicio puede recalcular, pero enviamos por claridad)
            'SubTotal' => number_format($subtotal, 2, '.', ''),
            'Total' => number_format($total, 2, '.', ''),
        ];

        // Conceptos
        $idx = 0;
        foreach ($detalles as $row) {
            $cant = (int)$row['cantidad'];
            $pu   = (float)$row['precio_unitario'];
            $desc = (string)($row['producto'] ?? ('Producto #' . (int)$row['producto_id']));
            $fields["Items[$idx][ProductCode]"] = '01010101';
            $fields["Items[$idx][IdentificationNumber]"] = (string)((int)$row['producto_id']);
            $fields["Items[$idx][Description]"] = $desc;
            $fields["Items[$idx][Unit]"] = 'Pieza';
            $fields["Items[$idx][UnitCode]"] = 'H87';
            $fields["Items[$idx][UnitPrice]"] = number_format($pu, 2, '.', '');
            $fields["Items[$idx][Quantity]"] = $cant;
            $fields["Items[$idx][Subtotal]"] = number_format($cant * $pu, 2, '.', '');
            $fields["Items[$idx][TaxObject]"] = '01'; // No objeto de impuesto
            $idx++;
        }

        try {
            $resp = facturama_create_cfdi($fields);
            // Esperamos campos comunes
            $facturamaId = (string)($resp['Id'] ?? ($resp['id'] ?? ''));
            $uuidResp    = (string)($resp['Uuid'] ?? ($resp['uuid'] ?? ''));
            $serieResp   = (string)($resp['Serie'] ?? '');
            $folioResp   = isset($resp['Folio']) ? (string)$resp['Folio'] : ((string)($resp['FolioInt'] ?? ''));

            // Links
            $pdfUrl = '';
            $xmlUrl = '';
            if (isset($resp['Links']) && is_array($resp['Links'])) {
                $pdfUrl = (string)($resp['Links']['Pdf'] ?? ($resp['Links']['PdfUrl'] ?? ''));
                $xmlUrl = (string)($resp['Links']['Xml'] ?? ($resp['Links']['XmlUrl'] ?? ''));
            }

            // Actualizar factura local
            $up = $pdo->prepare('UPDATE facturas SET facturama_id=?, uuid=?, serie=?, folio=?, metodo_pago=?, forma_pago=?, uso_cfdi=?, xml_path=?, pdf_path=? WHERE id=?');
            $up->execute([$facturamaId ?: null, $uuidResp ?: null, $serieResp ?: null, $folioResp ?: $folioFactura, $metodoPago, $formaPago, $usoCfdi, $xmlUrl ?: null, $pdfUrl ?: null, $facturaId]);
        } catch (Throwable $fe) {
            // Si falla Facturama, revertimos todo
            $pdo->rollBack();
            json_error(['Error al timbrar con Facturama'], 502, $fe->getMessage());
        }
    }

    // Respuesta desde vistas
    $f  = $repo->select(
        'SELECT * FROM vista_facturas WHERE factura_id = ?',
        [$facturaId],
        'fetch'
    );
    $fd = $repo->select(
        'SELECT * FROM vista_factura_detalles WHERE factura_id = ?',
        [$facturaId]
    );
    $pdo->commit();
    json_response(['factura' => $f, 'detalles' => $fd]);
} catch (Throwable $e) {
    try { DB::get()->rollBack(); } catch (Throwable $e2) {}
    json_error(['Error al generar factura'], 500, $e->getMessage());
}
