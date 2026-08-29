<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\ErrorHandler;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Security;
use App\Core\View;
use App\Models\Company;
use App\Models\Notification;
use App\Models\Setting;

abstract class Controller
{
    protected ?array $company = null;

    public function __construct()
    {
        View::share('flash', Flash::pull());
        View::share('nonce', Security::nonce());
        View::share('platformName', Setting::get('platform_name', 'CotizaPro B2B'));
        View::share('auth', Auth::user());
    }

    /** Resuelve la empresa por slug de URL o por dominio propio. */
    protected function tenant(array $params): array
    {
        $slug = (string) ($params['slug'] ?? '');
        $c = $slug !== '' ? Company::bySlug($slug) : Company::byDomain(App::host());
        if (!$c) {
            ErrorHandler::render(404);
        }
        if (!Company::isLive($c)) {
            View::render('site/suspendida', ['company' => $c], 'layout/blank');
            exit;
        }
        $this->company = $c;
        View::share('company', $c);
        View::share('theme', Company::theme($c));
        return $c;
    }

    /** Contexto del panel de empresa: sesión, empresa y notificaciones. */
    protected function panel(string ...$roles): array
    {
        $u = $roles ? Auth::requireRole(...$roles) : Auth::requireCompany();
        $c = Company::find((int) $u['company_id']);
        if (!$c) {
            Auth::logout();
            redirect('/entrar');
        }
        $this->company = $c;
        View::share('company', $c);
        View::share('theme', Company::theme($c));
        View::share('auth', $u);
        View::share('notifCount', Notification::countUnread((int) $u['id']));
        View::share('notifs', Notification::unread((int) $u['id']));
        return [$u, $c];
    }

    protected function super(): array
    {
        $u = Auth::requireSuper();
        View::share('auth', $u);
        View::share('notifCount', Notification::countUnread((int) $u['id']));
        View::share('notifs', Notification::unread((int) $u['id']));
        return $u;
    }

    protected function guardPost(): void
    {
        if (!Request::isPost()) {
            ErrorHandler::render(405);
        }
        Csrf::verify();
    }

    protected function view(string $tpl, array $data = [], string $layout = 'layout/base'): void
    {
        View::render($tpl, $data, $layout);
    }

    protected function back(string $fallback = '/'): never
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $origin = App::origin();
        if ($ref !== '' && str_starts_with($ref, $origin)) {
            header('Location: ' . $ref, true, 302);
            exit;
        }
        redirect($fallback);
    }
}
