<?php
namespace MenuGold\Controllers;

use MenuGold\Core\Controller;
use MenuGold\Core\DB;
use MenuGold\Core\Lang;
use MenuGold\Core\Money;
use MenuGold\Core\RateLimiter;
use MenuGold\Core\Session;
use MenuGold\Core\Str;
use MenuGold\Core\Url;
use MenuGold\Models\Coupon;
use MenuGold\Models\Menu;
use MenuGold\Models\Order;
use MenuGold\Models\Restaurant;
use MenuGold\Models\TableModel;

/** Menú público del comensal. */
class MenuController extends Controller
{
    /** @var array|null */
    private $restaurant = null;
    /** @var array|null */
    private $table = null;
    /** @var bool el enlace de mesa venía sin firma válida */
    private $badTableLink = false;

    /** Resuelve restaurante y mesa; devuelve una respuesta de error o null. */
    private function resolve(array $params)
    {
        $this->restaurant = Restaurant::findBySlug(isset($params['slug']) ? $params['slug'] : '');
        if (!$this->restaurant) {
            return $this->notFound('Ese restaurante no existe en la plataforma.');
        }
        if (!Restaurant::isPublic($this->restaurant)) {
            return $this->view('menu/suspended', array('restaurant' => $this->restaurant), 403);
        }

        Money::setCurrency($this->restaurant['currency']);
        $langs = Restaurant::langs($this->restaurant);
        $chosen = Session::get('lang_' . $this->restaurant['slug'], $this->restaurant['lang_default']);
        Lang::setLocale(in_array($chosen, $langs, true) ? $chosen : $this->restaurant['lang_default']);
        date_default_timezone_set($this->restaurant['timezone']);

        if (!empty($params['token'])) {
            $t = TableModel::findByToken((int)$this->restaurant['id'], $params['token']);
            // El enlace de la mesa va firmado con HMAC: sin firma válida no se abre mesa.
            if ($t && TableModel::verify($t, (string)$this->request->query('k', ''))) {
                $this->table = $t;
                Session::set('table_' . $this->restaurant['slug'], (int)$t['id']);
            } else {
                Session::forget('table_' . $this->restaurant['slug']);
                $this->badTableLink = true;
            }
        } elseif (Session::has('table_' . $this->restaurant['slug'])) {
            $this->table = TableModel::find((int)$this->restaurant['id'], (int)Session::get('table_' . $this->restaurant['slug']));
        }
        return null;
    }

    public function show(array $params)
    {
        $err = $this->resolve($params);
        if ($err) { return $err; }

        $r = $this->restaurant;
        $categories = Menu::forRestaurant($r);
        $state = Restaurant::openState($r);
        $settings = Restaurant::settings((int)$r['id']);
        $zones = DB::all('SELECT id, name, fee, min_order, eta_minutes FROM delivery_zones WHERE restaurant_id = :r AND is_active = 1 ORDER BY sort, id',
            array('r' => (int)$r['id']));

        return $this->view('menu/show', array(
            'restaurant' => $r,
            'categories' => $categories,
            'table'      => $this->table,
            'state'      => $state,
            'zones'      => $zones,
            'settings'   => $settings,
            'modes'      => Restaurant::modes($r),
            'langs'      => Restaurant::langs($r),
            'badTableLink' => $this->badTableLink,
        ));
    }

    public function product(array $params)
    {
        $err = $this->resolve($params);
        if ($err) { return $err; }

        $p = Menu::product((int)$this->restaurant['id'], (int)$params['id']);
        if (!$p) {
            return $this->request->wantsJson()
                ? $this->fail('Ese platillo ya no está disponible.', 404)
                : $this->notFound('Ese platillo ya no está disponible.');
        }
        DB::run('UPDATE products SET views = views + 1 WHERE id = :id', array('id' => (int)$p['id']));

        if ($this->request->wantsJson()) {
            return $this->ok(array('product' => $this->productPayload($p)));
        }
        // Sin JavaScript se abre la ficha como página completa.
        return $this->view('menu/product', array(
            'restaurant' => $this->restaurant,
            'product'    => $p,
            'table'      => $this->table,
        ));
    }

