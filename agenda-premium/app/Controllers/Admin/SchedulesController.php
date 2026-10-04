<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Clock;
use App\Core\Db;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Validator;

/** Horarios reutilizables: bloques por día de la semana y excepciones por fecha. El anfitrión solo gestiona el suyo. */
final class SchedulesController extends A2Controller
{
    private const SCRIPTS = ['js/admin-schedules.js'];
    public const DAYS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    public function index(Request $req, array $p): Response
    {
        $scope = $this->scope();
        $where = '';
        $params = [];
        if ($scope !== null) {
            $where = 'WHERE s.id = (SELECT schedule_id FROM hosts WHERE id = ?)';
            $params[] = $scope;
        }
        $rows = Db::all(
            'SELECT s.*, (SELECT COUNT(*) FROM hosts h WHERE h.schedule_id = s.id) AS hosts_count,
                    (SELECT COUNT(*) FROM event_types e WHERE e.schedule_id = s.id) AS events_count
               FROM schedules s ' . $where . ' ORDER BY s.is_default DESC, s.name',
            $params
        );
        foreach ($rows as &$s) {
            $s['summary'] = $this->summary((int) $s['id']);
            $s['can_edit'] = $this->canEdit((int) $s['id']);
        }
        unset($s);
        return $this->page('admin/schedules/index', [
            'schedules' => $rows,
            'is_host' => $scope !== null,
            'can_create' => $this->canCreate(),
        ], '/admin/horarios', 'Horarios', self::SCRIPTS);
    }

    public function create(Request $req, array $p): Response
    {
        if (!$this->canCreate()) {
            $this->abort(403);
        }
        $tz = Settings::tz();
        $scope = $this->scope();
        if ($scope) {
            $tz = Tz::safe((string) Db::val('SELECT timezone FROM hosts WHERE id = ?', [$scope]), $tz);
        }
        $rules = [];
        foreach ([1, 2, 3, 4, 5] as $d) {
            $rules[$d] = [['start' => '09:00', 'end' => '17:00']];
        }
        return $this->form(['id' => null, 'name' => $scope ? 'Mi horario' : '', 'timezone' => $tz, 'is_default' => 0], $rules, [], null);
    }

    public function edit(Request $req, array $p): Response
    {
        $s = $this->mine((int) $p['id']);
        [$rules, $ex] = $this->load((int) $s['id']);
        return $this->form($s, $rules, $ex, null);
    }

    public function store(Request $req, array $p): Response
    {
        if (!$this->canCreate()) {
            $this->abort(403);
        }
        return $this->persist($req, null);
    }

    public function update(Request $req, array $p): Response
    {
        $s = $this->mine((int) $p['id']);
        return $this->persist($req, (int) $s['id']);
    }

    public function makeDefault(Request $req, array $p): Response
    {
        if (!$this->isAdmin()) {
            $this->abort(403);
        }
        $id = (int) $p['id'];
        $s = Db::one('SELECT id, name FROM schedules WHERE id = ?', [$id]);
        if (!$s) {
            $this->abort(404);
        }
        Db::tx(static function () use ($id): void {
            Db::exec('UPDATE schedules SET is_default = 0');
            Db::update('schedules', ['is_default' => 1], 'id = ?', [$id]);
        });
        Cache::bumpAvailability();
        Auth::audit('schedules.default', 'schedule', $id, (string) $s['name']);
        return $this->done($req, 'success', '«' . $s['name'] . '» es ahora el horario predeterminado.', '/admin/horarios');
    }

