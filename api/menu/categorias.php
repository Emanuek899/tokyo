<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../components/MenuRepo.php';

try {
    $pdo = DB::get();
    $repo = new MenuRepo($pdo);
    $rows = $repo->categorias();
    json_response($rows);
} catch (Throwable $e) {
    json_error(['Error al listar categorías'], 500, $e->getMessage());
}

