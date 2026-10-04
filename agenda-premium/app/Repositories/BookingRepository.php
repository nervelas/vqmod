<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Db;
use App\Core\Tz;

/** Consultas de ocupación y conteo usadas por el motor de disponibilidad y las reservas. */
final class BookingRepository
{
    public const ACTIVE = "('pending','confirmed')";

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM bookings WHERE id = ?', [$id]);
    }

    public static function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        return Db::one('SELECT * FROM bookings WHERE token = ?', [$token]);
    }

    /** Ids de anfitriones ocupados por la cita. */
    public static function hostIds(int $bookingId): array
    {
        return array_map('intval', Db::col('SELECT host_id FROM booking_hosts WHERE booking_id = ?', [$bookingId]));
    }

    /**
     * Intervalos ocupados de un anfitrión (UTC como timestamps) que tocan [from, to].
     * @return array<int,array{s:int,e:int,kind:string,event:?int,start:?int,end:?int,seats:int}>
     */
    public static function busyForHost(int $hostId, int $fromTs, int $toTs, ?int $excludeId = null): array
    {
        $out = [];
        $sql = 'SELECT b.id, b.event_type_id, b.starts_at, b.ends_at, b.blocked_start, b.blocked_end, b.seats
                FROM bookings b JOIN booking_hosts bh ON bh.booking_id = b.id
                WHERE bh.host_id = ? AND b.status IN ' . self::ACTIVE . ' AND b.blocked_end > ? AND b.blocked_start < ?'
                . ($excludeId ? ' AND b.id <> ' . (int) $excludeId : '');
        foreach (Db::all($sql, [$hostId, Tz::fromTs($fromTs), Tz::fromTs($toTs)]) as $r) {
            $out[] = ['s' => Tz::ts($r['blocked_start']), 'e' => Tz::ts($r['blocked_end']), 'kind' => 'booking', 'event' => (int) $r['event_type_id'],
                'start' => Tz::ts($r['starts_at']), 'end' => Tz::ts($r['ends_at']), 'seats' => (int) $r['seats']];
        }
        foreach (Db::all('SELECT starts_at, ends_at FROM time_off WHERE (host_id = ? OR host_id IS NULL) AND ends_at > ? AND starts_at < ?', [$hostId, Tz::fromTs($fromTs), Tz::fromTs($toTs)]) as $r) {
            $out[] = ['s' => Tz::ts($r['starts_at']), 'e' => Tz::ts($r['ends_at']), 'kind' => 'off', 'event' => null, 'start' => null, 'end' => null, 'seats' => 0];
        }
        foreach (Db::all('SELECT eb.starts_at, eb.ends_at FROM external_busy eb JOIN external_calendars c ON c.id = eb.calendar_id WHERE eb.host_id = ? AND c.active = 1 AND eb.ends_at > ? AND eb.starts_at < ?', [$hostId, Tz::fromTs($fromTs), Tz::fromTs($toTs)]) as $r) {
            $out[] = ['s' => Tz::ts($r['starts_at']), 'e' => Tz::ts($r['ends_at']), 'kind' => 'ext', 'event' => null, 'start' => null, 'end' => null, 'seats' => 0];
        }
        usort($out, static fn (array $a, array $b): int => $a['s'] <=> $b['s']);
        return $out;
    }

    /** Reservas que usan un recurso en [from, to]. Las del mismo grupo (evento+inicio+fin) cuentan una sola vez. */
    public static function busyForResource(int $resourceId, int $fromTs, int $toTs, ?int $excludeId = null): array
    {
        $sql = 'SELECT b.event_type_id, b.starts_at, b.ends_at, b.blocked_start, b.blocked_end, e.kind
                FROM bookings b JOIN event_types e ON e.id = b.event_type_id
                WHERE b.resource_id = ? AND b.status IN ' . self::ACTIVE . ' AND b.blocked_end > ? AND b.blocked_start < ?'
                . ($excludeId ? ' AND b.id <> ' . (int) $excludeId : '');
        $seen = [];
        $out = [];
        foreach (Db::all($sql, [$resourceId, Tz::fromTs($fromTs), Tz::fromTs($toTs)]) as $r) {
            if ($r['kind'] === 'group') {
                $key = $r['event_type_id'] . '|' . $r['starts_at'] . '|' . $r['ends_at'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
            }
            $out[] = ['s' => Tz::ts($r['blocked_start']), 'e' => Tz::ts($r['blocked_end'])];
        }
        return $out;
    }

    /**
     * Conteo de citas de un evento por día y semana (zona del negocio) para los límites.
     * Los eventos grupales cuentan sesiones distintas (anfitrión + inicio), no asistentes.
     * @return array{day:array<string,int>,week:array<string,int>,slots:array<string,bool>}
     */
    public static function limitCounts(int $eventId, bool $group, int $fromTs, int $toTs, string $tz, ?int $excludeId = null): array
    {
        $sql = 'SELECT host_id, starts_at FROM bookings WHERE event_type_id = ? AND status IN ' . self::ACTIVE . ' AND starts_at >= ? AND starts_at < ?'
            . ($excludeId ? ' AND id <> ' . (int) $excludeId : '');
        $rows = Db::all($sql, [$eventId, Tz::fromTs($fromTs - 8 * 86400), Tz::fromTs($toTs + 8 * 86400)]);
        $day = [];
        $week = [];
        $slots = [];
        foreach ($rows as $r) {
            $key = $r['host_id'] . '|' . $r['starts_at'];
            if ($group) {
                if (isset($slots[$key])) {
                    continue;
                }
            }
            $slots[$key] = true;
            $ts = Tz::ts($r['starts_at']);
            $d = Tz::formatTs($ts, $tz, 'Y-m-d');
            $w = Tz::formatTs($ts, $tz, 'o-W');
            $day[$d] = ($day[$d] ?? 0) + 1;
            $week[$w] = ($week[$w] ?? 0) + 1;
        }
        return ['day' => $day, 'week' => $week, 'slots' => $slots];
    }
}
