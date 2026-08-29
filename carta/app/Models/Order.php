<?php
namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Logger;
use MenuGold\Core\Money;
use MenuGold\Core\Security;

/**
 * Pedidos.
 *
 * Los precios y totales SIEMPRE se recalculan aquí, contra la base de datos.
 * Lo que manda el navegador sirve para saber QUÉ se pidió, nunca CUÁNTO cuesta.
 */
final class Order
{
    public static $statuses = array('new', 'cooking', 'ready', 'served', 'closed', 'cancelled');

    public static $statusLabels = array(
        'new'       => 'Recibido',
        'cooking'   => 'Preparando',
        'ready'     => 'Listo',
        'served'    => 'Entregado',
        'closed'    => 'Cobrado',
        'cancelled' => 'Anulado',
    );

    /**
     * Cotiza un carrito contra la base de datos.
     *
     * @param array $cart [['product_id'=>int,'qty'=>int,'variant_id'=>int,'options'=>[ids],'notes'=>string], ...]
     * @return array{items:array,subtotal:float,errors:array}
     */
    public static function priceCart(array $cart)
    {
        $errors = array();
        $items = array();
        $subtotalCents = 0;

        $ids = array();
        foreach ($cart as $line) {
            $pid = isset($line['product_id']) ? (int)$line['product_id'] : 0;
            if ($pid > 0) { $ids[$pid] = $pid; }
        }
        if (!$ids) {
            return array('items' => array(), 'subtotal' => 0.0, 'errors' => array('El carrito está vacío.'));
        }

        $ph = DB::placeholders(array_values($ids));
        $rows = DB::all('SELECT * FROM mg_products WHERE id IN (' . $ph . ') AND is_active = 1', array_values($ids));
        $products = array();
        foreach ($rows as $r) { $products[(int)$r['id']] = $r; }

        $promos = Promotion::activeNow();

        foreach ($cart as $line) {
            $pid = isset($line['product_id']) ? (int)$line['product_id'] : 0;
            $qty = isset($line['qty']) ? (int)$line['qty'] : 1;
            $qty = max(1, min(50, $qty));
            if (!isset($products[$pid])) {
                $errors[] = 'Un producto del carrito ya no está disponible.';
                continue;
            }
            $p = $products[$pid];
            if ((int)$p['is_sold_out'] === 1) {
                $errors[] = '«' . $p['name'] . '» se agotó.';
                continue;
            }

            $basePrice = Promotion::apply((float)$p['price'], $p, $promos);
            $unitCents = Money::cents($basePrice);
            $chosen = array();

            // Variante (tamaño, gramaje, presentación)
            $variantId = isset($line['variant_id']) ? (int)$line['variant_id'] : 0;
            if ($variantId > 0) {
                $v = DB::first('SELECT * FROM mg_variants WHERE id = :v AND product_id = :p LIMIT 1',
                    array('v' => $variantId, 'p' => $pid));
                if ($v) {
                    $unitCents += Money::cents($v['price_delta']);
                    $chosen[] = array('group' => 'Presentación', 'name' => $v['name'], 'price' => (float)$v['price_delta']);
                } else {
                    $errors[] = 'La presentación elegida para «' . $p['name'] . '» ya no existe.';
                }
            }

            // Modificadores: se comprueba que la opción pertenezca al producto,
            // y se respetan mínimos y máximos del grupo.
            $optionIds = array();
            if (isset($line['options']) && is_array($line['options'])) {
                foreach ($line['options'] as $o) {
                    $o = (int)$o;
                    if ($o > 0) { $optionIds[$o] = $o; }
                }
            }
            $groups = Menu::modifierGroups($pid);
            $validOptions = array();
            foreach ($groups as $g) {
                $inGroup = array();
                foreach ($g['options'] as $o) {
                    if (isset($optionIds[(int)$o['id']])) { $inGroup[] = $o; }
                }
                if ((int)$g['is_required'] === 1 && count($inGroup) < max(1, (int)$g['min_select'])) {
                    $errors[] = '«' . $p['name'] . '» requiere elegir ' . $g['name'] . '.';
                    continue;
                }
                $max = (int)$g['max_select'];
                if ($max > 0 && count($inGroup) > $max) { $inGroup = array_slice($inGroup, 0, $max); }
                foreach ($inGroup as $o) { $validOptions[] = array($g, $o); }
            }
            foreach ($validOptions as $pair) {
                list($g, $o) = $pair;
                $unitCents += Money::cents($o['price_delta']);
                $chosen[] = array('group' => $g['name'], 'name' => $o['name'], 'price' => (float)$o['price_delta']);
            }

            $lineCents = $unitCents * $qty;
            $subtotalCents += $lineCents;

            $variantName = '';
            foreach ($chosen as $c) {
                if ($c['group'] === 'Presentación') { $variantName = $c['name']; break; }
            }

            $items[] = array(
                'product_id'   => $pid,
                'name'         => $p['name'],
                'variant_name' => $variantName,
                'qty'          => $qty,
                'unit_price'   => Money::fromCents($unitCents),
                'base_price'   => $basePrice,
                'modifiers'    => $chosen,
                'line_total'   => Money::fromCents($lineCents),
                'notes'        => isset($line['notes']) ? mb_substr(trim((string)$line['notes']), 0, 200) : '',
                'image'        => $p['image'],
                'prep_minutes' => (int)$p['prep_minutes'],
            );
        }

        return array('items' => $items, 'subtotal' => Money::fromCents($subtotalCents), 'errors' => $errors);
    }

