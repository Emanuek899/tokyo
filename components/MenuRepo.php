<?php

declare(strict_types=1);

class MenuRepo
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Lista todos los productos con capacidad de filtrado por búsqueda, categoría, sede o precio.
     * 
     * @param array $parameters   Parámetros para bindear en la consulta (:limit, :offset, :selectPrecio, etc.)
     * @param array $joinList     Lista de joins SQL
     * @param array $whereList    Lista de condiciones WHERE
     * @param bool $colPrecioSede Indica si se usa la columna de precio por sede
     * @param string $orderSql    Campo por el cual ordenar
     * 
     * @return array              Lista de productos
     */
    public function listar(
        array $parameters,
        array $joinList,
        array $whereList,
        bool $colPrecioSede = false,
        string $orderSql = 'p.nombre ASC'
    ): array {
        $whereSql = $whereList ? ('WHERE ' . implode(' AND ', $whereList)) : '';
        $joinSql  = $joinList ? (' ' . implode(' ', $joinList) . ' ') : ' ';
        $selectPrecio = $parameters[':selectPrecio'] ?? 'p.precio AS precio_final';

        // Validar que selectPrecio contenga solo alias válidos
        if (!preg_match('/^[\w\.\s]+AS\s+precio_final$/i', $selectPrecio)) {
            $selectPrecio = 'p.precio AS precio_final';
        }

        $sql = "SELECT p.id, p.nombre, p.descripcion, p.imagen, {$selectPrecio},
                       c.id AS categoria_id, c.nombre AS categoria_nombre
                FROM productos p{$joinSql}{$whereSql}
                ORDER BY {$orderSql}
                LIMIT :limit OFFSET :offset";

        $st = $this->pdo->prepare($sql);

        // Bindeo seguro
        foreach ($parameters as $k => $v) {
            if ($k === ':selectPrecio') continue;

            if (strpos($sql, $k) !== false) {
                if ($k === ':limit' || $k === ':offset') {
                    $st->bindValue($k, (int)$v, PDO::PARAM_INT);
                } else {
                    $st->bindValue($k, $v);
                }
            }
        }

        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta los productos para el paginador.
     *
     * @param array $params     Parámetros a bindear
     * @param array $joinList   Joins
     * @param array $whereList  Condiciones WHERE
     * 
     * @return int              Total de registros encontrados
     */
    public function contar(array $params, array $joinList, array $whereList): int
    {
        $whereSql = $whereList ? ('WHERE ' . implode(' AND ', $whereList)) : '';
        $joinSql  = $joinList ? (' ' . implode(' ', $joinList) . ' ') : ' ';
        $sqlCount = "SELECT COUNT(*) FROM productos p{$joinSql}{$whereSql}";

        $st = $this->pdo->prepare($sqlCount);
        foreach ($params as $k => $v) {
            if (strpos($sqlCount, $k) !== false) {
                $st->bindValue($k, $v);
            }
        }

        $st->execute();
        return (int) $st->fetchColumn();
    }

    /**
     * Retorna las categorías de productos.
     * 
     * @return array
     */
    public function categorias(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, nombre FROM catalogo_categorias ORDER BY nombre ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un solo producto con sus detalles completos.
     * 
     * @param array $params        Parámetros de búsqueda (incluye :id)
     * @param string $joins        Joins necesarios (ya validados)
     * @param string $selectPrecio Campo calculado para el precio
     * 
     * @return array               Datos del producto o mensaje de error
     */
    public function obtener(array $params, string $joins, string $selectPrecio): array
    {
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

        if (!$row) {
            return ['message' => 'No se encontró el producto'];
        }

        $exist = isset($row['existencia']) ? (int)$row['existencia'] : 0;
        $activo = isset($row['activo']) ? (int)$row['activo'] : 1;
        $estado = ($activo === 0 || $exist <= 0) ? 'agotado' : 'disponible';

        return [
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

    /**
     * Obtiene los productos más vendidos por sede.
     * 
     * @param array $parameters Debe incluir :sede
     * @return array
     */
    public function topVendidosId(array $parameters): array
    {
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
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los productos más vendidos en general.
     * 
     * @return array
     */
    public function topVendidos(): array
    {
        $sql = "SELECT v.producto_id AS id,
                       p.nombre,
                       p.precio,
                       p.imagen,
                       p.categoria_id,
                       c.nombre AS categoria,
                       v.total_vendidos
                FROM vista_productos_mas_vendidos v
                JOIN productos p ON p.id = v.producto_id
                LEFT JOIN catalogo_categorias c ON c.id = p.categoria_id
                ORDER BY v.total_vendidos DESC
                LIMIT 20";

        $st = $this->pdo->prepare($sql);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
