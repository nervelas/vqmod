<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Clock;
use App\Core\Db;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Validator;

/** Tipos de evento: lectura enriquecida, validación y guardado. */
final class EventRepository
{
    public const KINDS = ['individual', 'group', 'round_robin', 'collective'];
    public const MODES = ['in_person', 'video_auto', 'video_custom', 'phone', 'home'];

    public static function find(int $id): ?array
    {
        $e = Db::one('SELECT * FROM event_types WHERE id = ?', [$id]);
        return $e ? self::hydrate($e) : null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $e = Db::one('SELECT * FROM event_types WHERE slug = ?', [$slug]);
        return $e ? self::hydrate($e) : null;
    }

    private static function hydrate(array $e): array
    {
        $id = (int) $e['id'];
        $e['hosts'] = Db::all(
            'SELECT h.id, h.name, h.slug, h.title, h.timezone, h.color, h.schedule_id, h.photo_file_id, eh.weight, eh.priority
             FROM event_hosts eh JOIN hosts h ON h.id = eh.host_id
             WHERE eh.event_type_id = ? AND h.active = 1 ORDER BY h.sort_order, h.id',
            [$id]
        );
        $e['resources'] = Db::all(
            'SELECT r.* FROM event_resources er JOIN resources r ON r.id = er.resource_id WHERE er.event_type_id = ? AND r.active = 1 ORDER BY r.id',
            [$id]
        );
        $e['fields'] = Db::all(
            'SELECT * FROM custom_fields WHERE active = 1 AND (event_type_id = ? OR event_type_id IS NULL) ORDER BY sort_order, id',
            [$id]
        );
        $list = [];
        foreach (explode(',', (string) $e['duration_options']) as $d) {
            $d = (int) trim($d);
            if ($d > 0) {
                $list[] = $d;
            }
        }
        $e['duration_list'] = $list ?: [(int) $e['default_duration']];
        return $e;
    }

    /** Eventos que se muestran en la página pública. */
    public static function publicList(): array
    {
        $rows = Db::all(
            "SELECT id FROM event_types
             WHERE active = 1 AND visibility = 'public' AND single_use = 0 AND (expires_at IS NULL OR expires_at > ?)
             ORDER BY sort_order, id",
            [Clock::utc()]
        );
        $out = [];
        foreach ($rows as $r) {
            $e = self::find((int) $r['id']);
            if ($e && $e['hosts']) {
                $out[] = $e;
            }
        }
        return $out;
    }

    /** null si se puede reservar; si no, el motivo en español. */
    public static function unavailableReason(array $event): ?string
    {
        if ((int) $event['active'] !== 1) {
            return 'Este servicio no está disponible por ahora.';
        }
        if (!empty($event['expires_at']) && Tz::ts((string) $event['expires_at']) <= Clock::now()) {
            return 'Este enlace ya venció.';
        }
        if ((int) $event['single_use'] === 1 && (int) Db::val("SELECT COUNT(*) FROM bookings WHERE event_type_id = ? AND status NOT IN ('cancelled','rejected')", [$event['id']]) > 0) {
            return 'Este enlace de un solo uso ya fue utilizado.';
        }
        if (empty($event['hosts'])) {
            return 'Este servicio todavía no tiene profesionales asignados.';
        }
        return null;
    }

