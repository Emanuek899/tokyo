<?php
declare(strict_types=1);

$BASE = dirname(__DIR__, 2);
require_once $BASE . '/backend/components/MenuRepo.php';
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

  $pdo = DB::get("A");
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Params de entrada
  $search      = trim((string)($_GET['search'] ?? ''));
  $ordenarIn   = trim((string)($_GET['ordenar'] ?? 'nombre')); // nombre|relevancia|precio_asc|precio_desc|novedad|id_asc|id_desc
  $categoriaId = (int)($_GET['categoria_id'] ?? 0);
  $sedeId      = (int)($_GET['sede_id'] ?? 0);
  $precioMin   = isset($_GET['precio_min']) ? (float)$_GET['precio_min'] : null;
  $precioMax   = isset($_GET['precio_max']) ? (float)$_GET['precio_max'] : null;

  $page        = max(1, (int)($_GET['page'] ?? 1));
  $perPage     = min(60, max(1, (int)($_GET['per_page'] ?? 24)));
  $offset      = ($page - 1) * $perPage;

  // Normalizar ordenar
  $ordenar = $ordenarIn;
  if ($ordenar === 'relevancia' && $search === '') {
    $ordenar = 'nombre'; // relevancia sin query no tiene sentido
  }

  // Joins dinámicos (por sede/categoría)
  $joins  = [];
  $wheres = [];
  $params = [];

  // Precio base (expresión) para usar en WHERE y ORDER sin depender del alias
  $haySedeProductos = ($sedeId > 0 && table_exists($pdo, 'sede_productos'));
  $colPrecioSede    = ($haySedeProductos && column_exists($pdo, 'sede_productos', 'precio'));
  $precioExpr       = $colPrecioSede ? 'COALESCE(sp.precio, p.precio)' : 'p.precio';

  if ($haySedeProductos) {
    $joins[] = 'LEFT JOIN sede_productos sp ON sp.producto_id = p.id AND sp.sede_id = :sede';
    $params[':sede'] = $sedeId;
  }

  // Filtro por categoría (columna directa o pivote)
  $joins[] = 'LEFT JOIN catalogo_categorias c ON c.id = p.categoria_id';
  if ($categoriaId > 0) {
    if (column_exists($pdo, 'productos', 'categoria_id')) {
      $wheres[] = 'p.categoria_id = :cat';
      $params[':cat'] = $categoriaId;
    } elseif (table_exists($pdo, 'producto_categorias')) {
      $joins[] = 'JOIN producto_categorias pc ON pc.producto_id = p.id';
      $wheres[] = 'pc.categoria_id = :cat';
      $params[':cat'] = $categoriaId;
    }
  }

  // Búsqueda
  $usarRelevancia = ($ordenar === 'relevancia' && $search !== '');
  if ($search !== '') {
    $wheres[] = '(p.nombre LIKE :q OR p.descripcion LIKE :q)';
    $params[':q'] = '%'.$search.'%';
    if ($usarRelevancia) {
      // :qs SOLO si se usa en ORDER por relevancia
      $params[':qs'] = $search.'%';
    }
  }

  // Filtro por rango de precio (usa expresión, no alias)
  if ($precioMin !== null) {
    $wheres[] = "($precioExpr) >= :pmin";
    $params[':pmin'] = $precioMin;
  }
  if ($precioMax !== null) {
    $wheres[] = "($precioExpr) <= :pmax";
    $params[':pmax'] = $precioMax;
  }

  // ORDER BY (sin alias desconocido)
  switch ($ordenar) {
    case 'precio_asc':  $orderSql = "$precioExpr ASC"; break;
    case 'precio_desc': $orderSql = "$precioExpr DESC"; break;
    case 'novedad':     $orderSql = column_exists($pdo, 'productos', 'created_at') ? 'p.created_at DESC' : 'p.id DESC'; break;
    case 'id_desc':     $orderSql = 'p.id DESC'; break;
    case 'id_asc':      $orderSql = 'p.id ASC';  break;
    case 'relevancia':  $orderSql = 'CASE WHEN p.nombre LIKE :qs THEN 0 ELSE 1 END, p.nombre ASC'; break;
    case 'nombre':
    default:            $orderSql = 'p.nombre ASC';
  }

  $params[':selectPrecio'] = $selectPrecio;
  $params[':offset'] = $offset;
  $params[':limit'] = $perPage;
  $params[':orderSql'] = $orderSql;

  $pdo = DB::get();
  $repo = new MenuRepo($pdo);
  $total = $repo->contar($params, $joins, $wheres);
  $items = $repo->listar($params, $joins, $wheres, $colPrecioSede);
  
  echo json_encode([
    'success'   => true,
    'total'     => $total,
    'page'      => $page,
    'per_page'  => $perPage,
    'items'     => $items
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error'   => 'Error en listar menú',
    'detail'  => $e->getMessage(),
  ], JSON_UNESCAPED_UNICODE);
}
