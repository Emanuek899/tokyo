<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/utils/response.php';
require_once dirname(__DIR__, 2) . '/utils/cart_session.php';
require_once dirname(__DIR__, 2) . '/config/conekta.php';
require_once dirname(__DIR__, 2) . '/utils/corte.php';
require_once dirname(__DIR__, 2) . '/utils/validator.php';
require_once dirname(__DIR__, 2) . '/components/CheckoutRepo.php';


try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error(['Método no permitido'], 405);
    }
    $pdo = DB::get();
    $cart = cart_get_all();
    $validCart = Validator::validate(['cart' => $cart], ['cart' => 'Empty']);
    if (empty($ValidCart)) {
        json_error($validatedCart, 422);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $tipo = $input['tipo'] ?? 'rapido'; // rapido | mesa | domicilio
    $mesa_id = isset($input['mesa_id']) ? (int)$input['mesa_id'] : null;
    $repartidor_id = isset($input['repartidor_id']) ? (int)$input['repartidor_id'] : null;
    $usuario_id = isset($input['usuario_id']) ? (int)$input['usuario_id'] : 1; // default
    $observacion = isset($input['observacion']) ? (string)$input['observacion'] : null;
    $corte_override = isset($input['corte_id']) ? (int)$input['corte_id'] : null;

    // FIX: sede_id robusto – prioridad: conekta_payments.metadata.context -> $_SESSION -> input
    $sede_id = null;
    $ref = isset($input['ref']) ? (string)$input['ref'] : null;
    $payment_id = isset($input['payment_id']) ? (int)$input['payment_id'] : null;
    if ($ref || $payment_id) {
        $st = $pdo->prepare('SELECT metadata FROM conekta_payments WHERE '.($ref?'reference = ?':'id = ?').' LIMIT 1');
        $st->execute([$ref ?: $payment_id]);
        $metaRow = $st->fetch(PDO::FETCH_ASSOC);
        if ($metaRow && !empty($metaRow['metadata'])) {
            $m = json_decode((string)$metaRow['metadata'], true);
            if (isset($m['context']['sede_id'])) $sede_id = (int)$m['context']['sede_id'];
            if (isset($m['context']['usuario_id'])) $usuario_id = (int)$m['context']['usuario_id'];
        }
    }
    if (!$sede_id && isset($_SESSION['sede_id'])) {
        $sede_id = (int)$_SESSION['sede_id'];
    }
    if (!$sede_id && isset($input['sede_id'])) {
        $sede_id = (int)$input['sede_id'];
    }
    if (!$sede_id) $sede_id = 1; // fallback, pero validaremos contra sedes

    $ids = array_keys($cart);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, precio FROM productos WHERE id IN ($in)");
    foreach ($ids as $i => $id) $st->bindValue($i+1, (int)$id, PDO::PARAM_INT);
    $st->execute();
    $prices = [];
    while ($r = $st->fetch()) $prices[(int)$r['id']] = (float)$r['precio'];

    $total = 0.0;
    $detalles = [];
    foreach ($cart as $pid => $qty) {
        $pid = (int)$pid; $qty = max(1, (int)$qty);
        if (!isset($prices[$pid])) continue;
        $precio = $prices[$pid];
        $total += $precio * $qty;
        $detalles[] = ['producto_id'=>$pid, 'cantidad'=>$qty, 'precio_unitario'=>$precio];
    }
    if (!$detalles) json_error(['Carrito inválido'], 422);

    // Surcharge pass-through (use metadata if available; fallback compute)
    $feesCfg = ConektaCfg::feesCfg();
    $surcharge_mx = 0.0; $method = null;
    if (isset($m) && isset($m['surcharge'])) {
        $sm = $m['surcharge'];
        if (isset($sm['mx'])) $surcharge_mx = (float)$sm['mx'];
        if (isset($sm['cents']) && !$surcharge_mx) $surcharge_mx = ((int)$sm['cents'])/100.0;
        if (isset($sm['method'])) $method = (string)$sm['method'];
    }
    if ($surcharge_mx <= 0 && $method) {
        // fallback compute if needed
        $gross = function(float $p, float $r, float $f, float $iva, ?float $min){
            $den = 1 - (1+$iva)*$r; if (abs($den) < 1e-9) return ['total'=>$p,'surcharge'=>0.0];
            $A = ($p + (1+$iva)*$f) / $den; $C1 = (($A*$r)+$f)*(1+$iva);
            if ($min !== null && $C1 < $min*(1+$iva)) $A = $p + $min*(1+$iva);
            return ['total'=>$A, 'surcharge'=>$A-$p];
        };
        if ($method === 'card') {
            $f=$feesCfg['fees']['card']; $res=$gross($total,(float)$f['rate'],(float)$f['fixed'],(float)$f['iva'], isset($f['min_fee'])?(float)$f['min_fee']:null);
            $surcharge_mx = (float)$res['surcharge'];
        } elseif ($method === 'spei' || $method === 'bank_transfer') {
            $f=$feesCfg['fees']['spei']; $surcharge_mx = (1+(float)$f['iva'])*(float)$f['fixed'];
        } elseif ($method === 'cash') {
            $f=$feesCfg['fees']['cash']; $tier=null; foreach($f['tiers'] as $t){ if(!isset($t['threshold'])||$t['threshold']===null||$total < (float)$t['threshold']) { $tier=$t; break; }} if(!$tier) $tier=end($f['tiers']); $res=$gross($total,(float)$tier['rate'],(float)$tier['fixed'],(float)$f['iva'], isset($tier['min_fee'])?(float)$tier['min_fee']:null); $surcharge_mx=(float)$res['surcharge'];
        }
    }
    if ($surcharge_mx > 0) {
        $total += $surcharge_mx;
        $detalles[] = ['producto_id'=>9000, 'cantidad'=>1, 'precio_unitario'=>$surcharge_mx];
    }

    // FIX: Validar sede existente para evitar FK 1452
    $chkSede = $pdo->prepare('SELECT 1 FROM sedes WHERE id = ? LIMIT 1');
    $chkSede->execute([$sede_id]);
    if (!$chkSede->fetchColumn()) {
        json_error(['Sede inválida'], 400, 'sede_id='.$sede_id.' no existe en sedes');
    }

    // Idempotencia con referencia de pago
    $lockedPay = null;
    if ($ref) {
        $pdo->beginTransaction();
        $qp = $pdo->prepare('SELECT id, status, venta_id FROM conekta_payments WHERE reference = ? FOR UPDATE');
        $qp->execute([$ref]);
        $lockedPay = $qp->fetch(PDO::FETCH_ASSOC);
        if ($lockedPay && (int)$lockedPay['venta_id'] > 0) {
            $venta_id = (int)$lockedPay['venta_id'];
            $pdo->commit();
            cart_clear();
            json_response(['success'=>true,'ok'=>true,'venta_id'=>$venta_id]);
        }
    }
    // Corte abierto para integridad
    $corte_id = $corte_override ?: null;
    if (!$corte_id) {
        $corteInfo = corte_abierto($pdo);
        $corte_id = isset($corteInfo['corte_id']) ? (int)$corteInfo['corte_id'] : null;
    }
    
    if (!$ref) $pdo->beginTransaction();
    $sqlVenta = 'INSERT INTO ventas (
        fecha, mesa_id, repartidor_id, 
        tipo_entrega, usuario_id, total, 
        estatus, entregado, estado_entrega, 
        sede_id, observacion, corte_id, propina_efectivo, 
        propina_cheque, propina_tarjeta) 
        VALUES (NOW(), :mesa, :repartidor, :tipo, 
        :usuario, :total, "activa", 0, "pendiente", :sede, :obs, :corte, 0, 0, 0)
        ';
        
    $iv = $pdo->prepare($sqlVenta);
    $iv->execute([
        ':mesa' => $mesa_id,
        ':repartidor' => $repartidor_id,
        ':tipo' => $tipo,
        ':usuario' => $usuario_id,
        ':total' => $total,
        ':sede' => $sede_id,
        ':obs' => $observacion,
        ':corte' => $corte_id,
    ]);
    $venta_id = (int)$pdo->lastInsertId();

    $idDet = $pdo->prepare('INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_unitario) VALUES (:venta, :prod, :cant, :precio)');
    foreach ($detalles as $d) {
        $idDet->execute([
            ':venta' => $venta_id,
            ':prod' => $d['producto_id'],
            ':cant' => $d['cantidad'],
            ':precio' => $d['precio_unitario'],
        ]);
    }
    $pdo->commit();
    if ($ref && isset($lockedPay['id'])) {
        try {
            $up = $pdo->prepare('UPDATE conekta_payments SET venta_id = :v WHERE id = :id AND (venta_id IS NULL OR venta_id = 0)');
            $up->execute([':v' => $venta_id, ':id' => (int)$lockedPay['id']]);
        } catch (Throwable $e) { /* ignore */ }
    }

    cart_clear();
    json_response(['success' => true, 'ok' => true, 'venta_id' => $venta_id]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    json_error(['Error al confirmar pedido'], 500, throw $e);
}
