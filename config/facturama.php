<?php
// Facturama API helper for sandbox (CFDI 4.0)

class Facturama {
    public static function baseUrl(): string {
        return rtrim(getenv('FACTURAMA_BASE') ?: 'https://apisandbox.facturama.mx', '/');
    }

    public static function authHeader(): string {
        $user = getenv('FACTURAMA_USER') ?: '';
        $pass = getenv('FACTURAMA_PASS') ?: '';
        return 'Authorization: Basic ' . base64_encode($user . ':' . $pass);
    }

    public static function request(string $method, string $path, $body = null, array $headers = []) {
        $url = self::baseUrl() . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        $defaultHeaders = [self::authHeader(), 'Accept: application/json'];
        if ($body !== null && !is_resource($body)) {
            $defaultHeaders[] = 'Content-Type: application/json';
            $payload = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            throw new RuntimeException('Error HTTP: ' . $err);
        }
        $ct = self::getContentType($headers);
        if ($ct === 'application/pdf' || $ct === 'application/xml') {
            return ['status' => $status, 'body' => $resp];
        }
        $json = json_decode($resp, true);
        if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
            return ['status' => $status, 'raw' => $resp];
        }
        return ['status' => $status, 'json' => $json];
    }

    private static function getContentType(array $headers): string {
        foreach ($headers as $h) {
            if (stripos($h, 'Accept:') === 0) {
                return trim(substr($h, strlen('Accept:')));
            }
        }
        return 'application/json';
    }

    public static function saveFile(string $path, string $content): void {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('No se pudo crear directorio: ' . $dir);
            }
        }
        file_put_contents($path, $content);
    }
}

function facturama_list_branch_offices() {
    try {
        $result = Facturama::request('GET', '/api/v3/BranchOffices');
        if ($result['status'] !== 200 || !isset($result['json'])) {
            return [];
        }
        return $result['json'];
    } catch (Throwable $e) {
        error_log("Error al obtener sucursales de Facturama: " . $e->getMessage());
        return [];
    }
}

function cfdi_expedition_cp(): string { return getenv('CFDI_EXPEDITION_CP') ?: '34217'; }
function cfdi_serie(): string { return getenv('CFDI_SERIE') ?: 'A'; }
