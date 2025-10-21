<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/utils/response.php';
require_once dirname(__DIR__, 2) . '/utils/corte.php';

if (!isset($_SESSION['usuario_id'])){
    $_SESSION['usuario_id'] = 1;
}

try {
    header('Content-Type: application/json; charset=utf-8');
    $pdo = DB::get();
    $res = corte_abierto($pdo);
    $out = ['success' => true, 'resultado' => ['abierto' => (bool)$res['abierto']]];
    if (!empty($res['corte_id'])) $out['resultado']['corte_id'] = (int)$res['corte_id'];
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    json_error(['Error al verificar corte'], 500, $e->getMessage());
}

