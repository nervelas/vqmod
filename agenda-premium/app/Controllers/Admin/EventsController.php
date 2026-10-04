<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Db;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Validator;
use App\Services\EventRepository;

/** Tipos de evento: lista, formulario por pestañas, duplicar, enlace de un solo uso, pausar y eliminar. */
final class EventsController extends A2Controller
{
    public const FIELD_TYPES = [
        'text' => 'Texto corto',
        'textarea' => 'Texto largo',
        'select' => 'Lista desplegable',
        'radio' => 'Opción única',
        'checkbox' => 'Casillas',
        'number' => 'Número',
        'email' => 'Correo',
        'phone' => 'Teléfono',
        'date' => 'Fecha',
        'file' => 'Archivo',
        'consent' => 'Consentimiento',
    ];
    private const DURATION_PRESETS = [15, 20, 30, 45, 60, 90, 120];
    private const SCRIPTS = ['js/admin-events.js'];

    public function index(Request $req, array $p): Response
    {
        $events = Db::all(
            'SELECT e.*, (SELECT COUNT(*) FROM bookings b WHERE b.event_type_id = e.id) AS bookings_count,
                    (SELECT COUNT(*) FROM event_hosts h WHERE h.event_type_id = e.id) AS hosts_count
               FROM event_types e ORDER BY e.sort_order, e.id'
        );
        $now = Tz::fromTs(\App\Core\Clock::now());
        foreach ($events as &$e) {
            $e['url'] = abs_url('/e/' . $e['slug']);
            $e['expired'] = !empty($e['expires_at']) && (string) $e['expires_at'] < $now;
            $e['durations'] = $this->durationList((string) $e['duration_options'], (int) $e['default_duration']);
        }
        unset($e);
        $link = null;
        $linkId = (int) $req->get('enlace', 0);
        if ($linkId > 0) {
            $row = Db::one('SELECT id, name, slug, expires_at FROM event_types WHERE id = ? AND single_use = 1', [$linkId]);
            if ($row) {
                $link = ['url' => abs_url('/e/' . $row['slug']), 'name' => $row['name'], 'expires_at' => $row['expires_at']];
            }
        }
        return $this->page('admin/events/index', ['events' => $events, 'link' => $link, 'tz' => Settings::tz()], '/admin/eventos', 'Tipos de evento', self::SCRIPTS);
    }

    public function order(Request $req, array $p): Response
    {
        $ids = self::idList($req->post['ids'] ?? ($req->json()['ids'] ?? []));
        if (!$ids) {
            return $this->json(['ok' => false, 'error' => 'No recibimos el nuevo orden.'], 422);
        }
        Db::tx(static function () use ($ids): void {
            foreach ($ids as $i => $id) {
                Db::update('event_types', ['sort_order' => ($i + 1) * 10], 'id = ?', [$id]);
            }
        });
        Auth::audit('events.order', 'event_type', null, 'Orden de eventos actualizado');
        return $this->json(['ok' => true]);
    }

    public function create(Request $req, array $p): Response
    {
        return $this->form($this->defaults(), null, '');
    }

    public function edit(Request $req, array $p): Response
    {
        $ev = $this->load((int) $p['id']);
        if (!$ev) {
            $this->abort(404);
        }
        return $this->form($ev, (int) $p['id'], '');
    }

    public function store(Request $req, array $p): Response
    {
        return $this->persist($req, null);
    }

    public function update(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        if (!Db::val('SELECT id FROM event_types WHERE id = ?', [$id])) {
            $this->abort(404);
        }
        return $this->persist($req, $id);
    }

