<?php
declare(strict_types=1);

/** Cifrado de las API keys. AES-256-GCM si hay OpenSSL; si no, ofuscación HMAC. */
final class Crypto
{
    private const PREFIX_GCM = 'pfa1:';
    private const PREFIX_FALLBACK = 'pfb1:';

    /** Clave maestra almacenada fuera del alcance del navegador. */
    public static function masterKey(): string
    {
        $path = Support::vaultDir() . '/secret.key';
        if (is_file($path)) {
            $raw = (string) @file_get_contents($path);
            if (strlen($raw) >= 32) {
                return substr(hash('sha256', $raw, true), 0, 32);
            }
        }
        try {
            $seed = base64_encode(random_bytes(48));
        } catch (Throwable $e) {
            $seed = base64_encode(hash('sha256', uniqid('pf', true) . PF_ROOT, true));
        }
        @file_put_contents($path, $seed, LOCK_EX);
        @chmod($path, 0600);
        return substr(hash('sha256', $seed, true), 0, 32);
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        if (extension_loaded('openssl')) {
            $iv = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::masterKey(), OPENSSL_RAW_DATA, $iv, $tag);
            if ($cipher !== false) {
                return self::PREFIX_GCM . base64_encode($iv . $tag . $cipher);
            }
            Logger::write('crypto', 'openssl_encrypt falló, se usa el método alternativo');
        }
        $key = self::masterKey();
        $out = '';
        $len = strlen($plain);
        for ($i = 0; $i < $len; $i++) {
            $out .= chr(ord($plain[$i]) ^ ord($key[$i % 32]));
        }
        return self::PREFIX_FALLBACK . base64_encode($out);
    }

    public static function decrypt(string $stored): string
    {
        if ($stored === '') {
            return '';
        }
        if (str_starts_with($stored, self::PREFIX_GCM)) {
            if (!extension_loaded('openssl')) {
                Logger::write('crypto', 'Hay keys cifradas con OpenSSL pero la extensión ya no está disponible');
                return '';
            }
            $raw = base64_decode(substr($stored, strlen(self::PREFIX_GCM)), true);
            if ($raw === false || strlen($raw) < 29) {
                return '';
            }
            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $cipher = substr($raw, 28);
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::masterKey(), OPENSSL_RAW_DATA, $iv, $tag);
            return $plain === false ? '' : $plain;
        }
        if (str_starts_with($stored, self::PREFIX_FALLBACK)) {
            $raw = base64_decode(substr($stored, strlen(self::PREFIX_FALLBACK)), true);
            if ($raw === false) {
                return '';
            }
            $key = self::masterKey();
            $out = '';
            $len = strlen($raw);
            for ($i = 0; $i < $len; $i++) {
                $out .= chr(ord($raw[$i]) ^ ord($key[$i % 32]));
            }
            return $out;
        }
        return $stored; // valor heredado sin cifrar
    }

    /** Muestra solo los últimos caracteres: la key nunca viaja completa al navegador. */
    public static function mask(string $plain): string
    {
        $len = strlen($plain);
        if ($len === 0) {
            return '';
        }
        if ($len <= 6) {
            return str_repeat('•', $len);
        }
        return str_repeat('•', 8) . substr($plain, -4);
    }
}
