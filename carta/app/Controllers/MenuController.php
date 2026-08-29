<?php
namespace MenuGold\Controllers;

use MenuGold\Core\Controller;
use MenuGold\Core\DB;
use MenuGold\Core\Lang;
use MenuGold\Core\Money;
use MenuGold\Core\RateLimiter;
use MenuGold\Core\Session;
use MenuGold\Core\Url;
use MenuGold\Models\Coupon;
use MenuGold\Models\Menu;
use MenuGold\Models\Order;
use MenuGold\Models\Settings;
use MenuGold\Models\TableModel;

/** Menú del comensal. Vive en la raíz del dominio. */
class MenuController extends Controller
{
    /** @var array|null */
    private $table = null;
    /** @var bool el enlace de mesa venía sin firma válida */
    private $badTableLink = false;

    /** Recupera la mesa del enlace firmado o de la sesión. */
    private function resolveTable(array $params)
    {
        if (!empty($params['token'])) {
            $t = TableModel::findByToken($params['token']);
            // El enlace va firmado con HMAC: sin firma válida no se abre mesa.
            if ($t && (int)$t['is_active'] === 1 && TableModel::verify($t, (string)$this->request->query('k', ''))) {
                $this->table = $t;
                Session::set('table_id', (int)$t['id']);
            } else {
                Session::forget('table_id');
                $this->badTableLink = true;
            }
        } elseif (Session::has('table_id')) {
            $t = TableModel::find((int)Session::get('table_id', 0));
            if ($t && (int)$t['is_active'] === 1) { $this->table = $t; }
        }
    }

    private function tableId()
    {
        return $this->table ? (int)$this->table['id'] : (int)Session::get('table_id', 0);
    }

    public function index(array $params = array())
    {
        $this->resolveTable($params);
        $zones = DB::all('SELECT id, name, fee, min_total, minutes FROM mg_delivery_zones WHERE is_active = 1 ORDER BY sort, id');

        return $this->view('menu/show', array(
            'categories'   => Menu::tree(),
            'table'        => $this->table,
            'state'        => Settings::openNow(),
            'zones'        => $zones,
            'modes'        => Settings::modes(),
            'langs'        => Settings::list('langs'),
            'badTableLink' => $this->badTableLink,
        ));
    }

    /** Mismo menú, entrando por el QR de una mesa. */
    public function table(array $params)
    {
        return $this->index($params);
    }

    public function product(array $params)
    {
        $this->resolveTable(array());
        $p = Menu::product((int)$params['id']);
        if (!$p) {
            return $this->request->wantsJson()
                ? $this->fail('Ese platillo ya no está disponible.', 404)
                : $this->notFound('Ese platillo ya no está disponible.');
        }
        if ($this->request->wantsJson()) {
            return $this->ok(array('product' => $this->productPayload($p)));
        }
        // Sin JavaScript la ficha se abre como página completa.
        return $this->view('menu/product', array('product' => $p, 'table' => $this->table));
    }

    /** Estructura que recibe el navegador para pintar la ficha. */
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
                'id' => (int)$v['id'], 'name' => $v['name'],
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
        $term = $this->request->str('q', '');
        $rows = $term === '' ? array() : Menu::search($term);
        $out = array();
        foreach ($rows as $r) {
            $out[] = array('id' => (int)$r['id'], 'name' => Lang::field($r, 'name'), 'price' => (float)$r['price']);
        }
        return $this->ok(array('results' => $out));
    }

    public function language(array $params)
    {
        $lang = isset($params['lang']) ? $params['lang'] : 'es';
        if (in_array($lang, Settings::list('langs'), true)) { Session::set('lang', $lang); }
        return $this->back('/');
    }

    /** Cotización en vivo del carrito, sin crear el pedido. */
    public function quote(array $params)
    {
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $priced = Order::priceCart($this->request->arr('items'));
        $totals = Order::totals($priced['subtotal'], array(
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
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $priced = Order::priceCart($this->request->arr('items'));
        $res = Coupon::validate($this->request->str('code', ''), $priced['subtotal']);
        if (!$res['ok']) { return $this->fail($res['error']); }

        $c = $res['coupon'];
        $msg = $c['type'] === 'percent'
            ? 'Cupón aplicado: ' . rtrim(rtrim(number_format((float)$c['value'], 2, '.', ''), '0'), '.') . '% menos.'
            : 'Cupón aplicado: ' . Money::format($c['value']) . ' menos.';
        return $this->ok(array('message' => $msg));
    }

    /** Crea el pedido. */
    public function place(array $params)
    {
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        if (!Settings::takesOrders()) {
            return $this->fail('En este momento el menú es solo de consulta.');
        }
        if (!RateLimiter::attempt('order', 8, 300)) {
            return $this->fail('Recibimos varios pedidos desde este dispositivo. Espera un momento.', 429);
        }

        $mode = $this->request->str('mode', 'dine_in');
        $tableId = 0;
        if ($mode === 'dine_in') {
            $this->resolveTable(array());
            $tableId = $this->tableId();
            if (!$tableId) {
                return $this->fail('Escanea el código QR de tu mesa para pedir desde el salón.');
            }
        }

        $res = Order::place(array(
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
            'payment'     => $this->request->str('payment', ''),
        ));
        if (!$res['ok']) { return $this->fail($res['error']); }

        $order = $res['order'];
        $whatsapp = '';
        if (Settings::get('order_mode') === 'whatsapp' && Settings::get('whatsapp') !== '') {
            $whatsapp = mg_wa(Settings::get('whatsapp'), Order::whatsappText($order));
        }

        return $this->ok(array(
            'code'         => $order['code'],
            'track_url'    => Url::to('/pedido/' . $order['public_token']),
            'whatsapp_url' => $whatsapp,
        ));
    }

    /** Llamar al mesero o pedir la cuenta. */
    public function serviceCall(array $params)
    {
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $type = $this->request->str('type', 'waiter') === 'bill' ? 'bill' : 'waiter';
        $this->resolveTable(array());
        $tableId = $this->tableId();
        if (!$tableId) {
            return $this->fail('Solo puedes llamar al mesero desde el QR de tu mesa.');
        }
        if (!RateLimiter::attempt('call:' . $tableId, 4, 180)) {
            return $this->fail('Ya avisamos al mesero. Viene en camino.', 429);
        }

        $open = DB::value("SELECT id FROM mg_service_calls WHERE table_id = :t AND type = :ty AND status = 'open'",
            array('t' => $tableId, 'ty' => $type));
        if (!$open) {
            DB::insert('mg_service_calls', array(
                'table_id'   => $tableId,
                'type'       => $type,
                'status'     => 'open',
                'created_at' => date('Y-m-d H:i:s'),
            ));
        }
        if ($type === 'bill') {
            DB::update('mg_tables', array('status' => 'bill'), 'id = :id', array('id' => $tableId));
        }
        return $this->ok(array('message' => $type === 'bill' ? 'Pedimos tu cuenta. Ya viene.' : 'Avisamos al mesero. Viene en camino.'));
    }
}