    public function duplicate(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        if (!Db::val('SELECT id FROM event_types WHERE id = ?', [$id])) {
            $this->abort(404);
        }
        try {
            $new = EventRepository::duplicate($id);
        } catch (\InvalidArgumentException $e) {
            return $this->done($req, 'error', $e->getMessage(), '/admin/eventos');
        } catch (\Throwable $e) {
            Logger::error('No se pudo duplicar el evento ' . $id, $e);
            return $this->done($req, 'error', 'No pudimos duplicar el evento. Inténtalo de nuevo.', '/admin/eventos');
        }
        Auth::audit('events.duplicate', 'event_type', $new, 'Duplicado desde #' . $id);
        $this->flash('success', 'Listo, duplicamos el evento. Revisa el nombre y los detalles antes de activarlo.');
        return $this->redirect('/admin/eventos/' . $new . '/editar');
    }

    public function singleUse(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        if (!Db::val('SELECT id FROM event_types WHERE id = ?', [$id])) {
            $this->abort(404);
        }
        $expires = null;
        $raw = trim((string) ($req->post['expires'] ?? ''));
        if ($raw !== '') {
            if (!Validator::date($raw)) {
                return $this->done($req, 'error', 'La fecha límite no es válida.', '/admin/eventos');
            }
            $expires = Tz::localToUtc($raw . ' 23:59:59', Settings::tz());
            if ($expires <= Tz::fromTs(\App\Core\Clock::now())) {
                return $this->done($req, 'error', 'La fecha límite debe ser posterior a hoy.', '/admin/eventos');
            }
        }
        try {
            $new = EventRepository::singleUseCopy($id, $expires);
        } catch (\InvalidArgumentException $e) {
            return $this->done($req, 'error', $e->getMessage(), '/admin/eventos');
        } catch (\Throwable $e) {
            Logger::error('No se pudo crear el enlace de un solo uso del evento ' . $id, $e);
            return $this->done($req, 'error', 'No pudimos crear el enlace. Inténtalo de nuevo.', '/admin/eventos');
        }
        Auth::audit('events.single_use', 'event_type', $new, 'Enlace de un solo uso desde #' . $id);
        $this->flash('success', 'Creamos un enlace que solo se puede usar una vez. Cópialo y compártelo.');
        return $this->redirect('/admin/eventos', ['enlace' => $new]);
    }

    public function toggle(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $row = Db::one('SELECT id, name, active FROM event_types WHERE id = ?', [$id]);
        if (!$row) {
            $this->abort(404);
        }
        $new = (int) $row['active'] === 1 ? 0 : 1;
        Db::update('event_types', ['active' => $new, 'updated_at' => \App\Core\Clock::utc()], 'id = ?', [$id]);
        $this->bumpAvailability();
        Auth::audit($new ? 'events.activate' : 'events.pause', 'event_type', $id, (string) $row['name']);
        return $this->done($req, 'success', $new ? 'El evento volvió a estar disponible para reservas.' : 'Pausamos el evento: ya no se puede reservar, pero conserva sus citas.', '/admin/eventos');
    }

    public function delete(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $row = Db::one('SELECT id, name FROM event_types WHERE id = ?', [$id]);
        if (!$row) {
            $this->abort(404);
        }
        if ((int) Db::val('SELECT COUNT(*) FROM bookings WHERE event_type_id = ?', [$id]) > 0) {
            return $this->done($req, 'error', 'Este evento ya tiene citas, así que no se puede eliminar. Mejor pausa el evento para que deje de recibir reservas.', '/admin/eventos');
        }
        try {
            EventRepository::delete($id);
        } catch (\InvalidArgumentException $e) {
            return $this->done($req, 'error', $e->getMessage(), '/admin/eventos');
        } catch (\Throwable $e) {
            Logger::error('No se pudo eliminar el evento ' . $id, $e);
            return $this->done($req, 'error', 'No pudimos eliminar el evento. Inténtalo de nuevo.', '/admin/eventos');
        }
        Auth::audit('events.delete', 'event_type', $id, (string) $row['name']);
        return $this->done($req, 'success', 'Eliminamos el evento «' . $row['name'] . '».', '/admin/eventos');
    }

    // ------------------------------------------------------------------ guardado

