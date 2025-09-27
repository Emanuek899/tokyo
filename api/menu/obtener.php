<?php
declare(strict_types=1);

$BASE = dirname(__DIR__, 2);
// Ajusta estas rutas al layout real del proyecto
if (is_file($BASE . '/backend/config/db.php')) {
  require_once $BASE . '/backend/config/db.php';
  require_once $BASE . '/backend/utils/response.php';
} else {
  require_once $BASE . '/config/db.php';
  require_once $BASE . '/utils/response.php';
}

function table_exists(PDO $pdo, string $table): bool {
  $q = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
  $q->execute([$table]);
  return (bool)$q->fetchColumn();
}
function column_exists(PDO $pdo, string $table, string $column): bool {
  $q = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
  $q->execute([$table, $column]);
  return (bool)$q->fetchColumn();
}

try {
  header('Content-Type: application/json; charset=utf-8');

  $pdo = DB::get();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $id     = (int)($_GET['id'] ?? 0);
  $sedeId = (int)($_GET['sede_id'] ?? 0);

  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parámetro id requerido']);
    return;
  }

  // Precio base (expresión) con posible override por sede
  $haySedeProductos = ($sedeId > 0 && table_exists($pdo, 'sede_productos'));
  $colPrecioSede    = ($haySedeProductos && column_exists($pdo, 'sede_productos', 'precio'));
  $precioExpr       = $colPrecioSede ? 'COALESCE(sp.precio, p.precio)' : 'p.precio';
  $selectPrecio     = $colPrecioSede ? 'COALESCE(sp.precio, p.precio) AS precio_final' : 'p.precio AS precio_final';

  $join = 'LEFT JOIN catalogo_categorias c ON c.id = p.categoria_id';
  $params = [':id' => $id];
  if ($haySedeProductos) {
    $join .= ' LEFT JOIN sede_productos sp ON sp.producto_id = p.id AND sp.sede_id = :sede';
    $params[':sede'] = $sedeId;
  }

  $sql = "SELECT p.id, p.nombre, p.descripcion, p.imagen, p.precio, p.existencia, p.activo,
                 $selectPrecio,
                 c.id AS categoria_id, c.nombre AS categoria_nombre
          FROM productos p
          $join
          WHERE p.id = :id
          LIMIT 1";

  $st = $pdo->prepare($sql);
  foreach ($params as $k => $v) $st->bindValue($k, $v);
  $st->execute();
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
    return;
  }

  // Estado derivado simple
  $exist = isset($row['existencia']) ? (int)$row['existencia'] : 0;
  $activo = isset($row['activo']) ? (int)$row['activo'] : 1;
  $estado = ($activo === 0 || $exist <= 0) ? 'agotado' : 'disponible';

  $item = [
    'id' => (int)$row['id'],
    'nombre' => (string)($row['nombre'] ?? ''),
    'descripcion' => (string)($row['descripcion'] ?? ''),
    'imagen' => $row['imagen'] ?? null,
    'precio' => isset($row['precio']) ? (float)$row['precio'] : null,
    'precio_final' => isset($row['precio_final']) ? (float)$row['precio_final'] : null,
    'categoria_id' => isset($row['categoria_id']) ? (int)$row['categoria_id'] : null,
    'categoria_nombre' => $row['categoria_nombre'] ?? null,
    'existencia' => $exist,
    'activo' => $activo,
    'estado' => $estado,
  ];

  echo json_encode(['success' => true, 'item' => $item], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error'   => 'Error al obtener producto',
    'detail'  => $e->getMessage(),
  ], JSON_UNESCAPED_UNICODE);
}

