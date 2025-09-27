<?php
// Conekta configuration helper

class ConektaCfg {
    public static function apiVersion(): string {
        return '2.2.0';
    }
    public static function privateKey(): string {
        $k = getenv('CONEKTA_PRIVATE_KEY') ?: 'key_uqtl4GdCdSEPXT0mnNvYuNq';
        return $k;
    }
    public static function publicKey(): string {
        $k = getenv('CONEKTA_PUBLIC_KEY') ?: 'key_BP16ubnqXqI6K1Tzv2JDjS7';
        return $k;
    }
    public static function webhookSecret(): string {
        return getenv('CONEKTA_WEBHOOK_SECRET') ?: 'Test1234';
    }
    public static function apiBase(): string {
        // Conekta v2 API base
        return 'https://api.conekta.io';
    }
    public static function successUrl(string $ref): string {
        return self::baseUrl()."/rest2/tokyo/vistas/pago_exitoso.php?ref=".rawurlencode($ref);
    }
    public static function failureUrl(string $ref): string {
        return self::baseUrl()."/tokyo/vistas/pago_fallido.php?ref=".rawurlencode($ref);
    }
    public static function baseUrl(): string {
        // Tries to infer from server vars; fallback to http://localhost
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        return $scheme.'://'.$host;
    }

    // Pass-through fees configuration (defaults). Read-only helper.
    public static function passThroughEnabled(): bool {
        $v = getenv('CONEKTA_PASS_THROUGH');
        if ($v === false || $v === '') return true; // enabled by default
        $v = strtolower((string)$v);
        return !in_array($v, ['0','false','off','no'], true);
    }

    public static function feesCfg(): array {
        // Default example values; adjust via env or override in code if needed
        return [
            'fees' => [
                'card' => [ 'rate' => 0.034, 'fixed' => 3.00, 'iva' => 0.16, 'min_fee' => 5.40 ],
                'spei' => [ 'rate' => 0.0,   'fixed' => 12.50, 'iva' => 0.16, 'min_fee' => null ],
                'cash' => [ 'iva' => 0.16, 'tiers' => [
                    [ 'threshold' => 700.00, 'rate' => 0.027, 'fixed' => 2.70, 'min_fee' => 5.40 ],
                    [ 'threshold' => null,   'rate' => 0.017, 'fixed' => 10.0, 'min_fee' => 5.40 ],
                ]],
            ],
            'pass_through_enabled' => self::passThroughEnabled(),
        ];
    }
}

function conekta_bearer_header(): string {
    $key = ConektaCfg::privateKey();
    return 'authorization: Bearer '.$key;
}

function conekta_user_agent(): string {
    return 'TokyoSushi/1.0 (PHP/'.PHP_VERSION.')';
}

function conekta_accept_header(): string {
    return 'accept: application/vnd.conekta-v'.ConektaCfg::apiVersion().'+json';
}

function conekta_lang_header(): string {
    return 'accept-language: es';
}
