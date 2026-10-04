<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Clock;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;

/** Utilidades compartidas por los controladores del panel (alcance por rol, IDOR, CSV, fechas locales). */
final class A1Support
{
    public const STATUS_LABELS = [
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmada',
        'cancelled' => 'Cancelada',
        'completed' => 'Completada',
        'no_show' => 'No asistió',
        'rejected' => 'Rechazada',
    ];

    public const STATUS_BADGES = [
        'pending' => 'badge-warn',
        'confirmed' => 'badge-ok',
        'cancelled' => 'badge-muted',
        'completed' => 'badge-gold',
        'no_show' => 'badge-err',
        'rejected' => 'badge-muted',
    ];

    public const SOURCES = [
        'llamada' => 'Llamada telefónica',
        'whatsapp' => 'WhatsApp',
        'presencial' => 'Presencial',
    ];

    public static function statusLabel(string $s): string
    {
        return self::STATUS_LABELS[$s] ?? $s;
    }

    public static function statusBadge(string $s): string
    {
        return self::STATUS_BADGES[$s] ?? 'badge-muted';
    }

    /** Actor para BookingService. */
    public static function actor(): array
    {
        $u = Auth::user() ?? [];
        return ['type' => 'user', 'label' => (string) ($u['name'] ?? 'Panel'), 'user_id' => isset($u['id']) ? (int) $u['id'] : null];
    }

    /**
     * Restricción SQL de citas según el rol: [fragmento, parámetros]. Rol anfitrión => solo sus citas (incluye eventos colectivos).
     * @return array{0:string,1:array}
     */
    public static function bookingScope(string $a = 'b'): array
    {
        $s = Auth::scopedHostId();
        if ($s === null) {
            return ['1 = 1', []];
        }
        return ["({$a}.host_id = ? OR EXISTS (SELECT 1 FROM booking_hosts bhs WHERE bhs.booking_id = {$a}.id AND bhs.host_id = ?))", [$s, $s]];
    }

    /** Clientes visibles: el anfitrión solo ve a quienes han reservado con él. */
    public static function clientScope(string $a = 'c'): array
    {
        $s = Auth::scopedHostId();
        if ($s === null) {
            return ['1 = 1', []];
        }
        return ["EXISTS (SELECT 1 FROM bookings bs WHERE bs.client_id = {$a}.id AND (bs.host_id = ? OR EXISTS (SELECT 1 FROM booking_hosts bhs WHERE bhs.booking_id = bs.id AND bhs.host_id = ?)))", [$s, $s]];
    }

    /** Carga una cita y verifica el alcance del usuario (IDOR: para lo ajeno responde 404, como si no existiera). */
    public static function booking(int $id): array
    {
        [$sql, $p] = self::bookingScope('b');
        $row = Db::one('SELECT b.* FROM bookings b WHERE b.id = ? AND ' . $sql, array_merge([$id], $p));
        if (!$row) {
            throw new HttpException(404);
        }
        return $row;
    }

    public static function client(int $id): array
    {
        [$sql, $p] = self::clientScope('c');
        $row = Db::one('SELECT c.* FROM clients c WHERE c.id = ? AND ' . $sql, array_merge([$id], $p));
        if (!$row) {
            throw new HttpException(404);
        }
        return $row;
    }

    /** Escapa comodines de LIKE (usar con ESCAPE '|'). */
    public static function like(string $s): string
    {
        return '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $s) . '%';
    }

    /** Fecha local de hoy (zona del negocio). */
    public static function today(?int $ts = null): string
    {
        return Tz::formatTs($ts ?? Clock::now(), Settings::tz(), 'Y-m-d');
    }

    public static function addDays(string $date, int $n): string
    {
        return (new \DateTimeImmutable($date . ' 12:00:00', new \DateTimeZone('UTC')))->modify(($n >= 0 ? '+' : '') . $n . ' day')->format('Y-m-d');
    }

    /** Rango UTC [inicio, fin) que cubre las fechas locales $from..$to (ambas incluidas). */
    public static function rangeUtc(string $from, string $to): array
    {
        $tz = Settings::tz();
        return [Tz::localToUtc($from . ' 00:00:00', $tz), Tz::localToUtc(self::addDays($to, 1) . ' 00:00:00', $tz)];
    }

