<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/config/facturama.php';
require_once dirname(__DIR__, 3) . '/utils/response.php';
require_once dirname(__DIR__, 3) . '/utils/security.php';

function build_items(PDO $pdo, int $ticketId): array {
    $q = $pdo->prepare('SELECT td.id, td.producto_id, p.nombre AS descripcion, td.cantidad, td.precio_unitario
                        FROM ticket_detalles td
                        LEFT JOIN productos p ON p.id = td.producto_id
                        WHERE td.ticket_id = ?');
    $q->execute([$ticketId]);
    $rows = $q->fetchAll();
    $items = [];
    foreach ($rows as $r) {
        $qty = (float)$r['cantidad'];
        $pu  = (float)$r['precio_unitario'];
        $subtotal = round($qty * $pu, 2);
        $ivaBase = $subtotal; // asumir no exento
        $iva = round($ivaBase * 0.16, 2);
        $total = round($subtotal + $iva, 2);
        $items[] = [
            'ProductCode' => '01010101',
            'IdentificationNumber' => (string)$r['producto_id'],
            'Description' => $r['descripcion'],
            'Unit' => 'Unidad',
            'UnitCode' => 'H87',
            'UnitPrice' => (float)$pu,
            'Quantity' => (float)$qty,
            'Subtotal' => (float)$subtotal,
            'Taxes' => [[
                'Name' => 'IVA',
                'Rate' => 0.16,
                'IsRetention' => false,
                'Base' => (float)$ivaBase,
                'Total' => (float)$iva,
            ]],
            'Total' => (float)$total,
        ];
    }
    return $items;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error('Metodo no permitido', 405);
    }

    require_rate_limit('timbrar', 30, 600);
    require_csrf_token();

    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $ticketId = (int)($body['ticket_id'] ?? 0);
    $rfc = strtoupper(trim((string)($body['rfc'] ?? '')));
    $razon = trim((string)($body['razon_social'] ?? ''));
    $regimen = trim((string)($body['regimen'] ?? ''));
    $cp = trim((string)($body['cp'] ?? ''));
    $uso = trim((string)($body['uso_cfdi'] ?? ''));
    $correo = trim((string)($body['correo'] ?? ''));
    $payment_method = trim((string)($body['payment_method'] ?? 'PUE'));
    $payment_form = trim((string)($body['payment_form'] ?? '03'));
    $observ = trim((string)($body['observaciones'] ?? ''));

    if ($ticketId <= 0) { json_error('ticket_id requerido', 422); }
    if (!preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/u', $rfc)) { json_error('RFC inválido', 422); }
    if ($razon === '' || $regimen === '' || $cp === '' || $uso === '') { json_error('Datos fiscales incompletos', 422); }

    $pdo = DB::get();
    $pdo->beginTransaction();

    // Idempotencia: si ya existe factura generada para ese ticket, regrésala
    $q = $pdo->prepare('SELECT id, facturama_id, uuid, xml_path, pdf_path FROM facturas WHERE ticket_id = ? AND estado = "generada" ORDER BY id DESC LIMIT 1');
    $q->execute([$ticketId]);
    $ex = $q->fetch();
    if ($ex && !empty($ex['uuid'])) {
        $pdo->commit();
        json_response(['ok'=>true, 'factura_id'=>(int)$ex['id'], 'uuid'=>$ex['uuid'], 'xml_url'=>'/tokyo/api/public/facturacion/descargar.php?uuid=' . urlencode($ex['uuid']) . '&tipo=xml', 'pdf_url'=>'/tokyo/api/public/facturacion/descargar.php?uuid=' . urlencode($ex['uuid']) . '&tipo=pdf', 'idempotent'=>true]);
    }

    // Upsert cliente fiscal
    $csel = $pdo->prepare('SELECT id FROM clientes_facturacion WHERE rfc = ? LIMIT 1');
    $csel->execute([$rfc]);
    $cid = (int)($csel->fetchColumn() ?: 0);
    if ($cid > 0) {
        $up = $pdo->prepare('UPDATE clientes_facturacion SET razon_social=?, regimen=?, cp=?, uso_cfdi=?, correo=? WHERE id=?');
        $up->execute([$razon, $regimen, $cp, $uso, $correo, $cid]);
    } else {
        $ins = $pdo->prepare('INSERT INTO clientes_facturacion (rfc, razon_social, regimen, cp, uso_cfdi, correo) VALUES (?,?,?,?,?,?)');
        $ins->execute([$rfc, $razon, $regimen, $cp, $uso, $correo]);
        $cid = (int)$pdo->lastInsertId();
    }

    // Cargar ticket
    $t = $pdo->prepare('SELECT id, folio, total, fecha FROM tickets WHERE id = ? LIMIT 1');
    $t->execute([$ticketId]);
    $ticket = $t->fetch();
    if (!$ticket) { $pdo->rollBack(); json_error('Ticket no encontrado', 404); }

    $items = build_items($pdo, $ticketId);
    $subtotal = array_reduce($items, fn($a,$i)=>$a + (float)$i['Subtotal'], 0.0);
    $iva = 0.0; foreach ($items as $it) { foreach ($it['Taxes'] as $tx) { $iva += (float)$tx['Total']; } }
    $total = $subtotal + $iva;

    $cfdi = [
        'Serie' => cfdi_serie(),
        'Currency' => 'MXN',
        'ExpeditionPlace' => cfdi_expedition_cp(),
        'CfdiType' => 'I',
        'PaymentMethod' => $payment_method,
        'PaymentForm' => $payment_form,
        'Receiver' => [
            'Rfc' => $rfc,
            'Name' => $razon,
            'FiscalRegime' => $regimen,
            'TaxZipCode' => $cp,
            'CfdiUse' => $uso,
            'Email' => $correo ?: null,
        ],
        'Items' => $items,
        'Observations' => $observ ?: null,
    ];

    // Timbrar
    $res = Facturama::request('POST', '/api/3/cfdis', $cfdi);
    if (($res['status'] ?? 0) < 200 || ($res['status'] ?? 0) >= 300) {
        $msg = $res['json']['Message'] ?? ($res['json']['message'] ?? 'Error Facturama');
        $detail = $res['json']['ModelState'] ?? null;
        $pdo->rollBack();
        json_response(['ok'=>false, 'code'=>'TIMBRADO_ERROR', 'message'=>$msg, 'detail'=>$detail, 'status'=>$res['status'] ?? 0], 422);
    }
    $j = $res['json'] ?? [];
    $fid = $j['Id'] ?? ($j['id'] ?? null);
    $uuid = $j['FolioFiscal'] ?? ($j['Uuid'] ?? null);
    $serie = $j['Serie'] ?? cfdi_serie();
    $folio = $j['Folio'] ?? null;
    if (!$fid || !$uuid) { $pdo->rollBack(); json_error('Respuesta incompleta de Facturama', 502, $j); }

    // Insert factura
    $insF = $pdo->prepare('INSERT INTO facturas (facturama_id, ticket_id, cliente_id, folio, uuid, subtotal, impuestos, total, fecha_emision, estado, serie, metodo_pago, forma_pago, uso_cfdi) VALUES (?,?,?,?,?,?,?,?,NOW(),"generada",?,?,?,?)');
    $insF->execute([(string)$fid, (int)$ticket['id'], (int)$cid, (string)($folio ?? ('F-' . $ticket['id'])), (string)$uuid, (float)$subtotal, (float)$iva, (float)$total, (string)$serie, (string)$payment_method, (string)$payment_form, (string)$uso]);
    $facturaId = (int)$pdo->lastInsertId();

    // Detalles locales
    $insD = $pdo->prepare('INSERT INTO factura_detalles (factura_id, ticket_detalle_id, producto_id, descripcion, cantidad, precio_unitario, importe) VALUES (?,?,?,?,?,?,?)');
    foreach ($items as $it) {
        $insD->execute([$facturaId, null, (int)$it['IdentificationNumber'], $it['Description'], (int)$it['Quantity'], (float)$it['UnitPrice'], (float)$it['Subtotal']]);
    }

    // Descarga XML/PDF
    $baseDir = dirname(__DIR__, 3) . '/tokyo/vistas/upload/facturas/' . date('Y') . '/' . date('m');
    $xmlPath = $baseDir . '/'. $uuid . '.xml';
    $pdfPath = $baseDir . '/'. $uuid . '.pdf';

    $xml = Facturama::request('GET', '/api/Cfdi/xml/issued/' . urlencode((string)$fid), null, ['Accept: application/xml']);
    if (($xml['status'] ?? 0) >= 200 && ($xml['status'] ?? 0) < 300) {
        Facturama::saveFile($xmlPath, $xml['body']);
    }
    $pdf = Facturama::request('GET', '/api/Cfdi/pdf/issued/' . urlencode((string)$fid), null, ['Accept: application/pdf']);
    if (($pdf['status'] ?? 0) >= 200 && ($pdf['status'] ?? 0) < 300) {
        Facturama::saveFile($pdfPath, $pdf['body']);
    }

    $upd = $pdo->prepare('UPDATE facturas SET xml_path = ?, pdf_path = ? WHERE id = ?');
    $upd->execute([str_replace(dirname(__DIR__, 3), '', $xmlPath), str_replace(dirname(__DIR__, 3), '', $pdfPath), $facturaId]);

    $pdo->commit();
    json_response(['ok'=>true, 'factura_id'=>$facturaId, 'uuid'=>$uuid, 'xml_url'=>'/tokyo/api/public/facturacion/descargar.php?uuid=' . urlencode($uuid) . '&tipo=xml', 'pdf_url'=>'/tokyo/api/public/facturacion/descargar.php?uuid=' . urlencode($uuid) . '&tipo=pdf']);
} catch (Throwable $e) {
    try { DB::get()->rollBack(); } catch (Throwable $e2) {}
    json_error('Error al timbrar', 500, $e->getMessage());
}

