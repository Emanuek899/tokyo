<?php
// SAT catálogos 4.0 desde JSON: helper de lectura y validación

function sat_catalogs_path(): string {
    return __DIR__ . '/sat_catalogos_4_0.json';
}

function load_sat_catalogs(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $path = sat_catalogs_path();
    if (!is_file($path)) {
        throw new RuntimeException('SAT catálogos JSON no encontrado');
    }
    $raw = file_get_contents($path);
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('SAT catálogos JSON inválido');
    }
    $cache = $json;
    return $cache;
}

function get_regimenes(): array {
    $cats = load_sat_catalogs();
    return array_values($cats['regimenes'] ?? []);
}

function get_usos(): array {
    $cats = load_sat_catalogs();
    return array_values($cats['usos'] ?? []);
}

function get_usos_por_regimen(string $regimen): array {
    $regimen = trim($regimen);
    $cats = load_sat_catalogs();
    $comp = $cats['compatibilidad'] ?? [];
    foreach ($comp as $row) {
        if ((string)($row['regimen'] ?? '') === $regimen) {
            return array_values($row['usos'] ?? []);
        }
    }
    // Si no hay match, devolver vacío (no todos los usos)
    return [];
}

function is_uso_permitido(string $regimen, string $uso): bool {
    $uso = strtoupper(trim($uso));
    $permitidos = get_usos_por_regimen($regimen);
    return in_array($uso, $permitidos, true);
}

function is_rfc_generico(string $rfc): bool {
    $rfc = strtoupper(trim($rfc));
    return in_array($rfc, ['XAXX010101000','XEXX010101000'], true);
}

