<?php
declare(strict_types=1);

namespace Aurea\Services;

use Aurea\Core\Db;

/** Feriados de Guatemala (con Semana Santa por algoritmo de Pascua) y consulta de feriados. */
final class HolidayService
{
    /** Domingo de Pascua (algoritmo anónimo gregoriano / Meeus-Jones-Butcher). */
    public static function easter(int $year): \DateTimeImmutable
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
        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    /** Lista de feriados predeterminados de un año. [fecha, nombre, kind, close_time|null, scope] */
    public static function defaults(int $year): array
    {
        $easter = self::easter($year);
        $d = static fn(string $s) => sprintf('%04d-%s', $year, $s);
        return [
            [$d('01-01'), 'Año Nuevo', 'full', null, 'national'],
            [$easter->modify('-3 days')->format('Y-m-d'), 'Jueves Santo', 'full', null, 'national'],
            [$easter->modify('-2 days')->format('Y-m-d'), 'Viernes Santo', 'full', null, 'national'],
            [$easter->modify('-1 day')->format('Y-m-d'), 'Sábado Santo', 'full', null, 'national'],
            [$d('05-01'), 'Día del Trabajo', 'full', null, 'national'],
            [$d('06-30'), 'Día del Ejército', 'full', null, 'national'],
            [$d('08-15'), 'Virgen de la Asunción (solo ciudad de Guatemala)', 'full', null, 'city'],
            [$d('09-15'), 'Día de la Independencia', 'full', null, 'national'],
            [$d('10-20'), 'Día de la Revolución', 'full', null, 'national'],
            [$d('11-01'), 'Día de Todos los Santos', 'full', null, 'national'],
            [$d('12-24'), 'Nochebuena (medio día)', 'half', '12:00:00', 'national'],
            [$d('12-25'), 'Navidad', 'full', null, 'national'],
            [$d('12-31'), 'Fin de año (medio día)', 'half', '12:00:00', 'national'],
        ];
    }

    /** Inserta los feriados predeterminados del año (no duplica). Devuelve cuántos agregó. */
    public static function seedYear(int $year): int
    {
        $n = 0;
        foreach (self::defaults($year) as [$date, $name, $kind, $close, $scope]) {
            $n += Db::exec('INSERT IGNORE INTO holidays (hdate,name,kind,close_time,scope,active) VALUES (?,?,?,?,?,1)', [$date, $name, $kind, $close, $scope]);
        }
        return $n;
    }

    /** Devuelve ['kind'=>'full'|'half','close'=>'HH:MM:SS'|null,'name'=>..] o null. Si hay varios, el más restrictivo. */
    public static function forDate(string $date): ?array
    {
        $rows = Db::all('SELECT name,kind,close_time FROM holidays WHERE hdate=? AND active=1', [$date]);
        if (!$rows) { return null; }
        $best = null;
        foreach ($rows as $r) {
            if ($r['kind'] === 'full') { return ['kind' => 'full', 'close' => null, 'name' => $r['name']]; }
            $best = ['kind' => 'half', 'close' => $r['close_time'] ?: '12:00:00', 'name' => $r['name']];
        }
        return $best;
    }

    /** Feriados activos en un rango (para el cálculo masivo de disponibilidad). [fecha => info] */
    public static function range(string $from, string $to): array
    {
        $out = [];
        foreach (Db::all('SELECT hdate,name,kind,close_time FROM holidays WHERE hdate BETWEEN ? AND ? AND active=1', [$from, $to]) as $r) {
            $cur = $out[$r['hdate']] ?? null;
            if ($cur && $cur['kind'] === 'full') { continue; }
            $out[$r['hdate']] = ['kind' => $r['kind'], 'close' => $r['kind'] === 'half' ? ($r['close_time'] ?: '12:00:00') : null, 'name' => $r['name']];
        }
        return $out;
    }
}
