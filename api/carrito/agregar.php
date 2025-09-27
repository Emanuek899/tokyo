<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/utils/response.php';
require_once dirname(__DIR__, 2) . '/utils/cart_session.php';
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/utils/corte.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error('Método no permitido', 405);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $producto_id = (int)($input['producto_id'] ?? 0);
    $cantidad = (int)($input['cantidad'] ?? 0);
    if ($producto_id <= 0 || $cantidad <= 0) {
        json_error('Datos inválidos', 422);
    }
    // Validación: requiere corte de caja abierto
    $pdo = DB::get();
    $c = corte_abierto($pdo);
    if (empty($c['abierto'])) {
        http_response_code(409);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'error'=>'No hay corte de caja abierto','details'=>'Abra un corte para poder tomar pedidos'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    cart_add($producto_id, $cantidad);
    json_response(['success'=>true,'ok' => true, 'carrito' => cart_get_all()]);
} catch (Throwable $e) {
    json_error('Error al agregar al carrito', 500, $e->getMessage());
}

