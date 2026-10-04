<?php
declare(strict_types=1);

namespace Aurea\Core;

final class Util
{
    public const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    public const DAYS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    public static function token(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function slug(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        return $s !== '' ? substr($s, 0, 120) : 'profesional';
    }

    /** Teléfono -> [código país, dígitos] o null si es inválido. Guatemala: 8 dígitos. */
    public static function phone(string $raw, string $defaultCc = '502'): ?array
    {
        $raw = trim($raw);
        $cc = $defaultCc;
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strpos($raw, '+') === 0 || strpos($raw, '00') === 0) {
            if (strpos($raw, '00') === 0) { $digits = substr($digits, 2); }
            if (strpos($digits, '502') === 0 && strlen($digits) === 11) {
                $cc = '502';
                $digits = substr($digits, 3);
            } elseif (strpos($digits, '502') === 0) {
                return null;
            } else {
                // Otro país: el código va en los primeros 1-3 dígitos; se exige formato +cc número
                if (!preg_match('/^\+?\s*(\d{1,3})[\s\-]*(.+)$/', $raw, $m)) { return null; }
                $cc = $m[1];
                $digits = preg_replace('/\D+/', '', $m[2]) ?? '';
            }
        }
        if (!preg_match('/^\d{1,3}$/', $cc)) { return null; }
        if ($cc === '502') {
            // Se tolera 502 pegado sin '+'
            if (strlen($digits) === 11 && strpos($digits, '502') === 0) { $digits = substr($digits, 3); }
            return preg_match('/^[2-7]\d{7}$/', $digits) ? [$cc, $digits] : null;
        }
        return preg_match('/^\d{6,14}$/', $digits) ? [$cc, $digits] : null;
    }

    public static function isEmail(string $s): bool
    {
        return strlen($s) <= 190 && filter_var($s, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isDate(string $s): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) { return false; }
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
    }

    public static function isTime(string $s): bool
    {
        return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $s);
    }

    public static function isColor(string $s): bool
    {
        return (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $s);
    }

    /** Solo http(s). Evita javascript: y similares. */
    public static function safeUrl(string $s): string
    {
        $s = trim($s);
        return (preg_match('#^https?://[^\s<>"\']+$#i', $s) && strlen($s) <= 500) ? $s : '';
    }

    public static function minutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($hhmm, 0, 5)));
        return $h * 60 + $m;
    }

    public static function dateLong(string $date): string
    {
        $t = strtotime(substr($date, 0, 10));
        if (!$t) { return ''; }
        return self::DAYS[(int)date('w', $t)] . ' ' . (int)date('j', $t) . ' de ' . self::MONTHS[(int)date('n', $t) - 1] . ' de ' . date('Y', $t);
    }

    public static function dateShort(string $date): string
    {
        $t = strtotime(substr($date, 0, 10));
        if (!$t) { return ''; }
        return ucfirst(self::DAYS[(int)date('w', $t)]) . ' ' . (int)date('j', $t) . ' ' . self::MONTHS[(int)date('n', $t) - 1];
    }

    public static function waLink(string $cc, string $phone, string $text): string
    {
        return 'https://wa.me/' . preg_replace('/\D+/', '', $cc . $phone) . '?text=' . rawurlencode($text);
    }

    public static function limit(string $s, int $n): string
    {
        return mb_substr(trim($s), 0, $n, 'UTF-8');
    }

    public static function csvCell($v): string
    {
        $s = (string)$v;
        // Mitiga inyección de fórmulas en Excel/Sheets
        if ($s !== '' && strpbrk($s[0], "=+-@\t\r") !== false) {
            $s = "'" . $s;
        }
        return '"' . str_replace('"', '""', $s) . '"';
    }

    public static function csv(array $header, array $rows): string
    {
        $out = "\xEF\xBB\xBF" . implode(',', array_map([self::class, 'csvCell'], $header)) . "\r\n";
        foreach ($rows as $r) {
            $out .= implode(',', array_map([self::class, 'csvCell'], array_values($r))) . "\r\n";
        }
        return $out;
    }
}