    /** Total final: descuento, envío, impuesto y propina. */
    public static function totals($subtotal, array $opts = array())
    {
        $subCents = Money::cents($subtotal);
        $discountCents = 0;
        $deliveryCents = 0;
        $couponCode = '';

        if (!empty($opts['coupon'])) {
            $c = Coupon::validate($opts['coupon'], $subtotal);
            if ($c['ok']) {
                $couponCode = $c['coupon']['code'];
                $discountCents = $c['coupon']['type'] === 'percent'
                    ? (int)round($subCents * ((float)$c['coupon']['value'] / 100))
                    : Money::cents($c['coupon']['value']);
            }
        }
        if ($discountCents > $subCents) { $discountCents = $subCents; }

        $zone = null;
        if (!empty($opts['mode']) && $opts['mode'] === 'delivery' && !empty($opts['zone_id'])) {
            $zone = DB::first('SELECT * FROM mg_delivery_zones WHERE id = :z AND is_active = 1', array('z' => (int)$opts['zone_id']));
            if ($zone) { $deliveryCents = Money::cents($zone['fee']); }
        }

        $baseCents = $subCents - $discountCents;

        // Si el impuesto ya viene incluido en el precio no se suma aparte.
        $taxRate = Settings::float('tax_rate', 0);
        $taxCents = 0;
        if ($taxRate > 0 && !Settings::bool('tax_included', true)) {
            $taxCents = (int)round($baseCents * ($taxRate / 100));
        }

        // La propina se calcula sobre el consumo, nunca sobre el envío.
        $tipCents = 0;
        if (Settings::bool('tip_enabled', true) && isset($opts['tip_percent']) && $opts['tip_percent'] !== null && $opts['tip_percent'] !== '') {
            $tp = max(0, min(50, (float)$opts['tip_percent']));
            $tipCents = (int)round($baseCents * ($tp / 100));
        } elseif (isset($opts['tip_amount']) && $opts['tip_amount'] !== null) {
            $tipCents = max(0, Money::cents($opts['tip_amount']));
        }

        $totalCents = $baseCents + $taxCents + $deliveryCents + $tipCents;

        return array(
            'subtotal'     => Money::fromCents($subCents),
            'discount'     => Money::fromCents($discountCents),
            'delivery_fee' => Money::fromCents($deliveryCents),
            'tax'          => Money::fromCents($taxCents),
            'tip'          => Money::fromCents($tipCents),
            'total'        => Money::fromCents($totalCents),
            'coupon_code'  => $couponCode,
            'zone'         => $zone,
        );
    }

