<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/utils/response.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error('Método no permitido', 405);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $ref = isset($input['ref']) ? (string)$input['ref'] : '';
    if (!$ref) json_error('Falta ref', 422);
    $pdo = DB::get();
    $up = $pdo->prepare('UPDATE conekta_payments SET status="paid", updated_at=NOW() WHERE reference = :ref AND status <> "paid"');
    $up->execute([':ref' => $ref]);
    $st = $pdo->prepare('SELECT id, reference, status, venta_id FROM conekta_payments WHERE reference = ?');
    $st->execute([$ref]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_error('No encontrado', 404);
    json_response(['success' => true, 'reference' => $row['reference'], 'status' => $row['status'], 'venta_id' => $row['venta_id'] ? (int)$row['venta_id'] : null, 'payment_id' => (int)$row['id']]);
} catch (Throwable $e) {
    json_error('Error', 500, $e->getMessage());
}

