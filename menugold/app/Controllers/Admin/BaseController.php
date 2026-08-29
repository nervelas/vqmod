<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Auth;
use MenuGold\Core\Controller;
use MenuGold\Core\DB;
use MenuGold\Core\Money;
use MenuGold\Core\Response;
use MenuGold\Core\Session;
use MenuGold\Core\Url;
use MenuGold\Core\View;
use MenuGold\Models\Restaurant;

/** Base de todo el panel: sesión, permisos y datos compartidos con las vistas. */
abstract class BaseController extends Controller
{
    /** @var array|null restaurante activo */
    protected $restaurant = null;
    /** @var array|null */
    protected $user = null;
    /** Permiso necesario para entrar a este controlador. */
    protected $ability = '';

    /**
     * Comprueba sesión, permiso y restaurante activo.
     * @return Response|null respuesta de corte, o null si todo está bien
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

        $rid = Auth::restaurantId();
        if ($rid <= 0) {
            if (Auth::isSuper()) {
                return $this->redirect('/super/restaurantes');
            }
            return $this->denied('Tu usuario no está asignado a ningún restaurante.');
        }
        $this->restaurant = Restaurant::find($rid);
        if (!$this->restaurant) {
            Auth::logout();
            return $this->redirect('/panel/entrar');
        }
        if ($this->restaurant['status'] === 'suspended' && !Auth::isSuper()) {
            return $this->view('admin/suspended', array('restaurant' => $this->restaurant), 403);
        }

        Money::setCurrency($this->restaurant['currency']);
        date_default_timezone_set($this->restaurant['timezone']);
        $this->shareLayout();
        return null;
    }

    private function shareLayout()
    {
        $rid = (int)$this->restaurant['id'];
        View::share('auth_user', $this->user);
        View::share('restaurant', $this->restaurant);
        View::share('nav_active', $this->request->path);
        View::share('badge_orders', (int)DB::value(
            "SELECT COUNT(*) FROM orders WHERE restaurant_id = :r AND status IN ('new','preparing')",
            array('r' => $rid), 0));
        View::share('badge_calls', (int)DB::value(
            "SELECT COUNT(*) FROM service_calls WHERE restaurant_id = :r AND status = 'open'",
            array('r' => $rid), 0));
        View::share('flashes', Session::flashAll());
        View::share('impersonating', Auth::isSuper() && Session::get('impersonate_restaurant'));
    }

    protected function rid()
    {
        return (int)$this->restaurant['id'];
    }

    /** Consulta acotada al restaurante activo: aislamiento estricto. */
    protected function own($table, $id)
    {
        return DB::first('SELECT * FROM `' . $table . '` WHERE id = :id AND restaurant_id = :r LIMIT 1',
            array('id' => (int)$id, 'r' => $this->rid()));
    }
}
