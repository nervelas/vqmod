<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Cache;
use App\Core\Clock;
use App\Core\Db;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Validator;

/** Asistente de inicio en 5 pasos: profesión, eventos, horario, equipo y compartir. */
final class WizardController extends A4Controller
{
    private const STEPS = [1 => 'Profesión', 2 => 'Eventos', 3 => 'Horario', 4 => 'Equipo', 5 => 'Compartir'];
    private const DAYS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    public function index(Request $req, array $p): Response
    {
        $saved = max(1, min(5, Settings::int('onboarding_step', 1)));
        $n = $req->get('paso') !== null ? (int) $req->get('paso') : (Settings::bool('onboarding_done') ? 1 : $saved);
        return $this->show(max(1, min(5, $n)));
    }

    public function step(Request $req, array $p): Response
    {
        $n = (int) ($p['n'] ?? 1);
        $result = match ($n) {
            1 => $this->saveProfession($req),
            2 => $this->saveEvents($req),
            3 => $this->saveSchedule($req),
            4 => $this->saveTeam($req),
            default => ['ok' => true],
        };
        if (!$result['ok']) {
            return $this->show($n, $result['errors'] ?? [], $req->post, 422);
        }
        if (!empty($result['flash'])) {
            $this->flash('success', (string) $result['flash']);
        }
        $next = min(5, $n + 1);
        if ($next > Settings::int('onboarding_step', 1)) {
            Settings::set('onboarding_step', (string) $next);
        }
        return $this->redirect('/admin/asistente', ['paso' => $next]);
    }

    public function finish(Request $req, array $p): Response
    {
        Settings::setMany(['onboarding_done' => '1', 'onboarding_step' => '5']);
        $this->audit('wizard.finish', 'Asistente de inicio completado', 'settings');
        $this->flash('success', '¡Todo listo! Tu agenda está preparada para recibir citas.');
        return $this->redirect('/admin');
    }

    private function show(int $n, array $errors = [], array $old = [], int $status = 200): Response
    {
        $data = [
            'title' => 'Asistente de inicio',
            'n' => $n,
            'steps' => self::STEPS,
            'errors' => $errors,
            'old' => $old,
            'reached' => max($n, Settings::int('onboarding_step', 1)),
            'done' => Settings::bool('onboarding_done'),
        ];
        $scripts = ['js/admin-wizard.js'];
        switch ($n) {
            case 1:
                $profs = [];
                if (class_exists('App\\Services\\ProfessionService')) {
                    try {
                        $profs = \App\Services\ProfessionService::all();
                    } catch (\Throwable $e) {
                        Logger::error('Asistente: no se pudieron leer las profesiones', $e);
                    }
                }
                $data += ['professions' => $profs, 'current' => (string) Settings::get('profession', 'otro'), 'picked' => (string) ($old['profession'] ?? Settings::get('profession', 'otro')), 'demo' => !empty($old['demo'])];
                break;
            case 2:
                $data += ['events' => Db::all('SELECT id, slug, name, default_duration, duration_options, price, active, kind FROM event_types ORDER BY sort_order, id')];
                break;
            case 3:
                $data += ['days' => $this->scheduleView($old), 'dayNames' => self::DAYS, 'tz' => Settings::tz()];
                break;
            case 4:
                $host = Db::one('SELECT id, name, title, user_id FROM hosts ORDER BY (user_id IS NULL), sort_order, id LIMIT 1');
                $data += [
                    'host' => $host,
                    'others' => $host ? Db::all('SELECT id, name, title, active FROM hosts WHERE id <> ? ORDER BY sort_order, id LIMIT 12', [$host['id']]) : [],
                    'hostLabel' => (string) Settings::get('host_label', 'profesional'),
                ];
                break;
            default:
                $events = Db::all("SELECT slug, name FROM event_types WHERE active = 1 AND visibility = 'public' ORDER BY sort_order, id LIMIT 6");
                $links = [];
                foreach ($events as $e) {
                    $links[] = ['name' => $e['name'], 'url' => abs_url('/e/' . $e['slug'])];
                }
                $data += ['publicUrl' => abs_url('/'), 'links' => $links, 'business' => (string) Settings::get('business_name', 'Agenda Premium')];
                $scripts = ['vendor/qrcode-generator/qrcode.js', 'js/admin-wizard.js'];
        }
        $data['scripts'] = $scripts;
        $res = $this->page('admin/wizard/index', $data, $scripts, '/admin/asistente');
        $res->status = $status;
        return $res;
    }

