<?php
class MenuRepo{
    private PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    /** 
     * Lista todos los productos, con capacidad de filtrar por busqueda
     * categoria, sede y precio  
     */ 
    public function listar(array $parameters, array $joinList, array $whereList){
        $whereSql = $whereList ? ('WHERE '.implode(' AND ', $whereList)) : '';
        $joinSql  = $joinList ? (' '.implode(' ', $joinList).' ') : ' ';
        $sql = "SELECT p.id, p.nombre, p.descripcion, p.imagen, p.precio AS precio_final,  c.id AS categoria_id, c.nombre AS categoria_nombre
        FROM productos p{$joinSql}{$whereSql}
        ORDER BY :orderSql
        LIMIT :limit OFFSET :offset";
        $st = $this->pdo->prepare($sql);
          // Bind SOLO de claves usadas en $sql
        foreach ($parameters as $k => $v) {
            if (strpos($sql, $k) !== false) {
            $st->bindValue($k, $v);
            }
        }
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Prepara el contador para el paginador de elementos
     */
    public function contar(array $params,array $joinList, array $whereList){
        $whereSql = $whereList ? ('WHERE '.implode(' AND ', $whereList)) : '';
        $joinSql  = $joinList ? (' '.implode(' ', $joinList).' ') : ' ';
        $sqlCount = "SELECT COUNT(*) FROM productos p{$joinSql}{$whereSql}";
        $st = $this->pdo->prepare($sqlCount);
        foreach ($params as $k => $v) {
            if (strpos($sqlCount, $k) !== false) {
                $st->bindValue($k, $v);
            }
        }
        $st->execute();
        return (int)$st->fetchColumn();
    }

    /**
     * Retorna las categorias de los productos
     */
    public function categorias(){
        $sts = $this->pdo->query("SELECT id, nombre FROM catalogo_categorias ORDER BY nombre ASC");
        return $sts->fetchAll(PDO::FETCH_ASSOC);

    }

    /**
     * Obtiene solo un producto con detalles
     */
    public function obtener(array $params, $exist, $activo, $estado, $joins, $selectPrecio){
        $sql = "SELECT p.id, p.nombre, p.descripcion, p.imagen, p.precio, p.existencia, p.activo,
                {$selectPrecio},
                c.id AS categoria_id, c.nombre AS categoria_nombre
                FROM productos p
                {$joins}
                WHERE p.id = :id
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $exist = isset($row['existencia']) ? (int)$row['existencia'] : 0;
        $activo = isset($row['activo']) ? (int)$row['activo'] : 1;
        $estado = ($activo === 0 || $exist <= 0) ? 'agotado' : 'disponible';
        if($row){
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
        }
        $item = [
            'message' => 'No se encontro el producto',
        ];
        return $item;
    }
    
    public function topVendidosId(array $parameters){
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
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parameters);
        return $stmt->fetchAll();
    }

    public function topVendidos(){
        $sql = "SELECT v.producto_id AS id, p.nombre, p.precio, p.imagen, p.categoria_id, c.nombre AS categoria,
                v.total_vendidos
        FROM vista_productos_mas_vendidos v
        JOIN productos p ON p.id = v.producto_id
        LEFT JOIN catalogo_categorias c ON c.id = p.categoria_id
        ORDER BY v.total_vendidos DESC
        LIMIT 20";
        $rows = $this->pdo->prepare($sql);
        $rows->execute();
        return $rows->fetchAll();
    }
    
}