    /** Estructura enviada al navegador para la ficha del producto. */
    private function productPayload(array $p)
    {
        $tags = array();
        foreach ($p['tags_list'] as $t) { $tags[] = mg_tag_label($t); }
        $groups = array();
        foreach ($p['groups'] as $g) {
            $opts = array();
            foreach ($g['options'] as $o) {
                $opts[] = array(
                    'id' => (int)$o['id'], 'label' => $o['label'],
                    'price_delta' => (float)$o['price_delta'], 'is_default' => (int)$o['is_default'] === 1,
                );
            }
            $groups[] = array(
                'id' => (int)$g['id'], 'label' => $g['label'], 'type' => $g['type'],
                'min_select' => (int)$g['min_select'], 'max_select' => (int)$g['max_select'],
                'is_required' => (int)$g['is_required'] === 1, 'options' => $opts,
            );
        }
        $variants = array();
        foreach ($p['variants'] as $v) {
            $variants[] = array(
                'id' => (int)$v['id'], 'name' => Lang::field($v, 'name'),
                'price_delta' => (float)$v['price_delta'], 'is_default' => (int)$v['is_default'] === 1,
            );
        }
        return array(
            'id'             => (int)$p['id'],
            'label'          => $p['label'],
            'about'          => $p['about'],
            'category_label' => $p['category_label'],
            'price'          => (float)$p['price'],
            'final_price'    => (float)$p['final_price'],
            'tags'           => $tags,
            'variants'       => $variants,
            'groups'         => $groups,
            'photo_html'     => mg_img($p['image'], array('alt' => $p['label'], 'sizes' => '(min-width: 860px) 720px, 100vw', 'loading' => 'eager')),
            'thumb_html'     => mg_img($p['image'], array('alt' => '', 'sizes' => '62px')),
        );
    }

    public function search(array $params)
    {
        $err = $this->resolve($params);
        if ($err) { return $err; }
        $term = $this->request->str('q', '');
        $rows = $term === '' ? array() : Menu::search((int)$this->restaurant['id'], $term);
        $out = array();
        foreach ($rows as $r) {
            $out[] = array('id' => (int)$r['id'], 'name' => Lang::field($r, 'name'), 'price' => (float)$r['price']);
        }
        return $this->ok(array('results' => $out));
    }

    public function language(array $params)
    {
        $err = $this->resolve($params);
        if ($err) { return $err; }
        $lang = isset($params['lang']) ? $params['lang'] : 'es';
        if (in_array($lang, Restaurant::langs($this->restaurant), true)) {
            Session::set('lang_' . $this->restaurant['slug'], $lang);
        }
        return $this->back('/r/' . $this->restaurant['slug']);
    }

