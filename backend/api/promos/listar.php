<?php
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/db.php';

try {
    // Intentar DB primero (tabla ofertas_dia)
    try {
        $pdo = DB::get();
        $stmt = $pdo->query("SELECT id, descripcion, vigente, fecha FROM ofertas_dia WHERE vigente = 1 ORDER BY fecha DESC, id DESC LIMIT 50");
        $rows = $stmt->fetchAll();
        if ($rows) {
            $items = array_map(function($r){
                $fecha = $r['fecha'] ?? null;
                return [
                    'id' => (int)$r['id'],
                    'nombre' => $fecha ? ('Oferta del ' . $fecha) : 'Oferta del día',
                    'tipo' => 'oferta',
                    'regla' => $r['descripcion'] ?? '',
                    'vigencia' => $fecha ?: 'Vigente'
                ];
            }, $rows);
            json_response($items);
        }
    } catch (Throwable $e) {
        // continuará a fallback
    }

    // Fallback a JSON si no hay DB o sin filas
    $cand = [
        __DIR__ . '/../../../vistas/data/promos.sample.json',
        __DIR__ . '/../../../sushi_cliente/data/promos.sample.json'
    ];
    $jsonPath = null;
    foreach ($cand as $p) { if (is_readable($p)) { $jsonPath = $p; break; } }
    if (!$jsonPath) { json_response([]); }
    $data = json_decode(file_get_contents($jsonPath), true);
    json_response($data ?: []);
} catch (Throwable $e) {
    json_error('Error al listar promos', 500, $e->getMessage());
}
