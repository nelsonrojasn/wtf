<?php

class EncryptedCookie {
    // Definir una clave secreta fuerte en config/settings.php
    private static string $secret_key = 'TU_CLAVE_SECRETA_HIPER_SEGURA_CAMBIAME';
    private static string $cipher = 'aes-256-gcm';

    /**
     * Empaqueta, cifra y firma los datos para la cookie
     */
    public static function set(string $name, array $data, int $ttl_seconds = 86400): void {
        $data['expires_at'] = time() + $ttl_seconds;
        $json = json_encode($data);

        // Generar Vector de Inicialización (IV)
        $ivlen = openssl_cipher_iv_length(self::$cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);

        // Cifrar datos usando AES-256-GCM
        $ciphertext = openssl_encrypt($json, self::$cipher, self::$secret_key, 0, $iv, $tag);
        
        // Empaquetar payload en base64 (IV + TAG + CIPHERTEXT)
        $payload = base64_encode($iv . $tag . $ciphertext);

        // Setear cookie segura (HttpOnly, SameSite Strict, Secure en prod)
        setcookie($name, $payload, [
            'expires'  => time() + $ttl_seconds,
            'path'     => '/',
            'httponly' => true,      // Previene acceso desde Javascript (XSS protection)
            'samesite' => 'Lax',     // Protege contra CSRF
            'secure'   => false,     // Cambiar a true en producción (HTTPS)
        ]);
    }

    /**
     * Lee, valida y descifra la cookie. Retorna null si fue manipulada o expiró.
     */
    public static function get(string $name): ?array {
        if (!isset($_COOKIE[$name])) {
            return null;
        }

        $decoded = base64_decode($_COOKIE[$name], true);
        if (!$decoded) return null;

        $ivlen = openssl_cipher_iv_length(self::$cipher);
        $taglen = 16; // GCM Tag length por defecto

        // Extraer los componentes del payload
        $iv = substr($decoded, 0, $ivlen);
        $tag = substr($decoded, $ivlen, $taglen);
        $ciphertext = substr($decoded, $ivlen + $taglen);

        // Descifrar y verificar integridad
        $json = openssl_decrypt($ciphertext, self::$cipher, self::$secret_key, 0, $iv, $tag);
        if (!$json) {
            return null; // La firma no coincide o los datos fueron alterados
        }

        $data = json_decode($json, true);

        // Verificar expiración
        if (isset($data['expires_at']) && time() > $data['expires_at']) {
            return null;
        }

        return $data;
    }

    /**
     * Elimina la cookie
     */
    public static function destroy(string $name): void {
        setcookie($name, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
        ]);
    }
}