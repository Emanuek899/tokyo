<?php
class FacturacionRepo {
    private PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

        /**
     * Select SQL query
     */
    public function select(string $sql, array $params, string $mode = ''){
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        switch($mode){
            case 'column':
                return $stmt->fetchColumn();
                break;
            case 'fetch':
                return $stmt->fetch(PDO::FETCH_ASSOC);
                break;
            default:
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
        }
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
}