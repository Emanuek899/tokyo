<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

$BASE = dirname(__DIR__, 2);
// Ajusta estas rutas al layout real del proyecto
if (is_file($BASE . '/backend/config/db.php')) {
  require_once $BASE . '/backend/config/db.php';

} else {
  require_once $BASE . '/config/db.php';
}
require_once __DIR__ . '/backend/components/MenuRepo.php';
require_once __DIR__ . '/utils/validator.php';
require_once __DIR__ . '/utils/response.php';


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
  $id     = (int)($_GET['id'] ?? 0);
  $sedeId = (int)($_GET['sede_id'] ?? 0);

  $valData = [['id' => $id],['id' => 'Id']];

  $validatedData = Validator::validate($valData[0], $valData[1]);
  if (!empty($validatedData)) {
    json_error(['success' => false, 'error' => $validatedData]);
    exit;
  }

  $pdo = DB::get();

  // Precio base (expresión) con posible override por sede
  $haySedeProductos = ($sedeId > 0 && table_exists($pdo, 'sede_productos'));
  $colPrecioSede    = ($haySedeProductos && column_exists($pdo, 'sede_productos', 'precio'));
  $precioExpr       = $colPrecioSede ? 'COALESCE(sp.precio, p.precio)' : 'p.precio';
  $selectPrecio     = $colPrecioSede ? 'COALESCE(sp.precio, p.precio) AS precio_final' : 'p.precio AS precio_final';
  $join = 'LEFT JOIN catalogo_categorias c ON c.id = p.categoria_id';
  if ($haySedeProductos) {
    $join .= ' LEFT JOIN sede_productos sp ON sp.producto_id = p.id AND sp.sede_id = :sede';
    $params[':sede'] = $sedeId;
  }
  $params[':id'] =  $id;
  $exist = isset($row['existencia']) ? (int)$row['existencia'] : 0;
  $activo = isset($row['activo']) ? (int)$row['activo'] : 1;
  $estado = ($activo === 0 || $exist <= 0) ? 'agotado' : 'disponible';

  $repo = new MenuRepo($pdo);
  $row = $repo->obtener($params, $exist, $activo, $estado, $join, $selectPrecio);

  echo json_encode(['success' => true, 'item' => $row], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error'   => 'Error al obtener producto',
    'detail'  => $e->getMessage(),
  ], JSON_UNESCAPED_UNICODE);
}

