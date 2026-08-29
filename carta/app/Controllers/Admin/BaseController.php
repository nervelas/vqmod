<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Auth;
use MenuGold\Core\Controller;
use MenuGold\Core\DB;
use MenuGold\Core\Session;
use MenuGold\Core\View;

/** Base de todo el panel: sesión, permisos y datos compartidos con las vistas. */
abstract class BaseController extends Controller
{
    /** @var array|null */
    protected $user = null;
    /** Permiso necesario para entrar a este controlador. */
    protected $ability = '';

    /**
     * Comprueba sesión y permiso.
     * @return \MenuGold\Core\Response|null respuesta de corte, o null si todo está bien
     */
    protected function guard($ability = null)
    {
        $this->user = Auth::user();
        if (!$this->user) {
            Session::set('after_login', $this->request->path);
            return $this->redirect('/panel/entrar');
        }
        $ability = $ability !== null ? $ability : $this->ability;
        if ($ability !== '' && !Auth::can($ability)) {
            return $this->denied('Tu usuario no tiene acceso a esta sección.');
        }
        $this->shareLayout();
        return null;
    }

    private function shareLayout()
    {
        View::share('auth_user', $this->user);
        View::share('nav_active', $this->request->path);
        View::share('badge_orders', (int)DB::value(
            "SELECT COUNT(*) FROM mg_orders WHERE status IN ('new','cooking')", array(), 0));
        View::share('badge_calls', (int)DB::value(
            "SELECT COUNT(*) FROM mg_service_calls WHERE status = 'open'", array(), 0));
    }

    protected function uid()
    {
        return (int)$this->user['id'];
    }

    protected function row($table, $id)
    {
        return DB::first('SELECT * FROM `' . $table . '` WHERE id = :id LIMIT 1', array('id' => (int)$id));
    }
}
