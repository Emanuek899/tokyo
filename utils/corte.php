<?php
// Helper para validar corte de caja abierto
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function corte_abierto(PDO $pdo): array {
    $uid = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
    if ($uid) {
        $st = $pdo->prepare('SELECT id FROM corte_caja WHERE usuario_id = ? AND fecha_fin IS NULL ORDER BY fecha_inicio DESC LIMIT 1');
        $st->execute([$uid]);
    } else {
        $st = $pdo->query('SELECT id FROM corte_caja WHERE fecha_fin IS NULL ORDER BY fecha_inicio DESC LIMIT 1');
    }
    $id = $st ? $st->fetchColumn() : false;
    return [ 'abierto' => $id ? true : false, 'corte_id' => $id ? (int)$id : null ];
}

