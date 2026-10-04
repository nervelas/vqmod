<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Clock;
use App\Core\Controller;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Tz;
use App\Core\Validator;

/** Base común de los controladores del panel (Admin3): render, alcance por anfitrión y utilidades de formularios. */
abstract class A3Controller extends Controller
{
    protected function page(string $tpl, array $data, string $active, string $title, array $scripts = []): Response
    {
        $data['title'] = $title;
        $data['active'] = $active;
        $data['scripts'] = array_merge(['js/admin-p3.js'], $scripts);
        return $this->view($tpl, $data, 'layouts/admin');
    }

    protected function userId(): ?int
    {
        $u = Auth::user();
        return $u ? (int) $u['id'] : null;
    }

    /** null = ve todo; int = solo ese anfitrión. */
    protected function scope(): ?int
    {
        return Auth::scopedHostId();
    }

    protected function svc(string $name): bool
    {
        return class_exists('App\\Services\\' . $name);
    }

    protected function bizTz(): string
    {
        return Settings::tz();
    }

    /** Fecha local de hoy (zona del negocio). */
    protected function today(): string
    {
        return Tz::formatTs(Clock::now(), $this->bizTz(), 'Y-m-d');
    }

    /** Inicio y fin UTC de un rango de fechas locales (inclusivo). */
    protected function rangeUtc(string $from, string $to): array
    {
        $tz = $this->bizTz();
        return [Tz::localToUtc($from . ' 00:00:00', $tz), Tz::localToUtc($to . ' 23:59:59', $tz)];
    }

    protected function dateParam(Request $req, string $key, string $default): string
    {
        $v = $req->str($key, 10);
        return Validator::date($v) ? $v : $default;
    }

    protected function money(Request $req, string $key): ?string
    {
        return Validator::money($req->str($key, 20));
    }

    protected function hosts(): array
    {
        $s = $this->scope();
        if ($s !== null) {
            return Db::all('SELECT id, name FROM hosts WHERE id = ? ORDER BY name', [$s]);
        }
        return Db::all('SELECT id, name FROM hosts WHERE active = 1 ORDER BY sort_order, name');
    }

    protected function events(): array
    {
        return Db::all('SELECT id, name, default_duration FROM event_types ORDER BY active DESC, sort_order, name');
    }

    /** Carga una cita y verifica el alcance del anfitrión (IDOR). */
    protected function bookingOr404(int $id): array
    {
        $b = Db::one('SELECT * FROM bookings WHERE id = ?', [$id]);
        if (!$b) {
            throw new HttpException(404);
        }
        $s = $this->scope();
        if ($s !== null && $s !== (int) $b['host_id']) {
            throw new HttpException(404);
        }
        return $b;
    }

    protected function back(Request $req, string $fallback): Response
    {
        return $this->redirect($fallback);
    }

    /** Valida y normaliza texto de zona horaria. */
    protected function tzParam(Request $req, string $key): string
    {
        return Tz::safe($req->str($key, 64), $this->bizTz());
    }

    protected function audit(string $action, string $entity, $id, ?string $detail = null): void
    {
        Auth::audit($action, $entity, $id, $detail);
    }
}
