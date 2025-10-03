<?php
declare(strict_types=1);

$BASE = dirname(__DIR__, 4);
require_once $BASE . '/utils/response.php';
require_once $BASE . '/config/sat_catalogos.php';

try {
    $path = sat_catalogs_path();
    $cats = load_sat_catalogs();
    $mtime = is_file($path) ? filemtime($path) : time();
    $counts = [
        'regimenes' => count($cats['regimenes'] ?? []),
        'usos' => count($cats['usos'] ?? []),
        'compatibilidad' => count($cats['compatibilidad'] ?? []),
    ];
    header('Cache-Control: public, max-age=300');
    json_response(['ok'=>true, 'updated_at'=>$mtime, 'counts'=>$counts]);
} catch (Throwable $e) {
    json_error('Error en versión de catálogos', 500, $e->getMessage());
}