    public function delete(Request $req, array $p): Response
    {
        if (!$this->isAdmin()) {
            $this->abort(403);
        }
        $id = (int) $p['id'];
        $s = Db::one('SELECT id, name, is_default FROM schedules WHERE id = ?', [$id]);
        if (!$s) {
            $this->abort(404);
        }
        if ((int) $s['is_default'] === 1) {
            return $this->done($req, 'error', 'No se puede eliminar el horario predeterminado. Marca otro como predeterminado primero.', '/admin/horarios');
        }
        Db::delete('schedules', 'id = ?', [$id]);
        Cache::bumpAvailability();
        Auth::audit('schedules.delete', 'schedule', $id, (string) $s['name']);
        return $this->done($req, 'success', 'Eliminamos el horario «' . $s['name'] . '». Quienes lo usaban pasan al predeterminado.', '/admin/horarios');
    }

    // ------------------------------------------------------------------ permisos

    /** ¿Puede el usuario actual crear un horario? Un anfitrión solo si no tiene uno propio. */
    private function canCreate(): bool
    {
        $scope = $this->scope();
        if ($scope === null) {
            return true;
        }
        if ($scope === 0) {
            return false;
        }
        return !$this->hostOwnsDedicated($scope);
    }

    private function hostOwnsDedicated(int $hostId): bool
    {
        $sid = Db::val('SELECT schedule_id FROM hosts WHERE id = ?', [$hostId]);
        return $sid !== null && $this->isDedicated((int) $sid, $hostId);
    }

    private function isDedicated(int $scheduleId, int $hostId): bool
    {
        $s = Db::one('SELECT is_default FROM schedules WHERE id = ?', [$scheduleId]);
        if (!$s || (int) $s['is_default'] === 1) {
            return false;
        }
        return (int) Db::val('SELECT COUNT(*) FROM hosts WHERE schedule_id = ? AND id <> ?', [$scheduleId, $hostId]) === 0;
    }

    private function canEdit(int $scheduleId): bool
    {
        $scope = $this->scope();
        if ($scope === null) {
            return true;
        }
        if ($scope === 0) {
            return false;
        }
        return (int) Db::val('SELECT schedule_id FROM hosts WHERE id = ?', [$scope]) === $scheduleId && $this->isDedicated($scheduleId, $scope);
    }

    /** Horario editable por el usuario actual o 404 (no se revela si existe). */
    private function mine(int $id): array
    {
        $s = Db::one('SELECT * FROM schedules WHERE id = ?', [$id]);
        if (!$s || !$this->canEdit($id)) {
            $this->abort($s ? 403 : 404);
        }
        return $s;
    }

    // ------------------------------------------------------------------ datos

    /** @return array{0:array<int,array>,1:array<int,array>} reglas por día y excepciones */
    private function load(int $id): array
    {
        $rules = [];
        foreach (Db::all('SELECT weekday, start_time, end_time FROM schedule_rules WHERE schedule_id = ? ORDER BY weekday, start_time', [$id]) as $r) {
            $rules[(int) $r['weekday']][] = ['start' => substr((string) $r['start_time'], 0, 5), 'end' => substr((string) $r['end_time'], 0, 5)];
        }
        $ex = [];
        foreach (Db::all('SELECT date, is_open, start_time, end_time, note FROM schedule_overrides WHERE schedule_id = ? ORDER BY date, start_time', [$id]) as $o) {
            $d = (string) $o['date'];
            if (!isset($ex[$d])) {
                $ex[$d] = ['date' => $d, 'open' => (int) $o['is_open'], 'note' => (string) $o['note'], 'blocks' => []];
            }
            if ((int) $o['is_open'] === 1 && $o['start_time'] !== null) {
                $ex[$d]['blocks'][] = ['start' => substr((string) $o['start_time'], 0, 5), 'end' => substr((string) $o['end_time'], 0, 5)];
            }
        }
        return [$rules, array_values($ex)];
    }

    private function summary(int $id): array
    {
        [$rules, $ex] = $this->load($id);
        $out = [];
        foreach (self::DAYS as $d => $name) {
            $out[$d] = $rules[$d] ?? [];
        }
        return ['days' => $out, 'exceptions' => count($ex)];
    }

