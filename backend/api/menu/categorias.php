<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

try {
    $pdo = DB::get();
    $rows = $pdo->query("SELECT id, nombre FROM catalogo_categorias ORDER BY nombre ASC")->fetchAll();
    json_response($rows);
} catch (Throwable $e) {
    json_error('Error al listar categorías', 500, $e->getMessage());
}

