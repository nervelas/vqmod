<?php
namespace MenuGold\Core;

final class Lang
{
    /** @var string */
    private static $locale = 'es';
    /** @var array<string,array> */
    private static $lines = array();

    public static function setLocale($locale)
    {
        $locale = in_array($locale, array('es', 'en'), true) ? $locale : 'es';
        self::$locale = $locale;
        if (!isset(self::$lines[$locale])) {
            $file = MG_APP . '/Support/lang/' . $locale . '.php';
            self::$lines[$locale] = is_file($file) ? (array)include $file : array();
        }
    }

    public static function locale()
    {
        return self::$locale;
    }

    public static function get($key, array $repl = array())
    {
        if (!isset(self::$lines[self::$locale])) {
            self::setLocale(self::$locale);
        }
        $lines = self::$lines[self::$locale];
        $text = isset($lines[$key]) ? $lines[$key] : null;
        if ($text === null && self::$locale !== 'es') {
            self::setLocale('es');
            $text = isset(self::$lines['es'][$key]) ? self::$lines['es'][$key] : $key;
            self::$locale = 'en';
        }
        if ($text === null) { $text = $key; }
        foreach ($repl as $k => $v) {
            $text = str_replace(':' . $k, (string)$v, $text);
        }
        return $text;
    }

    /** Elige el campo traducido de una fila (name / name_en). */
    public static function field(array $row, $field)
    {
        if (self::$locale === 'en') {
            $en = $field . '_en';
            if (!empty($row[$en])) { return $row[$en]; }
        }
        return isset($row[$field]) ? $row[$field] : '';
    }
}
