<?php
declare(strict_types=1);

namespace MenuGold\Controllers;

use MenuGold\Core\App;
use MenuGold\Core\Controller;
use MenuGold\Core\DB;
use MenuGold\Core\HttpException;
use MenuGold\Core\Lang;
use MenuGold\Core\Security;
use MenuGold\Core\Setting;
use MenuGold\Core\View;
use MenuGold\Models\Category;
use MenuGold\Models\DeliveryZone;
use MenuGold\Models\Order;
use MenuGold\Models\Product;
use MenuGold\Models\Promotion;
use MenuGold\Models\Restaurant;
use MenuGold\Models\RestaurantTable;

/**
 * Menu publico del cliente: la vitrina del restaurante.
 */
class Menu extends Controller
{
    /** Resuelve el restaurante del contexto (por slug o por dominio). */
    public static function resolver(?string $slug): array
    {
        $m = new Restaurant();
        $r = null;
        if ($slug !== null && $slug !== '') {
            $r = $m->bySlug($slug);
        }
        if (!$r) $r = App::restaurantByDomain();
        if (!$r) throw HttpException::notFound('No encontramos ese restaurante.');
        if ($r['estado'] === 'suspendido') {
            throw HttpException::forbidden('Este menú no está disponible por el momento.');
        }
        if ($m->vencido($r)) {
            throw HttpException::forbidden('Este menú no está disponible por el momento.');
        }
        App::setRestaurant($r);
        if (in_array((string)$r['idioma'], ['es', 'en'], true) && empty($_GET['lang']) && empty($_SESSION['_lang'])) {
            Lang::set((string)$r['idioma']);
        }
        if (!empty($_GET['lang'])) Lang::set((string)$_GET['lang']);
        return $r;
    }

    public function porDominio(array $p = []): void
    {
        $this->index(['slug' => null, 'mesa' => $p['mesa'] ?? null]);
    }

    public function index(array $p = []): void
    {
        $r = self::resolver($p['slug'] ?? null);
        $rid = (int)$r['id'];

        // --- Mesa (si se escaneó el QR de una mesa) ---
        $mesa = null;
        $mesaValida = false;
        $nombreMesa = trim((string)($p['mesa'] ?? ''));
        if ($nombreMesa !== '') {
            $mt = new RestaurantTable();
            $mesa = $mt->forRestaurant($rid)->first('nombre = :n AND activo = 1', ['n' => $nombreMesa]);
            if (!$mesa && ctype_digit($nombreMesa)) {
                $mesa = $mt->forRestaurant($rid)->first('nombre = :n AND activo = 1', ['n' => 'Mesa ' . $nombreMesa]);
            }
            if ($mesa) {
                $token = (string)($_GET['t'] ?? '');
                $mesaValida = $token !== '' && Security::verifyTableToken($rid, (int)$mesa['id'], $token);
                if ($mesaValida) {
                    $_SESSION['mesa_' . $rid] = ['id' => (int)$mesa['id'], 'nombre' => (string)$mesa['nombre'], 'ts' => time()];
                }
            }
        }
        // Mesa recordada en la sesión (el cliente navegó dentro del menú)
        if (!$mesaValida && !empty($_SESSION['mesa_' . $rid])) {
            $g = $_SESSION['mesa_' . $rid];
            if (time() - (int)$g['ts'] < 10800) {  // 3 horas
                $mesa = (new RestaurantTable())->forRestaurant($rid)->find((int)$g['id']);
                $mesaValida = (bool)$mesa;
            } else {
                unset($_SESSION['mesa_' . $rid]);
            }
        }

        // --- Menú ---
        $catModel  = (new Category())->forRestaurant($rid);
        $prodModel = (new Product())->forRestaurant($rid);
        $categorias = $catModel->disponibles();
        $productos  = $prodModel->menuPublico($rid);

        $porCategoria = [];
        foreach ($productos as $pr) {
            if (!Product::disponibleAhora($pr)) continue;
            $porCategoria[(int)$pr['category_id']][] = $pr;
        }
        // Solo categorías con platillos
        $categorias = array_values(array_filter($categorias, static fn($c) => !empty($porCategoria[(int)$c['id']])));

        $destacados = [];
        foreach ($productos as $pr) {
            if ((int)$pr['destacado'] === 1 && (int)$pr['agotado'] === 0 && Product::disponibleAhora($pr)) {
                $destacados[] = $pr;
            }
            if (count($destacados) >= 8) break;
        }

        $promociones = (new Promotion())->forRestaurant($rid)->vigentes();
        $apertura    = (new Restaurant())->estadoApertura($r);
        $zonas       = (new DeliveryZone())->forRestaurant($rid)->activas();

        // Productos con modificadores (para saber si abrir la ficha con opciones)
        $conMods = DB::column(
            'SELECT DISTINCT pm.product_id FROM product_modifiers pm
             INNER JOIN products p ON p.id = pm.product_id
             WHERE p.restaurant_id = :r', ['r' => $rid]
        );

        // Pedidos activos de este visitante
        $token = Order::tokenCliente();
        $misPedidos = (new Order())->forRestaurant($rid)->porToken($token, $rid);
        $activo = null;
        foreach ($misPedidos as $o) {
            if (in_array($o['estado'], Order::ABIERTOS, true)) { $activo = $o; break; }
        }

        View::share('lang', Lang::current());
        $this->view('menu/index', [
            'r'            => $r,
            'mesa'         => $mesaValida ? $mesa : null,
            'categorias'   => $categorias,
            'porCategoria' => $porCategoria,
            'destacados'   => $destacados,
            'promociones'  => $promociones,
            'apertura'     => $apertura,
            'zonas'        => $zonas,
            'conMods'      => array_map('intval', $conMods),
            'pedidoActivo' => $activo,
            'modos'        => Restaurant::modos($r),
            'propinas'     => Restaurant::propinas($r),
        ], 'menu');
    }

    public function seguimientoDominio(array $p = []): void
    {
        $this->seguimiento(['slug' => null, 'codigo' => $p['codigo'] ?? '']);
    }

    public function seguimiento(array $p = []): void
    {
        $r = self::resolver($p['slug'] ?? null);
        $rid = (int)$r['id'];
        $codigo = trim((string)($p['codigo'] ?? ''));

        $om = (new Order())->forRestaurant($rid);
        $pedido = $om->first('codigo = :c', ['c' => $codigo]);
        if (!$pedido) throw HttpException::notFound('No encontramos ese pedido.');

        // Solo el dueño de la sesión (o quien tiene el código exacto) puede verlo
        $pedido['items'] = $om->lineas((int)$pedido['id']);
        $eventos = $om->timeline((int)$pedido['id']);

        $this->view('menu/seguimiento', [
            'r'       => $r,
            'pedido'  => $pedido,
            'eventos' => $eventos,
            'esMio'   => hash_equals((string)$pedido['session_token'], Order::tokenCliente()),
        ], 'menu');
    }

    public function gracias(array $p = []): void
    {
        $r = self::resolver($p['slug'] ?? null);
        $rid = (int)$r['id'];
        $om = (new Order())->forRestaurant($rid);
        $pedido = $om->first('codigo = :c', ['c' => trim((string)($p['codigo'] ?? ''))]);
        if (!$pedido) throw HttpException::notFound('No encontramos ese pedido.');
        $pedido['items'] = $om->lineas((int)$pedido['id']);

        $this->view('menu/gracias', [
            'r'      => $r,
            'pedido' => $pedido,
            'resena' => (string)$r['google_reviews'],
        ], 'menu');
    }
}
