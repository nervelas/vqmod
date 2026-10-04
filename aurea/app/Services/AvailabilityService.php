<?php
declare(strict_types=1);

namespace Aurea\Services;

use Aurea\Core\Db;

/**
 * Motor de disponibilidad. Una sola fuente de verdad: la misma lógica genera los horarios
 * que se muestran y valida la reserva final (dentro de la transacción con bloqueo).
 */
final class AvailabilityService
{
    public const ACTIVE = ['pending', 'confirmed', 'completed'];

    /**
     * Carga todo lo necesario para calcular un rango de fechas de un profesional.
     * Con $lock=true las citas se leen con FOR UPDATE (uso dentro de la transacción de reserva).
     */
    public static function context(int $profId, string $fromDate, string $toDate, bool $lock = false, ?int $excludeAppt = null): array
    {
        $from = $fromDate . ' 00:00:00';
        $to = date('Y-m-d', strtotime($toDate . ' +1 day')) . ' 00:00:00';
        $sched = [];
        foreach (Db::all('SELECT weekday,location_id,start_time,end_time FROM schedules WHERE professional_id=? ORDER BY start_time', [$profId]) as $r) {
            $sched[(int)$r['weekday']][] = [
                'loc' => $r['location_id'] === null ? null : (int)$r['location_id'],
                's' => \Aurea\Core\Util::minutes($r['start_time']), 'e' => \Aurea\Core\Util::minutes($r['end_time']),
            ];
        }
        $offs = [];
        foreach (Db::all('SELECT start_at,end_at FROM time_off WHERE (professional_id=? OR professional_id IS NULL) AND end_at>? AND start_at<?', [$profId, $from, $to]) as $r) {
            $offs[] = [strtotime($r['start_at']), strtotime($r['end_at'])];
        }
        $in = Db::in(self::ACTIVE);
        $sql = 'SELECT id,service_id,start_at,end_at,block_start,block_end FROM appointments WHERE professional_id=? AND status IN (' . $in . ') AND block_end>? AND block_start<?';
        $params = array_merge([$profId], self::ACTIVE, [$from, $to]);
        if ($excludeAppt) { $sql .= ' AND id<>?'; $params[] = $excludeAppt; }
        if ($lock) { $sql .= ' FOR UPDATE'; }
        $appts = [];
        foreach (Db::all($sql, $params) as $r) {
            $appts[] = [
                'svc' => (int)$r['service_id'], 'start' => strtotime($r['start_at']), 'end' => strtotime($r['end_at']),
                'bs' => strtotime($r['block_start']), 'be' => strtotime($r['block_end']),
            ];
        }
        return ['sched' => $sched, 'offs' => $offs, 'appts' => $appts, 'hol' => HolidayService::range($fromDate, $toDate)];
    }

    /** Horarios de un día. Cada slot: ['time'=>'HH:MM','start'=>'Y-m-d H:i:s','left'=>cupos]. */
    public static function slots(array $svc, array $ctx, string $date, ?int $locId = null, array $opts = []): array
    {
        $now = $opts['now'] ?? time();
        $earliest = !empty($opts['ignore_notice']) ? 0 : $now + (int)$svc['min_notice_hours'] * 3600;
        $lastDate = date('Y-m-d', $now + (int)$svc['max_advance_days'] * 86400);
        if (empty($opts['ignore_notice']) && $date > $lastDate) { return []; }
        if ($date < date('Y-m-d', $now) && empty($opts['allow_past'])) { return []; }

        $hol = $ctx['hol'][$date] ?? null;
        if ($hol && $hol['kind'] === 'full') { return []; }
        $closeMin = ($hol && $hol['kind'] === 'half') ? \Aurea\Core\Util::minutes($hol['close']) : 1440;

        $wd = (int)date('w', strtotime($date . ' 12:00:00'));
        $dayStart = strtotime($date . ' 00:00:00');
        $dur = (int)$svc['duration_min'];
        $step = max(5, (int)$svc['slot_interval']);
        $out = [];
        $seen = [];
        foreach ($ctx['sched'][$wd] ?? [] as $b) {
            if ($locId !== null && $b['loc'] !== null && $b['loc'] !== $locId) { continue; }
            $end = min($b['e'], $closeMin);
            for ($m = $b['s']; $m + $dur <= $end; $m += $step) {
                $ts = $dayStart + $m * 60;
                if ($ts < $earliest || isset($seen[$ts])) { continue; }
                $left = self::freeCapacity($svc, $ctx, $ts);
                if ($left > 0) {
                    $seen[$ts] = true;
                    $out[] = ['time' => date('H:i', $ts), 'start' => date('Y-m-d H:i:s', $ts), 'left' => $left];
                }
            }
        }
        usort($out, static fn($a, $b) => strcmp($a['start'], $b['start']));
        return $out;
    }

