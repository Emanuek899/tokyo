<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Método no permitido', 405);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || !isset($input['items']) || !is_array($input['items'])) {
        json_error('Entrada inválida', 422);
    }
    $items = $input['items'];
    $ids = array_values(
        array_unique(
            array_filter(
                array_map(
                    fn($i)=> (int)($i['id'] ?? 0), $items
                ),
                fn($v)=>$v>0
            )
        )
    );
    if (!$ids) json_response(['items'=>[], 'subtotal'=>0, 'envio'=>0, 'total'=>0]);

    $pdo = DB::get();
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, nombre, precio FROM productos WHERE id IN ($in)");
    foreach ($ids as $idx => $id) $stmt->bindValue($idx+1, $id, PDO::PARAM_INT);
    $stmt->execute();
    $prices = [];
    while ($r = $stmt->fetch()) $prices[(int)$r['id']] = ['nombre'=>$r['nombre'], 'precio'=>(float)$r['precio']];

    $outItems = [];
    $subtotal = 0.0;
    foreach ($items as $it) {
        $pid = (int)($it['id'] ?? 0);
        $qty = max(0, (int)($it['cantidad'] ?? 0));
        if ($pid && $qty && isset($prices[$pid])) {
            $precio = $prices[$pid]['precio'];
            $line = $precio * $qty; // cálculo en API
            $subtotal += $line;
            $outItems[] = [
                'id' => $pid,
                'nombre' => $prices[$pid]['nombre'],
                'precio' => $precio,
                'cantidad' => $qty,
                'subtotal' => $line
            ];
        }
    }
    $envio = isset($input['envio']) ? (float)$input['envio'] : 30.0;
    $total = $subtotal + $envio;
    json_response(['items'=>$outItems, 'subtotal'=>$subtotal, 'envio'=>$envio, 'total'=>$total]);
} catch (Throwable $e) {
    json_error('Error al calcular carrito', 500, $e->getMessage());
}

