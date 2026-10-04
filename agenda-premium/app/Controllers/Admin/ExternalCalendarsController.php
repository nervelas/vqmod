<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Db;
use App\Core\Fmt;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Services\ExternalCalendarService;

/** Calendarios externos (enlaces ICS privados): se leen para bloquear horarios ocupados. */
final class ExternalCalendarsController extends A2Controller
{
    private const SCRIPTS = ['js/admin-schedules.js'];
    private const MAX_PER_HOST = 10;

    public function index(Request $req, array $p): Response
    {
        $scope = $this->scope();
        if ($scope === 0) {
            return $this->page('admin/calendars/index', ['groups' => [], 'hosts' => [], 'unlinked' => true, 'failing' => 0, 'feed' => []], '/admin/calendarios', 'Calendarios externos', self::SCRIPTS);
        }
        $hosts = $scope === null
            ? Db::all('SELECT id, name, timezone, ics_token FROM hosts WHERE active = 1 ORDER BY sort_order, name')
            : Db::all('SELECT id, name, timezone, ics_token FROM hosts WHERE id = ?', [$scope]);
        $tzBiz = Settings::tz();
        $groups = [];
        $failing = 0;
        foreach ($hosts as $h) {
            $cals = Db::all('SELECT * FROM external_calendars WHERE host_id = ? ORDER BY name, id', [$h['id']]);
            foreach ($cals as &$c) {
                $c['masked'] = $this->mask((string) $c['url']);
                $tz = (string) $h['timezone'] ?: $tzBiz;
                $c['last_ok'] = $c['last_ok_at'] ? Fmt::dateTime((string) $c['last_ok_at'], $tz) : '';
                $c['last_try'] = $c['last_fetch_at'] ? Fmt::dateTime((string) $c['last_fetch_at'], $tz) : '';
                $c['failed'] = $c['last_status'] !== null && $c['last_status'] !== 'ok';
                if ($c['failed'] && (int) $c['active'] === 1) {
                    $failing++;
                }
            }
            unset($c);
            $groups[] = ['host' => $h, 'calendars' => $cals, 'feed' => abs_url('/ics/' . $h['ics_token'] . '.ics')];
        }
        return $this->page('admin/calendars/index', ['groups' => $groups, 'hosts' => $hosts, 'unlinked' => false, 'failing' => $failing, 'max' => self::MAX_PER_HOST], '/admin/calendarios', 'Calendarios externos', self::SCRIPTS);
    }

    public function save(Request $req, array $p): Response
    {
        $scope = $this->scope();
        $hostId = $scope ?? self::intOrNull($req, 'host_id');
        if ($hostId === null || $hostId === 0 || !Auth::canAccessHost($hostId) || !Db::val('SELECT id FROM hosts WHERE id = ?', [$hostId])) {
            return $this->done($req, 'error', 'Elige a qué anfitrión pertenece el calendario.', '/admin/calendarios');
        }
        $name = $req->str('name', 120);
        $url = ExternalCalendarService::normalizeUrl($req->str('url', 2000));
        if ($name === '') {
            return $this->done($req, 'error', 'Ponle un nombre al calendario, por ejemplo «Calendario personal».', '/admin/calendarios');
        }
        if (!preg_match('~^https?://[^\s]+$~i', $url)) {
            return $this->done($req, 'error', 'El enlace debe empezar con https:// (o webcal://). Copia la dirección secreta en formato iCal.', '/admin/calendarios');
        }
        if ((int) Db::val('SELECT COUNT(*) FROM external_calendars WHERE host_id = ?', [$hostId]) >= self::MAX_PER_HOST) {
            return $this->done($req, 'error', 'Ya llegaste al máximo de ' . self::MAX_PER_HOST . ' calendarios para este anfitrión.', '/admin/calendarios');
        }
        $id = Db::insert('external_calendars', ['host_id' => $hostId, 'name' => $name, 'url' => $url, 'active' => 1, 'next_fetch_at' => null]);
        Auth::audit('calendars.create', 'external_calendar', $id, $name);
        $r = ExternalCalendarService::sync($id);
        Cache::bumpAvailability();
        if ($r['ok']) {
            return $this->done($req, 'success', 'Conectamos «' . $name . '». Sus eventos ya bloquean horarios.', '/admin/calendarios');
        }
        return $this->done($req, 'warn', 'Guardamos «' . $name . '», pero no pudimos leerlo todavía: ' . ($r['error'] ?? 'error desconocido') . ' Revisa el enlace y pulsa «Sincronizar».', '/admin/calendarios');
    }

