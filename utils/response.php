<?php
function json_response($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $code = 400, $extra = null): void {
    $payload = ['error' => $message];
    if ($extra !== null) $payload['details'] = $extra;
    json_response($payload, $code);
}