    private function persist(Request $req, ?int $id): Response
    {
        $name = $req->str('name', 120);
        $tz = $req->str('timezone', 64);
        $error = null;
        if ($name === '') {
            $error = 'Ponle un nombre al horario, por ejemplo «Horario de consultorio».';
        } elseif (!Tz::valid($tz)) {
            $error = 'Elige una zona horaria válida.';
        }
        [$rules, $e1] = $this->parseRules($req->post['rules'] ?? []);
        [$ex, $e2] = $this->parseExceptions($req->post['ex'] ?? []);
        $error = $error ?? $e1 ?? $e2;

        if ($error !== null) {
            return $this->form(['id' => $id, 'name' => $name, 'timezone' => $tz ?: Settings::tz(), 'is_default' => $req->bool('is_default') ? 1 : 0], $rules, $ex, $error, 422);
        }
        $scope = $this->scope();
        $makeDefault = $this->isAdmin() && $req->bool('is_default');
        try {
            $id = (int) Db::tx(function () use ($id, $name, $tz, $rules, $ex, $makeDefault, $scope): int {
                $row = ['name' => $name, 'timezone' => $tz];
                if ($id === null) {
                    $row['is_default'] = 0;
                    $row['created_at'] = Clock::utc();
                    $id = Db::insert('schedules', $row);
                    if ($scope) {
                        Db::update('hosts', ['schedule_id' => $id], 'id = ?', [$scope]);
                    }
                } else {
                    Db::update('schedules', $row, 'id = ?', [$id]);
                }
                if ($makeDefault) {
                    Db::exec('UPDATE schedules SET is_default = 0');
                    Db::update('schedules', ['is_default' => 1], 'id = ?', [$id]);
                }
                Db::delete('schedule_rules', 'schedule_id = ?', [$id]);
                foreach ($rules as $d => $blocks) {
                    foreach ($blocks as $b) {
                        Db::insert('schedule_rules', ['schedule_id' => $id, 'weekday' => $d, 'start_time' => $b['start'] . ':00', 'end_time' => $b['end'] . ':00']);
                    }
                }
                Db::delete('schedule_overrides', 'schedule_id = ?', [$id]);
                foreach ($ex as $o) {
                    if (!$o['open']) {
                        Db::insert('schedule_overrides', ['schedule_id' => $id, 'date' => $o['date'], 'is_open' => 0, 'start_time' => null, 'end_time' => null, 'note' => $o['note'] ?: null]);
                        continue;
                    }
                    foreach ($o['blocks'] as $b) {
                        Db::insert('schedule_overrides', ['schedule_id' => $id, 'date' => $o['date'], 'is_open' => 1, 'start_time' => $b['start'] . ':00', 'end_time' => $b['end'] . ':00', 'note' => $o['note'] ?: null]);
                    }
                }
                return $id;
            });
        } catch (\Throwable $e) {
            Logger::error('No se pudo guardar el horario', $e);
            return $this->form(['id' => $id, 'name' => $name, 'timezone' => $tz, 'is_default' => 0], $rules, $ex, 'No pudimos guardar el horario. Inténtalo de nuevo.', 422);
        }
        Cache::bumpAvailability();
        Auth::audit('schedules.save', 'schedule', $id, $name);
        $this->flash('success', 'Guardamos el horario «' . $name . '».');
        return $this->redirect('/admin/horarios/' . $id . '/editar');
    }

    /** @return array{0:array,1:?string} */
    private function parseRules($raw): array
    {
        $rules = [];
        $error = null;
        if (!is_array($raw)) {
            return [[], null];
        }
        foreach (self::DAYS as $d => $dayName) {
            $list = $raw[$d] ?? [];
            if (!is_array($list)) {
                continue;
            }
            [$blocks, $err] = $this->parseBlocks($list, $dayName);
            if ($blocks) {
                $rules[$d] = $blocks;
            }
            $error = $error ?? $err;
        }
        return [$rules, $error];
    }

