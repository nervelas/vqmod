<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Validator;

/**
 * Exporta e importa TODA la configuración del negocio como JSON versionado (sin secretos, citas, clientes ni usuarios).
 * Modo «fusionar»: crea lo nuevo y actualiza lo que coincide (por slug, nombre o código).
 * Modo «reemplazar»: antes elimina la configuración existente que no esté en uso por citas ni usuarios.
 */
final class ConfigPortabilityService
{
    public const FORMAT = 1;
    private const APP = 'agenda-premium';
    private const MAX_BYTES = 5242880;

    /** Ajustes que nunca salen ni entran (secretos, estado interno, archivos locales y textos legales, que viajan aparte). Además se bloquea todo lo que empiece con legal_. */
    private const SETTINGS_BLOCKED = [
        'smtp_pass', 'wa_api_token', 'captcha_secret', 'cron_token', 'app_key', 'cron_last_run', 'installed_version', 'avail_version',
        'onboarding_done', 'logo_file_id', 'favicon_file_id', 'hero_file_id',
        'privacy_text', 'terms_text', 'cookies_notice',
    ];
    private const SETTINGS_SECRET_RE = '/(_pass|_password|_secret|_token|^app_key)$/i';

    /** Columnas portables por entidad. Prefijo ? = admite nulo; B booleano, I:min:max entero, D decimal, E:a|b lista, S:n texto corto, T texto largo. */
    private const SPEC = [
        'schedules' => ['name' => 'S:120', 'timezone' => 'TZ', 'is_default' => 'B'],
        'rules' => ['weekday' => 'I:1:7', 'start_time' => 'TIME', 'end_time' => 'TIME'],
        'overrides' => ['date' => 'DATE', 'is_open' => 'B', 'start_time' => '?TIME', 'end_time' => '?TIME', 'note' => '?S:190'],
        'holidays' => ['date' => 'DATE', 'name' => 'S:120', 'kind' => 'E:full|half', 'half_day_end' => '?TIME', 'scope' => 'E:national|capital', 'active' => 'B', 'source' => 'E:auto|manual'],
        'resources' => ['name' => 'S:120', 'description' => '?S:255', 'capacity' => 'I:1:10000', 'active' => 'B'],
        'teams' => ['slug' => 'SLUG', 'name' => 'S:120', 'description' => '?T', 'active' => 'B'],
        'hosts' => ['slug' => 'SLUG', 'name' => 'S:120', 'title' => '?S:160', 'bio' => '?T', 'timezone' => 'TZ', 'color' => 'COLOR', 'email' => '?S:190', 'phone' => '?S:30', 'whatsapp' => '?S:30', 'public_profile' => 'B', 'active' => 'B', 'sort_order' => 'I:-100000:100000'],
        'events' => [
            'slug' => 'SLUG', 'name' => 'S:160', 'description' => '?T', 'kind' => 'E:individual|group|round_robin|collective', 'color' => 'COLOR',
            'duration_options' => 'S:60', 'default_duration' => 'I:5:1440', 'mode' => 'E:in_person|video_auto|video_custom|phone|home',
            'location' => '?S:255', 'video_url' => '?S:500', 'min_notice_minutes' => 'I:0:5256000', 'max_advance_days' => 'I:0:3650',
            'window_start' => '?DATE', 'window_end' => '?DATE', 'slot_interval' => 'I:5:1440', 'buffer_before' => 'I:0:1440', 'buffer_after' => 'I:0:1440',
            'travel_minutes' => 'I:0:1440', 'daily_limit' => '?I:0:10000', 'weekly_limit' => '?I:0:10000', 'approval' => 'B', 'price' => 'D',
            'deposit_type' => 'E:none|fixed|percent', 'deposit_value' => 'D', 'cancel_hours' => 'I:0:100000', 'cancel_policy_text' => '?T', 'confirm_message' => '?T',
            'redirect_url' => '?S:500', 'capacity' => 'I:1:10000', 'rr_mode' => 'E:equitable|weighted|priority', 'series_sessions' => 'I:1:100',
            'series_interval_days' => 'I:1:365', 'visibility' => 'E:public|secret', 'respect_holidays' => 'B', 'allow_guests' => 'B', 'max_guests' => 'I:0:100',
            'allow_coupon' => 'B', 'require_phone' => 'B', 'active' => 'B', 'sort_order' => 'I:-100000:100000',
        ],
        'fields' => ['name' => 'S:60', 'label' => 'S:190', 'type' => 'E:text|textarea|select|radio|checkbox|number|email|phone|date|file|consent', 'options' => '?T', 'help' => '?S:255', 'required' => 'B', 'condition_field' => '?S:60', 'condition_value' => '?S:190', 'sort_order' => 'I:-100000:100000', 'active' => 'B'],
        'workflows' => ['name' => 'S:160', 'trigger_key' => 'E:booking.created|booking.approved|booking.rescheduled|booking.cancelled|booking.completed|booking.no_show|booking.before_start|booking.after_end', 'offset_minutes' => 'I:0:525600', 'action' => 'E:email|whatsapp|whatsapp_api|webhook|set_status|review_request|add_tag', 'recipient' => 'E:guest|host|admin', 'subject' => '?S:255', 'template' => '?T', 'action_value' => '?S:500', 'active' => 'B', 'sort_order' => 'I:-100000:100000'],
        'routing_forms' => ['slug' => 'SLUG', 'name' => 'S:160', 'description' => '?T', 'questions' => 'T', 'default_action' => 'E:event|host|team|message|url', 'default_value' => '?S:500', 'default_message' => '?T', 'active' => 'B'],
        'routing_rules' => ['priority' => 'I:-100000:100000', 'match_mode' => 'E:all|any', 'conditions' => 'T', 'action' => 'E:event|host|team|message|url', 'action_value' => '?S:500', 'message' => '?T', 'active' => 'B'],
        'packages' => ['name' => 'S:160', 'description' => '?S:255', 'sessions' => 'I:1:1000', 'price' => 'D', 'validity_days' => 'I:1:3650', 'active' => 'B'],
        'coupons' => ['code' => 'S:40', 'type' => 'E:percent|fixed', 'value' => 'D', 'max_uses' => '?I:1:1000000', 'valid_from' => '?DT', 'valid_to' => '?DT', 'active' => 'B'],
        'webhooks' => ['name' => 'S:120', 'url' => 'URL', 'events' => 'S:500', 'active' => 'B'],
    ];

