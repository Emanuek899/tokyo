<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/response.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Método no permitido', 405);
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $rfc = trim((string)($body['rfc'] ?? ''));
    $razon = trim((string)($body['razon_social'] ?? ''));
    if ($rfc === '' || $razon === '') json_error('RFC y razón social requeridos', 422);
    $pdo = DB::get();

    // Upsert por RFC
    $exists = $pdo->prepare('SELECT id FROM clientes_facturacion WHERE rfc = ? LIMIT 1');
    $exists->execute([$rfc]);
    $id = $exists->fetchColumn();

    if ($id) {
        $sql = 'UPDATE clientes_facturacion SET razon_social=?, correo=?, telefono=?, calle=?, numero_ext=?, numero_int=?, colonia=?, municipio=?, estado=?, pais=?, cp=?, regimen=?, uso_cfdi=? WHERE id=?';
        $pdo->prepare($sql)->execute([
            $razon,
            $body['correo'] ?? null,
            $body['telefono'] ?? null,
            $body['calle'] ?? null,
            $body['numero_ext'] ?? null,
            $body['numero_int'] ?? null,
            $body['colonia'] ?? null,
            $body['municipio'] ?? null,
            $body['estado'] ?? null,
            $body['pais'] ?? 'México',
            $body['cp'] ?? null,
            $body['regimen'] ?? null,
            $body['uso_cfdi'] ?? null,
            $id
        ]);
    } else {
        $sql = 'INSERT INTO clientes_facturacion (rfc, razon_social, correo, telefono, calle, numero_ext, numero_int, colonia, municipio, estado, pais, cp, regimen, uso_cfdi) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $pdo->prepare($sql)->execute([
            $rfc, $razon,
            $body['correo'] ?? null,
            $body['telefono'] ?? null,
            $body['calle'] ?? null,
            $body['numero_ext'] ?? null,
            $body['numero_int'] ?? null,
            $body['colonia'] ?? null,
            $body['municipio'] ?? null,
            $body['estado'] ?? null,
            $body['pais'] ?? 'México',
            $body['cp'] ?? null,
            $body['regimen'] ?? null,
            $body['uso_cfdi'] ?? null
        ]);
        $id = (int)$pdo->lastInsertId();
    }

    $row = $pdo->prepare('SELECT * FROM clientes_facturacion WHERE id = ?');
    $row->execute([$id]);
    json_response(['cliente' => $row->fetch()]);
} catch (Throwable $e) {
    json_error('Error al registrar cliente', 500, $e->getMessage());
}

