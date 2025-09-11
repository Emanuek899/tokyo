<?php
// Simple PDO connection to MariaDB/MySQL for 'restaurante' database

class DB {
    private static $pdo = null;
    private static $sedes = [
        'A' => [
            'host' => getenv('DB_HOST_A') ?: '127.0.0.1',
            'port' => getenv('DB_PORT_A') ?: '3306',
            'db'   => getenv('DB_NAME_A') ?: 'restaurante',
            'user' => getenv('DB_NAME_A') ?: 'root',
            'pass' => getenv('DB_NAME_A') ?: '',
        ],
        'B' => [
            'host' => getenv('DB_HOST_B') ?: '127.0.0.1',
            'port' => getenv('DB_PORT_B') ?: '3306',
            'db'   => getenv('DB_NAME_B') ?: 'restaurante',
            'user' => getenv('DB_NAME_B') ?: 'root',
            'pass' => getenv('DB_NAME_B') ?: '',
        ]
    ];

    public static function get(): PDO {
        if (self::$pdo) return self::$pdo;
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $db   = getenv('DB_NAME') ?: 'restaurante';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        self::$pdo = new PDO($dsn, $user, $pass, $opts);
        return self::$pdo;
    }
}

