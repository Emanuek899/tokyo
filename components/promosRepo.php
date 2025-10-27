<?php
class promosRepo{
    private PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    public function select($sql, $parameters = []){
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parameters);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}