    /** @return array{0:array,1:?string} */
    private function parseExceptions($raw): array
    {
        $out = [];
        $error = null;
        $seen = [];
        if (!is_array($raw)) {
            return [[], null];
        }
        foreach (array_slice($raw, 0, 400) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $date = trim((string) ($r['date'] ?? ''));
            if ($date === '') {
                continue;
            }
            if (!Validator::date($date)) {
                $error = $error ?? 'Una de las excepciones tiene una fecha que no es válida.';
                continue;
            }
            if (isset($seen[$date])) {
                $error = $error ?? 'La fecha ' . $this->dmy($date) . ' está repetida en las excepciones.';
                continue;
            }
            $seen[$date] = true;
            $open = !empty($r['open']);
            $blocks = [];
            if ($open) {
                [$blocks, $err] = $this->parseBlocks(is_array($r['blocks'] ?? null) ? $r['blocks'] : [], 'El ' . $this->dmy($date));
                $error = $error ?? $err;
                if (!$blocks && $err === null) {
                    $error = 'El ' . $this->dmy($date) . ' está marcado como abierto pero no tiene horas. Agrega un bloque o márcalo como cerrado.';
                }
            }
            $out[] = ['date' => $date, 'open' => $open ? 1 : 0, 'note' => Str::clean((string) ($r['note'] ?? ''), 190), 'blocks' => $blocks];
        }
        usort($out, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));
        return [$out, $error];
    }

    /** @return array{0:array,1:?string} bloques ordenados, sin traslapes */
    private function parseBlocks(array $list, string $label): array
    {
        $blocks = [];
        foreach (array_slice($list, 0, 12) as $b) {
            if (!is_array($b)) {
                continue;
            }
            $s = trim((string) ($b['start'] ?? ''));
            $e = trim((string) ($b['end'] ?? ''));
            if ($s === '' && $e === '') {
                continue;
            }
            if (!Validator::time($s) || !Validator::time($e)) {
                return [$blocks, $label . ': hay una hora que no es válida.'];
            }
            $s = substr($s, 0, 5);
            $e = substr($e, 0, 5);
            if ($s >= $e) {
                return [$blocks, $label . ': la hora de fin debe ser posterior a la de inicio (' . $s . ' a ' . $e . ').'];
            }
            $blocks[] = ['start' => $s, 'end' => $e];
        }
        usort($blocks, static fn (array $a, array $b): int => strcmp($a['start'], $b['start']));
        for ($i = 1; $i < count($blocks); $i++) {
            if ($blocks[$i]['start'] < $blocks[$i - 1]['end']) {
                return [$blocks, $label . ': los bloques ' . $blocks[$i - 1]['start'] . '–' . $blocks[$i - 1]['end'] . ' y ' . $blocks[$i]['start'] . '–' . $blocks[$i]['end'] . ' se traslapan.'];
            }
        }
        return [$blocks, null];
    }

    private function dmy(string $d): string
    {
        return substr($d, 8, 2) . '/' . substr($d, 5, 2) . '/' . substr($d, 0, 4);
    }

    private function form(array $s, array $rules, array $ex, ?string $error, int $status = 200): Response
    {
        $id = $s['id'] ?? null;
        return $this->page('admin/schedules/form', [
            's' => $s, 'id' => $id, 'rules' => $rules, 'ex' => $ex, 'error' => $error,
            'days' => self::DAYS, 'is_admin' => $this->isAdmin(),
            'in_use' => $id ? [
                'hosts' => (int) Db::val('SELECT COUNT(*) FROM hosts WHERE schedule_id = ?', [$id]),
                'events' => (int) Db::val('SELECT COUNT(*) FROM event_types WHERE schedule_id = ?', [$id]),
            ] : ['hosts' => 0, 'events' => 0],
        ], '/admin/horarios', $id ? 'Editar horario' : 'Nuevo horario', self::SCRIPTS, $status);
    }
}