    // ---- Paso 1 ----
    private function saveProfession(Request $req): array
    {
        $key = $req->str('profession', 40);
        if (!class_exists('App\\Services\\ProfessionService')) {
            return ['ok' => false, 'errors' => ['profession' => 'El catálogo de profesiones aún no está disponible.']];
        }
        $all = \App\Services\ProfessionService::all();
        if (!isset($all[$key])) {
            return ['ok' => false, 'errors' => ['profession' => 'Elige una de las opciones.']];
        }
        try {
            $sum = \App\Services\ProfessionService::apply($key, $req->bool('demo'));
        } catch (\Throwable $e) {
            Logger::error('Asistente: ProfessionService::apply falló', $e);
            return ['ok' => false, 'errors' => ['profession' => 'No pudimos aplicar la profesión. Inténtalo de nuevo.']];
        }
        Cache::clearAll();
        Cache::bumpAvailability();
        $this->audit('wizard.profession', $key . ($req->bool('demo') ? ' con datos de ejemplo' : ''), 'settings');
        $bits = [];
        foreach (['events' => 'eventos', 'workflows' => 'recordatorios', 'fields' => 'preguntas', 'bookings' => 'citas de ejemplo'] as $k => $label) {
            if (!empty($sum[$k])) {
                $bits[] = (int) $sum[$k] . ' ' . $label;
            }
        }
        return ['ok' => true, 'flash' => 'Preparamos tu agenda como «' . $all[$key]['name'] . '»' . ($bits ? ': ' . implode(', ', $bits) . '.' : '.')];
    }

    // ---- Paso 2 ----
    private function saveEvents(Request $req): array
    {
        $in = $req->post['ev'] ?? [];
        $errors = [];
        $updates = [];
        $ids = array_map('intval', Db::col('SELECT id FROM event_types'));
        if (!is_array($in)) {
            $in = [];
        }
        foreach ($in as $id => $row) {
            $id = (int) $id;
            if (!in_array($id, $ids, true) || !is_array($row)) {
                continue;
            }
            $price = Validator::money((string) ($row['price'] ?? '0') === '' ? '0' : (string) $row['price']);
            if ($price === null) {
                $errors['ev_' . $id . '_price'] = 'Precio no válido (usa solo números, por ejemplo 250 o 250.50).';
            }
            $dur = Validator::intRange((string) ($row['duration'] ?? ''), 5, 480);
            if ($dur === null) {
                $errors['ev_' . $id . '_duration'] = 'Indica la duración en minutos (entre 5 y 480).';
            }
            $updates[$id] = ['active' => !empty($row['active']) ? 1 : 0, 'price' => $price, 'duration' => $dur];
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }
        Db::tx(static function () use ($updates): void {
            foreach ($updates as $id => $u) {
                $cur = Db::one('SELECT duration_options, default_duration FROM event_types WHERE id = ?', [$id]);
                if (!$cur) {
                    continue;
                }
                $opts = array_values(array_filter(array_map('intval', explode(',', (string) $cur['duration_options']))));
                if ((int) $cur['default_duration'] !== $u['duration']) {
                    // si solo tenía una duración, la reemplazamos; si tenía varias, la añadimos
                    $opts = count($opts) <= 1 ? [$u['duration']] : array_values(array_unique(array_merge($opts, [$u['duration']])));
                    sort($opts);
                }
                Db::update('event_types', [
                    'active' => $u['active'],
                    'price' => $u['price'],
                    'default_duration' => $u['duration'],
                    'duration_options' => implode(',', $opts ?: [$u['duration']]),
                    'updated_at' => Clock::utc(),
                ], 'id = ?', [$id]);
            }
        });
        Cache::bumpAvailability();
        $this->audit('wizard.events', count($updates) . ' eventos ajustados', 'event_types');
        return ['ok' => true, 'flash' => $updates ? 'Guardamos los eventos.' : ''];
    }

    // ---- Paso 3 ----
    private function defaultSchedule(bool $create): ?array
    {
        $s = Db::one('SELECT * FROM schedules ORDER BY is_default DESC, id LIMIT 1');
        if (!$s && $create) {
            $id = Db::insert('schedules', ['name' => 'Horario general', 'timezone' => Settings::tz(), 'is_default' => 1, 'created_at' => Clock::utc()]);
            $s = Db::one('SELECT * FROM schedules WHERE id = ?', [$id]);
        }
        return $s;
    }

