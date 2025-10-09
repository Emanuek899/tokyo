<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../../utils/validator.php';
require_once __DIR__ . '/../../components/MenuRepo.php';

try {
    $pdo = DB::get();
    $sedeId = isset($_GET['sede_id']) ? (int)$_GET['sede_id'] : 0;
    $validatedData = Validator::validate(['sedeId' => $sedeId], ['sedeId' => 'Id']);
    $repo = new MenuRepo($pdo);
    if ($sedeId > 0) {
        $rows = $repo->topVendidosId([':sede' => $sedeId]);
    } else {
        $rows = $repo->topVendidos();
    }
    $items = array_map(function($r){
        return [
            'id' => (int)$r['id'],
            'nombre' => $r['nombre'],
            'slug' => rawurlencode($r['nombre']),
            'precio' => (float)$r['precio'],
            'imagen' => $r['imagen'] ?: 'assets/img/placeholder.svg',
            'tags' => [],
            'estado' => 'disponible',
            'categoria' => $r['categoria'] ?: 'Sin categoría',
            'vendidos' => (int)$r['total_vendidos']
        ];
    }, $rows);
    json_response($items);
} catch (Throwable $e) {
    json_error('Error al listar top vendidos', 500, $e->getMessage());
}

