<?php
declare(strict_types=1);
namespace S5\Core;

/** TOTP (RFC 6238) sin dependencias, para 2FA opcional del dueño. */
final class Totp
{
    private const ALPHA = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function newSecret(): string
    {
        $s = '';
        $b = random_bytes(20);
        for ($i = 0; $i < 32; $i++) {
            $s .= self::ALPHA[ord($b[$i % 20]) & 31];
        }
        return $s;
    }

    private static function b32decode(string $s): string
    {
        $s = strtoupper(preg_replace('/[^A-Z2-7]/', '', $s) ?? '');
        $bits = '';
        foreach (str_split($s) as $c) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHA, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }

    public static function code(string $secret, ?int $time = null, int $step = 30): string
    {
        $counter = intdiv($time ?? time(), $step);
        $bin = pack('N2', 0, $counter);
        $hash = hash_hmac('sha1', $bin, self::b32decode($secret), true);
        $o = ord($hash[19]) & 0xf;
        $val = ((ord($hash[$o]) & 0x7f) << 24) | ((ord($hash[$o + 1]) & 0xff) << 16) | ((ord($hash[$o + 2]) & 0xff) << 8) | (ord($hash[$o + 3]) & 0xff);
        return str_pad((string) ($val % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return false;
        }
        $t = time();
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $t + $i * 30), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function uri(string $secret, string $account, string $issuer = 'Servicom'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer);
    }
}