    /**
     * Cupos libres para iniciar el servicio en $ts (0 = ocupado). Considera ausencias, solapamientos
     * (incluyendo buffers) y sesiones grupales del mismo servicio a la misma hora.
     */
    public static function freeCapacity(array $svc, array $ctx, int $ts): int
    {
        $dur = (int)$svc['duration_min'] * 60;
        $end = $ts + $dur;
        $bs = $ts - (int)$svc['buffer_before'] * 60;
        $be = $end + (int)$svc['buffer_after'] * 60;
        foreach ($ctx['offs'] as [$os, $oe]) {
            if ($ts < $oe && $end > $os) { return 0; }
        }
        $cap = max(1, (int)$svc['capacity']);
        $same = 0;
        foreach ($ctx['appts'] as $a) {
            if ($cap > 1 && $a['svc'] === (int)$svc['id'] && $a['start'] === $ts) { $same++; continue; }
            if ($bs < $a['be'] && $be > $a['bs']) { return 0; }
        }
        return $cap - $same;
    }

    /** Profesionales que ofrecen el servicio (y atienden en la sede indicada). */
    public static function professionalsFor(int $serviceId, ?int $locId = null, ?int $onlyId = null): array
    {
        $sql = 'SELECT p.*, ps.price_override FROM professionals p JOIN professional_services ps ON ps.professional_id=p.id AND ps.service_id=? WHERE p.active=1';
        $params = [$serviceId];
        if ($onlyId) { $sql .= ' AND p.id=?'; $params[] = $onlyId; }
        if ($locId) {
            $sql .= ' AND (EXISTS (SELECT 1 FROM professional_locations pl WHERE pl.professional_id=p.id AND pl.location_id=?)
                      OR NOT EXISTS (SELECT 1 FROM professional_locations pl2 WHERE pl2.professional_id=p.id))';
            $params[] = $locId;
        }
        return Db::all($sql . ' ORDER BY p.sort, p.name', $params);
    }

    /** Horarios de un día para uno o varios profesionales: time => [profId,...] (más datos del slot). */
    public static function daySlots(array $svc, array $profs, string $date, ?int $locId, array $opts = []): array
    {
        $map = [];
        foreach ($profs as $p) {
            $ctx = self::context((int)$p['id'], $date, $date, false, $opts['exclude'] ?? null);
            foreach (self::slots($svc, $ctx, $date, $locId, $opts) as $s) {
                $k = $s['start'];
                if (!isset($map[$k])) { $map[$k] = ['time' => $s['time'], 'start' => $s['start'], 'left' => 0, 'pros' => []]; }
                $map[$k]['pros'][] = (int)$p['id'];
                $map[$k]['left'] = max($map[$k]['left'], $s['left']);
            }
        }
        ksort($map);
        return array_values($map);
    }

    /** Días con disponibilidad en un mes (YYYY-MM): fecha => cantidad de horarios. */
    public static function monthDays(array $svc, array $profs, string $ym, ?int $locId, array $opts = []): array
    {
        $first = $ym . '-01';
        $last = date('Y-m-t', strtotime($first));
        $days = [];
        foreach ($profs as $p) {
            $ctx = self::context((int)$p['id'], $first, $last, false, $opts['exclude'] ?? null);
            for ($d = $first; $d <= $last; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
                $n = count(self::slots($svc, $ctx, $d, $locId, $opts));
                if ($n > 0) { $days[$d] = ($days[$d] ?? 0) + $n; }
            }
        }
        ksort($days);
        return $days;
    }

    /** Primer horario libre ('Y-m-d H:i:s') dentro de la ventana de anticipación, o null. */
    public static function nextAvailable(array $svc, array $profs, ?int $locId, array $opts = []): ?array
    {
        $now = $opts['now'] ?? time();
        $from = date('Y-m-d', $now);
        $to = date('Y-m-d', $now + min((int)$svc['max_advance_days'], 120) * 86400);
        $best = null;
        foreach ($profs as $p) {
            $ctx = self::context((int)$p['id'], $from, $to, false, $opts['exclude'] ?? null);
            for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
                if ($best && $d > substr($best['start'], 0, 10)) { break; }
                $s = self::slots($svc, $ctx, $d, $locId, $opts);
                if ($s) {
                    if (!$best || $s[0]['start'] < $best['start']) { $best = $s[0] + ['pro' => (int)$p['id']]; }
                    break;
                }
            }
        }
        return $best;
    }

    /**
     * Valida (con bloqueo de filas) que $start siga disponible para ese profesional.
     * Debe llamarse dentro de una transacción con el profesional ya bloqueado.
     * Opciones: ignore_notice, ignore_schedule (solo evita choques con otras citas y ausencias), exclude (id de cita).
     */
    public static function validate(array $svc, int $profId, string $start, ?int $locId, array $opts = []): bool
    {
        $ts = strtotime($start);
        if ($ts === false) { return false; }
        $date = date('Y-m-d', $ts);
        // Lectura sin FOR UPDATE a propósito: el bloqueo de la fila del profesional ya serializa, y evitar
        // bloqueos de rango previene interbloqueos entre agendas contiguas del índice.
        $ctx = self::context($profId, $date, $date, false, $opts['exclude'] ?? null);
        if (!empty($opts['ignore_schedule'])) {
            return self::freeCapacity($svc, $ctx, $ts) > 0;
        }
        foreach (self::slots($svc, $ctx, $date, $locId, $opts) as $s) {
            if ($s['start'] === date('Y-m-d H:i:s', $ts)) { return true; }
        }
        return false;
    }

    /**
     * Orden de preferencia para "cualquiera disponible": reparte equitativamente
     * (menos citas en el mes primero; a igualdad, quien lleva más tiempo sin recibir una).
     */
    public static function fairOrder(array $profIds, string $date): array
    {
        if (count($profIds) < 2) { return $profIds; }
        $from = date('Y-m-01', strtotime($date));
        $to = date('Y-m-t', strtotime($date)) . ' 23:59:59';
        $in = Db::in($profIds);
        $rows = Db::all('SELECT professional_id, COUNT(*) n, MAX(created_at) last_at FROM appointments
            WHERE professional_id IN (' . $in . ') AND status IN (\'pending\',\'confirmed\',\'completed\') AND start_at BETWEEN ? AND ?
            GROUP BY professional_id', array_merge($profIds, [$from, $to]));
        $stats = [];
        foreach ($rows as $r) { $stats[(int)$r['professional_id']] = [(int)$r['n'], (string)$r['last_at']]; }
        usort($profIds, static function ($a, $b) use ($stats) {
            $sa = $stats[$a] ?? [0, '']; $sb = $stats[$b] ?? [0, ''];
            return [$sa[0], $sa[1], $a] <=> [$sb[0], $sb[1], $b];
        });
        return $profIds;
    }
}
