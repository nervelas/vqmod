<?php
declare(strict_types=1);

namespace Aurea\Core;

/** TOTP (RFC 6238) propio, compatible con Google Authenticator / Authy. */
final class Totp
{
    private const ALPHA = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function secret(int $bytes = 20): string
    {
        return self::b32enc(random_bytes($bytes));
    }

    public static function b32enc(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) { $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT); }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) { $out .= self::ALPHA[bindec(str_pad($chunk, 5, '0'))]; }
        return $out;
    }

    public static function b32dec(string $s): string
    {
        $s = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $s) ?? '');
        $bits = '';
        foreach (str_split($s) as $c) { $bits .= str_pad(decbin((int)strpos(self::ALPHA, $c)), 5, '0', STR_PAD_LEFT); }
        $out = '';
        foreach (str_split($bits, 8) as $byte) { if (strlen($byte) === 8) { $out .= chr(bindec($byte)); } }
        return $out;
    }

    public static function code(string $secret, ?int $time = null, int $digits = 6): string
    {
        $counter = intdiv($time ?? time(), 30);
        $hash = hash_hmac('sha1', pack('N*', 0, $counter), self::b32dec($secret), true);
        $o = ord($hash[19]) & 0xF;
        $n = ((ord($hash[$o]) & 0x7F) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);
        return str_pad((string)($n % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) { return false; }
        $now = time();
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $now + 30 * $i), $code)) { return true; }
        }
        return false;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
    }
}