    /** Ruta interna segura para "next"/"volver" (solo rutas del panel, sin esquemas ni dobles barras). */
    public static function safeNext(?string $n, string $default = '/admin'): string
    {
        $n = (string) $n;
        if ($n === '' || strlen($n) > 300 || !preg_match('#^/admin(?:/[A-Za-z0-9_\-.~%/]*)?(?:\?[A-Za-z0-9_\-.~%&=+\[\]]*)?$#', $n)) {
            return $default;
        }
        if (strpos($n, '//') !== false || strpos($n, '..') !== false) {
            return $default;
        }
        if (preg_match('#^/admin/(login|2fa|salir|olvide|restablecer|invitacion)#', $n)) {
            return $default;
        }
        return $n;
    }

    /** @return string[] */
    public static function tagList(?string $tags): array
    {
        $out = [];
        foreach (explode(',', (string) $tags) as $t) {
            $t = self::cleanTag($t);
            if ($t !== '') {
                $out[mb_strtolower($t)] = $t;
            }
        }
        return array_values($out);
    }

    public static function cleanTag(string $t): string
    {
        $t = Str::clean(str_replace([',', '%', '_', '|'], ' ', $t), 30);
        return trim((string) preg_replace('/\s+/u', ' ', $t));
    }

    /** CSV seguro (UTF-8 con BOM para Excel; neutraliza fórmulas =,+,-,@). */
    public static function csv(array $header, array $rows): string
    {
        $out = "\xEF\xBB\xBF" . self::csvLine($header);
        foreach ($rows as $r) {
            $out .= self::csvLine($r);
        }
        return $out;
    }

    private static function csvLine(array $cells): string
    {
        $parts = [];
        foreach ($cells as $v) {
            $s = (string) $v;
            if ($s !== '' && strpos("=+-@\t\r", $s[0]) !== false) {
                $s = "'" . $s;
            }
            $parts[] = '"' . str_replace('"', '""', $s) . '"';
        }
        return implode(',', $parts) . "\r\n";
    }

    /** Enlace de WhatsApp de un toque. */
    public static function waLink(string $phone, string $text): string
    {
        if (class_exists('App\\Services\\WhatsAppService')) {
            return \App\Services\WhatsAppService::link($phone, $text);
        }
        return 'https://wa.me/' . preg_replace('/\D+/', '', $phone) . '?text=' . rawurlencode($text);
    }

    /** ¿Existen los servicios del núcleo de reservas? (se construyen en paralelo) */
    public static function hasBookingCore(): bool
    {
        return class_exists('App\\Services\\BookingService') && class_exists('App\\Services\\AvailabilityService') && class_exists('App\\Services\\EventRepository');
    }

    /** Paginación: [offset, páginas, página actual] */
    public static function pages(int $total, int $page, int $per): array
    {
        $pages = max(1, (int) ceil($total / $per));
        $page = min(max(1, $page), $pages);
        return [($page - 1) * $per, $pages, $page];
    }

    /** Anfitriones visibles para el usuario (activos). */
    public static function hosts(bool $onlyActive = true): array
    {
        $s = Auth::scopedHostId();
        $sql = 'SELECT id, name, color, timezone FROM hosts' . ($onlyActive ? ' WHERE active = 1' : '');
        $rows = Db::all($sql . ' ORDER BY sort_order, name');
        if ($s !== null) {
            $rows = array_values(array_filter($rows, static fn (array $h): bool => (int) $h['id'] === $s));
        }
        return $rows;
    }

    /** Eventos activos que el usuario puede agendar (el anfitrión solo los suyos). */
    public static function events(): array
    {
        $s = Auth::scopedHostId();
        if ($s === null) {
            return Db::all('SELECT id, name, color, kind, default_duration, duration_options FROM event_types WHERE active = 1 ORDER BY sort_order, name');
        }
        return Db::all('SELECT e.id, e.name, e.color, e.kind, e.default_duration, e.duration_options FROM event_types e WHERE e.active = 1 AND EXISTS (SELECT 1 FROM event_hosts eh WHERE eh.event_type_id = e.id AND eh.host_id = ?) ORDER BY e.sort_order, e.name', [$s]);
    }

    /** Lista de duraciones (minutos) de un evento. */
    public static function durations(array $event): array
    {
        $list = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($event['duration_options'] ?? ''))), static fn (int $d): bool => $d > 0)));
        $def = (int) ($event['default_duration'] ?? 30);
        if (!$list) {
            $list = [$def > 0 ? $def : 30];
        }
        sort($list);
        return $list;
    }
}
