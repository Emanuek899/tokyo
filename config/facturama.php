<?php
/**
 * Facturama API Helper (CFDI 4.0)
 * 
 * Clase helper para integración con el servicio de facturación electrónica
 * Facturama. Soporta ambientes de sandbox y producción.
 * 
 * Variables de entorno requeridas:
 * - FACTURAMA_BASE: URL base del API (default: sandbox)
 * - FACTURAMA_USER: Usuario de la cuenta
 * - FACTURAMA_PASS: Contraseña de la cuenta
 * - FACTURAMA_EXPEDITION_PLACE: Código postal del lugar de expedición
 * - CFDI_EXPEDITION_CP: Código postal (fallback legacy)
 * - CFDI_SERIE: Serie de facturación (default: 'A')
 */

declare(strict_types=1);

class Facturama
{
    /**
     * Obtiene la URL base del API de Facturama
     * Por defecto usa el ambiente de sandbox
     * 
     * @return string URL base sin trailing slash
     */
    public static function baseUrl(): string
    {
        return rtrim(getenv('FACTURAMA_BASE') ?: 'https://apisandbox.facturama.mx', '/');
    }

    /**
     * Genera el header de autenticación Basic Auth
     * 
     * @return string Header Authorization completo
     */
    public static function authHeader(): string
    {
        $user = getenv('FACTURAMA_USER') ?: '';
        $pass = getenv('FACTURAMA_PASS') ?: '';
        return 'Authorization: Basic ' . base64_encode($user . ':' . $pass);
    }

    /**
     * Obtiene el código postal del lugar de expedición
     * Soporta múltiples variables de entorno para compatibilidad
     * 
     * @return string Código postal de expedición
     */
    public static function expeditionPlace(): string
    {
        // Prioridad: FACTURAMA_EXPEDITION_PLACE > CFDI_EXPEDITION_CP > default
        return getenv('FACTURAMA_EXPEDITION_PLACE') 
            ?: getenv('CFDI_EXPEDITION_CP') 
            ?: '34217';
    }

    /**
     * Obtiene la serie de facturación configurada
     * 
     * @return string Serie de facturación (default: 'A')
     */
    public static function serie(): string
    {
        return getenv('CFDI_SERIE') ?: 'A';
    }

    /**
     * Realiza una petición HTTP al API de Facturama
     * 
     * @param string $method Método HTTP (GET, POST, PUT, DELETE)
     * @param string $path Ruta del endpoint (ej: '/api/v3/cfdi')
     * @param mixed $body Cuerpo de la petición (array, string o null)
     * @param array $headers Headers adicionales
     * @return array Respuesta con 'status', 'json', 'body' o 'raw'
     * @throws RuntimeException Si hay error en la conexión
     */
    public static function request(
        string $method, 
        string $path, 
        $body = null, 
        array $headers = []
    ): array {
        $url = self::baseUrl() . '/' . ltrim($path, '/');
        $ch = curl_init($url);

        // Headers por defecto
        $defaultHeaders = [
            self::authHeader(),
            'Accept: application/json'
        ];

        // Configurar body si existe
        if ($body !== null && !is_resource($body)) {
            $defaultHeaders[] = 'Content-Type: application/json';
            $payload = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        // Configurar cURL
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Seguridad SSL

        // Ejecutar petición
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        // Validar respuesta
        if ($resp === false) {
            throw new RuntimeException('Error de conexión con Facturama: ' . $err);
        }

        // Determinar tipo de contenido de respuesta
        $ct = self::getContentType($headers);
        
        // Si es PDF o XML, retornar contenido binario
        if ($ct === 'application/pdf' || $ct === 'application/xml') {
            return [
                'status' => $status,
                'body' => $resp
            ];
        }

        // Intentar parsear JSON
        $json = json_decode($resp, true);
        
        if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
            // Si falla el JSON, retornar respuesta raw
            return [
                'status' => $status,
                'raw' => $resp
            ];
        }

        return [
            'status' => $status,
            'json' => $json
        ];
    }

    /**
     * Extrae el Content-Type de los headers
     * 
     * @param array $headers Lista de headers
     * @return string Content-Type encontrado o 'application/json' por defecto
     */
    private static function getContentType(array $headers): string
    {
        foreach ($headers as $h) {
            if (stripos($h, 'Accept:') === 0) {
                return trim(substr($h, strlen('Accept:')));
            }
        }
        return 'application/json';
    }

    /**
     * Guarda un archivo asegurando que el directorio exista
     * 
     * @param string $path Ruta completa del archivo
     * @param string $content Contenido a guardar
     * @return void
     * @throws RuntimeException Si no se puede crear el directorio
     */
    public static function saveFile(string $path, string $content): void
    {
        $dir = dirname($path);
        
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('No se pudo crear directorio: ' . $dir);
            }
        }
        
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('No se pudo guardar archivo: ' . $path);
        }
    }

    /**
     * Verifica si la configuración de Facturama está completa
     * 
     * @return bool True si user y pass están configurados
     */
    public static function isConfigured(): bool
    {
        $user = getenv('FACTURAMA_USER');
        $pass = getenv('FACTURAMA_PASS');
        
        return !empty($user) && !empty($pass);
    }
}