    /**
     * Guarda un evento. $data: columnas de event_types + hosts (lista de ids o [host_id => ['weight'=>,'priority'=>]]) + resources (lista de ids).
     * @throws \InvalidArgumentException mensajes en español
     */
    public static function save(array $data, ?int $id = null): int
    {
        $old = $id ? Db::one('SELECT * FROM event_types WHERE id = ?', [$id]) : null;
        if ($id && !$old) {
            throw new \InvalidArgumentException('El evento no existe.');
        }
        $v = static fn (string $k, $def = null) => array_key_exists($k, $data) ? $data[$k] : ($old[$k] ?? $def);

        $name = Str::clean((string) $v('name', ''), 160);
        if ($name === '') {
            throw new \InvalidArgumentException('Escribe un nombre para el evento.');
        }
        $kind = (string) $v('kind', 'individual');
        if (!in_array($kind, self::KINDS, true)) {
            throw new \InvalidArgumentException('El tipo de evento no es válido.');
        }
        $mode = (string) $v('mode', 'in_person');
        if (!in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException('La modalidad no es válida.');
        }

        $durRaw = $v('duration_options', '30');
        $durations = [];
        foreach (is_array($durRaw) ? $durRaw : explode(',', (string) $durRaw) as $d) {
            $n = Validator::intRange(trim((string) $d), 5, 720);
            if ($n === null) {
                throw new \InvalidArgumentException('Las duraciones deben ser números de minutos entre 5 y 720.');
            }
            $durations[$n] = $n;
        }
        if (!$durations) {
            throw new \InvalidArgumentException('Elige al menos una duración.');
        }
        sort($durations);
        $defDur = (int) $v('default_duration', $durations[0]);
        if (!in_array($defDur, $durations, true)) {
            $defDur = $durations[0];
        }

        $color = (string) $v('color', '#C9A050');
        if (!Validator::color($color)) {
            throw new \InvalidArgumentException('El color debe tener el formato #RRGGBB.');
        }
        $int = static function ($val, int $min, int $max, string $label) {
            $n = Validator::intRange($val, $min, $max);
            if ($n === null) {
                throw new \InvalidArgumentException($label . ' debe ser un número entre ' . $min . ' y ' . $max . '.');
            }
            return $n;
        };
        $nullInt = static function ($val, int $min, int $max, string $label) use ($int) {
            return ($val === null || $val === '' || (string) $val === '0') ? null : $int($val, $min, $max, $label);
        };
        $price = Validator::money($v('price', '0'));
        if ($price === null) {
            throw new \InvalidArgumentException('El precio no es válido.');
        }
        $depType = (string) $v('deposit_type', 'none');
        if (!in_array($depType, ['none', 'fixed', 'percent'], true)) {
            throw new \InvalidArgumentException('El tipo de depósito no es válido.');
        }
        $depVal = Validator::money($v('deposit_value', '0'));
        if ($depVal === null) {
            throw new \InvalidArgumentException('El monto del depósito no es válido.');
        }
        if ($depType === 'percent' && (float) $depVal > 100) {
            throw new \InvalidArgumentException('El depósito en porcentaje no puede pasar de 100 %.');
        }
        if ($depType === 'fixed' && (float) $depVal > (float) $price && (float) $price > 0) {
            throw new \InvalidArgumentException('El depósito no puede ser mayor que el precio.');
        }
        $videoUrl = trim((string) $v('video_url', ''));
        if ($mode === 'video_custom' && !Validator::url($videoUrl)) {
            throw new \InvalidArgumentException('Escribe el enlace de la videollamada (empieza con https://).');
        }
        $redirect = trim((string) $v('redirect_url', ''));
        if ($redirect !== '' && !Validator::url($redirect)) {
            throw new \InvalidArgumentException('La dirección de redirección no es válida.');
        }
        foreach (['window_start', 'window_end'] as $k) {
            $x = $v($k, null);
            if ($x !== null && $x !== '' && !Validator::date((string) $x)) {
                throw new \InvalidArgumentException('Las fechas de la ventana de disponibilidad no son válidas.');
            }
        }
        $ws = $v('window_start', null) ?: null;
        $we = $v('window_end', null) ?: null;
        if ($ws && $we && $we < $ws) {
            throw new \InvalidArgumentException('La ventana de fechas termina antes de empezar.');
        }
        $expires = $v('expires_at', null);
        if ($expires !== null && $expires !== '' && !Validator::datetimeUtc((string) $expires)) {
            throw new \InvalidArgumentException('La fecha límite no es válida.');
        }
        $scheduleId = $v('schedule_id', null);
        $scheduleId = $scheduleId ? (int) $scheduleId : null;
        if ($scheduleId && !Db::val('SELECT id FROM schedules WHERE id = ?', [$scheduleId])) {
            throw new \InvalidArgumentException('El horario elegido no existe.');
        }
        $visibility = (string) $v('visibility', 'public');
        $rr = (string) $v('rr_mode', 'equitable');
        if (!in_array($visibility, ['public', 'secret'], true) || !in_array($rr, ['equitable', 'weighted', 'priority'], true)) {
            throw new \InvalidArgumentException('Hay una opción no válida en el evento.');
        }
        $active = (int) (bool) $v('active', 1);

        // Anfitriones
        $hostsIn = array_key_exists('hosts', $data) ? $data['hosts'] : null;
        $hosts = [];
        if ($hostsIn === null && $id) {
            foreach (Db::all('SELECT host_id, weight, priority FROM event_hosts WHERE event_type_id = ?', [$id]) as $r) {
                $hosts[(int) $r['host_id']] = ['weight' => (int) $r['weight'], 'priority' => (int) $r['priority']];
            }
        } else {
            foreach ((array) $hostsIn as $k => $val) {
                $hid = is_array($val) ? (int) $k : (int) $val;
                $w = is_array($val) ? max(1, (int) ($val['weight'] ?? 1)) : 1;
                $p = is_array($val) ? max(1, (int) ($val['priority'] ?? 1)) : 1;
                if ($hid > 0 && Db::val('SELECT id FROM hosts WHERE id = ?', [$hid])) {
                    $hosts[$hid] = ['weight' => $w, 'priority' => $p];
                }
            }
        }
        if (in_array($kind, ['individual', 'group'], true) && count($hosts) > 1) {
            throw new \InvalidArgumentException('Este tipo de evento admite un solo anfitrión. Para varios, usa round robin o colectivo.');
        }
        if ($kind === 'collective' && count($hosts) < 2 && $active) {
            throw new \InvalidArgumentException('Un evento colectivo necesita al menos dos anfitriones.');
        }
        if ($active && !$hosts) {
            throw new \InvalidArgumentException('Asigna al menos un anfitrión para activar el evento.');
        }
        $resIn = array_key_exists('resources', $data) ? (array) $data['resources'] : null;
        $resources = [];
        if ($resIn === null && $id) {
            $resources = array_map('intval', Db::col('SELECT resource_id FROM event_resources WHERE event_type_id = ?', [$id]));
        } else {
            foreach ((array) $resIn as $rid) {
                if ((int) $rid > 0 && Db::val('SELECT id FROM resources WHERE id = ?', [(int) $rid])) {
                    $resources[] = (int) $rid;
                }
            }
        }

        $slug = trim((string) $v('slug', ''));
        if ($slug === '') {
            $slug = Str::uniqueSlug('event_types', $name, $id);
        } elseif (!Validator::slug($slug)) {
            throw new \InvalidArgumentException('El enlace solo puede llevar letras minúsculas, números y guiones.');
        } elseif ((int) Db::val('SELECT COUNT(*) FROM event_types WHERE slug = ?' . ($id ? ' AND id <> ' . (int) $id : ''), [$slug]) > 0) {
            throw new \InvalidArgumentException('Ese enlace ya lo usa otro evento.');
        }

        $row = [
            'slug' => $slug,
            'name' => $name,
            'description' => Str::clean((string) $v('description', ''), 5000) ?: null,
            'kind' => $kind,
            'color' => strtoupper($color),
            'duration_options' => implode(',', $durations),
            'default_duration' => $defDur,
            'mode' => $mode,
            'location' => Str::clean((string) $v('location', ''), 255) ?: null,
            'video_url' => $videoUrl !== '' ? $videoUrl : null,
            'min_notice_minutes' => $int($v('min_notice_minutes', 120), 0, 525600, 'El aviso mínimo'),
            'max_advance_days' => $int($v('max_advance_days', 60), 1, 730, 'La anticipación máxima'),
            'window_start' => $ws,
            'window_end' => $we,
            'slot_interval' => $int($v('slot_interval', 30), 5, 720, 'El intervalo de horarios'),
            'buffer_before' => $int($v('buffer_before', 0), 0, 240, 'El margen antes'),
            'buffer_after' => $int($v('buffer_after', 0), 0, 240, 'El margen después'),
            'travel_minutes' => $int($v('travel_minutes', 0), 0, 240, 'El tiempo de traslado'),
            'daily_limit' => $nullInt($v('daily_limit', null), 1, 1000, 'El límite diario'),
            'weekly_limit' => $nullInt($v('weekly_limit', null), 1, 5000, 'El límite semanal'),
            'approval' => (int) (bool) $v('approval', 0),
            'price' => $price,
            'deposit_type' => $depType,
            'deposit_value' => $depVal,
            'cancel_hours' => $int($v('cancel_hours', 24), 0, 8760, 'Las horas mínimas de cancelación'),
            'cancel_policy_text' => Str::clean((string) $v('cancel_policy_text', ''), 2000) ?: null,
            'confirm_message' => Str::clean((string) $v('confirm_message', ''), 2000) ?: null,
            'redirect_url' => $redirect !== '' ? $redirect : null,
            'schedule_id' => $scheduleId,
            'capacity' => $int($v('capacity', 1), 1, 1000, 'Los cupos'),
            'rr_mode' => $rr,
            'series_sessions' => $int($v('series_sessions', 1), 1, 52, 'El número de sesiones'),
            'series_interval_days' => $int($v('series_interval_days', 7), 1, 90, 'El intervalo de la serie'),
            'single_use' => (int) (bool) $v('single_use', 0),
            'expires_at' => ($expires !== null && $expires !== '') ? $expires : null,
            'visibility' => $visibility,
            'respect_holidays' => (int) (bool) $v('respect_holidays', 1),
            'allow_guests' => (int) (bool) $v('allow_guests', 0),
            'max_guests' => $int($v('max_guests', 0), 0, 50, 'El máximo de invitados'),
            'allow_coupon' => (int) (bool) $v('allow_coupon', 1),
            'require_phone' => (int) (bool) $v('require_phone', 1),
            'team_id' => $v('team_id', null) ? (int) $v('team_id', null) : null,
            'active' => $active,
            'sort_order' => (int) $v('sort_order', 0),
            'updated_at' => Clock::utc(),
        ];

        return (int) Db::tx(function () use ($id, $row, $hosts, $resources): int {
            if ($id) {
                Db::update('event_types', $row, 'id = ?', [$id]);
                $eid = $id;
            } else {
                $row['created_at'] = Clock::utc();
                if (!isset($row['sort_order']) || $row['sort_order'] === 0) {
                    $row['sort_order'] = (int) Db::val('SELECT COALESCE(MAX(sort_order),0) + 1 FROM event_types');
                }
                $eid = Db::insert('event_types', $row);
            }
            Db::delete('event_hosts', 'event_type_id = ?', [$eid]);
            foreach ($hosts as $hid => $h) {
                Db::insert('event_hosts', ['event_type_id' => $eid, 'host_id' => $hid, 'weight' => $h['weight'], 'priority' => $h['priority']]);
            }
            Db::delete('event_resources', 'event_type_id = ?', [$eid]);
            foreach (array_unique($resources) as $rid) {
                Db::insert('event_resources', ['event_type_id' => $eid, 'resource_id' => $rid]);
            }
            Cache::bumpAvailability();
            return $eid;
        });
    }

