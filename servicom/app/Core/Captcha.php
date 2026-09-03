<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Captcha matemático propio (sin servicios externos).
 * La respuesta viaja firmada con HMAC; nunca se guarda en sesión para
 * soportar varias pestañas abiertas.
 */
final class Captcha
{
    public static function make(): array
    {
        $ops = ['+', '-', '×'];
        $op  = $ops[random_int(0, 2)];
        $a   = random_int(3, 19);
        $b   = random_int(2, 9);
        if ($op === '-' && $b > $a) {
            [$a, $b] = [$b, $a];
        }
        $r = match ($op) {
            '+' => $a + $b,
            '-' => $a - $b,
            default => $a * $b,
        };
        $ts    = time();
        $nonce = bin2hex(random_bytes(8));
        return [
            'question' => "{$a} {$op} {$b}",
            'stamp'    => $ts . '.' . $nonce . '.' . self::sign((string) $r, $ts, $nonce),
        ];
    }

    public static function check(string $answer, string $stamp): bool
    {
        $parts = explode('.', $stamp);
        if (count($parts) !== 3) {
            return false;
        }
        [$ts, $nonce, $sig] = $parts;
        if (!ctype_digit($ts) || (time() - (int) $ts) > 1800 || (time() - (int) $ts) < 1) {
            return false;
        }
        $answer = trim($answer);
        if ($answer === '' || !preg_match('/^-?\d{1,4}$/', $answer)) {
            return false;
        }
        return hash_equals(self::sign((string) (int) $answer, (int) $ts, $nonce), $sig);
    }

    private static function sign(string $result, int $ts, string $nonce): string
    {
        $key = (string) Config::get('app_key', 'cotizapro');
        return substr(hash_hmac('sha256', $result . '|' . $ts . '|' . $nonce, $key), 0, 32);
    }
}
