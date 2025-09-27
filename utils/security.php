<?php
session_start();

function issue_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf_token(): void {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    $valid = $_SESSION['csrf_token'] ?? '';
    if (!$sent || !$valid || !hash_equals($valid, $sent)) {
        http_response_code(419);
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false, 'error'=>'CSRF token inválido']);
        exit;
    }
}

function require_rate_limit(string $key, int $limit = 30, int $windowSeconds = 600): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $now = time();
    $bucketKey = 'rl_' . md5($ip . '|' . $key);
    $bucket = $_SESSION[$bucketKey] ?? ['count' => 0, 'start' => $now];
    if ($now - $bucket['start'] > $windowSeconds) {
        $bucket = ['count' => 0, 'start' => $now];
    }
    $bucket['count']++;
    $_SESSION[$bucketKey] = $bucket;
    if ($bucket['count'] > $limit) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false, 'error'=>'Demasiadas solicitudes; intenta más tarde']);
        exit;
    }
}

