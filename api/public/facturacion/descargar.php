<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/config/facturama.php';

try {
    $uuid = trim((string)($_GET['uuid'] ?? ''));
    $tipo = strtolower(trim((string)($_GET['tipo'] ?? 'xml')));
    if ($uuid === '' || !in_array($tipo, ['xml','pdf'], true)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false, 'error'=>'Parámetros inválidos']);
        exit;
    }
    $pdo = DB::get();
    $st = $pdo->prepare('SELECT facturama_id, xml_path, pdf_path FROM facturas WHERE uuid = ? LIMIT 1');
    $st->execute([$uuid]);
    $f = $st->fetch();
    if (!$f) { http_response_code(404); echo 'No encontrado'; exit; }
    $relPath = $tipo === 'xml' ? ($f['xml_path'] ?? '') : ($f['pdf_path'] ?? '');
    $abs = $relPath ? (dirname(__DIR__, 3) . $relPath) : '';
    if ($abs && is_file($abs)) {
        if ($tipo === 'xml') { header('Content-Type: application/xml'); }
        else { header('Content-Type: application/pdf'); }
        header('Content-Disposition: inline; filename="' . basename($abs) . '"');
        readfile($abs); exit;
    }
    // descargar de Facturama
    $fid = $f['facturama_id'] ?? null;
    if (!$fid) { http_response_code(404); echo 'No disponible'; exit; }
    if ($tipo === 'xml') {
        $res = Facturama::request('GET', '/api/Cfdi/xml/issued/' . urlencode((string)$fid), null, ['Accept: application/xml']);
        if (($res['status'] ?? 0) >= 200 && ($res['status'] ?? 0) < 300) {
            header('Content-Type: application/xml'); echo $res['body']; exit;
        }
    } else {
        $res = Facturama::request('GET', '/api/Cfdi/pdf/issued/' . urlencode((string)$fid), null, ['Accept: application/pdf']);
        if (($res['status'] ?? 0) >= 200 && ($res['status'] ?? 0) < 300) {
            header('Content-Type: application/pdf'); echo $res['body']; exit;
        }
    }
    http_response_code(502);
    echo 'No disponible';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error';
}

