<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Multi-idioma del menu publico (es / en).
 */
final class Lang
{
    private static string $lang = 'es';
    private static array $strings = [];

    public static function boot(): void
    {
        $l = (string)($_GET['lang'] ?? $_SESSION['_lang'] ?? '');
        if (!in_array($l, ['es', 'en'], true)) $l = 'es';
        $_SESSION['_lang'] = $l;
        self::$lang = $l;
    }

    public static function set(string $l): void
    {
        if (in_array($l, ['es', 'en'], true)) {
            self::$lang = $l;
            $_SESSION['_lang'] = $l;
            self::$strings = [];
        }
    }

    public static function current(): string { return self::$lang; }

    public static function get(string $key, array $replace = []): string
    {
        if (!self::$strings) {
            $file = MG_ROOT . '/app/Lang/' . self::$lang . '.php';
            self::$strings = is_file($file) ? (array)require $file : [];
        }
        $txt = self::$strings[$key] ?? $key;
        foreach ($replace as $k => $v) $txt = str_replace(':' . $k, (string)$v, $txt);
        return $txt;
    }
}
