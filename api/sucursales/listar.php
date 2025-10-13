<?php
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/db.php';

try {
    $pdo = null;
    try { $pdo = DB::get(); } catch (Throwable $e) { $pdo = null; }
    $city = isset($_GET['ciudad']) ? trim((string)$_GET['ciudad']) : '';
    $sedeId = isset($_GET['sede_id']) ? (int)$_GET['sede_id'] : 0;

    if ($pdo) {
        // Leer desde DB tabla sedes
        if ($sedeId > 0) {
            $stmt = $pdo->prepare("SELECT id, nombre, direccion, telefono, correo, web, activo FROM sedes WHERE activo = 1 AND id = ?");
            $stmt->execute([$sedeId]);
            $rows = $stmt->fetchAll();
        } else {
            $sql = "SELECT id, nombre, direccion, telefono, correo, web, activo FROM sedes WHERE activo = 1";
            $rows = $pdo->query($sql)->fetchAll();
        }
        // Mapear al formato esperado en front (nav: s.nombre; sucursales: s.latitud/s.longitud)
        $items = array_map(function($r) use ($city) {
            $nombre = (string)($r['nombre'] ?? '');
            $item = [
                'id' => (int)$r['id'],
                'nombre' => $nombre,
                'ciudad' => null, // opcional
                'colonia' => $nombre,
                'horario' => '—',
                'servicios' => [],
                'latitud' => null,
                'longitud' => null,
                // alias por compatibilidad (no usados directamente, pero útiles si otro código los lee)
                'lat' => null,
                'lng' => null,
                'direccion' => $r['direccion'] ?? null,
                'telefono' => $r['telefono'] ?? null,
                'correo' => $r['correo'] ?? null,
                'web' => $r['web'] ?? null,
            ];
            // Si se envía ciudad, filtrar rudimentariamente por coincidencia en dirección o nombre
            if ($city !== '') {
                $hay = (stripos($item['direccion'] ?? '', $city) !== false) || (stripos($r['nombre'] ?? '', $city) !== false);
                if (!$hay) return null;
            }
            return $item;
        }, $rows);
        // Limpiar nulos si hubo filtro
        $items = array_values(array_filter($items));
        return json_response($items);
    }

    // Fallback a JSON si no hay DB
    $cand = [
        __DIR__ . '/../vistas/data/sucursales.sample.json',
        __DIR__ . '/../sushi_cliente/data/sucursales.sample.json'
    ];
    $jsonPath = null;
    foreach ($cand as $p) { if (is_readable($p)) { $jsonPath = $p; break; } }
    if (!$jsonPath) { json_response([]); }
    $data = json_decode(file_get_contents($jsonPath), true) ?: [];
    if ($city !== '') {
        $data = array_values(array_filter($data, fn($s) => isset($s['ciudad']) && $s['ciudad'] === $city));
    }
    json_response($data);
} catch (Throwable $e) {
    json_error(['Error al listar sucursales'], 500, $e->getMessage());
}