    /** @var array{created:array<string,int>,updated:array<string,int>,skipped:array<string,int>,notes:string[]} */
    private static array $stats = ['created' => [], 'updated' => [], 'skipped' => [], 'notes' => []];

    // ------------------------------------------------------------------ exportar

    public static function export(): array
    {
        $sched = [];
        $schedName = [];
        foreach (Db::all('SELECT * FROM schedules ORDER BY id') as $s) {
            $schedName[(int) $s['id']] = $s['name'];
            $row = self::pick($s, 'schedules');
            $row['rules'] = array_map(fn (array $r): array => self::pick($r, 'rules'), Db::all('SELECT * FROM schedule_rules WHERE schedule_id = ? ORDER BY weekday, start_time', [$s['id']]));
            $row['overrides'] = array_map(fn (array $r): array => self::pick($r, 'overrides'), Db::all('SELECT * FROM schedule_overrides WHERE schedule_id = ? ORDER BY date, start_time', [$s['id']]));
            $sched[] = $row;
        }

        $hostSlug = [];
        $hosts = [];
        foreach (Db::all('SELECT * FROM hosts ORDER BY sort_order, id') as $h) {
            $hostSlug[(int) $h['id']] = $h['slug'];
            $hosts[] = self::pick($h, 'hosts') + ['schedule' => $schedName[(int) $h['schedule_id']] ?? null];
        }
        $teamSlug = [];
        $teams = [];
        foreach (Db::all('SELECT * FROM teams ORDER BY id') as $t) {
            $teamSlug[(int) $t['id']] = $t['slug'];
            $teams[] = self::pick($t, 'teams') + ['hosts' => array_values(array_filter(array_map(static fn ($id) => $hostSlug[(int) $id] ?? null, Db::col('SELECT host_id FROM team_hosts WHERE team_id = ?', [$t['id']]))))];
        }
        $resName = [];
        $resources = [];
        foreach (Db::all('SELECT * FROM resources ORDER BY id') as $r) {
            $resName[(int) $r['id']] = $r['name'];
            $resources[] = self::pick($r, 'resources');
        }

        $eventSlug = [];
        $events = [];
        foreach (Db::all("SELECT * FROM event_types WHERE single_use = 0 ORDER BY sort_order, id") as $e) {
            $eventSlug[(int) $e['id']] = $e['slug'];
            $events[] = self::pick($e, 'events') + [
                'schedule' => $schedName[(int) $e['schedule_id']] ?? null,
                'team' => $teamSlug[(int) $e['team_id']] ?? null,
                'hosts' => array_values(array_filter(array_map(static fn (array $r) => isset($hostSlug[(int) $r['host_id']]) ? ['host' => $hostSlug[(int) $r['host_id']], 'weight' => (int) $r['weight'], 'priority' => (int) $r['priority']] : null, Db::all('SELECT host_id, weight, priority FROM event_hosts WHERE event_type_id = ?', [$e['id']])))),
                'resources' => array_values(array_filter(array_map(static fn ($id) => $resName[(int) $id] ?? null, Db::col('SELECT resource_id FROM event_resources WHERE event_type_id = ?', [$e['id']])))),
                'fields' => array_map(fn (array $f): array => self::pick($f, 'fields'), Db::all('SELECT * FROM custom_fields WHERE event_type_id = ? ORDER BY sort_order, id', [$e['id']])),
            ];
        }

        $link = static function (array $row, string $col) use ($eventSlug): ?string {
            return $row[$col] === null ? null : ($eventSlug[(int) $row[$col]] ?? null);
        };

        $forms = [];
        foreach (Db::all('SELECT * FROM routing_forms ORDER BY id') as $f) {
            $row = self::pick($f, 'routing_forms');
            $row['default_value'] = self::targetOut((string) $f['default_action'], $f['default_value'], $eventSlug, $hostSlug, $teamSlug);
            $row['rules'] = array_map(function (array $r) use ($eventSlug, $hostSlug, $teamSlug): array {
                $x = self::pick($r, 'routing_rules');
                $x['action_value'] = self::targetOut((string) $r['action'], $r['action_value'], $eventSlug, $hostSlug, $teamSlug);
                return $x;
            }, Db::all('SELECT * FROM routing_rules WHERE form_id = ? ORDER BY priority, id', [$f['id']]));
            $forms[] = $row;
        }

        $settings = [];
        foreach (Settings::all() as $k => $v) {
            if (!self::settingBlocked((string) $k)) {
                $settings[$k] = (string) $v;
            }
        }
        $legal = LegalService::consentText()['texts'];

        $data = [
            'settings' => $settings,
            'legal' => $legal,
            'schedules' => $sched,
            'holidays' => array_map(fn (array $r): array => self::pick($r, 'holidays'), Db::all('SELECT * FROM holidays ORDER BY date, name')),
            'resources' => $resources,
            'teams' => $teams,
            'hosts' => $hosts,
            'events' => $events,
            'custom_fields' => array_map(fn (array $f): array => self::pick($f, 'fields'), Db::all('SELECT * FROM custom_fields WHERE event_type_id IS NULL ORDER BY sort_order, id')),
            'workflows' => array_map(fn (array $w): array => self::pick($w, 'workflows') + ['event' => $link($w, 'event_type_id')], Db::all('SELECT * FROM workflows ORDER BY sort_order, id')),
            'routing_forms' => $forms,
            'packages' => array_map(fn (array $p): array => self::pick($p, 'packages') + ['event' => $link($p, 'event_type_id')], Db::all('SELECT * FROM packages ORDER BY id')),
            'coupons' => array_map(fn (array $c): array => self::pick($c, 'coupons') + ['event' => $link($c, 'event_type_id')], Db::all('SELECT * FROM coupons ORDER BY id')),
            'webhooks' => array_map(fn (array $w): array => self::pick($w, 'webhooks'), Db::all('SELECT * FROM webhooks ORDER BY id')),
        ];
        return [
            'app' => self::APP,
            'version' => self::FORMAT,
            'app_version' => defined('AP_VERSION') ? AP_VERSION : '',
            'exported_at' => Clock::utc(),
            'business' => (string) Settings::get('business_name', ''),
        ] + $data;
    }

