<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Clock;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Tz;
use App\Core\Validator;
use App\Services\HolidayService;

/** Feriados de Guatemala por año: precargados y editables. */
final class HolidaysController extends A2Controller
{
    private const SCRIPTS = ['js/admin-schedules.js'];

    public function index(Request $req, array $p): Response
    {
        $year = $this->year($req->get('anio'));
        HolidayService::ensureYear($year);
        $rows = Db::all('SELECT * FROM holidays WHERE date BETWEEN ? AND ? ORDER BY date, id', [$year . '-01-01', $year . '-12-31']);
        foreach ($rows as &$r) {
            $r['dow'] = \App\Core\Fmt::dayName((int) date('w', (int) strtotime((string) $r['date'] . ' 12:00:00 UTC')));
            $r['dmy'] = substr((string) $r['date'], 8, 2) . '/' . substr((string) $r['date'], 5, 2) . '/' . substr((string) $r['date'], 0, 4);
            $r['end'] = $r['half_day_end'] ? substr((string) $r['half_day_end'], 0, 5) : '';
        }
        unset($r);
        return $this->page('admin/holidays/index', [
            'rows' => $rows, 'year' => $year,
            'enabled' => Settings::bool('holidays_enabled'),
            'capital' => Settings::bool('holidays_capital'),
            'this_year' => (int) Tz::formatTs(Clock::now(), Settings::tz(), 'Y'),
        ], '/admin/feriados', 'Feriados', self::SCRIPTS);
    }

    public function save(Request $req, array $p): Response
    {
        $id = self::intOrNull($req, 'id');
        $date = $req->str('date', 10);
        $name = $req->str('name', 120);
        $kind = $req->str('kind', 4) === 'half' ? 'half' : 'full';
        $end = $req->str('half_day_end', 5);
        $scope = $req->str('scope', 8) === 'capital' ? 'capital' : 'national';
        $back = '/admin/feriados?anio=' . $this->year(substr($date, 0, 4));
        if (!Validator::date($date) || $name === '') {
            return $this->done($req, 'error', 'Escribe el nombre del feriado y una fecha válida.', '/admin/feriados');
        }
        if ($kind === 'half' && !Validator::time($end)) {
            return $this->done($req, 'error', 'Para un medio día indica hasta qué hora se atiende (por ejemplo 12:00).', $back);
        }
        $row = ['date' => $date, 'name' => $name, 'kind' => $kind, 'half_day_end' => $kind === 'half' ? substr($end, 0, 5) . ':00' : null, 'scope' => $scope, 'active' => $req->bool('active') ? 1 : 0];
        if ((int) Db::val('SELECT COUNT(*) FROM holidays WHERE date = ? AND name = ?' . ($id ? ' AND id <> ' . (int) $id : ''), [$date, $name]) > 0) {
            return $this->done($req, 'error', 'Ya existe un feriado con ese nombre en esa fecha.', $back);
        }
        if ($id) {
            if (!Db::val('SELECT id FROM holidays WHERE id = ?', [$id])) {
                $this->abort(404);
            }
            Db::update('holidays', $row, 'id = ?', [$id]);
        } else {
            $id = Db::insert('holidays', $row + ['source' => 'manual']);
        }
        Cache::bumpAvailability();
        Auth::audit('holidays.save', 'holiday', $id, $date . ' ' . $name);
        return $this->done($req, 'success', 'Guardamos el feriado «' . $name . '».', $back);
    }

    public function toggle(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $h = Db::one('SELECT id, date, name, active FROM holidays WHERE id = ?', [$id]);
        if (!$h) {
            $this->abort(404);
        }
        $new = (int) $h['active'] === 1 ? 0 : 1;
        Db::update('holidays', ['active' => $new], 'id = ?', [$id]);
        Cache::bumpAvailability();
        Auth::audit('holidays.toggle', 'holiday', $id, $h['name'] . ($new ? ' activado' : ' desactivado'));
        return $this->done($req, 'success', $new ? '«' . $h['name'] . '» ahora cierra la agenda.' : '«' . $h['name'] . '» ya no cierra la agenda: se atiende normal.', '/admin/feriados?anio=' . substr((string) $h['date'], 0, 4));
    }

    public function delete(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $h = Db::one('SELECT id, date, name FROM holidays WHERE id = ?', [$id]);
        if (!$h) {
            $this->abort(404);
        }
        Db::delete('holidays', 'id = ?', [$id]);
        Cache::bumpAvailability();
        Auth::audit('holidays.delete', 'holiday', $id, $h['date'] . ' ' . $h['name']);
        return $this->done($req, 'success', 'Quitamos «' . $h['name'] . '» de la lista.', '/admin/feriados?anio=' . substr((string) $h['date'], 0, 4));
    }

    public function reset(Request $req, array $p): Response
    {
        $year = $this->year($req->input('anio'));
        $n = HolidayService::regenerate($year);
        Cache::bumpAvailability();
        Auth::audit('holidays.reset', 'holiday', null, 'Año ' . $year);
        return $this->done($req, 'success', 'Restablecimos los feriados de ' . $year . ' (' . $n . ' fechas). Tus feriados manuales se conservaron.', '/admin/feriados?anio=' . $year);
    }

    public function master(Request $req, array $p): Response
    {
        Settings::set('holidays_enabled', $req->bool('holidays_enabled') ? '1' : '0');
        Settings::set('holidays_capital', $req->bool('holidays_capital') ? '1' : '0');
        Cache::bumpAvailability();
        Auth::audit('holidays.master', 'settings', null, 'Feriados ' . (Settings::bool('holidays_enabled') ? 'activados' : 'desactivados'));
        return $this->done($req, 'success', Settings::bool('holidays_enabled') ? 'Los feriados cierran la agenda.' : 'Apagamos los feriados: la agenda se atiende todos los días de su horario.', '/admin/feriados' . ($req->input('anio') ? '?anio=' . $this->year($req->input('anio')) : ''));
    }

    private function year($v): int
    {
        $y = is_scalar($v) && ctype_digit((string) $v) ? (int) $v : (int) Tz::formatTs(Clock::now(), Settings::tz(), 'Y');
        return max(2000, min(2100, $y));
    }
}
