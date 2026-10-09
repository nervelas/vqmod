<?php
declare(strict_types=1);
namespace S5\Core;

/**
 * Cifrado de secretos en BD. Formato actual "enc2:" = AES-256-GCM (OpenSSL), sin depender de libsodium.
 * Compatibilidad: los valores antiguos "enc1:" (libsodium secretbox) se siguen leyendo SI la extensión sodium existe.
 * La llave maestra (32 bytes en base64) vive solo en config.php, fuera del acceso web.
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    private static function key(): string
    {
        $raw = base64_decode((string) Config::get('secret_key', ''), true);
        if ($raw === false || strlen($raw) !== 32) {
            throw new \RuntimeException('Llave maestra inválida.');
        }
        return $raw;
    }

    public static function newKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    public static function available(): bool
    {
        return function_exists('openssl_encrypt') && in_array(self::CIPHER, openssl_get_cipher_methods(), true);
    }

    public static function encrypt(string $plain): string
    {
        if (!self::available()) {
            if (function_exists('sodium_crypto_secretbox')) {   // respaldo: solo si OpenSSL no pudiera usarse
                $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                return 'enc1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
            }
            throw new \RuntimeException('Se requiere la extensión openssl para cifrar secretos.');
        }
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ct === false || strlen($tag) !== 16) {
            throw new \RuntimeException('No se pudo cifrar el secreto.');
        }
        return 'enc2:' . base64_encode($iv . $tag . $ct);
    }

    public static function decrypt(string $blob): string
    {
        if (str_starts_with($blob, 'enc2:')) {
            $raw = base64_decode(substr($blob, 5), true);
            if ($raw === false || strlen($raw) < 28 || !self::available()) {
                return '';
            }
            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $ct = substr($raw, 28);
            $out = openssl_decrypt($ct, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
            return $out === false ? '' : $out;
        }
        if (str_starts_with($blob, 'enc1:') && function_exists('sodium_crypto_secretbox_open')) {
            $raw = base64_decode(substr($blob, 5), true);
            if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
                return '';
            }
            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $out = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, self::key());
            return $out === false ? '' : $out;
        }
        return '';
    }

    /** HMAC con subclave derivada (enlaces firmados del portal). */
    public static function sign(string $data, string $ctx = 'portal'): string
    {
        return hash_hmac('sha256', $data, hash('sha256', self::key() . '|' . $ctx, true));
    }
}
