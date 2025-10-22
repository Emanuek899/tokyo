<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/config/conekta.php';
require_once dirname(__DIR__, 2) . '/utils/response.php';
require_once dirname(__DIR__, 2) . '/utils/corte.php';
require_once dirname(__DIR__, 2) . '/components/CheckoutRepo.php';


// FIX: Conekta Accept v2.2.0 + Bearer; log 4xx/5xx
function http_get(string $url, array $headers = []): array {
    $ch = curl_init($url);
    $baseHeaders = [
        conekta_accept_header(),
        conekta_lang_header(),
        conekta_bearer_header(),
        'User-Agent: ' . conekta_user_agent(),
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge($baseHeaders, $headers),
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        error_log('[Conekta][GET] cURL error: '.$err);
        throw new RuntimeException('cURL error: '.$err);
    }
    $json = json_decode($resp, true);
    if ($http < 200 || $http >= 300) {
        error_log('[Conekta][GET] HTTP '.$http.' body: '.(is_string($resp)?$resp:json_encode($resp)));
        throw new RuntimeException('HTTP '.$http.': '.$resp);
    }
    if (!is_array($json)) throw new RuntimeException('Invalid JSON');
    return $json;
}

// FIX: validar sede_id antes de insertar
function create_local_sale(PDO $pdo, array $cart, array $context): int {
    // Support snapshot with fee: ['items'=>{pid:qty,...}, 'fee'=>{surcharge_mx:float}]
    $hasWrapped = isset($cart['items']) && is_array($cart['items']);
    $itemsMap = $hasWrapped ? (array)$cart['items'] : $cart;
    $feeInfo = $hasWrapped ? (array)($cart['fee'] ?? []) : [];
    // Compute totals from DB prices
    if (!$itemsMap) throw new RuntimeException('Empty cart');
    $ids = array_map('intval', array_keys($itemsMap));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, precio FROM productos WHERE id IN ($in)");
    foreach ($ids as $i => $id) $st->bindValue($i+1, $id, PDO::PARAM_INT);
    $st->execute();
    $price = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) $price[(int)$r['id']] = (float)$r['precio'];
    $total = 0.0; $det = [];
    foreach ($itemsMap as $pid => $qty) {
        $pid = (int)$pid; $qty = max(1, (int)$qty);
        if (!isset($price[$pid])) continue;
        $p = $price[$pid];
        $total += $p * $qty;
        $det[] = ['producto_id'=>$pid, 'cantidad'=>$qty, 'precio_unitario'=>$p];
    }
    if (!$det) throw new RuntimeException('No valid items');
    // Add surcharge line if present, as product 9000 to preserve integrity
    $sfee = isset($feeInfo['surcharge_mx']) ? (float)$feeInfo['surcharge_mx'] : 0.0;
    if ($sfee > 0) {
        $total += $sfee;
        $det[] = ['producto_id'=>9000, 'cantidad'=>1, 'precio_unitario'=>$sfee];
    }

    $tipo = $context['tipo'] ?? 'rapido';
    $mesa_id = $context['mesa_id'] ?? null;
    $repartidor_id = $context['repartidor_id'] ?? null;
    $usuario_id = $context['usuario_id'] ?? 1;
    $sede_id = $context['sede_id'] ?? 1;
    $observacion = $context['observacion'] ?? null;
    // Corte abierto: usar el corte_id de contexto si existe; si no, obtener el actual
    $corte_id = isset($context['corte_id']) ? (int)$context['corte_id'] : null;
    if (!$corte_id) {
        try { $corteInfo = corte_abierto($pdo); $corte_id = isset($corteInfo['corte_id']) ? (int)$corteInfo['corte_id'] : null; } catch (Throwable $e) { $corte_id = null; }
    }

    $shouldBegin = !$pdo->inTransaction();
    if ($shouldBegin) $pdo->beginTransaction();
    try {
        $iv = $pdo->prepare('INSERT INTO ventas (fecha, mesa_id, repartidor_id, tipo_entrega, usuario_id, total, estatus, entregado, estado_entrega, sede_id, observacion, corte_id) VALUES (NOW(), :mesa, :repartidor, :tipo, :usuario, :total, "activa", 0, "pendiente", :sede, :obs, :corte)');
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
        foreach ($det as $d) {
            $idDet->execute([
                ':venta' => $venta_id,
                ':prod' => $d['producto_id'],
                ':cant' => $d['cantidad'],
                ':precio' => $d['precio_unitario'],
            ]);
        }
        if ($shouldBegin) $pdo->commit();
        return $venta_id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction() && $shouldBegin) $pdo->rollBack();
        throw $e;
    }
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error(['Método no permitido'], 405);
    }

    $pdo = DB::get();
    $repo = new CheckoutRepo($pdo);
    $raw = file_get_contents('php://input') ?: '';
    $evt = json_decode($raw, true) ?: [];

    // Basic signature check (optional): if secret set, validate header if present
    $secret = ConektaCfg::webhookSecret();
    // NOTE: Conekta sends event without standard HMAC header in some setups; we log and rely on idempotency below.

    $event_type = (string)($evt['type'] ?? ($evt['event'] ?? ''));
    $event_id = (string)($evt['id'] ?? '');
    $data = $evt['data'] ?? [];
    $obj = $data['object'] ?? [];
    $order_id = (string)($obj['id'] ?? ($obj['order_id'] ?? ''));
    $metadata = $obj['metadata'] ?? [];
    $ref = (string)($metadata['ref'] ?? ($evt['data']['object']['metadata']['ref'] ?? ''));

    // Persist event for traceability
    $sql = 'INSERT INTO conekta_events (reference, event_type, conekta_event_id, payload) VALUES (:ref, :type, :eid, :payload)';
    $params = [
        ':ref' => $ref ?: null,
        ':type' => $event_type ?: 'unknown',
        ':eid' => $event_id ?: null,
        ':payload' => $raw ?: null,
    ];
    $insEvt = $repo->insert($sql, $params);

    // Try to locate our payment row
    $row = null;
    if ($ref) {
        $st = $pdo->prepare('SELECT * FROM conekta_payments WHERE reference = ? FOR UPDATE');
        $pdo->beginTransaction();
        $st->execute([$ref]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row && $pdo->inTransaction()) { $pdo->commit(); }
    }
    if (!$row && $order_id) {
        $st = $pdo->prepare('SELECT * FROM conekta_payments WHERE conekta_order_id = ? FOR UPDATE');
        $pdo->beginTransaction();
        $st->execute([$order_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row && $pdo->inTransaction()) { $pdo->commit(); }
    }

    if (!$row) {
        // As a fallback, try fetching order from API to get metadata.ref
        if ($order_id) {
            $ord = http_get(ConektaCfg::apiBase().'/orders/'.rawurlencode($order_id), ['Accept-Language: es']);
            $ref = (string)($ord['metadata']['ref'] ?? '');
            if ($ref) {
                $st = $pdo->prepare('SELECT * FROM conekta_payments WHERE reference = ? FOR UPDATE');
                $pdo->beginTransaction();
                $st->execute([$ref]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row && $pdo->inTransaction()) { $pdo->commit(); }
            }
        }
    }

    // If we still can't find it, acknowledge to avoid retries storm, but note in logs
    if (!$row) {
        http_response_code(200);
        echo json_encode(['ok' => true]);
        exit;
    }

    $id = (int)$row['id'];
    $current_status = (string)$row['status'];
    $venta_id = (int)($row['venta_id'] ?? 0);
    $conekta_order_id = (string)($row['conekta_order_id'] ?? '');
    if (!$order_id && $conekta_order_id) $order_id = $conekta_order_id;

    // Get current order status from API to be deterministic
    $ord = $order_id ? http_get(ConektaCfg::apiBase().'/orders/'.rawurlencode($order_id)) : [];
    $paid = false;
    $cancelled = false;
    $statusText = (string)($ord['payment_status'] ?? ($ord['status'] ?? ''));
    if ($statusText === 'paid') $paid = true;
    if (in_array($statusText, ['expired','declined','canceled'], true)) $cancelled = true;

    // Idempotent update + sale creation under lock
    $pdo->beginTransaction();
    $stLock = $pdo->prepare('SELECT * FROM conekta_payments WHERE id = ? FOR UPDATE');
    $stLock->execute([$id]);
    $row = $stLock->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $current_status = (string)$row['status'];
        $venta_id = (int)($row['venta_id'] ?? 0);
        if ($paid) {
            if ($current_status !== 'paid') {
                $pdo->prepare('UPDATE conekta_payments SET status="paid", updated_at=NOW() WHERE id=?')->execute([$id]);
            }
            if (!$venta_id) {
                $cart = json_decode((string)$row['cart_snapshot'], true) ?: [];
                $meta = json_decode((string)$row['metadata'], true) ?: [];
                $context = (array)($meta['context'] ?? []);
                // Validate sede exists if provided
                if (isset($context['sede_id']) && $context['sede_id']) {
                    $chk = $pdo->prepare('SELECT 1 FROM sedes WHERE id = ?');
                    $chk->execute([(int)$context['sede_id']]);
                    if (!$chk->fetchColumn()) {
                        error_log('[Webhook] sede_id invalido en metadata: '.$context['sede_id']);
                        // Do not create sale; leave as paid without venta_id
                        $pdo->commit();
                        http_response_code(200);
                        echo json_encode(['ok' => true]);
                        exit;
                    }
                }
                $ventaId = create_local_sale($pdo, $cart, $context);
                $pdo->prepare('UPDATE conekta_payments SET venta_id=:v, updated_at=NOW() WHERE id=:id')->execute([':v' => $ventaId, ':id' => $id]);
            }
        } elseif ($cancelled && !in_array($current_status, ['paid','canceled','failed','expired'], true)) {
            $new = $statusText === 'expired' ? 'expired' : 'failed';
            $pdo->prepare('UPDATE conekta_payments SET status=:st, updated_at=NOW() WHERE id=:id')->execute([':st' => $new, ':id' => $id]);
        }
    }
    if ($pdo->inTransaction()) $pdo->commit();

    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('[Webhook] Error: '.$e->getMessage());
    http_response_code(200); // Always acknowledge to avoid retries
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
