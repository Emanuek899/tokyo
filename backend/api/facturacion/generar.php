<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Método no permitido', 405);
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $ticketId = (int)($body['ticket_id'] ?? 0);
    $clienteId = (int)($body['cliente_id'] ?? 0);
    if ($ticketId <= 0 || $clienteId <= 0) json_error('ticket_id y cliente_id requeridos', 422);

    $pdo = DB::get();
    $pdo->beginTransaction();

    // Validar ticket (columna propina ya no existe en tickets)
    $t = $pdo->prepare('SELECT id, folio, total, fecha FROM tickets WHERE id = ? LIMIT 1');
    $t->execute([$ticketId]);
    $ticket = $t->fetch();
    if (!$ticket) { $pdo->rollBack(); json_error('Ticket no encontrado', 404); }

    // Traer detalles del ticket
    $d = $pdo->prepare('SELECT td.id, td.producto_id, td.cantidad, td.precio_unitario, p.nombre AS producto
                        FROM ticket_detalles td
                        LEFT JOIN productos p ON p.id = td.producto_id
                        WHERE td.ticket_id = ?');
    $d->execute([$ticketId]);
    $detalles = $d->fetchAll();

    // Calcular totales
    $subtotal = 0.0;
    foreach ($detalles as $row) {
        $subtotal += ((float)$row['precio_unitario']) * ((int)$row['cantidad']);
    }
    $impuestos = 0.0; // Ajustable si se requiere IVA
    $total = (float)$ticket['total'];

    // Insertar factura
    $folio = 'F-' . $ticket['folio'];
    $uuid = bin2hex(random_bytes(8));
    $stmt = $pdo->prepare('INSERT INTO facturas (ticket_id, cliente_id, folio, uuid, subtotal, impuestos, total, fecha_emision, estado) VALUES (?,?,?,?,?,?,?,NOW(),"generada")');
    $stmt->execute([$ticketId, $clienteId, $folio, $uuid, $subtotal, $impuestos, $total]);
    $facturaId = (int)$pdo->lastInsertId();

    // Insertar detalles de factura
    $ins = $pdo->prepare('INSERT INTO factura_detalles (factura_id, ticket_detalle_id, producto_id, descripcion, cantidad, precio_unitario, importe) VALUES (?,?,?,?,?,?,?)');
    foreach ($detalles as $row) {
        $cant = (int)$row['cantidad'];
        $pu = (float)$row['precio_unitario'];
        $ins->execute([$facturaId, (int)$row['id'], (int)$row['producto_id'], $row['producto'], $cant, $pu, $cant * $pu]);
    }

    $pdo->commit();

    // Devolver factura completa
    $f = $pdo->prepare('SELECT * FROM vista_facturas WHERE factura_id = ?');
    $f->execute([$facturaId]);
    $fact = $f->fetch();
    $fd = $pdo->prepare('SELECT * FROM vista_factura_detalles WHERE factura_id = ?');
    $fd->execute([$facturaId]);
    $items = $fd->fetchAll();
    json_response(['factura' => $fact, 'detalles' => $items]);
} catch (Throwable $e) {
    try { DB::get()->rollBack(); } catch (Throwable $e2) {}
    json_error('Error al generar factura', 500, $e->getMessage());
}
