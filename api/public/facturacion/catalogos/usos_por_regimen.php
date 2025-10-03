<?php
declare(strict_types=1);

$BASE = dirname(__DIR__, 4);
require_once $BASE . '/utils/response.php';
require_once $BASE . '/config/sat_catalogos.php';

try {
    $regimen = isset($_GET['regimen']) ? trim((string)$_GET['regimen']) : '';
    if ($regimen === '') { json_error('Parámetro regimen requerido', 422); }
    $codes = get_usos_por_regimen($regimen);
    header('Cache-Control: public, max-age=3600');
    json_response(['ok'=>true, 'data'=>$codes]);
} catch (Throwable $e) {
    json_error('Error al filtrar usos', 500, $e->getMessage());
}
