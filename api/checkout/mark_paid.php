<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/utils/response.php';
require_once dirname(__DIR__, 2) . '/components/CheckoutRepo.php';
require_once dirname(__DIR__, 2) . '/utils/validator.php';


try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error(['Método no permitido'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $ref = isset($input['ref']) ? (string)$input['ref'] : '';
    $validatedRef = Validator::validate(['ref' => $ref], ['ref' => 'Empty|Required']);
    if (!empty($validatedRef)) json_error($validatedRef, 422);
    $pdo = DB::get();
    $repo = new CheckoutRepo($pdo);

    $sqlUpdate = '
        UPDATE conekta_payments 
        SET status="paid", updated_at=NOW() 
        WHERE reference = :ref AND status <> "paid"';
    $paramsUpdate = [':ref' => $ref];
    $repo->update($sqlUpdate, $paramsUpdate);

    $selectSql = '
        SELECT id, reference, status, venta_id 
        FROM conekta_payments 
        WHERE reference = :ref';
    $selectParams = [':ref' => $ref];
    $row = $repo->select($selectSql, $selectParams);
    // $validator = Validator::validate(["conekta_payment" => $row], ["conekta_payment" => 'Existence']);
    // if (!empty($validator)) json_error($validator, 404);
    // json_response(['success' => true, 'reference' => $row['reference'], 'status' => $row['status'], 'venta_id' => $row['venta_id'] ? (int)$row['venta_id'] : null, 'payment_id' => (int)$row['id']]);
    json_response($row);
} catch (Throwable $e) {
    json_error(['Error'], 500, $e->getMessage());
}

