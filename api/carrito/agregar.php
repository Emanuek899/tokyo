<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/utils/response.php';
require_once dirname(__DIR__, 2) . '/utils/cart_session.php';
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/utils/corte.php';
require_once dirname(__DIR__, 2) . '/utils/validator.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error('Método no permitido', 405);
    }
    $pdo = DB::get();
    $c = corte_abierto($pdo);
    // Validación: requiere corte de caja abierto
    $validator = Validator::validate(["corte" => $c['abierto']], ['corte' => 'Corte']);
    if (!empty($validator)) {
        json_error(['success'=>false, 'error'=> $validator], 409);
        exit;
    }    
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $producto_id = (int)($input['producto_id'] ?? 0);
    $cantidad = (int)($input['cantidad'] ?? 0);
    $dataVal = [
        'producto_id' => $producto_id,
        'cantidad' => $cantidad
    ];
    $dataRules = [
        'producto_id' => 'Id|Required',
        'cantidad' => 'Cantidad'
    ];
    $validator = Validator::validate($dataVal, $dataRules);
    if(!empty($validator)){
        json_error(['success'=>false, 'error'=> $validator], 422);
        exit;
    }
    cart_add($producto_id, $cantidad);
    json_response(['success'=>true,'ok' => true, 'carrito' => cart_get_all()]);
} catch (Throwable $e) {
    json_error('Error al agregar al carrito', 500, $e->getMessage());
}