    /** Crea el pedido dentro de una transacción. */
    public static function place(array $payload)
    {
        $priced = self::priceCart(isset($payload['items']) ? (array)$payload['items'] : array());
        if ($priced['errors']) {
            return array('ok' => false, 'error' => implode(' ', array_unique($priced['errors'])));
        }
        if (!$priced['items']) {
            return array('ok' => false, 'error' => 'El carrito está vacío.');
        }

        $mode = isset($payload['mode']) ? (string)$payload['mode'] : 'dine_in';
        if (!in_array($mode, array('dine_in', 'takeaway', 'delivery'), true)) { $mode = 'dine_in'; }
        if (!Settings::hasMode($mode)) {
            return array('ok' => false, 'error' => 'Ese modo de pedido no está habilitado.');
        }

        $tableId = null;
        if ($mode === 'dine_in') {
            if (empty($payload['table_id'])) {
                return array('ok' => false, 'error' => 'Escanea el código QR de tu mesa para pedir.');
            }
            $t = DB::first('SELECT id FROM mg_tables WHERE id = :t AND is_active = 1', array('t' => (int)$payload['table_id']));
            if (!$t) { return array('ok' => false, 'error' => 'La mesa indicada no existe.'); }
            $tableId = (int)$t['id'];
        }

        $zoneId = null;
        if ($mode === 'delivery') {
            if (empty($payload['zone_id'])) {
                return array('ok' => false, 'error' => 'Elige una zona de entrega.');
            }
            $zoneId = (int)$payload['zone_id'];
            if (trim((string)(isset($payload['address']) ? $payload['address'] : '')) === '') {
                return array('ok' => false, 'error' => 'Escribe la dirección de entrega.');
            }
        }
        if ($mode !== 'dine_in' && trim((string)(isset($payload['phone']) ? $payload['phone'] : '')) === '') {
            return array('ok' => false, 'error' => 'Necesitamos tu teléfono para confirmar el pedido.');
        }

        $totals = self::totals($priced['subtotal'], array(
            'coupon'      => isset($payload['coupon']) ? $payload['coupon'] : '',
            'mode'        => $mode,
            'zone_id'     => $zoneId,
            'tip_percent' => isset($payload['tip_percent']) ? $payload['tip_percent'] : null,
        ));

        if ($mode === 'delivery' && $totals['zone'] && $totals['subtotal'] < (float)$totals['zone']['min_total']) {
            return array('ok' => false, 'error' => 'El pedido mínimo para esa zona es ' . Money::format($totals['zone']['min_total']) . '.');
        }

        $customerId = null;
        if ($mode !== 'dine_in') { $customerId = Customer::touch($payload); }

        DB::begin();
        try {
            $orderId = DB::insert('mg_orders', array(
                'code'           => self::uniqueCode(),
                'public_token'   => Security::randomToken(18),
                'mode'           => $mode,
                'table_id'       => $tableId,
                'customer_id'    => $customerId,
                'customer_name'  => mb_substr(trim((string)(isset($payload['name']) ? $payload['name'] : '')), 0, 160),
                'customer_phone' => mb_substr(trim((string)(isset($payload['phone']) ? $payload['phone'] : '')), 0, 40),
                'address'        => mb_substr(trim((string)(isset($payload['address']) ? $payload['address'] : '')), 0, 255),
                'zone_id'        => $zoneId,
                'status'         => 'new',
                'subtotal'       => $totals['subtotal'],
                'discount'       => $totals['discount'],
                'delivery_fee'   => $totals['delivery_fee'],
                'tip'            => $totals['tip'],
                'tax'            => $totals['tax'],
                'total'          => $totals['total'],
                'coupon_code'    => $totals['coupon_code'],
                'payment_method' => mb_substr((string)(isset($payload['payment']) ? $payload['payment'] : ''), 0, 40),
                'notes'          => mb_substr(trim((string)(isset($payload['notes']) ? $payload['notes'] : '')), 0, 500),
                'placed_at'      => date('Y-m-d H:i:s'),
            ));

            foreach ($priced['items'] as $it) {
                $itemId = DB::insert('mg_order_items', array(
                    'order_id'     => $orderId,
                    'product_id'   => $it['product_id'],
                    'name'         => $it['name'],
                    'variant_name' => $it['variant_name'],
                    'qty'          => $it['qty'],
                    'unit_price'   => $it['unit_price'],
                    'line_total'   => $it['line_total'],
                    'notes'        => $it['notes'],
                    'status'       => 'pending',
                ));
                foreach ($it['modifiers'] as $m) {
                    DB::insert('mg_order_item_modifiers', array(
                        'order_item_id' => $itemId,
                        'name'          => $m['group'] . ': ' . $m['name'],
                        'price_delta'   => $m['price'],
                    ));
                }
            }

            if ($totals['coupon_code'] !== '') { Coupon::consume($totals['coupon_code']); }
            if ($tableId) { DB::update('mg_tables', array('status' => 'busy'), 'id = :id', array('id' => $tableId)); }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            Logger::error('Order::place ' . $e->getMessage());
            return array('ok' => false, 'error' => 'No se pudo registrar el pedido. Intenta de nuevo.');
        }

        return array('ok' => true, 'order' => self::find($orderId));
    }

