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
    $cantidad = (int)($input['cantidad'] ?? -1);
    if ($producto_id <= 0 || $cantidad < 0) {
        json_error('Datos inválidos', 422);
    }
    $cart = cart_get_all();
    $cur = isset($cart[$producto_id]) ? (int)$cart[$producto_id] : 0;
    $isIncrease = ($cantidad > $cur);
    if ($isIncrease) {
        $pdo = DB::get();
        $c = corte_abierto($pdo);
        if (empty($c['abierto'])) {
            $data = [
                'success'=>false,
                'error'=>'No hay corte de caja abierto',
                'details'=>'Abra un corte para poder tomar pedidos'
            ];
            json_response($data, 409);
            exit;
        }
    }
    cart_set_qty($producto_id, $cantidad);
    json_response(['success'=>true,'ok' => true, 'carrito' => cart_get_all()]);
} catch (Throwable $e) {
    json_error('Error al actualizar carrito', 500, $e->getMessage());
}

