<?php
declare(strict_types=1);

namespace App\Core;

/** Captcha matematico propio, sin servicios externos. */
final class Captcha
{
    public static function generate(string $bucket = 'default'): string
    {
        $a = random_int(2, 9);
        $b = random_int(2, 9);
        $_SESSION['_captcha'][$bucket] = $a + $b;
        return sprintf('%d + %d', $a, $b);
    }

    public static function check(string $bucket, mixed $respuesta): bool
    {
        $esperado = $_SESSION['_captcha'][$bucket] ?? null;
        unset($_SESSION['_captcha'][$bucket]);
        return $esperado !== null && is_numeric($respuesta) && (int)$respuesta === (int)$esperado;
    }
}