    private static function uniqueCode()
    {
        for ($i = 0; $i < 12; $i++) {
            $code = Security::orderCode();
            if (!DB::value('SELECT id FROM mg_orders WHERE code = :c', array('c' => $code))) { return $code; }
        }
        return Security::orderCode() . random_int(10, 99);
    }

    public static function find($id)
    {
        $o = DB::first('SELECT * FROM mg_orders WHERE id = :id LIMIT 1', array('id' => (int)$id));
        return $o ? self::hydrate($o) : null;
    }

    public static function findByToken($token)
    {
        $o = DB::first('SELECT * FROM mg_orders WHERE public_token = :t LIMIT 1', array('t' => (string)$token));
        return $o ? self::hydrate($o) : null;
    }

    public static function hydrate(array $o)
    {
        $o['items'] = DB::all('SELECT * FROM mg_order_items WHERE order_id = :o ORDER BY id', array('o' => (int)$o['id']));
        foreach ($o['items'] as $i => $it) {
            $o['items'][$i]['modifiers'] = DB::all(
                'SELECT name, price_delta FROM mg_order_item_modifiers WHERE order_item_id = :i ORDER BY id',
                array('i' => (int)$it['id'])
            );
        }
        $o['table'] = $o['table_id'] ? DB::first('SELECT * FROM mg_tables WHERE id = :t', array('t' => (int)$o['table_id'])) : null;
        $o['status_label'] = isset(self::$statusLabels[$o['status']]) ? self::$statusLabels[$o['status']] : $o['status'];
        $o['mode_label'] = self::modeLabel($o['mode']);
        return $o;
    }

    /** Cambio de estado. Devuelve true si algo cambió. */
    public static function setStatus($orderId, $status, $note = '')
    {
        if (!in_array($status, self::$statuses, true)) { return false; }
        $o = DB::first('SELECT id, status FROM mg_orders WHERE id = :id', array('id' => (int)$orderId));
        if (!$o || $o['status'] === $status) { return false; }

        $now = date('Y-m-d H:i:s');
        $data = array('status' => $status);
        if ($status === 'ready')  { $data['ready_at'] = $now; }
        if ($status === 'closed') { $data['closed_at'] = $now; $data['payment_status'] = 'paid'; }
        if ($status === 'cancelled' && $note !== '') { $data['cancel_reason'] = mb_substr($note, 0, 255); }
        DB::update('mg_orders', $data, 'id = :id', array('id' => (int)$orderId));

        if (in_array($status, array('closed', 'cancelled'), true)) {
            self::releaseTableIfDone((int)$orderId);
        }
        if ($status === 'closed') { Customer::registerPayment((int)$orderId); }
        return true;
    }

    private static function releaseTableIfDone($orderId)
    {
        $tableId = (int)DB::value('SELECT table_id FROM mg_orders WHERE id = :id', array('id' => $orderId), 0);
        if (!$tableId) { return; }
        $pending = (int)DB::value(
            "SELECT COUNT(*) FROM mg_orders WHERE table_id = :t AND status IN ('new','cooking','ready','served')",
            array('t' => $tableId), 0);
        if ($pending === 0) {
            DB::update('mg_tables', array('status' => 'free'), 'id = :id', array('id' => $tableId));
            DB::run("UPDATE mg_service_calls SET status='done', resolved_at=NOW() WHERE table_id = :t AND status='open'", array('t' => $tableId));
        }
    }

    /** Pedidos activos para la pantalla de cocina. */
    public static function kitchenBoard()
    {
        $rows = DB::all(
            "SELECT o.*, t.name AS table_name FROM mg_orders o
             LEFT JOIN mg_tables t ON t.id = o.table_id
             WHERE o.status IN ('new','cooking','ready')
             ORDER BY o.placed_at ASC"
        );
        $board = array('new' => array(), 'cooking' => array(), 'ready' => array());
        foreach ($rows as $o) {
            $o = self::hydrate($o);
            $o['minutes'] = (int)floor((time() - strtotime($o['placed_at'])) / 60);
            $board[$o['status']][] = $o;
        }
        return $board;
    }

