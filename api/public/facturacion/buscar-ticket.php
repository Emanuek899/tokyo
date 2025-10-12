<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/utils/response.php';
require_once dirname(__DIR__, 3) . '/utils/security.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error('Metodo no permitido', 405);
    }

    require_rate_limit('buscar-ticket', 30, 600);
    require_csrf_token();

    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $ticketId = isset($body['ticket_id']) ? (int)$body['ticket_id'] : 0;
    $folio    = isset($body['folio']) ? (int)$body['folio'] : 0;
    $fechaStr = isset($body['fecha']) ? trim((string)$body['fecha']) : '';
    $totalIn  = isset($body['total']) ? (float)$body['total'] : null;

    $pdo = DB::get();

    // Resolver ticket por id o folio
    $t = null;
    if ($ticketId > 0) {
        $st = $pdo->prepare('SELECT t.id, t.folio, t.total, t.fecha, t.venta_id FROM tickets t WHERE t.id = ? LIMIT 1');
        $st->execute([$ticketId]);
        $t = $st->fetch();
    } elseif ($folio > 0) {
        $st = $pdo->prepare('SELECT t.id, t.folio, t.total, t.fecha, t.venta_id FROM tickets t WHERE t.folio = ? LIMIT 1');
        $st->execute([$folio]);
        $t = $st->fetch();
    }
    if (!$t) {
        json_response(['ok'=>false, 'code'=>'NOT_FOUND', 'message'=>'Ticket no encontrado'], 404);
    }

    // Validacion por fecha si viene
    if ($fechaStr) {
        $fechaDb = substr((string)$t['fecha'], 0, 10);
        $fechaCmp = preg_replace('#/#', '-', $fechaStr);
        $fechaCmp = date('Y-m-d', strtotime($fechaCmp));
        if ($fechaDb !== $fechaCmp) {
            json_response(['ok'=>false, 'code'=>'NOT_FOUND', 'message'=>'Ticket/fecha no coincide'], 404);
        }
    }

    // Validacion por total si viene
    if ($totalIn !== null) {
        $diff = abs(((float)$t['total']) - (float)$totalIn);
        if ($diff > 0.01) {
            json_response(['ok'=>false, 'code'=>'NOT_FOUND', 'message'=>'El total no coincide'], 404);
        }
    }

    // Elegibilidad: venta cerrada
    $st2 = $pdo->prepare('SELECT v.estatus FROM ventas v WHERE v.id = ? LIMIT 1');
    $st2->execute([(int)$t['venta_id']]);
    $est = $st2->fetchColumn();
    if (!$est || strtolower((string)$est) !== 'cerrada') {
        json_response(['ok'=>false, 'code'=>'NOT_ELIGIBLE', 'message'=>'La venta no está cerrada'], 422);
    }

    // Idempotencia: ya facturado?
    $st3 = $pdo->prepare('SELECT id, uuid, estado FROM facturas WHERE ticket_id = ? ORDER BY id DESC LIMIT 1');
    $st3->execute([(int)$t['id']]);
    $f = $st3->fetch();
    if ($f && (!empty($f['uuid'])) && strtolower((string)$f['estado']) !== 'cancelada') {
        json_response(['ok'=>false, 'code'=>'ALREADY_INVOICED', 'message'=>'El ticket ya tiene factura', 'uuid'=>$f['uuid']], 409);
    }

    // Ventana (mismo mes): opcional; si quieres forzar, descomenta
    // if (date('Y-m', strtotime($t['fecha'])) !== date('Y-m')) {
    //     json_response(['ok'=>false, 'code'=>'OUT_OF_WINDOW', 'message'=>'Fuera de ventana de facturación'], 422);
    // }

    // Detalles del ticket
    $d = $pdo->prepare('SELECT td.id, td.producto_id, p.nombre AS descripcion, td.cantidad, td.precio_unitario, (td.cantidad*td.precio_unitario) AS importe
                        FROM ticket_detalles td
                        LEFT JOIN productos p ON p.id = td.producto_id
                        WHERE td.ticket_id = ?');
    $d->execute([(int)$t['id']]);
    $partidas = $d->fetchAll();

    json_response(['ok'=>true, 'ticket'=>[
        'id'=>(int)$t['id'], 'folio'=>(int)$t['folio'], 'fecha'=>$t['fecha'], 'total'=>(float)$t['total'],
        'partidas'=>$partidas
    ]]);
} catch (Throwable $e) {
    error_log("Error en buscar-ticket.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    json_error(['message' => 'Error buscando ticket: ' . $e->getMessage()], 500);
}

