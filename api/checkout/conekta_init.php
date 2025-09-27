<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/config/conekta.php';
require_once dirname(__DIR__, 2) . '/utils/response.php';
require_once dirname(__DIR__, 2) . '/utils/cart_session.php';
require_once dirname(__DIR__, 2) . '/utils/corte.php';

// Helper to perform POST to Conekta API
// FIX: Conekta Accept v2.2.0 + Bearer; log 4xx/5xx
function http_post_json(string $url, array $body, array $headers = []): array {
    $ch = curl_init($url);
    $payload = json_encode($body);
    $baseHeaders = [
        'content-type: application/json',
        conekta_accept_header(),
        conekta_lang_header(),
        conekta_bearer_header(),
        'User-Agent: ' . conekta_user_agent(),
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => array_merge($baseHeaders, $headers),
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        error_log('[Conekta][POST] cURL error: '.$err);
        throw new RuntimeException('cURL error: '.$err);
    }
    $json = json_decode($resp, true);
    if ($http < 200 || $http >= 300) {
        $msg = is_array($json) ? json_encode($json, JSON_UNESCAPED_UNICODE) : (string)$resp;
        error_log('[Conekta][POST] HTTP '.$http.' body: '.$msg);
        throw new RuntimeException("Conekta HTTP $http: $msg");
    }
    if (!is_array($json)) {
        throw new RuntimeException('Invalid JSON from Conekta');
    }
    return $json;
}

