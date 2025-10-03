<?php
declare(strict_types=1);

$BASE = dirname(__DIR__, 3);
require_once $BASE . '/utils/response.php';
require_once $BASE . '/config/facturama.php';

try {
    $branches = facturama_list_branch_offices();
    // Normalize minimal fields
    $branches = array_map(function($b){
        return [
            'Id' => $b['Id'] ?? null,
            'Name' => $b['Name'] ?? ($b['Description'] ?? ''),
            'IsDefault' => (bool)($b['IsDefault'] ?? false),
            'Address' => [ 'ZipCode' => $b['Address']['ZipCode'] ?? ($b['ZipCode'] ?? null) ],
            'Series' => $b['Series'] ?? [],
        ];
    }, $branches);
    json_response(['ok'=>true, 'branches'=>$branches]);
} catch (Throwable $e) {
    json_error('Error al listar lugares de expedición', 500, $e->getMessage());
}

