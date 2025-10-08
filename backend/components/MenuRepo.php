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
        $sql = "SELECT p.id, p.nombre, p.descripcion, p.imagen, :selectPrecio,  c.id AS categoria_id, c.nombre AS categoria_nombre
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
    public function obtener(array $params, $exist, $activo, $estado){
        $sql = "SELECT p.id, p.nombre, p.descripcion, p.imagen, p.precio, p.existencia, p.activo,
                :selectPrecio,
                c.id AS categoria_id, c.nombre AS categoria_nombre
                FROM productos p
                :joins
                WHERE p.id = :id
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $st->fetchAll();
        $item = [
            'id' => (int)$st['id'],
            'nombre' => (string)($st['nombre'] ?? ''),
            'descripcion' => (string)($st['descripcion'] ?? ''),
            'imagen' => $st['imagen'] ?? null,
            'precio' => isset($st['precio']) ? (float)$st['precio'] : null,
            'precio_final' => isset($st['precio_final']) ? (float)$st['precio_final'] : null,
            'categoria_id' => isset($st['categoria_id']) ? (int)$st['categoria_id'] : null,
            'categoria_nombre' => $st['categoria_nombre'] ?? null,
            'existencia' => $exist,
            'activo' => $activo,
            'estado' => $estado,
        ];
        return $item;
    }
}