<?php
// --- Utilidad para normalizar contenido CFDI/XML/PDF sin romper el flujo actual ---
function unwrap_if_base64_or_json($input) {
    if (!is_string($input) || $input === '') return $input;
    $trim = ltrim($input);

    // Caso A: JSON con {ContentEncoding:"base64", Content:"..."} (PDF o XML)
    if ($trim !== '' && $trim[0] === '{') {
        $j = json_decode($trim, true);
        if (json_last_error() === JSON_ERROR_NONE
            && isset($j['ContentEncoding'], $j['Content'])
            && strtolower((string)$j['ContentEncoding']) === 'base64') {
            $decoded = base64_decode($j['Content'], true);
            if ($decoded !== false) return $decoded; // PDF o XML binario
        }
    }

    // Caso B: XML plano tal cual
    if (function_exists('str_starts_with')) {
        if (str_starts_with($trim, '<?xml') || str_starts_with($trim, '<cfdi:')) {
            return $input;
        }
    } else {
        if (substr($trim, 0, 5) === '<?xml' || substr($trim, 0, 6) === '<cfdi:') {
            return $input;
        }
    }

    // Caso C (defensivo): base64 "pelón" que al decodificar inicia con "%PDF" o "<"
    $maybe = base64_decode($trim, true);
    if ($maybe !== false) {
        $m = ltrim($maybe);
        if ($m !== '' && ($m[0] === '<' || strncmp($m, "%PDF", 4) === 0)) return $maybe;
    }

    return $input;
}

