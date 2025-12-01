<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/utils/response.php';
require_once dirname(__DIR__, 2) . '/utils/security.php';

try {
    $pdo = DB::get();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        require_rate_limit('clientes-get', 60, 600);
        $rfc = strtoupper(trim((string)($_GET['rfc'] ?? '')));
        if ($rfc === '') { json_error(['RFC requerido'], 422); }
        $st = $pdo->prepare('SELECT id, rfc, razon_social, regimen AS regimen_fiscal, cp AS tax_zip, uso_cfdi, correo FROM clientes_facturacion WHERE rfc = ? LIMIT 1');
        $st->execute([$rfc]);
        $c = $st->fetch();
        if (!$c) { json_response(['ok'=>true, 'cliente'=>null]); }
        json_response(['ok'=>true, 'cliente'=>$c]);
    } elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        require_rate_limit('clientes-post', 30, 600);
        require_csrf_token();
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $rfc = strtoupper(trim((string)($body['rfc'] ?? '')));
        $razon = trim((string)($body['razon_social'] ?? ''));
        $regimen = trim((string)($body['regimen'] ?? ''));
        $cp = trim((string)($body['cp'] ?? ''));
        $uso = trim((string)($body['uso_cfdi'] ?? ''));
        $correo = trim((string)($body['correo'] ?? ''));
        if (!preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/u', $rfc)) { json_error(['RFC inválido'], 422); }
        if (!preg_match('/^[0-9]{5}$/', $cp)) { json_error(['CP inválido'], 422); }
        if ($razon === '' || $regimen === '' || $uso === '') { json_error(['Campos requeridos faltantes'], 422); }

        // Upsert por RFC
        $st = $pdo->prepare('SELECT id FROM clientes_facturacion WHERE rfc = ? LIMIT 1');
        $st->execute([$rfc]);
        $id = (int)($st->fetchColumn() ?: 0);
        if ($id > 0) {
            $up = $pdo->prepare('UPDATE clientes_facturacion SET razon_social=?, regimen=?, cp=?, uso_cfdi=?, correo=? WHERE id=?');
            $up->execute([$razon, $regimen, $cp, $uso, $correo, $id]);
            json_response(['ok'=>true, 'cliente_id'=>$id, 'updated'=>true]);
        } else {
            $ins = $pdo->prepare('INSERT INTO clientes_facturacion (rfc, razon_social, regimen, cp, uso_cfdi, correo) VALUES (?,?,?,?,?,?)');
            $ins->execute([$rfc, $razon, $regimen, $cp, $uso, $correo]);
            json_response(['ok'=>true, 'cliente_id'=>(int)$pdo->lastInsertId()]);
        }
    } else {
        json_error(['Metodo no permitido'], 405);
    }
} catch (Throwable $e) {
    json_error(['Error en clientes fiscales'], 500, $e->getMessage());
}