// Fees helpers (server-side authority)
function toCentsInt(float $mxn): int { return (int) round($mxn * 100, 0, PHP_ROUND_HALF_UP); }
function grossUpAmount(float $p, float $rate, float $fixed, float $iva, ?float $minFee = null): array {
    $den = 1 - (1+$iva)*$rate;
    if (abs($den) < 1e-9) throw new RuntimeException('Denominador cero en gross-up');
    $A = ($p + (1+$iva)*$fixed) / $den;
    $C1 = (($A*$rate) + $fixed) * (1+$iva);
    if ($minFee !== null) {
        $Cmin = $minFee * (1+$iva);
        if ($C1 < $Cmin) {
            $A = $p + $Cmin;
        }
    }
    return ['total' => $A, 'surcharge' => $A - $p];
}
function selectCashTier(array $tiers, float $subtotal): array {
    foreach ($tiers as $t) {
        if (!isset($t['threshold']) || $t['threshold'] === null || $subtotal < (float)$t['threshold']) return $t;
    }
    return end($tiers);
}
function computeSurchargeServer(float $subtotal, string $method, array $feesCfg): array {
    if (empty($feesCfg['pass_through_enabled'])) return ['total'=>$subtotal,'surcharge'=>0.0,'meta'=>['method'=>$method,'enabled'=>false]];
    switch ($method) {
        case 'card':
            $f = $feesCfg['fees']['card'];
            $r=(float)$f['rate']; $fx=(float)$f['fixed']; $iva=(float)$f['iva']; $min = isset($f['min_fee']) ? (float)$f['min_fee'] : null;
            $res = grossUpAmount($subtotal, $r, $fx, $iva, $min);
            $res['meta']=['method'=>'card','rate'=>$r,'fixed'=>$fx,'iva'=>$iva,'min_fee'=>$min,'grossed_up'=>true];
            return $res;
        case 'spei':
        case 'bank_transfer':
            $f = $feesCfg['fees']['spei'];
            $iva=(float)$f['iva']; $fx=(float)$f['fixed'];
            $s = (1+$iva)*$fx;
            return ['total'=>$subtotal+$s,'surcharge'=>$s,'meta'=>['method'=>'spei','fixed'=>$fx,'iva'=>$iva,'grossed_up'=>false]];
        case 'cash':
            $f = $feesCfg['fees']['cash'];
            $tier = selectCashTier($f['tiers'], $subtotal);
            $r=(float)$tier['rate']; $fx=(float)$tier['fixed']; $iva=(float)$f['iva']; $min = isset($tier['min_fee']) ? (float)$tier['min_fee'] : null;
            $res = grossUpAmount($subtotal, $r, $fx, $iva, $min);
            $res['meta']=['method'=>'cash','rate'=>$r,'fixed'=>$fx,'iva'=>$iva,'min_fee'=>$min,'grossed_up'=>true,'threshold'=>$tier['threshold']??null];
            return $res;
        default:
            return ['total'=>$subtotal,'surcharge'=>0.0,'meta'=>['method'=>$method,'enabled'=>false]];
    }
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error('Método no permitido', 405);
    }
    if (!ConektaCfg::privateKey()) {
        json_error('Conekta no configurado (CONEKTA_PRIVATE_KEY)', 500);
    }

    $pdo = DB::get();
    $cart = cart_get_all();
    if (empty($cart)) {
        json_error('Carrito vacío', 422);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $customer_name  = trim((string)($input['nombre'] ?? 'Cliente Tokyo'));
    $customer_phone = trim((string)($input['telefono'] ?? ''));
    // Normalize phone to E.164-ish to avoid 422 from Conekta
    if ($customer_phone !== '') {
        $digits = preg_replace('/\D+/', '', $customer_phone);
        if ($digits !== '') {
            if (strlen($digits) === 10) { $customer_phone = '+52'.$digits; }
            elseif (str_starts_with($digits, '52') && strlen($digits) >= 12) { $customer_phone = '+'.$digits; }
            else { $customer_phone = '+'.$digits; }
        }
    }
    $customer_email = trim((string)($input['email'] ?? ''));
    $payment_methods = $input['metodos'] ?? ['card']; // ['card','cash','bank_transfer']
    $method = is_array($payment_methods) && count($payment_methods) ? (string)$payment_methods[0] : 'card';
    // Optional order context
    $tipo = $input['tipo'] ?? 'rapido';
    $mesa_id = isset($input['mesa_id']) ? (int)$input['mesa_id'] : null;
    $repartidor_id = isset($input['repartidor_id']) ? (int)$input['repartidor_id'] : null;
    $usuario_id = isset($input['usuario_id']) ? (int)$input['usuario_id'] : 1;
    $sede_id = isset($input['sede_id']) ? (int)$input['sede_id'] : 1;
    $observacion = isset($input['observacion']) ? (string)$input['observacion'] : null;

    // Get current prices
    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, nombre, precio FROM productos WHERE id IN ($placeholders)");
    foreach ($ids as $i => $id) $st->bindValue($i+1, $id, PDO::PARAM_INT);
    $st->execute();
    $prodMap = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $prodMap[(int)$r['id']] = [
            'nombre' => (string)$r['nombre'],
            'precio' => (float)$r['precio'],
        ];
    }
    if (!$prodMap) json_error('Carrito inválido', 422);

    $line_items = [];
    $amount_total = 0; // in cents
    $subtotal_mx = 0.0; // MXN
    foreach ($cart as $pid => $qty) {
        $pid = (int)$pid; $qty = max(1, (int)$qty);
        if (!isset($prodMap[$pid])) continue;
        $precio = $prodMap[$pid]['precio'];
        $unit_cents = (int)round($precio * 100);
        $line_items[] = [
            'name' => $prodMap[$pid]['nombre'] ?: ('Producto '.$pid),
            'sku' => (string)$pid,
            'unit_price' => $unit_cents,
            'quantity' => $qty,
        ];
        $amount_total += $unit_cents * $qty;
        $subtotal_mx += $precio * $qty;
    }
    if (!$line_items) json_error('Carrito inválido', 422);

    // Surcharge gross-up calculation (server-side authority)
    $feesCfg = ConektaCfg::feesCfg();
    $calc = computeSurchargeServer($subtotal_mx, $method, $feesCfg);
    $surcharge_mx = (float)$calc['surcharge'];
    $grand_total_mx = (float)$calc['total'];
    $surcharge_cents = toCentsInt($surcharge_mx);
    if ($surcharge_cents > 0) {
        $line_items[] = [
            'name' => 'Comisión por método de pago ('.$calc['meta']['method'].')',
            'sku'  => 'FEE-'.strtoupper($calc['meta']['method']),
            'unit_price' => $surcharge_cents,
            'quantity' => 1,
        ];
        $amount_total += $surcharge_cents;
    }

    // Create local pending record
    $ref = 'tokyo_'.bin2hex(random_bytes(8));
    $ins = $pdo->prepare('INSERT INTO conekta_payments (reference, customer_name, customer_email, customer_phone, amount, currency, status, cart_snapshot, metadata) VALUES (:ref, :name, :email, :phone, :amount, :cur, "pending", :cart, :meta)');
    // Store snapshot with fee info
    $snapshot = [ 'items' => $cart, 'fee' => [ 'method' => $calc['meta']['method'], 'surcharge_mx' => $surcharge_mx, 'surcharge_cents' => $surcharge_cents ] ];
    $cart_snapshot = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
    // Obtener corte abierto actual para preservar integridad en ventas
    $corteInfo = corte_abierto($pdo);
    $corte_id = $corteInfo['corte_id'] ?? null;
    $meta = json_encode([
        'ref' => $ref,
        'context' => [
            'tipo' => $tipo,
            'mesa_id' => $mesa_id,
            'repartidor_id' => $repartidor_id,
            'usuario_id' => $usuario_id,
            'sede_id' => $sede_id,
            'corte_id' => $corte_id,
            'observacion' => $observacion,
        ],
        'surcharge' => [ 'mx' => $surcharge_mx, 'cents' => $surcharge_cents, 'method' => $calc['meta']['method'], 'meta' => $calc['meta'] ],
    ], JSON_UNESCAPED_UNICODE);
    $ins->execute([
        ':ref' => $ref,
        ':name' => $customer_name ?: null,
        ':email' => $customer_email ?: null,
        ':phone' => $customer_phone ?: null,
        ':amount' => $amount_total,
        ':cur' => 'MXN',
        ':cart' => $cart_snapshot,
        ':meta' => $meta,
    ]);
    $payment_id = (int)$pdo->lastInsertId();

    // Build Conekta order with Checkout Redirect
    $order = [
        'currency' => 'MXN',
        'customer_info' => array_filter([
            'name' => $customer_name ?: null,
            'phone' => $customer_phone ?: null,
            'email' => $customer_email ?: null,
        ]),
        'line_items' => $line_items,
        'metadata' => [
            'ref' => $ref,
            'payment_id' => $payment_id,
            'context' => ['tipo' => $tipo, 'sede_id' => $sede_id, 'corte_id' => $corte_id],
            'surcharge' => [ 'cents' => $surcharge_cents, 'method' => $calc['meta']['method'] ],
        ],
        'checkout' => [
            'type' => 'Redirect',
            'allowed_payment_methods' => $payment_methods,
            'success_url' => ConektaCfg::successUrl($ref),
            'failure_url' => ConektaCfg::failureUrl($ref),
            // 'expires_at' => time() + 60*30, // optional: 30 min
        ],
    ];

    try {
    $resp = http_post_json(ConektaCfg::apiBase().'/orders', $order);
    } catch (Throwable $e) {
        // Fallback for older API naming: HostedPayment
        $order['checkout']['type'] = 'HostedPayment';
        $resp = http_post_json(ConektaCfg::apiBase().'/orders', $order);
    }

    $order_id = (string)($resp['id'] ?? '');
    $checkout_id = (string)($resp['checkout']['id'] ?? '');
    $checkout_url = (string)($resp['checkout']['url'] ?? '');

    if (!$order_id || !$checkout_url) {
        throw new RuntimeException('Respuesta inválida de Conekta (sin order_id o url)');
    }

    $up = $pdo->prepare('UPDATE conekta_payments SET conekta_order_id=:oid, conekta_checkout_id=:cid, checkout_url=:url, raw_order=:raw WHERE id=:id');
    $up->execute([
        ':oid' => $order_id,
        ':cid' => $checkout_id ?: null,
        ':url' => $checkout_url,
        ':raw' => json_encode($resp, JSON_UNESCAPED_UNICODE),
        ':id' => $payment_id,
    ]);

    json_response([
        'success' => true,
        'ok' => true,
        'reference' => $ref,
        'checkout_url' => $checkout_url,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    $msg = $e->getMessage();
    echo json_encode([
        'success' => false,
        'error' => 'No se pudo iniciar el pago',
        'details' => $msg,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
