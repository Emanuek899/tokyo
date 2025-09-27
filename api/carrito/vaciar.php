<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/utils/response.php';
require_once dirname(__DIR__, 2) . '/utils/cart_session.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error('Método no permitido', 405);
    }
    cart_clear();
    json_response(['ok' => true]);
} catch (Throwable $e) {
    json_error('Error al vaciar carrito', 500, $e->getMessage());
}
