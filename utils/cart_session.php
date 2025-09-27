<?php
// Cart session helpers (moved from /api/carrito/_session.php)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['tokyo_cart']) || !is_array($_SESSION['tokyo_cart'])) {
    $_SESSION['tokyo_cart'] = [];
}

function cart_get_all(): array {
    return $_SESSION['tokyo_cart'];
}

function cart_set_qty(int $producto_id, int $cantidad): void {
    if ($cantidad <= 0) {
        unset($_SESSION['tokyo_cart'][$producto_id]);
    } else {
        $_SESSION['tokyo_cart'][$producto_id] = $cantidad;
    }
}

function cart_add(int $producto_id, int $cantidad): void {
    if ($cantidad <= 0) return;
    $cur = isset($_SESSION['tokyo_cart'][$producto_id]) ? (int)$_SESSION['tokyo_cart'][$producto_id] : 0;
    $_SESSION['tokyo_cart'][$producto_id] = $cur + $cantidad;
}

function cart_remove(int $producto_id): void {
    unset($_SESSION['tokyo_cart'][$producto_id]);
}

function cart_clear(): void {
    $_SESSION['tokyo_cart'] = [];
}

