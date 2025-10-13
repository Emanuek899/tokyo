<?php
class CheckoutRepo{
    private PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    /**
     * Select SQL query
     */
    public function select(string $sql, array $params){
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $row;
    }

    /**
     * Insert SQL Query
     */
    public function insert(string $sql, array $params){
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $id = $this->pdo->lastInsertId();
        return $id;
    }

    /**
     * Update SQL query
     */
    public function update(string $sql, array $params){
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Retorna el status de la venta?
     */
    public function getStatus($ref){
        $sql = 'SELECT id, reference, status, venta_id, checkout_url, conekta_order_id, metadata 
                FROM conekta_payments WHERE reference = :ref';
        $st = $this->pdo->prepare($sql);
        $st->execute([':ref' => $ref]);
        return $st->fetch(PDO::FETCH_ASSOC);
    }
}