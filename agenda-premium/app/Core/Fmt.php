<?php
declare(strict_types=1);

namespace App\Core;

/** Formatos para Guatemala: quetzales, fechas día/mes/año en español, 12/24 h. */
final class Fmt
{
    private const DAYS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    private const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public static function dayName(int $w, bool $short = false): string
    {
        $n = self::DAYS[$w % 7];
        return $short ? mb_substr($n, 0, 3) : $n;
    }

    public static function monthName(int $m, bool $short = false): string
    {
        $n = self::MONTHS[($m - 1) % 12];
        return $short ? mb_substr($n, 0, 3) : $n;
    }

    /** Q1,250.00 */
    public static function money($amount, bool $symbol = true): string
    {
        $s = number_format((float) $amount, 2, '.', ',');
        return $symbol ? (string) Settings::get('currency_symbol', 'Q') . $s : $s;
    }

    /** "lunes 5 de octubre de 2026" (a partir de UTC y zona) */
    public static function dateLong(string $utc, string $tz): string
    {
        $d = new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
        $d = $d->setTimezone(new \DateTimeZone($tz));
        return self::DAYS[(int) $d->format('w')] . ' ' . (int) $d->format('j') . ' de ' . self::MONTHS[(int) $d->format('n') - 1] . ' de ' . $d->format('Y');
    }

    /** 05/10/2026 */
    public static function dateShort(string $utc, string $tz): string
    {
        return Tz::format($utc, $tz, 'd/m/Y');
    }

    /** "2:30 p. m." o "14:30" */
    public static function time(string $utc, string $tz, ?string $fmt = null): string
    {
        $fmt = $fmt ?? (string) Settings::get('time_format', '12');
        $d = (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($tz));
        if ($fmt === '24') {
            return $d->format('H:i');
        }
        return $d->format('g:i') . ' ' . ($d->format('a') === 'am' ? 'a. m.' : 'p. m.');
    }

    /** "lunes 5 de octubre de 2026, 2:30 p. m." */
    public static function dateTime(string $utc, string $tz, ?string $fmt = null): string
    {
        return self::dateLong($utc, $tz) . ', ' . self::time($utc, $tz, $fmt);
    }

    /** Abreviatura de zona: "GMT-6" */
    public static function tzLabel(string $tz, ?string $atUtc = null): string
    {
        $d = new \DateTimeImmutable($atUtc ?? 'now', new \DateTimeZone('UTC'));
        $off = $d->setTimezone(new \DateTimeZone($tz))->getOffset();
        $sign = $off < 0 ? '-' : '+';
        $off = abs($off);
        $h = intdiv($off, 3600);
        $m = intdiv($off % 3600, 60);
        return 'GMT' . $sign . $h . ($m ? ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '') . ' · ' . $tz;
    }

    public static function duration(int $min): string
    {
        if ($min < 60) {
            return $min . ' min';
        }
        $h = intdiv($min, 60);
        $m = $min % 60;
        return $h . ' h' . ($m ? ' ' . $m . ' min' : '');
    }
}
