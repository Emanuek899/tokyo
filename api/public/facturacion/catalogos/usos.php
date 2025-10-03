<?php
declare(strict_types=1);

$BASE = dirname(__DIR__, 4);
require_once $BASE . '/utils/response.php';
require_once $BASE . '/config/sat_catalogos.php';

try {
    $data = get_usos();
    header('Cache-Control: public, max-age=3600');
    json_response(['ok'=>true, 'data'=>$data]);
} catch (Throwable $e) {
    json_error('Error al cargar usos CFDI', 500, $e->getMessage());
}
