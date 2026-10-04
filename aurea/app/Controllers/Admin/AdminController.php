<?php
declare(strict_types=1);

namespace Aurea\Controllers\Admin;

use Aurea\Core\Audit;
use Aurea\Core\Auth;
use Aurea\Core\Controller;
use Aurea\Core\Db;
use Aurea\Core\HttpException;
use Aurea\Core\Request;
use Aurea\Core\Response;
use Aurea\Core\View;

/** Base de los controladores del panel: exige sesión y aplica permisos por rol en servidor. */
abstract class AdminController extends Controller
{
    protected array $user = [];

    public function __construct(Request $req)
    {
        parent::__construct($req);
        $u = Auth::user();
        if (!$u) {
            throw new HttpException(Response::redirect(url('/admin/login')));
        }
        $this->user = $u;
    }

    /** Exige una capacidad; si no la tiene responde 403. */
    protected function need(string $ability): void
    {
        if (!Auth::can($ability)) { $this->abort(403); }
    }

    protected function abort(int $code = 404): void
    {
        $msg = $code === 403 ? 'No tienes permiso para ver esta sección.' : 'No encontramos lo que buscas.';
        throw new HttpException(Response::html(View::page('admin', 'admin/error', ['code' => $code, 'message' => $msg, 'title' => 'Error', 'active' => '', 'user' => $this->user]), $code));
    }

    protected function render(string $tpl, array $data = [], string $active = '', string $title = ''): Response
    {
        return Response::html(View::page('admin', 'admin/' . $tpl, $data + ['active' => $active, 'title' => $title, 'user' => $this->user]));
    }

    protected function ok(string $msg): void { $_SESSION['flash'][] = ['ok', $msg]; }
    protected function fail(string $msg): void { $_SESSION['flash'][] = ['err', $msg]; }
    protected function info(string $msg): void { $_SESSION['flash'][] = ['info', $msg]; }

    protected function back(string $default = '/admin'): Response
    {
        return Response::redirect(url($default));
    }

    protected function audit(string $action, string $entity = '', ?int $id = null, string $detail = ''): void
    {
        Audit::log($action, $entity, $id, $detail, $this->user);
    }

    /** Profesional al que está limitado el usuario (null = todos). */
    protected function scopePro(): ?int
    {
        return Auth::scopeProfessional();
    }

    /** Carga una cita aplicando el alcance (IDOR): un Profesional solo accede a las suyas. */
    protected function appointment(int $id): array
    {
        $a = Db::one('SELECT a.*, c.name client_name, c.phone_cc, c.phone client_phone, c.email client_email, c.nit client_nit, c.blocked client_blocked, c.noshow_count,
            s.name service_name, s.capacity, p.name prof_name, p.color prof_color, l.name loc_name, l.address loc_address
            FROM appointments a JOIN clients c ON c.id=a.client_id JOIN services s ON s.id=a.service_id JOIN professionals p ON p.id=a.professional_id
            LEFT JOIN locations l ON l.id=a.location_id WHERE a.id=?', [$id]);
        if (!$a) { $this->abort(404); }
        $sp = $this->scopePro();
        if ($sp !== null && (int)$a['professional_id'] !== $sp) { $this->abort(404); }
        return $a;
    }

    /** ¿El usuario puede ver a este cliente? (profesional: solo quienes tienen cita con él). */
    protected function clientVisible(int $clientId): bool
    {
        $sp = $this->scopePro();
        if ($sp === null) { return (bool)Db::val('SELECT id FROM clients WHERE id=?', [$clientId]); }
        return (bool)Db::val('SELECT a.id FROM appointments a WHERE a.client_id=? AND a.professional_id=? LIMIT 1', [$clientId, $sp]);
    }

    protected function postedId(): int { return $this->req->int('id'); }

    protected function paginate(int $total, int $per = 40): array
    {
        $pages = max(1, (int)ceil($total / $per));
        $page = min($pages, max(1, $this->req->int('p', 1)));
        return ['page' => $page, 'pages' => $pages, 'per' => $per, 'offset' => ($page - 1) * $per, 'total' => $total];
    }
}