    public static function recent($limit = 40, array $filters = array())
    {
        $limit = max(1, min(200, (int)$limit));
        $where = array('1 = 1');
        $params = array();
        if (!empty($filters['status'])) { $where[] = 'o.status = :st'; $params['st'] = $filters['status']; }
        if (!empty($filters['mode']))   { $where[] = 'o.mode = :md';   $params['md'] = $filters['mode']; }
        if (!empty($filters['from']))   { $where[] = 'o.placed_at >= :f';  $params['f'] = $filters['from'] . ' 00:00:00'; }
        if (!empty($filters['to']))     { $where[] = 'o.placed_at <= :t2'; $params['t2'] = $filters['to'] . ' 23:59:59'; }
        if (!empty($filters['q'])) {
            $where[] = '(o.code LIKE :q OR o.customer_name LIKE :q2 OR o.customer_phone LIKE :q3)';
            $like = '%' . $filters['q'] . '%';
            $params['q'] = $like; $params['q2'] = $like; $params['q3'] = $like;
        }
        return DB::all(
            'SELECT o.*, t.name AS table_name FROM mg_orders o
             LEFT JOIN mg_tables t ON t.id = o.table_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY o.placed_at DESC LIMIT ' . $limit,
            $params
        );
    }

    /** Huella de cambio para el flujo en vivo (SSE y sondeo). */
    public static function pulse()
    {
        $row = DB::first(
            "SELECT COALESCE(MAX(updated_at), '1970-01-01') AS last_change, COUNT(*) AS active
             FROM mg_orders WHERE status IN ('new','cooking','ready')"
        );
        $calls = (int)DB::value("SELECT COUNT(*) FROM mg_service_calls WHERE status='open'", array(), 0);
        return array(
            'last_change' => $row ? $row['last_change'] : '',
            'active'      => $row ? (int)$row['active'] : 0,
            'calls'       => $calls,
            'hash'        => md5(($row ? $row['last_change'] . '|' . $row['active'] : '') . '|' . $calls),
        );
    }

    /** Texto del pedido listo para WhatsApp. */
    public static function whatsappText(array $order)
    {
        $cur = Settings::get('currency', 'Q');
        $lines = array();
        $lines[] = '*Pedido ' . $order['code'] . '* · ' . Settings::get('name');
        if (!empty($order['table'])) { $lines[] = 'Mesa: ' . $order['table']['name']; }
        $lines[] = 'Modo: ' . self::modeLabel($order['mode']);
        $lines[] = '';
        foreach ($order['items'] as $it) {
            $lines[] = $it['qty'] . '× ' . $it['name'] . '  ' . Money::format($it['line_total'], $cur);
            foreach ((array)$it['modifiers'] as $m) {
                $lines[] = '   · ' . $m['name'] . ((float)$m['price_delta'] > 0 ? ' (+' . Money::format($m['price_delta'], $cur) . ')' : '');
            }
            if ($it['notes'] !== '') { $lines[] = '   Nota: ' . $it['notes']; }
        }
        $lines[] = '';
        $lines[] = 'Subtotal: ' . Money::format($order['subtotal'], $cur);
        if ((float)$order['discount'] > 0)     { $lines[] = 'Descuento: -' . Money::format($order['discount'], $cur); }
        if ((float)$order['delivery_fee'] > 0) { $lines[] = 'Envío: ' . Money::format($order['delivery_fee'], $cur); }
        if ((float)$order['tip'] > 0)          { $lines[] = 'Propina: ' . Money::format($order['tip'], $cur); }
        $lines[] = '*Total: ' . Money::format($order['total'], $cur) . '*';
        if ($order['customer_name'] !== '')  { $lines[] = ''; $lines[] = 'Cliente: ' . $order['customer_name']; }
        if ($order['customer_phone'] !== '') { $lines[] = 'Teléfono: ' . $order['customer_phone']; }
        if ($order['address'] !== '')        { $lines[] = 'Dirección: ' . $order['address']; }
        if ($order['notes'] !== '')          { $lines[] = 'Notas: ' . $order['notes']; }
        return implode("\n", $lines);
    }

    public static function modeLabel($mode)
    {
        $m = array('dine_in' => 'En mesa', 'takeaway' => 'Para llevar', 'delivery' => 'A domicilio');
        return isset($m[$mode]) ? $m[$mode] : $mode;
    }
}