    /** Convierte un texto JSON en el arreglo que espera import(), con mensajes amables. */
    public static function decode(string $json): array
    {
        if (strlen($json) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('El archivo es demasiado grande para ser una configuración (máximo 5 MB).');
        }
        $json = ltrim($json, "\xEF\xBB\xBF");
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('No pudimos leer el archivo: no es un JSON válido. Usa el archivo que descargaste al exportar la configuración.');
        }
        return $data;
    }

    // ------------------------------------------------------------------ importar

    /** @return array{created:array<string,int>,updated:array<string,int>,skipped:array<string,int>,notes:string[]} */
    public static function import(array $data, bool $replace): array
    {
        self::validateEnvelope($data);
        $d = $data;
        foreach (['settings', 'schedules', 'holidays', 'resources', 'teams', 'hosts', 'events', 'custom_fields', 'workflows', 'routing_forms', 'packages', 'coupons', 'webhooks'] as $k) {
            if (isset($d[$k]) && !is_array($d[$k])) {
                throw new \InvalidArgumentException('La sección «' . $k . '» del archivo no tiene el formato esperado.');
            }
        }
        self::$stats = ['created' => [], 'updated' => [], 'skipped' => [], 'notes' => []];

        Db::tx(static function () use ($d, $replace): void {
            if ($replace) {
                self::wipe();
            }
            self::importSettings((array) ($d['settings'] ?? []), (array) ($d['legal'] ?? []));
            $schedules = self::importSchedules((array) ($d['schedules'] ?? []));
            self::importHolidays((array) ($d['holidays'] ?? []));
            $resources = self::importNamed('resources', (array) ($d['resources'] ?? []));
            $hosts = self::importHosts((array) ($d['hosts'] ?? []), $schedules);
            $teams = self::importTeams((array) ($d['teams'] ?? []), $hosts);
            $events = self::importEvents((array) ($d['events'] ?? []), $schedules, $teams, $hosts, $resources);
            self::importFields(null, (array) ($d['custom_fields'] ?? []));
            self::importWorkflows((array) ($d['workflows'] ?? []), $events);
            self::importRouting((array) ($d['routing_forms'] ?? []), $events, $hosts, $teams);
            self::importPackages((array) ($d['packages'] ?? []), $events);
            self::importCoupons((array) ($d['coupons'] ?? []), $events);
            self::importWebhooks((array) ($d['webhooks'] ?? []));
            if ((int) Db::val('SELECT COUNT(*) FROM schedules WHERE is_default = 1') === 0) {
                Db::exec('UPDATE schedules SET is_default = 1 ORDER BY id LIMIT 1');
            }
            Settings::set('avail_version', (string) (Settings::int('avail_version', 1) + 1));
        });
        Settings::flush();
        return self::$stats;
    }

    private static function validateEnvelope(array $data): void
    {
        if (($data['app'] ?? null) !== self::APP || !isset($data['version']) || !isset($data['settings']) || !is_array($data['settings'])) {
            throw new \InvalidArgumentException('Este archivo no parece una configuración exportada desde Agenda Premium.');
        }
        $fmt = (int) $data['version'];
        if ($fmt > self::FORMAT) {
            throw new \InvalidArgumentException('Este archivo viene de una versión más nueva de Agenda Premium (versión de formato ' . $fmt . '). Actualiza el sistema e inténtalo de nuevo.');
        }
        if ($fmt < 1) {
            throw new \InvalidArgumentException('La versión del archivo no es válida.');
        }
    }

    /** Modo reemplazar: elimina la configuración que no esté en uso por citas, usuarios o clientes. */
    private static function wipe(): void
    {
        Db::exec('DELETE FROM workflows');
        Db::exec('DELETE FROM routing_forms');
        Db::exec('DELETE FROM webhooks');
        Db::exec('DELETE FROM coupons');
        Db::exec('DELETE FROM packages WHERE id NOT IN (SELECT package_id FROM client_packages)');
        Db::exec('DELETE FROM custom_fields WHERE event_type_id IS NULL');
        Db::exec('DELETE FROM event_types WHERE id NOT IN (SELECT event_type_id FROM bookings) AND id NOT IN (SELECT event_type_id FROM polls)');
        Db::exec('DELETE FROM teams');
        Db::exec('DELETE FROM hosts WHERE user_id IS NULL AND id NOT IN (SELECT host_id FROM bookings) AND id NOT IN (SELECT host_id FROM booking_hosts) AND id NOT IN (SELECT host_id FROM polls)');
        Db::exec('DELETE FROM resources WHERE id NOT IN (SELECT resource_id FROM bookings WHERE resource_id IS NOT NULL)');
        Db::exec('DELETE FROM holidays');
        Db::exec('DELETE FROM schedules WHERE id NOT IN (SELECT schedule_id FROM hosts WHERE schedule_id IS NOT NULL) AND id NOT IN (SELECT schedule_id FROM event_types WHERE schedule_id IS NOT NULL)');
    }

    private static function importSettings(array $settings, array $legal): void
    {
        $known = Settings::defaults();
        $values = [];
        foreach ($settings as $k => $v) {
            if (!is_string($k) || !array_key_exists($k, $known) || self::settingBlocked($k)) {
                self::skip('settings');
                continue;
            }
            if (!is_scalar($v) && $v !== null) {
                throw new \InvalidArgumentException('El ajuste «' . $k . '» tiene un valor no válido.');
            }
            $values[$k] = mb_substr((string) $v, 0, 100000);
        }
        if (isset($values['timezone']) && !Tz::valid($values['timezone'])) {
            throw new \InvalidArgumentException('La zona horaria del negocio en el archivo no es válida.');
        }
        $before = Settings::all();
        $changed = 0;
        foreach ($values as $k => $v) {
            if ((string) ($before[$k] ?? '') !== $v) {
                Settings::set($k, $v);
                $changed++;
            }
        }
        if ($changed > 0) {
            self::count('updated', 'settings', $changed);
        }
        $docs = array_intersect_key(array_map(static fn ($t) => is_string($t) ? $t : '', $legal), ['privacy' => 1, 'terms' => 1, 'cookies' => 1]);
        $docs = array_filter($docs, static fn (string $t): bool => trim($t) !== '');
        if ($docs) {
            LegalService::save($docs);
            self::count('updated', 'legal', count($docs));
        }
    }

    /** @return array<string,int> nombre => id */
    private static function importSchedules(array $list): array
    {
        $map = [];
        foreach (Db::all('SELECT id, name FROM schedules') as $r) {
            $map[$r['name']] = (int) $r['id'];
        }
        foreach ($list as $i => $s) {
            $row = self::clean('schedules', $s, 'el horario #' . ($i + 1));
            self::need($row, 'name', 'un horario');
            $id = $map[$row['name']] ?? null;
            if ($id === null) {
                $id = Db::insert('schedules', $row + ['created_at' => Clock::utc()]);
                self::count('created', 'schedules');
            } else {
                Db::update('schedules', $row, 'id = ?', [$id]);
                self::count('updated', 'schedules');
            }
            $map[$row['name']] = $id;
            if (!empty($row['is_default'])) {
                Db::exec('UPDATE schedules SET is_default = 0 WHERE id <> ?', [$id]);
            }
            Db::delete('schedule_rules', 'schedule_id = ?', [$id]);
            Db::delete('schedule_overrides', 'schedule_id = ?', [$id]);
            foreach ((array) ($s['rules'] ?? []) as $r) {
                Db::insert('schedule_rules', self::clean('rules', $r, 'una regla del horario «' . $row['name'] . '»') + ['schedule_id' => $id]);
            }
            foreach ((array) ($s['overrides'] ?? []) as $o) {
                Db::insert('schedule_overrides', self::clean('overrides', $o, 'una excepción del horario «' . $row['name'] . '»') + ['schedule_id' => $id]);
            }
        }
        return $map;
    }

    private static function importHolidays(array $list): void
    {
        foreach ($list as $h) {
            $row = self::clean('holidays', $h, 'un feriado');
            self::need($row, 'date', 'un feriado');
            self::need($row, 'name', 'un feriado');
            $id = Db::val('SELECT id FROM holidays WHERE date = ? AND name = ?', [$row['date'], $row['name']]);
            if ($id === null) {
                Db::insert('holidays', $row);
                self::count('created', 'holidays');
            } else {
                Db::update('holidays', $row, 'id = ?', [$id]);
                self::count('updated', 'holidays');
            }
        }
    }

    /** Entidades que se identifican por nombre (recursos). @return array<string,int> */
    private static function importNamed(string $table, array $list): array
    {
        $map = [];
        foreach (Db::all('SELECT id, name FROM ' . $table) as $r) {
            $map[$r['name']] = (int) $r['id'];
        }
        foreach ($list as $x) {
            $row = self::clean($table, $x, 'un recurso');
            self::need($row, 'name', 'un recurso');
            if (isset($map[$row['name']])) {
                Db::update($table, $row, 'id = ?', [$map[$row['name']]]);
                self::count('updated', $table);
            } else {
                $map[$row['name']] = Db::insert($table, $row);
                self::count('created', $table);
            }
        }
        return $map;
    }

    /** @return array<string,int> slug => id */
    private static function importHosts(array $list, array $schedules): array
    {
        $map = [];
        foreach (Db::all('SELECT id, slug FROM hosts') as $r) {
            $map[$r['slug']] = (int) $r['id'];
        }
        foreach ($list as $h) {
            $row = self::clean('hosts', $h, 'un anfitrión');
            self::need($row, 'slug', 'un anfitrión');
            $sid = isset($h['schedule']) && is_string($h['schedule']) ? ($schedules[$h['schedule']] ?? null) : null;
            if (isset($map[$row['slug']])) {
                Db::update('hosts', $row + ['schedule_id' => $sid], 'id = ?', [$map[$row['slug']]]);
                self::count('updated', 'hosts');
            } else {
                $map[$row['slug']] = Db::insert('hosts', $row + ['schedule_id' => $sid, 'ics_token' => Str::token(16), 'created_at' => Clock::utc()]);
                self::count('created', 'hosts');
            }
        }
        return $map;
    }

    /** @return array<string,int> slug => id */
    private static function importTeams(array $list, array $hosts): array
    {
        $map = [];
        foreach (Db::all('SELECT id, slug FROM teams') as $r) {
            $map[$r['slug']] = (int) $r['id'];
        }
        foreach ($list as $t) {
            $row = self::clean('teams', $t, 'un equipo');
            self::need($row, 'slug', 'un equipo');
            if (isset($map[$row['slug']])) {
                Db::update('teams', $row, 'id = ?', [$map[$row['slug']]]);
                self::count('updated', 'teams');
            } else {
                $map[$row['slug']] = Db::insert('teams', $row);
                self::count('created', 'teams');
            }
            Db::delete('team_hosts', 'team_id = ?', [$map[$row['slug']]]);
            foreach ((array) ($t['hosts'] ?? []) as $slug) {
                if (is_string($slug) && isset($hosts[$slug])) {
                    Db::q('INSERT IGNORE INTO team_hosts (team_id, host_id) VALUES (?, ?)', [$map[$row['slug']], $hosts[$slug]]);
                }
            }
        }
        return $map;
    }

    /** @return array<string,int> slug => id */
    private static function importEvents(array $list, array $schedules, array $teams, array $hosts, array $resources): array
    {
        $map = [];
        foreach (Db::all('SELECT id, slug FROM event_types') as $r) {
            $map[$r['slug']] = (int) $r['id'];
        }
        $now = Clock::utc();
        foreach ($list as $e) {
            $row = self::clean('events', $e, 'un evento');
            self::need($row, 'slug', 'un evento');
            self::need($row, 'name', 'un evento');
            $row['schedule_id'] = isset($e['schedule']) && is_string($e['schedule']) ? ($schedules[$e['schedule']] ?? null) : null;
            $row['team_id'] = isset($e['team']) && is_string($e['team']) ? ($teams[$e['team']] ?? null) : null;
            if (isset($map[$row['slug']])) {
                $id = $map[$row['slug']];
                Db::update('event_types', $row + ['updated_at' => $now], 'id = ?', [$id]);
                self::count('updated', 'events');
            } else {
                $id = $map[$row['slug']] = Db::insert('event_types', $row + ['created_at' => $now, 'updated_at' => $now]);
                self::count('created', 'events');
            }
            Db::delete('event_hosts', 'event_type_id = ?', [$id]);
            foreach ((array) ($e['hosts'] ?? []) as $eh) {
                $hid = is_array($eh) && is_string($eh['host'] ?? null) ? ($hosts[$eh['host']] ?? null) : null;
                if ($hid === null) {
                    self::skip('event_hosts');
                    continue;
                }
                Db::insert('event_hosts', ['event_type_id' => $id, 'host_id' => $hid, 'weight' => max(1, (int) ($eh['weight'] ?? 1)), 'priority' => max(1, (int) ($eh['priority'] ?? 1))]);
            }
            Db::delete('event_resources', 'event_type_id = ?', [$id]);
            foreach ((array) ($e['resources'] ?? []) as $name) {
                if (is_string($name) && isset($resources[$name])) {
                    Db::q('INSERT IGNORE INTO event_resources (event_type_id, resource_id) VALUES (?, ?)', [$id, $resources[$name]]);
                }
            }
            self::importFields($id, (array) ($e['fields'] ?? []));
        }
        return $map;
    }

    private static function importFields(?int $eventId, array $list): void
    {
        foreach ($list as $f) {
            $row = self::clean('fields', $f, 'una pregunta');
            self::need($row, 'name', 'una pregunta');
            self::need($row, 'label', 'una pregunta');
            $id = Db::val('SELECT id FROM custom_fields WHERE name = ? AND ' . ($eventId === null ? 'event_type_id IS NULL' : 'event_type_id = ?'), $eventId === null ? [$row['name']] : [$row['name'], $eventId]);
            if ($id === null) {
                Db::insert('custom_fields', $row + ['event_type_id' => $eventId]);
                self::count('created', 'fields');
            } else {
                Db::update('custom_fields', $row, 'id = ?', [$id]);
                self::count('updated', 'fields');
            }
        }
    }

    private static function importWorkflows(array $list, array $events): void
    {
        foreach ($list as $w) {
            $row = self::clean('workflows', $w, 'un flujo');
            self::need($row, 'name', 'un flujo');
            $eid = isset($w['event']) && is_string($w['event']) ? ($events[$w['event']] ?? null) : null;
            if (isset($w['event']) && $w['event'] !== null && $eid === null) {
                self::skip('workflows');
                self::note('El flujo «' . $row['name'] . '» se omitió porque su evento no existe.');
                continue;
            }
            $id = Db::val('SELECT id FROM workflows WHERE name = ? AND trigger_key = ? AND ' . ($eid === null ? 'event_type_id IS NULL' : 'event_type_id = ?'), $eid === null ? [$row['name'], $row['trigger_key'] ?? ''] : [$row['name'], $row['trigger_key'] ?? '', $eid]);
            if ($id === null) {
                Db::insert('workflows', $row + ['event_type_id' => $eid, 'created_at' => Clock::utc()]);
                self::count('created', 'workflows');
            } else {
                Db::update('workflows', $row, 'id = ?', [$id]);
                self::count('updated', 'workflows');
            }
        }
    }

    private static function importRouting(array $list, array $events, array $hosts, array $teams): void
    {
        foreach ($list as $f) {
            $row = self::clean('routing_forms', $f, 'un formulario de enrutamiento');
            self::need($row, 'slug', 'un formulario de enrutamiento');
            $row['default_value'] = self::targetIn((string) ($row['default_action'] ?? 'message'), $f['default_value'] ?? null, $events, $hosts, $teams);
            $id = Db::val('SELECT id FROM routing_forms WHERE slug = ?', [$row['slug']]);
            if ($id === null) {
                $id = Db::insert('routing_forms', $row + ['created_at' => Clock::utc()]);
                self::count('created', 'routing_forms');
            } else {
                Db::update('routing_forms', $row, 'id = ?', [$id]);
                self::count('updated', 'routing_forms');
            }
            Db::delete('routing_rules', 'form_id = ?', [$id]);
            foreach ((array) ($f['rules'] ?? []) as $r) {
                $rule = self::clean('routing_rules', $r, 'una regla de enrutamiento');
                $rule['action_value'] = self::targetIn((string) ($rule['action'] ?? 'message'), $r['action_value'] ?? null, $events, $hosts, $teams);
                Db::insert('routing_rules', $rule + ['form_id' => $id]);
                self::count('created', 'routing_rules');
            }
        }
    }

    private static function importPackages(array $list, array $events): void
    {
        foreach ($list as $p) {
            $row = self::clean('packages', $p, 'un paquete');
            self::need($row, 'name', 'un paquete');
            $eid = isset($p['event']) && is_string($p['event']) ? ($events[$p['event']] ?? null) : null;
            $id = Db::val('SELECT id FROM packages WHERE name = ?', [$row['name']]);
            if ($id === null) {
                Db::insert('packages', $row + ['event_type_id' => $eid]);
                self::count('created', 'packages');
            } else {
                Db::update('packages', $row + ['event_type_id' => $eid], 'id = ?', [$id]);
                self::count('updated', 'packages');
            }
        }
    }

    private static function importCoupons(array $list, array $events): void
    {
        foreach ($list as $c) {
            $row = self::clean('coupons', $c, 'un cupón');
            self::need($row, 'code', 'un cupón');
            $row['code'] = strtoupper($row['code']);
            $eid = isset($c['event']) && is_string($c['event']) ? ($events[$c['event']] ?? null) : null;
            $id = Db::val('SELECT id FROM coupons WHERE code = ?', [$row['code']]);
            if ($id === null) {
                Db::insert('coupons', $row + ['event_type_id' => $eid]);
                self::count('created', 'coupons');
            } else {
                Db::update('coupons', $row + ['event_type_id' => $eid], 'id = ?', [$id]);
                self::count('updated', 'coupons');
            }
        }
    }

    private static function importWebhooks(array $list): void
    {
        foreach ($list as $w) {
            $row = self::clean('webhooks', $w, 'un webhook');
            self::need($row, 'name', 'un webhook');
            self::need($row, 'url', 'un webhook');
            $id = Db::val('SELECT id FROM webhooks WHERE name = ? AND url = ?', [$row['name'], $row['url']]);
            if ($id === null) {
                Db::insert('webhooks', $row + ['secret' => Str::token(20), 'created_at' => Clock::utc()]);
                self::count('created', 'webhooks');
                self::note('El webhook «' . $row['name'] . '» se creó con una clave secreta nueva; cópiala desde la pantalla de webhooks.');
            } else {
                Db::update('webhooks', $row, 'id = ?', [$id]);
                self::count('updated', 'webhooks');
            }
        }
    }

    // ------------------------------------------------------------------ utilidades

    private static function settingBlocked(string $key): bool
    {
        return in_array($key, self::SETTINGS_BLOCKED, true) || str_starts_with($key, 'legal_') || (bool) preg_match(self::SETTINGS_SECRET_RE, $key);
    }

    private static function pick(array $row, string $spec): array
    {
        return array_intersect_key($row, self::SPEC[$spec]);
    }

    /** Referencia de destino de enrutamiento: id -> slug (evento, anfitrión o equipo). */
    private static function targetOut(string $action, $value, array $events, array $hosts, array $teams): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $maps = ['event' => $events, 'host' => $hosts, 'team' => $teams];
        return isset($maps[$action]) ? ($maps[$action][(int) $value] ?? null) : (string) $value;
    }

    /** Referencia de destino de enrutamiento: slug -> id. */
    private static function targetIn(string $action, $value, array $events, array $hosts, array $teams): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $maps = ['event' => $events, 'host' => $hosts, 'team' => $teams];
        if (!isset($maps[$action])) {
            return mb_substr($value, 0, 500);
        }
        if (!isset($maps[$action][$value])) {
            self::note('Un destino de enrutamiento («' . mb_substr($value, 0, 60) . '») no existe y quedó vacío.');
            return null;
        }
        return (string) $maps[$action][$value];
    }

    /** Valida y normaliza una fila según SPEC; solo devuelve las columnas presentes. */
    private static function clean(string $spec, $row, string $what): array
    {
        if (!is_array($row)) {
            throw new \InvalidArgumentException('Hay ' . $what . ' con un formato que no entendemos.');
        }
        $out = [];
        foreach (self::SPEC[$spec] as $col => $type) {
            if (!array_key_exists($col, $row)) {
                continue;
            }
            $nullable = $type[0] === '?';
            $type = ltrim($type, '?');
            $v = $row[$col];
            if ($v === null || $v === '') {
                if ($nullable) {
                    $out[$col] = null;
                }
                continue;
            }
            $out[$col] = self::coerce($type, $v, $col, $what);
        }
        return $out;
    }

    private static function coerce(string $type, $v, string $col, string $what)
    {
        $bad = static fn () => new \InvalidArgumentException('El valor de «' . $col . '» en ' . $what . ' no es válido.');
        if (!is_scalar($v)) {
            throw $bad();
        }
        [$kind, $a, $b] = array_pad(explode(':', $type, 3), 3, null);
        $s = (string) $v;
        switch ($kind) {
            case 'S':
                return Str::clean($s, (int) $a);
            case 'T':
                return Str::clean($s, 100000);
            case 'SLUG':
                if (!Validator::slug($s)) {
                    throw $bad();
                }
                return $s;
            case 'I':
                $i = Validator::intRange($v, (int) $a, (int) $b);
                if ($i === null) {
                    throw $bad();
                }
                return $i;
            case 'D':
                $m = Validator::money($v);
                if ($m === null) {
                    throw $bad();
                }
                return $m;
            case 'B':
                return in_array($v, [true, 1, '1', 'true'], true) ? 1 : 0;
            case 'E':
                if (!in_array($s, explode('|', (string) $a), true)) {
                    throw $bad();
                }
                return $s;
            case 'DATE':
                if (!Validator::date($s)) {
                    throw $bad();
                }
                return $s;
            case 'TIME':
                if (!Validator::time($s)) {
                    throw $bad();
                }
                return strlen($s) === 5 ? $s . ':00' : $s;
            case 'DT':
                if (!Validator::datetimeUtc($s)) {
                    throw $bad();
                }
                return $s;
            case 'COLOR':
                if (!Validator::color($s)) {
                    throw $bad();
                }
                return $s;
            case 'TZ':
                if (!Tz::valid($s)) {
                    throw $bad();
                }
                return $s;
            case 'URL':
                if (!Validator::url($s)) {
                    throw $bad();
                }
                return $s;
        }
        throw $bad();
    }

    private static function need(array $row, string $col, string $what): void
    {
        if (!isset($row[$col]) || $row[$col] === '') {
            throw new \InvalidArgumentException('Falta el campo «' . $col . '» en ' . $what . ' del archivo.');
        }
    }

    private static function count(string $kind, string $section, int $n = 1): void
    {
        self::$stats[$kind][$section] = (self::$stats[$kind][$section] ?? 0) + $n;
    }

    private static function skip(string $section): void
    {
        self::count('skipped', $section);
    }

    private static function note(string $msg): void
    {
        if (count(self::$stats['notes']) < 50) {
            self::$stats['notes'][] = $msg;
        }
    }
}