    /** Estructura para la vista: por día, activo y bloques (mínimo 2 filas). */
    private function scheduleView(array $old): array
    {
        $days = [];
        if (isset($old['d']) && is_array($old['d'])) {
            foreach (self::DAYS as $d => $_) {
                $row = is_array($old['d'][$d] ?? null) ? $old['d'][$d] : [];
                $blocks = [];
                foreach ((array) ($row['b'] ?? []) as $b) {
                    $blocks[] = ['s' => (string) ($b['s'] ?? ''), 'e' => (string) ($b['e'] ?? '')];
                }
                while (count($blocks) < 2) {
                    $blocks[] = ['s' => '', 'e' => ''];
                }
                $days[$d] = ['on' => !empty($row['on']), 'blocks' => $blocks];
            }
            return $days;
        }
        $s = $this->defaultSchedule(false);
        $rules = $s ? Db::all('SELECT weekday, start_time, end_time FROM schedule_rules WHERE schedule_id = ? ORDER BY weekday, start_time', [$s['id']]) : [];
        $by = [];
        foreach ($rules as $r) {
            $by[(int) $r['weekday']][] = ['s' => substr((string) $r['start_time'], 0, 5), 'e' => substr((string) $r['end_time'], 0, 5)];
        }
        foreach (self::DAYS as $d => $_) {
            if ($rules) {
                $blocks = $by[$d] ?? [];
            } else {
                $blocks = $d <= 5 ? [['s' => '08:00', 'e' => '12:00'], ['s' => '14:00', 'e' => '18:00']] : [];
            }
            $on = (bool) $blocks;
            while (count($blocks) < 2) {
                $blocks[] = ['s' => '', 'e' => ''];
            }
            $days[$d] = ['on' => $on, 'blocks' => array_slice($blocks, 0, 4)];
        }
        return $days;
    }

    private function saveSchedule(Request $req): array
    {
        $in = $req->post['d'] ?? [];
        if (!is_array($in)) {
            $in = [];
        }
        $errors = [];
        $rules = [];
        foreach (self::DAYS as $d => $name) {
            $row = is_array($in[$d] ?? null) ? $in[$d] : [];
            if (empty($row['on'])) {
                continue;
            }
            $blocks = [];
            foreach ((array) ($row['b'] ?? []) as $i => $b) {
                $s = is_array($b) ? trim((string) ($b['s'] ?? '')) : '';
                $e = is_array($b) ? trim((string) ($b['e'] ?? '')) : '';
                if ($s === '' && $e === '') {
                    continue;
                }
                if (!Validator::time($s) || !Validator::time($e) || strcmp(substr($s, 0, 5), substr($e, 0, 5)) >= 0) {
                    $errors['d_' . $d] = $name . ': cada bloque necesita hora de inicio y de fin, y el fin debe ser después del inicio.';
                    continue 2;
                }
                $blocks[] = [substr($s, 0, 5), substr($e, 0, 5)];
            }
            if (!$blocks) {
                $errors['d_' . $d] = $name . ': agrega al menos un bloque de horas o desmarca el día.';
                continue;
            }
            usort($blocks, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));
            for ($i = 1; $i < count($blocks); $i++) {
                if (strcmp($blocks[$i][0], $blocks[$i - 1][1]) < 0) {
                    $errors['d_' . $d] = $name . ': los bloques no pueden traslaparse.';
                    continue 2;
                }
            }
            foreach ($blocks as $b) {
                $rules[] = [$d, $b[0] . ':00', $b[1] . ':00'];
            }
        }
        if (!$errors && !$rules) {
            $errors['d_all'] = 'Activa al menos un día de atención.';
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }
        $s = $this->defaultSchedule(true);
        Db::tx(static function () use ($s, $rules): void {
            Db::delete('schedule_rules', 'schedule_id = ?', [$s['id']]);
            foreach ($rules as [$d, $a, $b]) {
                Db::insert('schedule_rules', ['schedule_id' => $s['id'], 'weekday' => $d, 'start_time' => $a, 'end_time' => $b]);
            }
        });
        Cache::bumpAvailability();
        $this->audit('wizard.schedule', count($rules) . ' bloques en ' . $s['name'], 'schedules', $s['id']);
        return ['ok' => true, 'flash' => 'Guardamos tu horario de atención.'];
    }

    // ---- Paso 4 ----
    private function saveTeam(Request $req): array
    {
        $host = Db::one('SELECT id, user_id FROM hosts ORDER BY (user_id IS NULL), sort_order, id LIMIT 1');
        if (!$host) {
            return ['ok' => true];
        }
        $name = $req->str('host_name', 120);
        $title = $req->str('host_title', 160);
        $errors = [];
        if ($name === '' || preg_match('/[<>]/', $name)) {
            $errors['host_name'] = 'Escribe el nombre de quien atiende (sin los signos < ni >).';
        }
        if (preg_match('/[<>]/', $title)) {
            $errors['host_title'] = 'El cargo no puede incluir los signos < ni >.';
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }
        Db::update('hosts', ['name' => $name, 'title' => $title !== '' ? $title : null], 'id = ?', [$host['id']]);
        if ($host['user_id']) {
            Db::update('users', ['name' => $name, 'updated_at' => Clock::utc()], 'id = ?', [$host['user_id']]);
        }
        $this->audit('wizard.team', 'Anfitrión principal: ' . $name, 'hosts', $host['id']);
        return ['ok' => true, 'flash' => 'Guardamos los datos del equipo.'];
    }
}
