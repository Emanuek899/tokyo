<?php
class DB {
    private static $connections = [];

    /**
     * Create a connection with the database
     * @param string $sede the name of the sede
     * @return PDO return the pdo object of the respective sede, with the connec
     *             tion of this one.
     */
    public static function get(string $sede = 'main'): PDO {
        
        if (isset(self::$connections[$sede])) {
            return self::$connections[$sede];
        }

        $dbSede = self::getSedeConfig($sede);
        $host = $dbSede['host'];
        $port = $dbSede['port'];
        $db   = $dbSede['db'];
        $user = $dbSede['user'];
        $pass = $dbSede['pass'];

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, $user, $pass, $opts);

        self::$connections[$sede] = $pdo;

        return $pdo;
    }
    
    /**
     * Select the respective config data for the sede
     * @param string $sede the sede 
     * @return array The database config for the respective sede
     */
    private static function getSedeConfig(string $sede): array {
        $dbSedes = [
            'main' => [
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '3306',
                'db'   => getenv('DB_NAME') ?: 'restaurante',
                'user' => getenv('DB_USER') ?: 'root',
                'pass' => getenv('DB_PASS') ?: '',
            ],
            'A' => [
                'host' => getenv('DB_HOST_A') ?: '127.0.0.1',
                'port' => getenv('DB_PORT_A') ?: '3306',
                'db'   => getenv('DB_NAME_A') ?: 'sede_a_db',
                'user' => getenv('DB_USER_A') ?: 'root_a',
                'pass' => getenv('DB_PASS_A') ?: '',
            ],
            'B' => [
                'host' => getenv('DB_HOST_B') ?: '127.0.0.1',
                'port' => getenv('DB_PORT_B') ?: '3306',
                'db'   => getenv('DB_NAME_B') ?: 'sede_b_db',
                'user' => getenv('DB_USER_B') ?: 'root_b',
                'pass' => getenv('DB_PASS_B') ?: '',
            ]
        ];
        
        if (!isset($dbSedes[$sede])) {
            throw new \Exception("Configuración de DB no encontrada para la sede: $sede");
        }
        return $dbSedes[$sede];
    }
}