<?php
declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Conversión de zonas horarias. En la base de datos todo es UTC "Y-m-d H:i:s". */
final class Tz
{
    public static function valid(?string $tz): bool
    {
        if ($tz === null || $tz === '' || strlen($tz) > 64) {
            return false;
        }
        try {
            new DateTimeZone($tz);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function safe(?string $tz, string $fallback = 'America/Guatemala'): string
    {
        return self::valid($tz) ? (string) $tz : $fallback;
    }

    public static function utcZone(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }

    /** Texto UTC de la BD -> timestamp. */
    public static function ts(string $utc): int
    {
        return (int) (new DateTimeImmutable($utc, self::utcZone()))->format('U');
    }

    public static function fromTs(int $ts): string
    {
        return gmdate('Y-m-d H:i:s', $ts);
    }

    /** Hora local "Y-m-d H:i[:s]" de una zona -> timestamp UTC. Las horas inexistentes (salto de verano) avanzan, las repetidas toman la primera. */
    public static function localToTs(string $local, string $tz): int
    {
        $d = new DateTimeImmutable($local, new DateTimeZone($tz));
        return (int) $d->format('U');
    }

    public static function localToUtc(string $local, string $tz): string
    {
        return self::fromTs(self::localToTs($local, $tz));
    }

    /** UTC de la BD -> texto local con formato de PHP date(). */
    public static function format(string $utc, string $tz, string $fmt = 'Y-m-d H:i'): string
    {
        return (new DateTimeImmutable($utc, self::utcZone()))->setTimezone(new DateTimeZone($tz))->format($fmt);
    }

    public static function formatTs(int $ts, string $tz, string $fmt = 'Y-m-d H:i'): string
    {
        return (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone($tz))->format($fmt);
    }

    /** ISO-8601 con Z (para JSON/ICS). */
    public static function iso(string $utc): string
    {
        return (new DateTimeImmutable($utc, self::utcZone()))->format('Y-m-d\TH:i:s\Z');
    }

    public static function parseIso(string $iso): ?string
    {
        try {
            $d = new DateTimeImmutable($iso);
            return gmdate('Y-m-d H:i:s', (int) $d->format('U'));
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Inicio (00:00 local) de la fecha local de un timestamp, como timestamp. */
    public static function startOfLocalDay(int $ts, string $tz): int
    {
        $d = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone($tz))->setTime(0, 0, 0);
        return (int) $d->format('U');
    }

    /** Zonas horarias para los selectores (agrupadas de forma simple). */
    public static function list(): array
    {
        $out = [];
        foreach (DateTimeZone::listIdentifiers() as $id) {
            if (strpos($id, '/') !== false && strpos($id, 'Etc/') !== 0) {
                $out[] = $id;
            }
        }
        return $out;
    }
}
