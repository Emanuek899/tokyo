<?php
declare(strict_types=1);

$BASE = dirname(__DIR__, 3);
require_once $BASE . '/utils/response.php';
require_once $BASE . '/config/facturama.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Verificar configuración de Facturama
    if (!getenv('FACTURAMA_USER') || !getenv('FACTURAMA_PASS')) {
        json_response([
            'ok' => true,
            'branches' => [
                [
                    'Id' => '1',
                    'Name' => 'Sucursal Principal',
                    'IsDefault' => true,
                    'Address' => ['ZipCode' => cfdi_expedition_cp()],
                    'Series' => [['Serie' => cfdi_serie()]]
                ],
                [
                    'Id' => '2',
                    'Name' => 'Sucursal Domingo Arrieta',
                    'IsDefault' => false,
                    'Address' => ['ZipCode' => cfdi_expedition_cp()],
                    'Series' => [['Serie' => cfdi_serie()]]
                ]
            ]
        ]);
        exit;
    }

    $branches = facturama_list_branch_offices();
    if (empty($branches)) {
        json_response([
            'ok' => true,
            'branches' => [
                [
                    'Id' => '1',
                    'Name' => 'Sucursal Forestal',
                    'IsDefault' => true,
                    'Address' => ['ZipCode' => cfdi_expedition_cp()],
                    'Series' => [['Serie' => cfdi_serie()]]
                ],
                [
                    'Id' => '2',
                    'Name' => 'Sucursal Domingo Arrieta',
                    'IsDefault' => false,
                    'Address' => ['ZipCode' => cfdi_expedition_cp()],
                    'Series' => [['Serie' => cfdi_serie()]]
                ]
            ]
        ]);
        exit;
    }

    // Normalize minimal fields
    $branches = array_map(static function($b){
        return [
            'Id' => $b['Id'] ?? null,
            'Name' => $b['Name'] ?? ($b['Description'] ?? ''),
            'IsDefault' => (bool)($b['IsDefault'] ?? false),
            'Address' => [ 'ZipCode' => $b['Address']['ZipCode'] ?? ($b['ZipCode'] ?? cfdi_expedition_cp()) 
        ],
            'Series' => $b['Series'] ?? [['Serie' => cfdi_serie()]]
        ];
    }, $branches);

    json_response(['ok'=>true, 'branches'=>$branches]);

} catch (Throwable $e) {

    error_log("Error en branch-offices.php: " . $e->getMessage());
    json_response([
        
        'ok' => true,
        'branches' => [
            [
                'Id' => '1',
                'Name' => 'Sucursal Principal',
                'IsDefault' => true,
                'Address' => ['ZipCode' => cfdi_expedition_cp()],
                'Series' => [['Serie' => cfdi_serie()]]
            ],
            [
                'Id' => '2',
                'Name' => 'Sucursal Domingo Arrieta',
                'IsDefault' => false,
                'Address' => ['ZipCode' => cfdi_expedition_cp()],
                'Series' => [['Serie' => cfdi_serie()]]
            ]
        ]
    ]);
}