    public static function duplicate(int $id): int
    {
        $e = Db::one('SELECT * FROM event_types WHERE id = ?', [$id]);
        if (!$e) {
            throw new \InvalidArgumentException('El evento no existe.');
        }
        return (int) Db::tx(function () use ($e, $id): int {
            $copy = $e;
            unset($copy['id']);
            $copy['name'] = Str::clean($e['name'] . ' (copia)', 160);
            $copy['slug'] = Str::uniqueSlug('event_types', (string) $e['slug'] . '-copia');
            $copy['active'] = 0;
            $copy['sort_order'] = (int) Db::val('SELECT COALESCE(MAX(sort_order),0) + 1 FROM event_types');
            $copy['created_at'] = $copy['updated_at'] = Clock::utc();
            $new = Db::insert('event_types', $copy);
            self::cloneRelations($id, $new);
            return $new;
        });
    }

    /** Copia de un solo uso: enlace secreto con caducidad opcional (UTC). */
    public static function singleUseCopy(int $id, ?string $expiresUtc = null): int
    {
        $e = Db::one('SELECT * FROM event_types WHERE id = ?', [$id]);
        if (!$e) {
            throw new \InvalidArgumentException('El evento no existe.');
        }
        if ($expiresUtc !== null && !Validator::datetimeUtc($expiresUtc)) {
            throw new \InvalidArgumentException('La fecha límite no es válida.');
        }
        return (int) Db::tx(function () use ($e, $id, $expiresUtc): int {
            $copy = $e;
            unset($copy['id']);
            $copy['slug'] = 'u-' . Str::token(8);
            $copy['single_use'] = 1;
            $copy['visibility'] = 'secret';
            $copy['active'] = 1;
            $copy['expires_at'] = $expiresUtc;
            $copy['created_at'] = $copy['updated_at'] = Clock::utc();
            $new = Db::insert('event_types', $copy);
            self::cloneRelations($id, $new);
            return $new;
        });
    }

    private static function cloneRelations(int $from, int $to): void
    {
        Db::exec('INSERT INTO event_hosts (event_type_id, host_id, weight, priority) SELECT ?, host_id, weight, priority FROM event_hosts WHERE event_type_id = ?', [$to, $from]);
        Db::exec('INSERT INTO event_resources (event_type_id, resource_id) SELECT ?, resource_id FROM event_resources WHERE event_type_id = ?', [$to, $from]);
        Db::exec(
            'INSERT INTO custom_fields (event_type_id, name, label, type, options, help, required, condition_field, condition_value, sort_order, active)
             SELECT ?, name, label, type, options, help, required, condition_field, condition_value, sort_order, active FROM custom_fields WHERE event_type_id = ?',
            [$to, $from]
        );
    }

    public static function delete(int $id): void
    {
        if ((int) Db::val('SELECT COUNT(*) FROM bookings WHERE event_type_id = ?', [$id]) > 0) {
            throw new \InvalidArgumentException('Este evento ya tiene citas. Para conservarlas, pausa el evento en lugar de eliminarlo.');
        }
        Db::delete('event_types', 'id = ?', [$id]);
        Cache::bumpAvailability();
    }
}