    private function persist(Request $req, ?int $id): Response
    {
        $data = $this->collect($req, $id);
        $questions = $this->collectQuestions($req, $id);
        $error = $questions['error'];
        if ($error === null) {
            try {
                $newId = Db::tx(function () use ($data, $id, $questions): int {
                    $saved = EventRepository::save($data, $id);
                    $this->saveQuestions($saved, $questions['rows']);
                    return $saved;
                });
                $this->bumpAvailability();
                Auth::audit($id === null ? 'events.create' : 'events.update', 'event_type', $newId, (string) $data['name']);
                $this->flash('success', $id === null ? 'Creamos el evento. Ya puedes compartir su enlace.' : 'Guardamos los cambios del evento.');
                return $this->redirect('/admin/eventos/' . $newId . '/editar');
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            } catch (\Throwable $e) {
                Logger::error('No se pudo guardar el evento', $e);
                $error = 'No pudimos guardar el evento. Revisa los datos e inténtalo de nuevo.';
            }
        }
        $ev = array_merge($this->defaults(), $data);
        $ev['id'] = $id;
        $ev['questions'] = $questions['view'];
        $ev['hosts_sel'] = $data['hosts'];
        $ev['res_sel'] = $data['resources'];
        $ev['durations'] = $this->durationList((string) $data['duration_options'], (int) $data['default_duration']);
        $ev['min_notice_value'] = $this->noticeParts((int) $data['min_notice_minutes'])[0];
        $ev['min_notice_unit'] = $this->noticeParts((int) $data['min_notice_minutes'])[1];
        $ev['expires_local'] = $this->expiresLocal($data['expires_at'] ?? null);
        return $this->form($ev, $id, $error, 422);
    }

    /** Normaliza lo enviado por el formulario (sin validar reglas de negocio: eso lo hace EventRepository::save). */
    private function collect(Request $req, ?int $id): array
    {
        $kinds = array_keys(self::KINDS);
        $kind = $req->str('kind', 20);
        $mode = $req->str('mode', 20);
        $name = $req->str('name', 160);
        $slug = strtolower($req->str('slug', 80));
        if ($slug === '' && $name !== '') {
            $slug = Str::uniqueSlug('event_types', $name, $id);
        }

        $durations = [];
        foreach ((array) ($req->post['durations'] ?? []) as $d) {
            if (is_scalar($d) && ctype_digit((string) $d)) {
                $durations[(int) $d] = (int) $d;
            }
        }
        foreach (preg_split('/[\s,;]+/', $req->str('duration_extra', 60)) ?: [] as $d) {
            if ($d !== '' && ctype_digit($d)) {
                $durations[(int) $d] = (int) $d;
            }
        }
        $durations = array_values(array_filter($durations, static fn (int $d): bool => $d >= 5 && $d <= 720));
        sort($durations);
        $default = self::intIn($req, 'default_duration', $durations[0] ?? 30, 5, 720);
        if ($durations && !in_array($default, $durations, true)) {
            $default = $durations[0];
        }
        if (!$durations) {
            $durations = [$default];
        }

        $notice = self::intIn($req, 'min_notice_value', 2, 0, 100000);
        $unit = $req->str('min_notice_unit', 10);
        $noticeMin = $notice * ($unit === 'days' ? 1440 : ($unit === 'hours' ? 60 : 1));

        $hosts = [];
        foreach ((array) ($req->post['hosts'] ?? []) as $hid => $cfg) {
            if (!is_array($cfg) || empty($cfg['on']) || !ctype_digit((string) $hid)) {
                continue;
            }
            $hosts[(int) $hid] = [
                'weight' => max(1, min(100, (int) ($cfg['weight'] ?? 1))),
                'priority' => max(1, min(100, (int) ($cfg['priority'] ?? 1))),
            ];
        }
        $validHosts = $hosts ? Db::col('SELECT id FROM hosts WHERE id IN (' . implode(',', array_fill(0, count($hosts), '?')) . ')', array_keys($hosts)) : [];
        $hosts = array_intersect_key($hosts, array_flip(array_map('intval', $validHosts)));

        $dateOrNull = static function (Request $r, string $k): ?string {
            $v = trim((string) ($r->post[$k] ?? ''));
            return $v === '' ? null : $v;
        };

        $expires = null;
        $expRaw = trim((string) ($req->post['expires'] ?? ''));
        if ($expRaw !== '' && (int) ($req->post['single_use'] ?? 0) === 1 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expRaw) && Validator::date($expRaw)) {
            $expires = Tz::localToUtc($expRaw . ' 23:59:59', Settings::tz());
        }

