<?php
// Simple PDO connection to MariaDB/MySQL for 'restaurante' database

class DB {
    private static $pdo = null;

    public static function get(string $sede = 'A'): PDO {
        if (self::$pdo) return self::$pdo;
        $sedes = [
            'A' => [
                'host' => getenv('DB_HOST_A') ?: '127.0.0.1',
                'port' => getenv('DB_PORT_A') ?: '3306',
                'db'   => getenv('DB_NAME_A') ?: 'restaurante',
                'user' => getenv('DB_USER_A') ?: 'root',
                'pass' => getenv('DB_PASS_A') ?: '',
            ],
            'B' => [
                'host' => getenv('DB_HOST_B') ?: '127.0.0.1',
                'port' => getenv('DB_PORT_B') ?: '3306',
                'db'   => getenv('DB_NAME_B') ?: 'restaurante_esp',
                'user' => getenv('DB_USER_B') ?: 'root',
                'pass' => getenv('DB_PASS_B') ?: '',
            ]
        ];
        try{
            if($sede == 'A'){
                $dbs = $sedes['A'];
                $dsn = "mysql:host={$dbs['host']};port={$dbs['port']};dbname={$dbs['db']};charset=utf8mb4";
                $opts = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                self::$pdo = new PDO($dsn, $dbs['user'], $dbs['pass'], $opts);
                return self::$pdo;
            }else {
                $dbs = $sedes['B'];
                $dsn = "mysql:host={$dbs['host']};port={$dbs['port']};dbname={$dbs['db']};charset=utf8mb4";
                $opts = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                self::$pdo = new PDO($dsn, $dbs['user'], $dbs['pass'], $opts);
                return self::$pdo;
            }
        }catch(PDOException $e){
            error_log("Error de conexion a la base de datos" . $e ->getMessage());
        }
    }
}

