<?php
declare(strict_types=1);
namespace S5\Core;

/** Cifrado de secretos en BD con libsodium (secretbox). La llave está solo en config.php. */
final class Crypto
{
    private static function key(): string
    {
        $k = (string) Config::get('secret_key', '');
        $raw = base64_decode($k, true);
        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('Llave maestra inválida.');
        }
        return $raw;
    }

    public static function newKey(): string
    {
        return base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }

    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'enc1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    public static function decrypt(string $blob): string
    {
        if (!str_starts_with($blob, 'enc1:')) {
            return '';
        }
        $raw = base64_decode(substr($blob, 5), true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            return '';
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $out = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, self::key());
        return $out === false ? '' : $out;
    }

    /** HMAC con subclave derivada (para enlaces firmados del portal) */
    public static function sign(string $data, string $ctx = 'portal'): string
    {
        return hash_hmac('sha256', $data, hash('sha256', self::key() . '|' . $ctx, true));
    }
}