    /** Cotización en vivo del carrito (sin crear el pedido). */
    public function quote(array $params)
    {
        $err = $this->resolve($params);
        if ($err) { return $err; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $priced = Order::priceCart($this->restaurant, $this->request->arr('items'));
        $totals = Order::totals($this->restaurant, $priced['subtotal'], array(
            'coupon'      => $this->request->str('coupon', ''),
            'mode'        => $this->request->str('mode', 'dine_in'),
            'zone_id'     => $this->request->int('zone_id', 0),
            'tip_percent' => $this->request->float('tip_percent', 0),
        ));
        unset($totals['zone']);
        return $this->ok(array('totals' => $totals, 'warnings' => array_values(array_unique($priced['errors']))));
    }

    public function coupon(array $params)
    {
        $err = $this->resolve($params);
        if ($err) { return $err; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $priced = Order::priceCart($this->restaurant, $this->request->arr('items'));
        $res = Coupon::validate((int)$this->restaurant['id'], $this->request->str('code', ''), $priced['subtotal']);
        if (!$res['ok']) { return $this->fail($res['error']); }

        $c = $res['coupon'];
        $msg = $c['type'] === 'percent'
            ? 'Cupón aplicado: ' . rtrim(rtrim(number_format((float)$c['value'], 2, '.', ''), '0'), '.') . '% menos.'
            : ($c['type'] === 'amount' ? 'Cupón aplicado: ' . Money::format($c['value']) . ' menos.' : 'Cupón aplicado: envío gratis.');
        return $this->ok(array('message' => $msg));
    }

    /** Crea el pedido. */
    public function place(array $params)
    {
        $err = $this->resolve($params);
        if ($err) { return $err; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        if (!RateLimiter::attempt('order:' . $this->restaurant['id'], 8, 300)) {
            return $this->fail('Recibimos varios pedidos desde este dispositivo. Espera un momento.', 429);
        }
        if (Restaurant::limitReached((int)$this->restaurant['id'], 'orders')) {
            return $this->fail('Este restaurante alcanzó el límite de pedidos de su plan.', 403);
        }

        $mode = $this->request->str('mode', 'dine_in');
        $tableId = 0;
        if ($mode === 'dine_in') {
            $tableId = $this->table ? (int)$this->table['id'] : (int)Session::get('table_' . $this->restaurant['slug'], 0);
            if (!$tableId) {
                return $this->fail('Escanea el código QR de tu mesa para pedir desde el salón.');
            }
        }

        $payload = array(
            'items'       => $this->request->arr('items'),
            'mode'        => $mode,
            'table_id'    => $tableId,
            'zone_id'     => $this->request->int('zone_id', 0),
            'coupon'      => $this->request->str('coupon', ''),
            'tip_percent' => $this->request->float('tip_percent', 0),
            'name'        => $this->request->str('name', ''),
            'phone'       => $this->request->str('phone', ''),
            'address'     => $this->request->str('address', ''),
            'notes'       => $this->request->str('notes', ''),
            'lang'        => Lang::locale(),
        );

        $res = Order::place($this->restaurant, $payload);
        if (!$res['ok']) {
            return $this->fail($res['error']);
        }

        $order = $res['order'];
        $whatsapp = '';
        if ($this->restaurant['order_mode'] === 'whatsapp' && $this->restaurant['whatsapp'] !== '') {
            $whatsapp = mg_wa($this->restaurant['whatsapp'], Order::whatsappText($this->restaurant, $order));
        }

        return $this->ok(array(
            'code'         => $order['code'],
            'track_url'    => Url::to('/pedido/' . $order['track_token']),
            'whatsapp_url' => $whatsapp,
        ));
    }

    /** Llamar al mesero o pedir la cuenta. */
    public function serviceCall(array $params)
    {
        $err = $this->resolve($params);
        if ($err) { return $err; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $type = $this->request->str('type', 'waiter') === 'bill' ? 'bill' : 'waiter';
        $tableId = $this->table ? (int)$this->table['id'] : (int)Session::get('table_' . $this->restaurant['slug'], 0);
        if (!$tableId) {
            return $this->fail('Solo puedes llamar al mesero desde el QR de tu mesa.');
        }
        if (!RateLimiter::attempt('call:' . $this->restaurant['id'] . ':' . $tableId, 4, 180)) {
            return $this->fail('Ya avisamos al mesero. Viene en camino.', 429);
        }

        $open = DB::value("SELECT id FROM service_calls WHERE restaurant_id = :r AND table_id = :t AND type = :ty AND status = 'open'",
            array('r' => (int)$this->restaurant['id'], 't' => $tableId, 'ty' => $type));
        if (!$open) {
            DB::insert('service_calls', array(
                'restaurant_id' => (int)$this->restaurant['id'],
                'table_id'      => $tableId,
                'type'          => $type,
                'status'        => 'open',
                'created_at'    => date('Y-m-d H:i:s'),
            ));
        }
        if ($type === 'bill') {
            DB::update('tables', array('status' => 'bill'), 'id = :id', array('id' => $tableId));
        }
        return $this->ok(array('message' => $type === 'bill' ? 'Pedimos tu cuenta. Ya viene.' : 'Avisamos al mesero. Viene en camino.'));
    }
}
