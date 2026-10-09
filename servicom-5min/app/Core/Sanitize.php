<?php
declare(strict_types=1);
namespace S5\Core;

final class Sanitize
{
    /** Texto plano: UTF-8 válido, sin HTML ni caracteres de control, recortado. */
    public static function text($s, int $max = 255): string
    {
        if (!is_string($s)) {
            if (is_int($s) || is_float($s)) {
                $s = (string) $s;
            } else {
                return '';
            }
        }
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = (string) @iconv('UTF-8', 'UTF-8//IGNORE', $s);
        }
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
        $s = strip_tags($s);
        $s = str_replace(['<', '>'], '', $s);
        $s = preg_replace('/[ \t]+/u', ' ', $s) ?? '';
        $s = trim($s);
        return mb_substr($s, 0, $max);
    }

    public static function multiline($s, int $max = 2000): string
    {
        if (!is_string($s)) {
            return '';
        }
        $s = str_replace("\r\n", "\n", $s);
        $parts = explode("\n", $s);
        $parts = array_map(fn($p) => self::text($p, $max), $parts);
        $out = trim(implode("\n", $parts));
        $out = preg_replace("/\n{3,}/", "\n\n", $out) ?? '';
        return mb_substr($out, 0, $max);
    }

    public static function email($s): string
    {
        $s = trim((string) $s);
        $s = mb_strtolower(self::text($s, 190));
        return filter_var($s, FILTER_VALIDATE_EMAIL) ? $s : '';
    }

    public static function url($s, array $hosts = []): string
    {
        $s = trim(self::text($s, 500));
        if ($s === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $s)) {
            $s = 'https://' . $s;
        }
        $p = parse_url($s);
        if (!$p || empty($p['host']) || !filter_var($s, FILTER_VALIDATE_URL)) {
            return '';
        }
        if (!in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) {
            return '';
        }
        if ($hosts) {
            $ok = false;
            $h = strtolower($p['host']);
            foreach ($hosts as $x) {
                if ($h === $x || str_ends_with($h, '.' . $x)) {
                    $ok = true;
                }
            }
            if (!$ok) {
                return '';
            }
        }
        return $s;
    }

    public static function phoneDigits($s, int $max = 15): string
    {
        $d = preg_replace('/\D+/', '', (string) $s) ?? '';
        return substr($d, 0, $max);
    }

    public static function phoneDisplay($s): string
    {
        $s = self::text($s, 30);
        return preg_replace('/[^0-9+() \-]/', '', $s) ?? '';
    }

    /** Slug: minúsculas, sin acentos, solo letras, números y guiones. */
    public static function slug(string $s, int $max = 40): string
    {
        $s = mb_strtolower(trim($s));
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t === false || $t === '') {
            $t = $s;
        }
        $map = ['ñ' => 'n', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u'];
        $t = strtr($t, $map);
        $t = preg_replace('/[^a-z0-9]+/', '-', $t) ?? '';
        $t = trim($t, '-');
        $t = substr($t, 0, $max);
        return trim($t, '-');
    }

    public static function price($v): float
    {
        $s = str_replace(['Q', 'q', ' ', ','], '', (string) $v);
        return is_numeric($s) ? max(0.0, min(99999999.0, round((float) $s, 2))) : 0.0;
    }

    public static function intRange($v, int $min, int $max): int
    {
        return max($min, min($max, (int) $v));
    }

    public static function hex(string $s, int $len): string
    {
        return preg_match('/^[a-f0-9]{' . $len . '}$/', $s) ? $s : '';
    }
}