        $depType = $req->str('deposit_type', 10);
        $team = self::intOrNull($req, 'team_id');
        if ($team !== null && !Db::val('SELECT id FROM teams WHERE id = ?', [$team])) {
            $team = null;
        }
        $schedule = self::intOrNull($req, 'schedule_id');
        if ($schedule !== null && !Db::val('SELECT id FROM schedules WHERE id = ?', [$schedule])) {
            $schedule = null;
        }
        $color = $req->str('color', 7);

        return [
            'name' => $name,
            'slug' => $slug,
            'description' => trim((string) ($req->post['description'] ?? '')) === '' ? null : mb_substr((string) $req->post['description'], 0, 4000),
            'color' => Validator::color($color) ? strtoupper($color) : '#C9A050',
            'kind' => in_array($kind, $kinds, true) ? $kind : 'individual',
            'capacity' => self::intIn($req, 'capacity', 1, 1, 1000),
            'rr_mode' => in_array($req->str('rr_mode', 12), ['equitable', 'weighted', 'priority'], true) ? $req->str('rr_mode', 12) : 'equitable',
            'series_sessions' => self::intIn($req, 'series_sessions', 1, 1, 52),
            'series_interval_days' => self::intIn($req, 'series_interval_days', 7, 1, 365),
            'mode' => array_key_exists($mode, self::MODES) ? $mode : 'in_person',
            'location' => $req->str('location', 255) ?: null,
            'video_url' => $req->str('video_url', 500) ?: null,
            'travel_minutes' => self::intIn($req, 'travel_minutes', 0, 0, 600),
            'duration_options' => implode(',', $durations),
            'default_duration' => $default,
            'schedule_id' => $schedule,
            'min_notice_minutes' => $noticeMin,
            'max_advance_days' => self::intIn($req, 'max_advance_days', 60, 1, 730),
            'window_start' => $dateOrNull($req, 'window_start'),
            'window_end' => $dateOrNull($req, 'window_end'),
            'slot_interval' => self::intIn($req, 'slot_interval', 30, 5, 240),
            'buffer_before' => self::intIn($req, 'buffer_before', 0, 0, 240),
            'buffer_after' => self::intIn($req, 'buffer_after', 0, 0, 240),
            'daily_limit' => self::intOrNull($req, 'daily_limit', 1, 1000),
            'weekly_limit' => self::intOrNull($req, 'weekly_limit', 1, 5000),
            'respect_holidays' => $req->bool('respect_holidays') ? 1 : 0,
            'approval' => $req->bool('approval') ? 1 : 0,
            'price' => $this->money($req->str('price', 14)),
            'deposit_type' => in_array($depType, ['none', 'fixed', 'percent'], true) ? $depType : 'none',
            'deposit_value' => $this->money($req->str('deposit_value', 14)),
            'cancel_hours' => self::intIn($req, 'cancel_hours', 24, 0, 8760),
            'cancel_policy_text' => trim((string) ($req->post['cancel_policy_text'] ?? '')) === '' ? null : mb_substr((string) $req->post['cancel_policy_text'], 0, 2000),
            'confirm_message' => trim((string) ($req->post['confirm_message'] ?? '')) === '' ? null : mb_substr((string) $req->post['confirm_message'], 0, 2000),
            'redirect_url' => $req->str('redirect_url', 500) ?: null,
            'allow_guests' => $req->bool('allow_guests') ? 1 : 0,
            'max_guests' => self::intIn($req, 'max_guests', 0, 0, 50),
            'allow_coupon' => $req->bool('allow_coupon') ? 1 : 0,
            'require_phone' => $req->bool('require_phone') ? 1 : 0,
            'visibility' => $req->str('visibility', 10) === 'secret' ? 'secret' : 'public',
            'single_use' => $req->bool('single_use') ? 1 : 0,
            'expires_at' => $expires,
            'team_id' => $team,
            'active' => $req->bool('active') ? 1 : 0,
            'hosts' => $hosts,
            'resources' => self::idList($req->post['resources'] ?? []),
        ];
    }

    /** Dinero con coma o punto; si no es válido se deja el texto para que el validador muestre el error. */
    private function money(string $raw)
    {
        if (trim($raw) === '') {
            return 0;
        }
        $m = Validator::money($raw);
        return $m === null ? $raw : (float) $m;
    }

    // ------------------------------------------------------------------ preguntas

    /** @return array{rows:array,view:array,error:?string} */
    private function collectQuestions(Request $req, ?int $eventId): array
    {
        $raw = $req->post['q'] ?? [];
        $rows = [];
        $view = [];
        $error = null;
        $names = [];
        if (!is_array($raw)) {
            $raw = [];
        }
        $types = array_keys(self::FIELD_TYPES);
        $pos = 0;
        foreach ($raw as $q) {
            if (!is_array($q)) {
                continue;
            }
            $fid = isset($q['id']) && ctype_digit((string) $q['id']) ? (int) $q['id'] : 0;
            $label = Str::clean((string) ($q['label'] ?? ''), 190);
            $type = (string) ($q['type'] ?? 'text');
            $r = [
                'id' => $fid,
                'scope' => ($q['scope'] ?? 'event') === 'global' ? 'global' : 'event',
                'name' => strtolower(Str::clean((string) ($q['name'] ?? ''), 60)),
                'label' => $label,
                'type' => in_array($type, $types, true) ? $type : 'text',
                'options' => trim((string) ($q['options'] ?? '')),
                'help' => Str::clean((string) ($q['help'] ?? ''), 255),
                'required' => !empty($q['required']) ? 1 : 0,
                'condition_field' => strtolower(Str::clean((string) ($q['condition_field'] ?? ''), 60)),
                'condition_value' => Str::clean((string) ($q['condition_value'] ?? ''), 190),
                'active' => array_key_exists('active', $q) ? (!empty($q['active']) ? 1 : 0) : 1,
                'del' => !empty($q['del']) ? 1 : 0,
                'sort_order' => ++$pos * 10,
            ];
            $view[] = $r;
            if ($r['del']) {
                $rows[] = $r;
                continue;
            }
            if ($r['label'] === '') {
                $error = $error ?? 'Cada pregunta necesita un texto visible. Completa o elimina las preguntas vacías.';
                continue;
            }
            if ($r['name'] === '') {
                $r['name'] = $this->fieldName($r['label']);
            }
            if (!preg_match('/^[a-z][a-z0-9_]{0,59}$/', $r['name'])) {
                $error = $error ?? 'La clave de la pregunta «' . $r['label'] . '» solo puede tener letras minúsculas, números y guion bajo, y empezar con letra.';
                continue;
            }
            if (isset($names[$r['name']])) {
                $error = $error ?? 'Hay dos preguntas con la misma clave «' . $r['name'] . '». Cámbiala en una de ellas.';
                continue;
            }
            $names[$r['name']] = true;
            $rows[] = $r;
            $view[count($view) - 1] = $r;
            if (in_array($r['type'], ['select', 'radio'], true) && count(array_filter(array_map('trim', explode("\n", $r['options'])))) < 2) {
                $error = $error ?? 'La pregunta «' . $r['label'] . '» necesita al menos dos opciones (una por línea).';
            }
        }
        foreach ($rows as $r) {
            if ($r['del'] || $r['condition_field'] === '') {
                continue;
            }
            if (!isset($names[$r['condition_field']]) || $r['condition_field'] === $r['name']) {
                $error = $error ?? 'La condición de «' . $r['label'] . '» apunta a una pregunta que no existe. Elige otra o quítala.';
            }
        }
        return ['rows' => $rows, 'view' => $view, 'error' => $error];
    }

    private function fieldName(string $label): string
    {
        $s = str_replace('-', '_', Str::slug($label, 50));
        if ($s === '' || $s === 'item') {
            $s = 'pregunta';
        }
        return preg_match('/^[a-z]/', $s) ? $s : 'p_' . $s;
    }

    private function saveQuestions(int $eventId, array $rows): void
    {
        foreach ($rows as $r) {
            $isGlobal = $r['scope'] === 'global';
            $owner = $isGlobal ? null : $eventId;
            if ($r['id'] > 0) {
                $cur = Db::one('SELECT id, event_type_id FROM custom_fields WHERE id = ?', [$r['id']]);
                // Solo se tocan preguntas de este evento o globales: nunca las de otro evento.
                if (!$cur || ($cur['event_type_id'] !== null && (int) $cur['event_type_id'] !== $eventId)) {
                    continue;
                }
                if ($r['del']) {
                    Db::delete('custom_fields', 'id = ?', [$r['id']]);
                    continue;
                }
                Db::update('custom_fields', $this->fieldRow($r, $cur['event_type_id'] === null ? null : $eventId), 'id = ?', [$r['id']]);
                continue;
            }
            if ($r['del']) {
                continue;
            }
            Db::insert('custom_fields', $this->fieldRow($r, $owner));
        }
    }

    private function fieldRow(array $r, ?int $owner): array
    {
        return [
            'event_type_id' => $owner,
            'name' => $r['name'],
            'label' => $r['label'],
            'type' => $r['type'],
            'options' => in_array($r['type'], ['select', 'radio', 'checkbox'], true) && $r['options'] !== '' ? $r['options'] : null,
            'help' => $r['help'] !== '' ? $r['help'] : null,
            'required' => $r['required'],
            'condition_field' => $r['condition_field'] !== '' ? $r['condition_field'] : null,
            'condition_value' => $r['condition_field'] !== '' ? $r['condition_value'] : null,
            'sort_order' => $r['sort_order'],
            'active' => $r['active'],
        ];
    }

    // ------------------------------------------------------------------ vista

    private function form(array $ev, ?int $id, ?string $error, int $status = 200): Response
    {
        $hosts = Db::all('SELECT h.id, h.name, h.title, h.color, h.active, h.timezone FROM hosts h ORDER BY h.sort_order, h.name');
        $teamHosts = [];
        foreach (Db::all('SELECT team_id, host_id FROM team_hosts') as $th) {
            $teamHosts[(int) $th['team_id']][] = (int) $th['host_id'];
        }
        $data = [
            'ev' => $ev,
            'id' => $id,
            'error' => $error,
            'hosts' => $hosts,
            'teams' => Db::all('SELECT id, name FROM teams WHERE active = 1 ORDER BY name'),
            'team_hosts' => $teamHosts,
            'resources' => Db::all('SELECT id, name, capacity, active FROM resources ORDER BY name'),
            'schedules' => Db::all('SELECT id, name, timezone, is_default FROM schedules ORDER BY is_default DESC, name'),
            'presets' => self::DURATION_PRESETS,
            'jitsi' => (string) Settings::get('video_provider_domain', 'meet.jit.si'),
            'public_url' => $id ? abs_url('/e/' . ($ev['slug'] ?? '')) : '',
            'base_url' => abs_url('/e/'),
            'biz' => ['name' => (string) Settings::get('business_name', 'Agenda Premium'), 'tz' => Settings::tz()],
        ];
        return $this->page('admin/events/form', $data, '/admin/eventos', $id ? 'Editar evento' : 'Nuevo evento', self::SCRIPTS, $status);
    }

    private function defaults(): array
    {
        return [
            'id' => null, 'name' => '', 'slug' => '', 'description' => '', 'color' => '#C9A050', 'kind' => 'individual',
            'capacity' => 1, 'rr_mode' => 'equitable', 'series_sessions' => 1, 'series_interval_days' => 7,
            'mode' => 'in_person', 'location' => '', 'video_url' => '', 'travel_minutes' => 0,
            'duration_options' => '30', 'default_duration' => 30, 'durations' => [30], 'schedule_id' => null,
            'min_notice_minutes' => 120, 'min_notice_value' => 2, 'min_notice_unit' => 'hours', 'max_advance_days' => 60,
            'window_start' => null, 'window_end' => null, 'slot_interval' => 30, 'buffer_before' => 0, 'buffer_after' => 0,
            'daily_limit' => null, 'weekly_limit' => null, 'respect_holidays' => 1, 'approval' => 0,
            'price' => 0, 'deposit_type' => 'none', 'deposit_value' => 0, 'cancel_hours' => 24, 'cancel_policy_text' => '',
            'confirm_message' => '', 'redirect_url' => '', 'allow_guests' => 0, 'max_guests' => 0, 'allow_coupon' => 1,
            'require_phone' => 1, 'visibility' => 'public', 'single_use' => 0, 'expires_at' => null, 'expires_local' => '',
            'team_id' => null, 'active' => 1, 'hosts_sel' => [], 'res_sel' => [], 'questions' => [],
        ];
    }

    private function load(int $id): ?array
    {
        $row = Db::one('SELECT * FROM event_types WHERE id = ?', [$id]);
        if (!$row) {
            return null;
        }
        $sel = [];
        foreach (Db::all('SELECT host_id, weight, priority FROM event_hosts WHERE event_type_id = ?', [$id]) as $h) {
            $sel[(int) $h['host_id']] = ['weight' => (int) $h['weight'], 'priority' => (int) $h['priority']];
        }
        $row['hosts_sel'] = $sel;
        $row['res_sel'] = array_map('intval', Db::col('SELECT resource_id FROM event_resources WHERE event_type_id = ?', [$id]));
        $row['durations'] = $this->durationList((string) $row['duration_options'], (int) $row['default_duration']);
        [$row['min_notice_value'], $row['min_notice_unit']] = $this->noticeParts((int) $row['min_notice_minutes']);
        $row['expires_local'] = $this->expiresLocal($row['expires_at']);
        $qs = [];
        foreach (Db::all('SELECT * FROM custom_fields WHERE event_type_id = ? OR event_type_id IS NULL ORDER BY sort_order, id', [$id]) as $f) {
            $f['scope'] = $f['event_type_id'] === null ? 'global' : 'event';
            $f['del'] = 0;
            $f['options'] = (string) $f['options'];
            $f['help'] = (string) $f['help'];
            $f['condition_field'] = (string) $f['condition_field'];
            $f['condition_value'] = (string) $f['condition_value'];
            $qs[] = $f;
        }
        $row['questions'] = $qs;
        return $row;
    }

    /** @return int[] */
    private function durationList(string $options, int $default): array
    {
        $out = [];
        foreach (explode(',', $options) as $d) {
            if (ctype_digit(trim($d)) && (int) $d > 0) {
                $out[(int) $d] = (int) $d;
            }
        }
        if (!$out) {
            $out[$default] = $default;
        }
        $out = array_values($out);
        sort($out);
        return $out;
    }

    /** @return array{0:int,1:string} */
    private function noticeParts(int $minutes): array
    {
        if ($minutes > 0 && $minutes % 1440 === 0) {
            return [intdiv($minutes, 1440), 'days'];
        }
        if ($minutes > 0 && $minutes % 60 === 0) {
            return [intdiv($minutes, 60), 'hours'];
        }
        return [$minutes, 'minutes'];
    }

    private function expiresLocal($utc): string
    {
        return $utc ? Tz::format((string) $utc, Settings::tz(), 'Y-m-d') : '';
    }

    /** El caché de disponibilidad depende de esta versión: cualquier cambio en eventos la invalida. */
    private function bumpAvailability(): void
    {
        Cache::bumpAvailability();
    }
}
