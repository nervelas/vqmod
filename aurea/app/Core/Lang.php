<?php
declare(strict_types=1);

namespace Aurea\Core;

/** Textos de la interfaz: lang/es.php (clave = texto original, valor = texto mostrado). */
final class Lang
{
    private static ?array $map = null;

    public static function get(string $text): string
    {
        if (self::$map === null) {
            $f = AUREA_ROOT . '/lang/es.php';
            self::$map = is_file($f) ? (array)(include $f) : [];
        }
        $v = self::$map[$text] ?? null;
        return is_string($v) && $v !== '' ? $v : $text;
    }
}