// ============================================
// FUNCIONES HELPER GLOBALES
// ============================================

/**
 * Lista las sucursales registradas en Facturama
 * 
 * @return array Lista de sucursales o array vacío si hay error
 */
function facturama_list_branch_offices(): array
{
    try {
        $result = Facturama::request('GET', '/api/v3/BranchOffices');
        
        if ($result['status'] !== 200 || !isset($result['json'])) {
            error_log("[Facturama] Error al listar sucursales. Status: " . $result['status']);
            return [];
        }
        
        return $result['json'];
    } catch (Throwable $e) {
        error_log("[Facturama] Error al obtener sucursales: " . $e->getMessage());
        return [];
    }
}

/**
 * Crea un CFDI en Facturama
 * 
 * @param array $fields Datos del CFDI en formato form-data o array
 * @return array Respuesta de Facturama con UUID, PDF y XML
 * @throws RuntimeException Si hay error en la creación
 */
function facturama_create_cfdi(array $fields): array
{
    try {
        // Convertir fields a formato JSON si es necesario
        $result = Facturama::request('POST', '/api/v3/cfdi', $fields);
        
        if ($result['status'] !== 200 && $result['status'] !== 201) {
            $errorMsg = isset($result['json']['Message']) 
                ? $result['json']['Message'] 
                : ($result['raw'] ?? 'Error desconocido');
            
            throw new RuntimeException("Error al crear CFDI: {$errorMsg}");
        }
        
        return $result['json'] ?? [];
    } catch (Throwable $e) {
        error_log("[Facturama] Error al crear CFDI: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Descarga el PDF de un CFDI
 * 
 * @param string $cfdiId ID del CFDI en Facturama
 * @return string Contenido binario del PDF
 * @throws RuntimeException Si hay error en la descarga
 */
function facturama_download_pdf(string $cfdiId): string
{
    try {
        $result = Facturama::request(
            'GET', 
            "/api/v3/cfdi/{$cfdiId}/pdf",
            null,
            ['Accept: application/pdf']
        );
        
        if ($result['status'] !== 200) {
            throw new RuntimeException("Error al descargar PDF. Status: " . $result['status']);
        }
        
        return $result['body'];
    } catch (Throwable $e) {
        error_log("[Facturama] Error al descargar PDF: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Descarga el XML de un CFDI
 * 
 * @param string $cfdiId ID del CFDI en Facturama
 * @return string Contenido del XML
 * @throws RuntimeException Si hay error en la descarga
 */
function facturama_download_xml(string $cfdiId): string
{
    try {
        $result = Facturama::request(
            'GET', 
            "/api/v3/cfdi/{$cfdiId}/xml",
            null,
            ['Accept: application/xml']
        );
        
        if ($result['status'] !== 200) {
            throw new RuntimeException("Error al descargar XML. Status: " . $result['status']);
        }
        
        return $result['body'];
    } catch (Throwable $e) {
        error_log("[Facturama] Error al descargar XML: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Cancela un CFDI en Facturama
 * 
 * @param string $cfdiId ID del CFDI a cancelar
 * @param string $motive Motivo de cancelación (01-04)
 * @param string|null $substitutionUuid UUID del CFDI que sustituye (si aplica)
 * @return array Respuesta de Facturama
 * @throws RuntimeException Si hay error en la cancelación
 */
function facturama_cancel_cfdi(
    string $cfdiId, 
    string $motive = '02', 
    ?string $substitutionUuid = null
): array {
    try {
        $body = ['motive' => $motive];
        
        if ($substitutionUuid) {
            $body['substitution'] = $substitutionUuid;
        }
        
        $result = Facturama::request('DELETE', "/api/v3/cfdi/{$cfdiId}", $body);
        
        if ($result['status'] !== 200) {
            $errorMsg = isset($result['json']['Message']) 
                ? $result['json']['Message'] 
                : 'Error al cancelar CFDI';
            
            throw new RuntimeException($errorMsg);
        }
        
        return $result['json'] ?? [];
    } catch (Throwable $e) {
        error_log("[Facturama] Error al cancelar CFDI: " . $e->getMessage());
        throw $e;
    }
}

// ============================================
// FUNCIONES DE CONFIGURACIÓN (Legacy)
// ============================================

/**
 * Obtiene el código postal de expedición
 * @deprecated Usar Facturama::expeditionPlace() en su lugar
 */
function cfdi_expedition_cp(): string
{
    return Facturama::expeditionPlace();
}

/**
 * Obtiene la serie de facturación
 * @deprecated Usar Facturama::serie() en su lugar
 */
function cfdi_serie(): string
{
    return Facturama::serie();
}