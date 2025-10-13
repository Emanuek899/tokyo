<?php
declare(strict_types=1);
ini_set('display_errors',1);
error_reporting(E_ALL);
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/utils/response.php';
require_once dirname(__DIR__, 2) . '/components/CheckoutRepo.php';
require_once dirname(__DIR__, 2) . '/utils/validator.php';



try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        json_error(['Método no permitido'], 405);
    }
    $ref = $_GET['ref'] ?? null;
    $validatedRef = Validator::validate(['ref' => $ref], ['ref' => 'Required']);
    if (!empty($validatedRef)){
        json_error($validatedRef, 422);
        exit;
    } 

    $pdo = DB::get();
    $repo = new CheckoutRepo($pdo);
    $row = $repo->getStatus($ref);

    if (!$row) json_error(['No encontrado'], 404);
    $stx = (string)$row['status'];
    if (in_array($stx, ['canceled','expired'], true)) { $stx = 'failed'; }
    $surcharge = null;
    if (!empty($row['metadata'])) {
        $meta = json_decode((string)$row['metadata'], true);
        if (isset($meta['surcharge']['mx'])) $surcharge = (float)$meta['surcharge']['mx'];
        elseif (isset($meta['surcharge']['cents'])) $surcharge = ((int)$meta['surcharge']['cents'])/100.0;
    }
    json_response([
        'success' => true,
        'status' => $stx,
        'venta_id' => $row['venta_id'] ? (int)$row['venta_id'] : null,
        'payment_id' => (int)$row['id'],
        'reference' => $row['reference'],
        'surcharge' => $surcharge,
    ]);
} catch (Throwable $e) {
    json_error(['Error'], 500, $e->getMessage());
}
