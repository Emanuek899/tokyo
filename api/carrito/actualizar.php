<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/utils/response.php';
require_once dirname(__DIR__, 2) . '/utils/cart_session.php';
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/utils/corte.php';
require_once dirname(__DIR__, 2) . '/utils/validator.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error(['Método no permitido'], 405);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $producto_id = (int)($input['producto_id'] ?? 0);
    $cantidad = (int)($input['cantidad'] ?? -1);

    $valData = [
        'producto_id' => $producto_id,
        'cantidad' => $cantidad
    ];
    $valRules = [
        'producto_id' => 'Id',
        'cantidad' => 'Cantidad'
    ];
    $validator = Validator::validate($valData, $valRules);
    if(!empty($validatedData)){
        json_error(['success'=>false, 'error'=> $validator], 422); 
        exit;
    }

    $cart = cart_get_all();
    $cur = isset($cart[$producto_id]) ? (int)$cart[$producto_id] : 0;
    $isIncrease = ($cantidad > $cur);
    if ($isIncrease) {
        $pdo = DB::get();
        $c = corte_abierto($pdo);
        $validator = Validator::validate(['corte' => $c['abierto']], ['corte' => 'Corte|Required']);
        if (empty($c['abierto'])) {
            $data = [
                'success'=>false,
                'error'=>'No hay corte de caja abierto',
                'details'=>'Abra un corte para poder tomar pedidos'
            ];
            json_response($data, 409);
            exit;
        }
    }
    cart_set_qty($producto_id, $cantidad);
    json_response(['success'=>true,'ok' => true, 'carrito' => cart_get_all()]);
} catch (Throwable $e) {
    json_error(['Error al actualizar carrito'], 500, $e->getMessage());
}

