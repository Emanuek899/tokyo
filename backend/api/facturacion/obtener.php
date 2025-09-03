<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

try {
    $id = isset($_GET['factura_id']) ? (int)$_GET['factura_id'] : 0;
    if ($id <= 0) json_error('factura_id requerido', 422);
    $pdo = DB::get();
    $f = $pdo->prepare('SELECT * FROM vista_facturas WHERE factura_id = ?');
    $f->execute([$id]);
    $fact = $f->fetch();
    if (!$fact) json_error('Factura no encontrada', 404);
    $fd = $pdo->prepare('SELECT * FROM vista_factura_detalles WHERE factura_id = ?');
    $fd->execute([$id]);
    $items = $fd->fetchAll();
    json_response(['factura' => $fact, 'detalles' => $items]);
} catch (Throwable $e) {
    json_error('Error al obtener factura', 500, $e->getMessage());
}

