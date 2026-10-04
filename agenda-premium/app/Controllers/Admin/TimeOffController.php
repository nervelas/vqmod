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

/** Ausencias y vacaciones por anfitrión o de todo el negocio (se guardan en UTC). */
final class TimeOffController extends A2Controller
{
    private const SCRIPTS = ['js/admin-schedules.js'];

    public function index(Request $req, array $p): Response
    {
        $scope = $this->scope();
        if ($scope === 0) {
            $this->abort(403);
        }
        $hosts = $scope === null
            ? Db::all('SELECT id, name, timezone FROM hosts ORDER BY sort_order, name')
            : Db::all('SELECT id, name, timezone FROM hosts WHERE id = ?', [$scope]);
        $biz = Settings::tz();
        $params = [];
        $where = '';
        if ($scope !== null) {
            $where = 'WHERE t.host_id = ? OR t.host_id IS NULL';
            $params[] = $scope;
        }
        $rows = Db::all(
            'SELECT t.*, h.name AS host_name, h.timezone AS host_tz FROM time_off t LEFT JOIN hosts h ON h.id = t.host_id ' . $where . ' ORDER BY t.ends_at DESC LIMIT 300',
            $params
        );
        $now = Tz::fromTs(Clock::now());
        $upcoming = [];
        $past = [];
        foreach ($rows as $r) {
            $tz = Tz::safe($r['host_tz'] ?? null, $biz);
            $r['tz'] = $tz;
            $r['label'] = $this->range((string) $r['starts_at'], (string) $r['ends_at'], $tz);
            $r['can_delete'] = $r['host_id'] === null ? $scope === null : Auth::canAccessHost((int) $r['host_id']);
            if ((string) $r['ends_at'] >= $now) {
                $upcoming[] = $r;
            } else {
                $past[] = $r;
            }
        }
        usort($upcoming, static fn (array $a, array $b): int => strcmp((string) $a['starts_at'], (string) $b['starts_at']));
        return $this->page('admin/timeoff/index', [
            'hosts' => $hosts, 'upcoming' => $upcoming, 'past' => array_slice($past, 0, 20),
            'can_business' => $scope === null, 'biz_tz' => $biz, 'today' => Tz::formatTs(Clock::now(), $biz, 'Y-m-d'),
        ], '/admin/ausencias', 'Ausencias', self::SCRIPTS);
    }

    public function save(Request $req, array $p): Response
    {
        $scope = $this->scope();
        if ($scope === 0) {
            $this->abort(403);
        }
        $raw = trim((string) ($req->post['host_id'] ?? ''));
        $hostId = ctype_digit($raw) && (int) $raw > 0 ? (int) $raw : null;
        if ($scope !== null) {
            $hostId = $scope; // un anfitrión solo registra ausencias propias
        } elseif ($hostId === null && $raw !== 'business') {
            return $this->done($req, 'error', 'Elige a quién corresponde la ausencia.', '/admin/ausencias');
        }
        $tz = Settings::tz();
        if ($hostId !== null) {
            $h = Db::one('SELECT id, timezone FROM hosts WHERE id = ?', [$hostId]);
            if (!$h) {
                return $this->done($req, 'error', 'El anfitrión elegido no existe.', '/admin/ausencias');
            }
            $tz = Tz::safe((string) $h['timezone'], $tz);
        }
        $d1 = $req->str('start_date', 10);
        $d2 = $req->str('end_date', 10) ?: $d1;
        $all = $req->bool('all_day');
        $t1 = $all ? '00:00' : $req->str('start_time', 5);
        $t2 = $all ? '23:59' : $req->str('end_time', 5);
        if (!Validator::date($d1) || !Validator::date($d2) || !Validator::time($t1) || !Validator::time($t2)) {
            return $this->done($req, 'error', 'Revisa las fechas y horas: alguna no es válida.', '/admin/ausencias');
        }
        $start = Tz::localToUtc($d1 . ' ' . $t1 . ':00', $tz);
        $end = Tz::localToUtc($d2 . ' ' . $t2 . ':' . ($all ? '59' : '00'), $tz);
        if ($end <= $start) {
            return $this->done($req, 'error', 'El final de la ausencia debe ser posterior a su inicio.', '/admin/ausencias');
        }
        if (Tz::ts($end) - Tz::ts($start) > 800 * 86400) {
            return $this->done($req, 'error', 'La ausencia no puede durar más de dos años.', '/admin/ausencias');
        }
        $id = Db::insert('time_off', [
            'host_id' => $hostId, 'starts_at' => $start, 'ends_at' => $end,
            'reason' => Str::clean($req->str('reason', 190), 190) ?: null, 'created_at' => Clock::utc(),
        ]);
        Cache::bumpAvailability();
        Auth::audit('timeoff.create', 'time_off', $id, ($hostId ?? 'negocio') . ' ' . $start . ' - ' . $end);
        return $this->done($req, 'success', $hostId === null ? 'Registramos el cierre del negocio.' : 'Registramos la ausencia. No se ofrecerán horarios en ese lapso.', '/admin/ausencias');
    }

    public function delete(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $r = Db::one('SELECT id, host_id FROM time_off WHERE id = ?', [$id]);
        if (!$r) {
            $this->abort(404);
        }
        $ok = $r['host_id'] === null ? $this->scope() === null : Auth::canAccessHost((int) $r['host_id']);
        if (!$ok) {
            $this->abort(403);
        }
        Db::delete('time_off', 'id = ?', [$id]);
        Cache::bumpAvailability();
        Auth::audit('timeoff.delete', 'time_off', $id);
        return $this->done($req, 'success', 'Quitamos la ausencia. Ese tiempo vuelve a estar disponible.', '/admin/ausencias');
    }

    private function range(string $startUtc, string $endUtc, string $tz): string
    {
        $s = Tz::format($startUtc, $tz, 'Y-m-d H:i:s');
        $e = Tz::format($endUtc, $tz, 'Y-m-d H:i:s');
        $dayS = substr($s, 0, 10);
        $dayE = substr($e, 0, 10);
        $dmy = static fn (string $d): string => substr($d, 8, 2) . '/' . substr($d, 5, 2) . '/' . substr($d, 0, 4);
        $allDay = substr($s, 11) === '00:00:00' && substr($e, 11) >= '23:59:00';
        if ($allDay) {
            return $dayS === $dayE ? $dmy($dayS) . ' · todo el día' : 'Del ' . $dmy($dayS) . ' al ' . $dmy($dayE);
        }
        $hm = static fn (string $x): string => \App\Core\Fmt::time(Tz::localToUtc($x, 'UTC'), 'UTC');
        return $dayS === $dayE
            ? $dmy($dayS) . ' · ' . $hm($s) . ' a ' . $hm($e)
            : $dmy($dayS) . ' ' . $hm($s) . ' → ' . $dmy($dayE) . ' ' . $hm($e);
    }
}
