<?php
declare(strict_types=1);

$BASE = dirname(__DIR__, 2);
// Rutas robustas para config y utils (soporta estructura con /backend)
$CFG = is_file($BASE . '/config/db.php') ? ($BASE . '/config/db.php') : ($BASE . '/backend/config/db.php');
$UTL = is_file($BASE . '/utils/response.php') ? ($BASE . '/utils/response.php') : ($BASE . '/backend/utils/response.php');
require_once $CFG;
require_once $UTL;

function getTicketId(PDO $pdo, int $ticketId, int $folioIn): int {
    // a) Si viene un ticketId válido y existe por ID, úsalo.
    if ($ticketId > 0) {
        $q = $pdo->prepare('SELECT id FROM tickets WHERE id = ? LIMIT 1');
        $q->execute([$ticketId]);
        if ($q->fetchColumn()) return $ticketId;

        // b) Si NO existe por ID, pero el número se ve como FOLIO (típicamente >= 1000),
        //    intenta mapearlo por folio.
        if ($ticketId >= 1000) {
            $q = $pdo->prepare('SELECT id FROM tickets WHERE folio = ? LIMIT 1');
            $q->execute([$ticketId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if ($row) return (int)$row['id'];
        }
    }

    // c) Si vino folio explícito, resolver a id
    if ($folioIn > 0) {
        $q = $pdo->prepare('SELECT id FROM tickets WHERE folio = ? LIMIT 1');
        $q->execute([$folioIn]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if ($row) return (int)$row['id'];
    }

    return 0;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error('Método no permitido', 405);
    }
    $body = json_decode(file_get_contents('php://input'), true) ?: [];

    $ticketIdRaw = isset($body['ticket_id']) ? (int)$body['ticket_id'] : 0;
    $folioIn     = isset($body['folio'])     ? (int)$body['folio']     : 0;
    $clienteId   = isset($body['cliente_id'])? (int)$body['cliente_id']: 0;

    if (($ticketIdRaw <= 0 && $folioIn <= 0) || $clienteId <= 0) {
        json_error('Se requiere ticket_id o folio, y cliente_id', 422, ['body'=>$body]);
    }

    $pdo = DB::get();
    $pdo->beginTransaction();

    // Log mínimo para diagnosticar ambientes
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    error_log("[facturacion/generar] DB={$dbName} in.ticket_id={$ticketIdRaw} in.folio={$folioIn} cliente_id={$clienteId}");

    // Resolver ticket_id válido (acepta que te manden el folio en ticket_id)
    $ticketId = getTicketId($pdo, $ticketIdRaw, $folioIn);
    if ($ticketId <= 0) {
        $pdo->rollBack();
        json_error('Ticket no encontrado', 404, ['ticket_id'=>$ticketIdRaw, 'folio'=>$folioIn, 'db'=>$dbName]);
    }

    // Idempotencia: si ya existe factura para ese ticket, regrésala
    $q = $pdo->prepare('SELECT id FROM facturas WHERE ticket_id = ? LIMIT 1');
    $q->execute([$ticketId]);
    $existingFacturaId = (int)($q->fetchColumn() ?: 0);
    if ($existingFacturaId > 0) {
        $f  = $pdo->prepare('SELECT * FROM vista_facturas WHERE factura_id = ?');
        $fd = $pdo->prepare('SELECT * FROM vista_factura_detalles WHERE factura_id = ?');
        $f->execute([$existingFacturaId]);
        $fd->execute([$existingFacturaId]);
        $pdo->commit();
        json_response(['factura' => $f->fetch(PDO::FETCH_ASSOC), 'detalles' => $fd->fetchAll(PDO::FETCH_ASSOC), 'idempotent'=>true]);
    }

    // Traer ticket
    $t = $pdo->prepare('SELECT id, folio, total, fecha FROM tickets WHERE id = ? LIMIT 1');
    $t->execute([$ticketId]);
    $ticket = $t->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        $pdo->rollBack();
        json_error('Ticket no encontrado', 404, ['ticket_id'=>$ticketId, 'db'=>$dbName]);
    }

    // Detalles del ticket
    $d = $pdo->prepare('
        SELECT td.id, td.producto_id, td.cantidad, td.precio_unitario, p.nombre AS producto
        FROM ticket_detalles td
        LEFT JOIN productos p ON p.id = td.producto_id
        WHERE td.ticket_id = ?
    ');
    $d->execute([$ticketId]);
    $detalles = $d->fetchAll(PDO::FETCH_ASSOC);

    // Totales
    $subtotal = 0.0;
    foreach ($detalles as $row) {
        $subtotal += ((float)$row['precio_unitario']) * ((int)$row['cantidad']);
    }
    $impuestos = 0.0; // Ajusta si requieres IVA
    $total = (float)$ticket['total'];

    // Insertar factura
    $folioFactura = 'F-' . $ticket['folio'];
    $uuid = bin2hex(random_bytes(8));
    $insF = $pdo->prepare('
        INSERT INTO facturas (ticket_id, cliente_id, folio, uuid, subtotal, impuestos, total, fecha_emision, estado)
        VALUES (?,?,?,?,?,?,?,NOW(),"generada")
    ');
    $insF->execute([(int)$ticket['id'], $clienteId, $folioFactura, $uuid, $subtotal, $impuestos, $total]);
    $facturaId = (int)$pdo->lastInsertId();

    // Insertar detalles
    $insD = $pdo->prepare('
        INSERT INTO factura_detalles
        (factura_id, ticket_detalle_id, producto_id, descripcion, cantidad, precio_unitario, importe)
        VALUES (?,?,?,?,?,?,?)
    ');
    foreach ($detalles as $row) {
        $cant = (int)$row['cantidad'];
        $pu   = (float)$row['precio_unitario'];
        $insD->execute([$facturaId, (int)$row['id'], (int)$row['producto_id'], $row['producto'], $cant, $pu, $cant * $pu]);
    }

    // Respuesta desde vistas
    $f  = $pdo->prepare('SELECT * FROM vista_facturas WHERE factura_id = ?');
    $fd = $pdo->prepare('SELECT * FROM vista_factura_detalles WHERE factura_id = ?');
    $f->execute([$facturaId]);
    $fd->execute([$facturaId]);

    $pdo->commit();
    json_response(['factura' => $f->fetch(PDO::FETCH_ASSOC), 'detalles' => $fd->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    try { DB::get()->rollBack(); } catch (Throwable $e2) {}
    json_error('Error al generar factura', 500, $e->getMessage());
}
