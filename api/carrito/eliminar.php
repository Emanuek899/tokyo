<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/utils/response.php';
require_once dirname(__DIR__, 2) . '/utils/cart_session.php';
require_once dirname(__DIR__, 2) . '/utils/cart_session.php';
require_once dirname(__DIR__, 2) . '/utils/validator.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error(['Método no permitido'], 405);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $producto_id = (int)($input['producto_id'] ?? 0);
    $validator = Validator::validate(['producto_id' => $producto_id], ['producto_id' => 'Id']);
    if (!empty($validator)) {
        json_error(['message' => 'Datos invalidos', 'error' => $validator], 422);
    }
    cart_remove($producto_id);
    json_response(['ok' => true, 'carrito' => cart_get_all()]);
} catch (Throwable $e) {
    json_error(['Error al eliminar del carrito'], 500, $e->getMessage());
}
