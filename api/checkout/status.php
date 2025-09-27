<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/utils/response.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        json_error('Método no permitido', 405);
    }
    $ref = (string)($_GET['ref'] ?? '');
    if (!$ref) json_error('Falta ref', 422);

    $pdo = DB::get();
    $st = $pdo->prepare('SELECT id, reference, status, venta_id, checkout_url, conekta_order_id, metadata FROM conekta_payments WHERE reference = ?');
    $st->execute([$ref]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_error('No encontrado', 404);

    $stx = (string)$row['status'];
    if (in_array($stx, ['canceled','expired'], true)) { $stx = 'failed'; }
    $surcharge = null;
    if (!empty($row['metadata'])) {
        $meta = json_decode((string)$row['metadata'], true);
        if (isset($meta['surcharge']['mx'])) $surcharge = (float)$meta['surcharge']['mx'];
        elseif (isset($meta['surcharge']['cents'])) $surcharge = ((int)$meta['surcharge']['cents'])/100.0;
    }
    json_response([
        'success' => true,
        'status' => $stx,
        'venta_id' => $row['venta_id'] ? (int)$row['venta_id'] : null,
        'payment_id' => (int)$row['id'],
        'reference' => $row['reference'],
        'surcharge' => $surcharge,
    ]);
} catch (Throwable $e) {
    json_error('Error', 500, $e->getMessage());
}
