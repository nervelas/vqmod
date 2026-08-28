<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Panel;

use MenuGold\Core\App;
use MenuGold\Core\Auth;
use MenuGold\Core\Controller;
use MenuGold\Core\DB;
use MenuGold\Core\HttpException;
use MenuGold\Core\View;
use MenuGold\Models\Plan;
use MenuGold\Models\Restaurant;

/**
 * Base de todos los controladores del panel del restaurante.
 * Garantiza el contexto de restaurante y comparte datos comunes.
 */
abstract class Base extends Controller
{
    protected array $r = [];
    protected int $rid = 0;

    public function __construct()
    {
        $this->r = App::restaurant() ?? [];
        $this->rid = (int)($this->r['id'] ?? 0);
        if ($this->rid <= 0) {
            throw HttpException::forbidden('No hay un restaurante activo en tu sesión.');
        }
    }

    /** Renderiza una vista del panel con los datos comunes. */
    protected function panel(string $vista, array $datos = []): void
    {
        View::share('usuario', Auth::user());
        View::share('restaurante', $this->r);
        View::share('flashes', flash());
        View::share('pendientes', $this->pendientes());
        View::display($vista, $datos + ['r' => $this->r, 'rid' => $this->rid], 'panel');
    }

    /** Contadores para las insignias del menú. */
    protected function pendientes(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        try {
            $cache = [
                'pedidos'  => DB::int("SELECT COUNT(*) FROM orders WHERE restaurant_id=:r AND estado IN ('nuevo','preparando')", ['r' => $this->rid]),
                'llamadas' => DB::int("SELECT COUNT(*) FROM waiter_calls WHERE restaurant_id=:r AND estado='pendiente'", ['r' => $this->rid]),
            ];
        } catch (\Throwable $e) {
            $cache = ['pedidos' => 0, 'llamadas' => 0];
        }
        return $cache;
    }

    /** Exige un permiso concreto o corta la petición. */
    protected function exigir(string $permiso): void
    {
        if (!Auth::can($permiso)) {
            if (\MenuGold\Core\Request::isAjax()) $this->fail('No tienes permiso para esta acción.', 403);
            throw HttpException::forbidden();
        }
    }

    /** Límites del plan contratado. */
    protected function limites(): array
    {
        return (new Plan())->limites(!empty($this->r['plan_id']) ? (int)$this->r['plan_id'] : null);
    }

    /**
     * Comprueba si se puede crear un registro más según el plan.
     * @return array{0:bool,1:string}
     */
    protected function cabeEnPlan(string $que): array
    {
        $lim = $this->limites();
        $uso = (new Restaurant())->uso($this->rid);
        $mapa = [
            'productos' => ['max_productos', 'platillos'],
            'mesas'     => ['max_mesas', 'mesas'],
            'usuarios'  => ['max_usuarios', 'usuarios'],
        ];
        if (!isset($mapa[$que])) return [true, ''];
        [$clave, $etiqueta] = $mapa[$que];
        $max = (int)$lim[$clave];
        if ($max <= 0) return [true, ''];
        if ((int)$uso[$que] >= $max) {
            return [false, 'Tu plan permite hasta ' . $max . ' ' . $etiqueta . '. Contacta a soporte para ampliarlo.'];
        }
        return [true, ''];
    }

    /** Paginación sencilla. */
    protected function paginar(int $total, int $porPagina = 40): array
    {
        $pagina = max(1, (int)($_GET['pag'] ?? 1));
        $paginas = max(1, (int)ceil($total / max(1, $porPagina)));
        if ($pagina > $paginas) $pagina = $paginas;
        return [
            'pagina'  => $pagina,
            'paginas' => $paginas,
            'offset'  => ($pagina - 1) * $porPagina,
            'por'     => $porPagina,
            'total'   => $total,
        ];
    }
}
