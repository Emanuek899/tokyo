<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

try {
    $pdo = DB::get();
    $sedeId = isset($_GET['sede_id']) ? (int)$_GET['sede_id'] : 0;

    if ($sedeId > 0) {
        $sql = "SELECT vd.producto_id AS id,
                       p.nombre,
                       p.precio,
                       p.imagen,
                       p.categoria_id,
                       c.nombre AS categoria,
                       SUM(vd.cantidad) AS total_vendidos
                FROM venta_detalles vd
                JOIN productos p ON p.id = vd.producto_id
                LEFT JOIN catalogo_categorias c ON c.id = p.categoria_id
                JOIN tickets t ON t.venta_id = vd.venta_id
                WHERE t.sede_id = :sede
                GROUP BY vd.producto_id, p.nombre, p.precio, p.imagen, p.categoria_id, c.nombre
                ORDER BY total_vendidos DESC
                LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':sede', $sedeId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
    } else {
        $sql = "SELECT v.producto_id AS id, p.nombre, p.precio, p.imagen, p.categoria_id, c.nombre AS categoria,
                       v.total_vendidos
                FROM vista_productos_mas_vendidos v
                JOIN productos p ON p.id = v.producto_id
                LEFT JOIN catalogo_categorias c ON c.id = p.categoria_id
                ORDER BY v.total_vendidos DESC
                LIMIT 20";
        $rows = $pdo->query($sql)->fetchAll();
    }

    $items = array_map(function($r){
        return [
            'id' => (int)$r['id'],
            'nombre' => $r['nombre'],
            'slug' => rawurlencode($r['nombre']),
            'precio' => (float)$r['precio'],
            'imagen' => $r['imagen'] ?: 'assets/img/placeholder.svg',
            'tags' => [],
            'estado' => 'disponible',
            'categoria' => $r['categoria'] ?: 'Sin categoría',
            'vendidos' => (int)$r['total_vendidos']
        ];
    }, $rows);
    json_response($items);
} catch (Throwable $e) {
    json_error('Error al listar top vendidos', 500, $e->getMessage());
}

