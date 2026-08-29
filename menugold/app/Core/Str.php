<?php
namespace MenuGold\Core;

final class Str
{
    public static function slug($text, $sep = '-')
    {
        $text = (string)$text;
        $from = array('á','à','ä','â','ã','å','é','è','ë','ê','í','ì','ï','î','ó','ò','ö','ô','õ','ú','ù','ü','û','ñ','ç','Á','À','Ä','Â','É','È','Ë','Ê','Í','Ì','Ï','Î','Ó','Ò','Ö','Ô','Ú','Ù','Ü','Û','Ñ','Ç');
        $to   = array('a','a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','n','c','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','u','u','u','u','n','c');
        $text = str_replace($from, $to, $text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', $sep, $text);
        $text = trim((string)$text, $sep);
        return $text !== '' ? $text : 'item';
    }

    public static function limit($text, $len = 120, $end = '…')
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8') <= $len ? $text : rtrim(mb_substr($text, 0, $len, 'UTF-8')) . $end;
        }
        return strlen($text) <= $len ? $text : rtrim(substr($text, 0, $len)) . $end;
    }

    public static function upper($t)
    {
        return function_exists('mb_strtoupper') ? mb_strtoupper((string)$t, 'UTF-8') : strtoupper((string)$t);
    }

    public static function initials($name, $n = 2)
    {
        $parts = preg_split('/\s+/u', trim((string)$name));
        $out = '';
        foreach ($parts as $p) {
            if ($p === '') { continue; }
            $out .= self::upper(function_exists('mb_substr') ? mb_substr($p, 0, 1, 'UTF-8') : substr($p, 0, 1));
            if (strlen($out) >= $n) { break; }
        }
        return $out !== '' ? $out : '?';
    }

    /** Normaliza teléfonos de Guatemala a formato internacional para wa.me */
    public static function phoneDigits($phone, $defaultCountry = '502')
    {
        $d = preg_replace('/\D+/', '', (string)$phone);
        if ($d === '') { return ''; }
        if (strlen($d) === 8) { $d = $defaultCountry . $d; }
        return $d;
    }

    public static function json($value, $default = array())
    {
        if (is_array($value)) { return $value; }
        if (!is_string($value) || $value === '') { return $default; }
        $d = json_decode($value, true);
        return is_array($d) ? $d : $default;
    }

    public static function uuid()
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
