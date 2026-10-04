<?php
declare(strict_types=1);

namespace App\Core;

final class Str
{
    /** Token aleatorio de 128 bits en hexadecimal (32 caracteres). */
    public static function token(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function slug(string $text, int $max = 70): string
    {
        $text = trim($text);
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($t !== false && $t !== '') {
                $text = $t;
            }
        }
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim($text, '-');
        if ($text === '') {
            $text = 'item';
        }
        return substr($text, 0, $max);
    }

    /** Devuelve un slug que no exista en $table.$col. */
    public static function uniqueSlug(string $table, string $text, ?int $ignoreId = null, string $col = 'slug'): string
    {
        $base = self::slug($text);
        $slug = $base;
        $i = 2;
        while (true) {
            $sql = 'SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $col . '` = ?' . ($ignoreId ? ' AND id <> ' . (int) $ignoreId : '');
            if ((int) Db::val($sql, [$slug]) === 0) {
                return $slug;
            }
            $slug = substr($base, 0, 64) . '-' . $i++;
        }
    }

    public static function truncate(string $s, int $len, string $end = '…'): string
    {
        return mb_strlen($s) > $len ? mb_substr($s, 0, $len - 1) . $end : $s;
    }

    /** Reemplaza {variable} en una plantilla. Los valores no definidos quedan vacíos. */
    public static function template(string $tpl, array $vars): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', static function (array $m) use ($vars): string {
            return isset($vars[$m[1]]) ? (string) $vars[$m[1]] : '';
        }, $tpl) ?? $tpl;
    }

    /** Teléfono -> solo dígitos con prefijo de país. 8 dígitos => +502. Devuelve null si no es válido. */
    public static function phone(?string $raw, string $cc = '502'): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if ($d === '') {
            return null;
        }
        if (strpos((string) $raw, '+') === 0 || strlen($d) > 8) {
            if (strpos($d, '00') === 0) {
                $d = substr($d, 2);
            }
            return (strlen($d) >= 9 && strlen($d) <= 15) ? $d : null;
        }
        return strlen($d) === 8 ? $cc . $d : null;
    }

    /** Teléfono para mostrar: +502 5555 1234 */
    public static function phoneDisplay(?string $digits): string
    {
        $d = (string) $digits;
        if ($d === '') {
            return '';
        }
        if (strpos($d, '502') === 0 && strlen($d) === 11) {
            return '+502 ' . substr($d, 3, 4) . ' ' . substr($d, 7);
        }
        return '+' . $d;
    }

    /** Quita etiquetas y limita; para texto plano de formularios. */
    public static function clean(?string $s, int $max = 255): string
    {
        $s = trim((string) $s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
        return mb_substr($s, 0, $max);
    }

    /** IP truncada para privacidad (IPv4 /24, IPv6 /48). */
    public static function ipTrunc(?string $ip): string
    {
        $ip = (string) $ip;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $p = explode('.', $ip);
            return $p[0] . '.' . $p[1] . '.' . $p[2] . '.0';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            if ($packed !== false) {
                return inet_ntop(substr($packed, 0, 6) . str_repeat("\0", 10)) ?: '';
            }
        }
        return '';
    }

    /** Descripción con formato básico (**negrita**, *cursiva*, saltos, enlaces) -> HTML seguro. */
    public static function richText(?string $s): string
    {
        $h = htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $h = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $h) ?? $h;
        $h = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/s', '<em>$1</em>', $h) ?? $h;
        $h = preg_replace_callback('/\[([^\]]{1,120})\]\((https?:\/\/[^\s)]{1,300})\)/', static function (array $m): string {
            return '<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
        }, $h) ?? $h;
        $paras = preg_split('/\n{2,}/', trim($h)) ?: [];
        $out = '';
        foreach ($paras as $p) {
            $out .= '<p>' . nl2br(trim($p), false) . '</p>';
        }
        return $out;
    }
}
