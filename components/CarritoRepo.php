<?php
class CarritoRepo{
    private PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    public function obtenerPrecio(array $ids, string $in){
        $stmt = $this->pdo->prepare("SELECT id, nombre, precio FROM productos WHERE id IN ($in)");
        foreach ($ids as $idx => $id) $stmt->bindValue($idx+1, $id, PDO::PARAM_INT);
        $stmt->execute();
        $prices = [];
        while ($r = $stmt->fetch()){
            $prices[(int)$r['id']] = ['nombre'=>$r['nombre'], 'precio'=>(float)$r['precio']];
        }
        return $prices;
    }
}