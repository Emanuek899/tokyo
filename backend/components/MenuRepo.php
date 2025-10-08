<?php
class MenuRepo{
    private PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

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
}