<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Db;
use App\Core\Settings;

/** Feriados de Guatemala precargados por año (editables en el panel). */
final class HolidayService
{
    /** Domingo de Pascua (algoritmo de Meeus/Jones/Butcher), formato Y-m-d. */
    public static function easter(int $year): string
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /** Lista de feriados del año: [fecha, nombre, tipo, fin de medio día, alcance, activo por defecto]. */
    public static function defaultsFor(int $year): array
    {
        $easter = new \DateTimeImmutable(self::easter($year), new \DateTimeZone('UTC'));
        $rel = static fn (int $days): string => $easter->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
        return [
            [$year . '-01-01', 'Año Nuevo', 'full', null, 'national', 1],
            [$rel(-3), 'Jueves Santo', 'full', null, 'national', 1],
            [$rel(-2), 'Viernes Santo', 'full', null, 'national', 1],
            [$rel(-1), 'Sábado Santo', 'full', null, 'national', 1],
            [$year . '-05-01', 'Día del Trabajo', 'full', null, 'national', 1],
            [$year . '-06-30', 'Día del Ejército', 'full', null, 'national', 1],
            [$year . '-08-15', 'Día de la Asunción (solo ciudad capital)', 'full', null, 'capital', 0],
            [$year . '-09-15', 'Día de la Independencia', 'full', null, 'national', 1],
            [$year . '-10-20', 'Día de la Revolución', 'full', null, 'national', 1],
            [$year . '-11-01', 'Día de Todos los Santos', 'full', null, 'national', 1],
            [$year . '-12-24', 'Nochebuena (medio día)', 'half', '12:00:00', 'national', 1],
            [$year . '-12-25', 'Navidad', 'full', null, 'national', 1],
            [$year . '-12-31', 'Fin de año (medio día)', 'half', '12:00:00', 'national', 1],
        ];
    }

    /** Inserta los feriados del año una sola vez (no pisa cambios ni borrados del administrador). */
    public static function ensureYear(int $year): void
    {
        $key = 'holidays_seeded_' . $year;
        if (Settings::get($key, '') === '1') {
            return;
        }
        self::insertYear($year);
        Settings::set($key, '1');
    }

    /** Restablece los feriados automáticos del año (conserva los manuales). Devuelve cuántos insertó. */
    public static function regenerate(int $year): int
    {
        Db::delete('holidays', "source = 'auto' AND date BETWEEN ? AND ?", [$year . '-01-01', $year . '-12-31']);
        Settings::set('holidays_seeded_' . $year, '1');
        return self::insertYear($year);
    }

    private static function insertYear(int $year): int
    {
        $n = 0;
        foreach (self::defaultsFor($year) as [$date, $name, $kind, $end, $scope, $active]) {
            Db::q('INSERT IGNORE INTO holidays (date, name, kind, half_day_end, scope, active, source) VALUES (?, ?, ?, ?, ?, ?, ?)', [$date, $name, $kind, $end, $scope, $active, 'auto']);
            $n++;
        }
        return $n;
    }

    /** Feriados activos entre dos fechas locales (Y-m-d), indexados por fecha. */
    public static function between(string $fromDate, string $toDate): array
    {
        $out = [];
        $years = range((int) substr($fromDate, 0, 4), (int) substr($toDate, 0, 4));
        foreach ($years as $y) {
            self::ensureYear($y);
        }
        foreach (Db::all('SELECT * FROM holidays WHERE active = 1 AND date BETWEEN ? AND ?', [$fromDate, $toDate]) as $h) {
            $out[$h['date']][] = $h;
        }
        return $out;
    }
}