    public function test(Request $req, array $p): Response
    {
        if (!RateLimiter::hit('a2cal-test:' . (int) (Auth::user()['id'] ?? 0), 15, 60)) {
            return $this->json(['ok' => false, 'error' => 'Demasiadas pruebas seguidas. Espera un minuto.'], 429);
        }
        $url = $req->str('url', 2000);
        if ($url === '') {
            return $this->json(['ok' => false, 'error' => 'Pega primero el enlace del calendario.'], 422);
        }
        $r = ExternalCalendarService::testUrl($url);
        if (!$r['ok']) {
            return $this->json(['ok' => false, 'error' => (string) ($r['error'] ?: 'No pudimos leer ese enlace.')], 422);
        }
        $n = (int) $r['events'];
        return $this->json(['ok' => true, 'message' => 'El enlace funciona. Encontramos ' . $n . ($n === 1 ? ' evento' : ' eventos') . ' en los próximos meses.', 'events' => $n]);
    }

    public function sync(Request $req, array $p): Response
    {
        $c = $this->mine((int) $p['id']);
        $r = ExternalCalendarService::sync((int) $c['id']);
        Cache::bumpAvailability();
        if ($r['ok']) {
            return $this->done($req, 'success', 'Sincronizamos «' . $c['name'] . '».', '/admin/calendarios');
        }
        return $this->done($req, 'error', 'No pudimos sincronizar «' . $c['name'] . '»: ' . ($r['error'] ?? 'error desconocido'), '/admin/calendarios');
    }

    public function toggle(Request $req, array $p): Response
    {
        $c = $this->mine((int) $p['id']);
        $new = (int) $c['active'] === 1 ? 0 : 1;
        Db::update('external_calendars', ['active' => $new, 'next_fetch_at' => null], 'id = ?', [(int) $c['id']]);
        if ($new === 0) {
            Db::delete('external_busy', 'calendar_id = ?', [(int) $c['id']]);
        }
        Cache::bumpAvailability();
        Auth::audit($new ? 'calendars.resume' : 'calendars.pause', 'external_calendar', $c['id'], (string) $c['name']);
        if ($new === 1) {
            ExternalCalendarService::sync((int) $c['id']);
            Cache::bumpAvailability();
        }
        return $this->done($req, 'success', $new ? 'Reactivamos «' . $c['name'] . '».' : 'Pausamos «' . $c['name'] . '»: sus eventos ya no bloquean horarios.', '/admin/calendarios');
    }

    public function delete(Request $req, array $p): Response
    {
        $c = $this->mine((int) $p['id']);
        Db::delete('external_calendars', 'id = ?', [(int) $c['id']]);
        Cache::bumpAvailability();
        Auth::audit('calendars.delete', 'external_calendar', $c['id'], (string) $c['name']);
        return $this->done($req, 'success', 'Quitamos «' . $c['name'] . '». Sus eventos ya no bloquean horarios.', '/admin/calendarios');
    }

    /** Calendario del que el usuario es dueño; si es de otro anfitrión responde 403 (IDOR). */
    private function mine(int $id): array
    {
        $c = Db::one('SELECT * FROM external_calendars WHERE id = ?', [$id]);
        if (!$c) {
            $this->abort(404);
        }
        if (!Auth::canAccessHost((int) $c['host_id'])) {
            $this->abort(403);
        }
        return $c;
    }

    /** El enlace es un secreto: en pantalla solo se muestra el dominio. */
    private function mask(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        return $host !== '' ? Str::truncate($host, 40) . '/••••••••' : 'Enlace guardado';
    }
}
