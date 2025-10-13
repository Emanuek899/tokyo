<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/utils/response.php';
require_once dirname(__DIR__, 2) . '/utils/cart_session.php';
require_once dirname(__DIR__, 2) . '/components/CarritoRepo.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        json_error(['Método no permitido'], 405);
    }
    $cart = cart_get_all();
    if (empty($cart)) {
        json_response(['items' => [], 'subtotal' => 0, 'envio' => 0, 'total' => 0]);
        exit;
    }
    $pdo = DB::get();
    $ids = array_keys($cart);
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $repo = new CarritoRepo($pdo);
    $map = $repo->obtenerPrecio($ids, $in);

    $items = [];
    $subtotal = 0.0;
    foreach ($cart as $pid => $qty) {
        $pid = (int)$pid; 
        $qty = max(0, (int)$qty);
        if ($qty > 0 && isset($map[$pid])) {
            $precio = $map[$pid]['precio'];
            $line = $precio * $qty;
            $subtotal += $line;
            $items[] = [
                'id' => $pid,
                'nombre' => $map[$pid]['nombre'],
                'precio' => $precio,
                'cantidad' => $qty,
                'subtotal' => $line,
            ];
        }
    }
    $envio = 0.0;
    $total = $subtotal + $envio;
    json_response(['items' => $items, 'subtotal' => $subtotal, 'envio' => $envio, 'total' => $total]);
} catch (Throwable $e) {
    json_error(['Error al listar carrito'], 500, $e->getMessage());
}
