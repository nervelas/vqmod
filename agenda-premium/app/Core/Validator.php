<?php
declare(strict_types=1);

namespace App\Core;

final class Validator
{
    public static function email(?string $v): bool
    {
        $v = (string) $v;
        return strlen($v) <= 190 && filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function date(?string $v): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $v, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    public static function time(?string $v): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $v);
    }

    public static function datetimeUtc(?string $v): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $v) && self::date(substr((string) $v, 0, 10));
    }

    public static function color(?string $v): bool
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $v);
    }

    public static function url(?string $v, bool $httpsOnly = false): bool
    {
        $v = (string) $v;
        if (strlen($v) > 500 || !filter_var($v, FILTER_VALIDATE_URL)) {
            return false;
        }
        $s = strtolower((string) parse_url($v, PHP_URL_SCHEME));
        return $httpsOnly ? $s === 'https' : in_array($s, ['http', 'https'], true);
    }

    public static function slug(?string $v): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $v) && strlen((string) $v) <= 80;
    }

    public static function inList($v, array $allowed): bool
    {
        return in_array($v, $allowed, true);
    }

    public static function intRange($v, int $min, int $max): ?int
    {
        if (!is_scalar($v) || !is_numeric($v) || (string) (int) $v !== (string) (int) (float) $v) {
            return null;
        }
        $i = (int) $v;
        return ($i >= $min && $i <= $max) ? $i : null;
    }

    public static function money($v): ?string
    {
        $s = str_replace(',', '.', trim((string) $v));
        if ($s === '' || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $s)) {
            return null;
        }
        return number_format((float) $s, 2, '.', '');
    }
}
