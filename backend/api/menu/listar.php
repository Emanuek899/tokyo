<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

try {
    $pdo = DB::get();

    $search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
    $categoriaParam = isset($_GET['categoria_id']) ? trim((string)$_GET['categoria_id']) : '';
    $ordenar = $_GET['ordenar'] ?? '';
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

    $where = ['p.activo = 1'];
    $params = [];

    if ($search !== '') {
        $where[] = '(p.nombre LIKE :q OR p.descripcion LIKE :q)';
        $params[':q'] = '%' . $search . '%';
    }

    // categoria_id puede ser CSV
    if ($categoriaParam !== '') {
        $catIds = array_values(array_filter(array_map('intval', explode(',', $categoriaParam)), fn($v) => $v > 0));
        if ($catIds) {
            $in = implode(',', array_fill(0, count($catIds), '?'));
            $where[] = "p.categoria_id IN ($in)";
            $params = array_merge($params, $catIds);
        }
    }

    $orderBy = 'p.nombre ASC';
    if ($ordenar === 'precio-asc') $orderBy = 'p.precio ASC';
    if ($ordenar === 'precio-desc') $orderBy = 'p.precio DESC';

    $sql = "SELECT p.id, p.nombre, p.precio, p.descripcion, p.existencia, p.imagen, p.categoria_id, c.nombre AS categoria
            FROM productos p
            LEFT JOIN catalogo_categorias c ON c.id = p.categoria_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY $orderBy
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);

    // bind dynamic params
    $i = 1;
    foreach ($params as $k => $v) {
        if (is_string($k) && str_starts_with($k, ':')) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        } else {
            $stmt->bindValue($i++, $v, PDO::PARAM_INT);
        }
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();

    // Adapt to frontend shape
    $items = array_map(function($r){
        return [
            'id' => (int)$r['id'],
            'nombre' => $r['nombre'],
            'slug' => rawurlencode($r['nombre']),
            'precio' => (float)$r['precio'],
            'imagen' => $r['imagen'] ?: 'assets/img/placeholder.svg',
            'tags' => [],
            'estado' => ((int)$r['existencia'] > 0 ? 'disponible' : 'agotado'),
            'categoria' => $r['categoria'] ?: 'Sin categoría'
        ];
    }, $rows);

    json_response($items);
} catch (Throwable $e) {
    json_error('Error al listar productos', 500, $e->getMessage());
}

