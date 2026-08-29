<?php
namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Money;
use MenuGold\Core\Security;
use MenuGold\Core\Logger;

/**
 * Pedidos. Los precios y totales SIEMPRE se recalculan aquí, en el
 * servidor, a partir de la base de datos: nunca se confía en el carrito.
 */
final class Order
{
    public static $statuses = array('new', 'preparing', 'ready', 'delivered', 'paid', 'cancelled');

    public static $statusLabels = array(
        'new'       => 'Recibido',
        'preparing' => 'Preparando',
        'ready'     => 'Listo',
        'delivered' => 'Entregado',
        'paid'      => 'Cobrado',
        'cancelled' => 'Anulado',
    );

    /**
     * Calcula el detalle de un carrito contra la base de datos.
     *
     * @param array $cart   [['product_id'=>int,'qty'=>int,'variant_id'=>int,'options'=>[ids],'notes'=>string], ...]
     * @return array{items:array,subtotal:float,errors:array}
     */
    public static function priceCart(array $restaurant, array $cart)
    {
        $rid = (int)$restaurant['id'];
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
        $params = array_values($ids);
        $params[] = $rid;
        $rows = DB::all('SELECT * FROM products WHERE id IN (' . $ph . ') AND restaurant_id = ? AND is_active = 1', $params);
        $products = array();
        foreach ($rows as $r) { $products[(int)$r['id']] = $r; }

        $promos = Promotion::activeFor($rid);

        foreach ($cart as $line) {
            $pid = isset($line['product_id']) ? (int)$line['product_id'] : 0;
            $qty = isset($line['qty']) ? (int)$line['qty'] : 1;
            if ($qty < 1) { $qty = 1; }
            if ($qty > 50) { $qty = 50; }
            if (!isset($products[$pid])) {
                $errors[] = 'Un producto del carrito ya no está disponible.';
                continue;
            }
            $p = $products[$pid];
            if ((int)$p['is_out_of_stock'] === 1) {
                $errors[] = '«' . $p['name'] . '» se agotó.';
                continue;
            }

            $unitCents = Money::cents(Promotion::apply((float)$p['price'], $p, $promos));
            $chosen = array();

            // Variante (tamaño / término)
            $variantId = isset($line['variant_id']) ? (int)$line['variant_id'] : 0;
            if ($variantId > 0) {
                $v = DB::first('SELECT * FROM variants WHERE id = :v AND product_id = :p LIMIT 1',
                    array('v' => $variantId, 'p' => $pid));
                if ($v) {
                    $unitCents += Money::cents($v['price_delta']);
                    $chosen[] = array('group' => 'Presentación', 'name' => $v['name'], 'price' => (float)$v['price_delta']);
                } else {
                    $errors[] = 'La presentación elegida para «' . $p['name'] . '» ya no existe.';
                }
            }

            // Modificadores: se valida pertenencia, mínimos y máximos.
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
                if ((int)$g['min_select'] > 0 && count($inGroup) > 0 && count($inGroup) < (int)$g['min_select']) {
                    $errors[] = 'Elige al menos ' . (int)$g['min_select'] . ' opción en ' . $g['name'] . '.';
                }
                $max = (int)$g['max_select'];
                if ($max > 0 && count($inGroup) > $max) {
                    $inGroup = array_slice($inGroup, 0, $max);
                }
                foreach ($inGroup as $o) { $validOptions[] = array($g, $o); }
            }
            foreach ($validOptions as $pair) {
                list($g, $o) = $pair;
                $unitCents += Money::cents($o['price_delta']);
                $chosen[] = array('group' => $g['name'], 'name' => $o['name'], 'price' => (float)$o['price_delta']);
            }

            $lineCents = $unitCents * $qty;
            $subtotalCents += $lineCents;

            $items[] = array(
                'product_id'      => $pid,
                'name_snapshot'   => $p['name'],
                'qty'             => $qty,
                'unit_price'      => Money::fromCents($unitCents),
                'base_price'      => (float)$p['price'],
                'modifiers'       => $chosen,
                'modifiers_total' => Money::fromCents($unitCents) - Promotion::apply((float)$p['price'], $p, $promos),
                'line_total'      => Money::fromCents($lineCents),
                'notes'           => isset($line['notes']) ? mb_substr(trim((string)$line['notes']), 0, 200) : '',
                'image'           => $p['image'],
                'prep_minutes'    => (int)$p['prep_minutes'],
            );
        }

        return array('items' => $items, 'subtotal' => Money::fromCents($subtotalCents), 'errors' => $errors);
    }

    /**
     * Calcula el total final (descuentos, envío, propina, impuesto).
     */
    public static function totals(array $restaurant, $subtotal, array $opts = array())
    {
        $subCents = Money::cents($subtotal);
        $discountCents = 0;
        $deliveryCents = 0;
        $couponCode = '';

        // Cupón
        if (!empty($opts['coupon'])) {
            $c = Coupon::validate((int)$restaurant['id'], $opts['coupon'], $subtotal);
            if ($c['ok']) {
                $couponCode = $c['coupon']['code'];
                if ($c['coupon']['type'] === 'percent') {
                    $discountCents = (int)round($subCents * ((float)$c['coupon']['value'] / 100));
                } elseif ($c['coupon']['type'] === 'amount') {
                    $discountCents = Money::cents($c['coupon']['value']);
                }
            }
        }
        if ($discountCents > $subCents) { $discountCents = $subCents; }

        // Envío
        $zone = null;
        if (!empty($opts['mode']) && $opts['mode'] === 'delivery' && !empty($opts['zone_id'])) {
            $zone = DB::first('SELECT * FROM delivery_zones WHERE id = :z AND restaurant_id = :r AND is_active = 1',
                array('z' => (int)$opts['zone_id'], 'r' => (int)$restaurant['id']));
            if ($zone) {
                $deliveryCents = Money::cents($zone['fee']);
                if (!empty($opts['coupon']) && isset($c) && $c['ok'] && $c['coupon']['type'] === 'free_delivery') {
                    $deliveryCents = 0;
                    $couponCode = $c['coupon']['code'];
                }
            }
        }

        $baseCents = $subCents - $discountCents;

        // Impuesto: si está incluido en el precio no se suma aparte.
        $taxRate = (float)$restaurant['tax_rate'];
        $taxCents = 0;
        if ($taxRate > 0 && (int)$restaurant['tax_included'] === 0) {
            $taxCents = (int)round($baseCents * ($taxRate / 100));
        }

        // Propina: porcentaje sobre el consumo, nunca sobre envío.
        $tipCents = 0;
        if (!empty($restaurant['tip_enabled']) && isset($opts['tip_percent'])) {
            $tp = max(0, min(50, (float)$opts['tip_percent']));
            $tipCents = (int)round($baseCents * ($tp / 100));
        } elseif (isset($opts['tip_amount'])) {
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
    public static function place(array $restaurant, array $payload)
    {
        $rid = (int)$restaurant['id'];
        $priced = self::priceCart($restaurant, isset($payload['items']) ? (array)$payload['items'] : array());
        if ($priced['errors']) {
            return array('ok' => false, 'error' => implode(' ', array_unique($priced['errors'])));
        }
        if (!$priced['items']) {
            return array('ok' => false, 'error' => 'El carrito está vacío.');
        }

        $mode = isset($payload['mode']) ? (string)$payload['mode'] : 'dine_in';
        if (!in_array($mode, array('dine_in', 'takeaway', 'delivery'), true)) { $mode = 'dine_in'; }
        if (!in_array($mode, Restaurant::modes($restaurant), true)) {
            return array('ok' => false, 'error' => 'Ese modo de pedido no está habilitado en este restaurante.');
        }

        $tableId = null;
        if ($mode === 'dine_in') {
            if (empty($payload['table_id'])) {
                return array('ok' => false, 'error' => 'Escanea el código QR de tu mesa para pedir.');
            }
            $t = DB::first('SELECT id FROM tables WHERE id = :t AND restaurant_id = :r AND is_active = 1',
                array('t' => (int)$payload['table_id'], 'r' => $rid));
            if (!$t) {
                return array('ok' => false, 'error' => 'La mesa indicada no existe.');
            }
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

        $totals = self::totals($restaurant, $priced['subtotal'], array(
            'coupon'      => isset($payload['coupon']) ? $payload['coupon'] : '',
            'mode'        => $mode,
            'zone_id'     => $zoneId,
            'tip_percent' => isset($payload['tip_percent']) ? $payload['tip_percent'] : null,
        ));

        if ($mode === 'delivery' && $totals['zone'] && $totals['subtotal'] < (float)$totals['zone']['min_order']) {
            return array('ok' => false, 'error' => 'El pedido mínimo para esa zona es ' . Money::format($totals['zone']['min_order']) . '.');
        }

        $customerId = null;
        if ($mode !== 'dine_in') {
            $customerId = Customer::touch($rid, $payload);
        }

        DB::begin();
        try {
            $code = self::uniqueCode($rid);
            $orderId = DB::insert('orders', array(
                'restaurant_id'    => $rid,
                'code'             => $code,
                'table_id'         => $tableId,
                'mode'             => $mode,
                'status'           => 'new',
                'customer_id'      => $customerId,
                'customer_name'    => mb_substr(trim((string)(isset($payload['name']) ? $payload['name'] : '')), 0, 120),
                'customer_phone'   => mb_substr(trim((string)(isset($payload['phone']) ? $payload['phone'] : '')), 0, 40),
                'address'          => mb_substr(trim((string)(isset($payload['address']) ? $payload['address'] : '')), 0, 255),
                'delivery_zone_id' => $zoneId,
                'delivery_fee'     => $totals['delivery_fee'],
                'subtotal'         => $totals['subtotal'],
                'discount'         => $totals['discount'],
                'tip'              => $totals['tip'],
                'tax'              => $totals['tax'],
                'total'            => $totals['total'],
                'coupon_code'      => $totals['coupon_code'],
                'payment_method'   => in_array(isset($payload['payment']) ? $payload['payment'] : '', array('cash','card','transfer','link'), true) ? $payload['payment'] : 'pending',
                'notes'            => mb_substr(trim((string)(isset($payload['notes']) ? $payload['notes'] : '')), 0, 500),
                'source'           => isset($payload['source']) && $payload['source'] === 'panel' ? 'panel' : 'qr',
                'lang'             => isset($payload['lang']) && $payload['lang'] === 'en' ? 'en' : 'es',
                'track_token'      => Security::randomToken(18),
                'placed_at'        => date('Y-m-d H:i:s'),
            ));

            foreach ($priced['items'] as $it) {
                DB::insert('order_items', array(
                    'order_id'        => $orderId,
                    'product_id'      => $it['product_id'],
                    'name_snapshot'   => $it['name_snapshot'],
                    'qty'             => $it['qty'],
                    'unit_price'      => $it['unit_price'],
                    'modifiers'       => json_encode($it['modifiers'], JSON_UNESCAPED_UNICODE),
                    'modifiers_total' => $it['modifiers_total'],
                    'line_total'      => $it['line_total'],
                    'notes'           => $it['notes'],
                    'status'          => 'pending',
                ));
            }

            DB::insert('order_events', array(
                'order_id' => $orderId, 'from_status' => '', 'to_status' => 'new',
                'note' => 'Pedido recibido', 'created_at' => date('Y-m-d H:i:s'),
            ));

            if ($totals['coupon_code'] !== '') {
                DB::run('UPDATE coupons SET used = used + 1 WHERE restaurant_id = :r AND code = :c',
                    array('r' => $rid, 'c' => $totals['coupon_code']));
            }
            if ($tableId) {
                DB::update('tables', array('status' => 'occupied'), 'id = :id', array('id' => $tableId));
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            Logger::error('Order::place ' . $e->getMessage());
            return array('ok' => false, 'error' => 'No se pudo registrar el pedido. Intenta de nuevo.');
        }

        return array('ok' => true, 'order' => self::find($rid, $orderId), 'code' => $code);
    }

    private static function uniqueCode($restaurantId)
    {
        for ($i = 0; $i < 12; $i++) {
            $code = Security::orderCode();
            $exists = DB::value('SELECT id FROM orders WHERE restaurant_id = :r AND code = :c', array('r' => (int)$restaurantId, 'c' => $code));
            if (!$exists) { return $code; }
        }
        return Security::orderCode() . random_int(10, 99);
    }

    public static function find($restaurantId, $id)
    {
        $o = DB::first('SELECT * FROM orders WHERE id = :id AND restaurant_id = :r LIMIT 1',
            array('id' => (int)$id, 'r' => (int)$restaurantId));
        return $o ? self::hydrate($o) : null;
    }

    public static function findByToken($token)
    {
        $o = DB::first('SELECT * FROM orders WHERE track_token = :t LIMIT 1', array('t' => (string)$token));
        return $o ? self::hydrate($o) : null;
    }

    public static function hydrate(array $o)
    {
        $o['items'] = DB::all('SELECT * FROM order_items WHERE order_id = :o ORDER BY id', array('o' => (int)$o['id']));
        foreach ($o['items'] as $i => $it) {
            $o['items'][$i]['modifiers'] = json_decode((string)$it['modifiers'], true);
            if (!is_array($o['items'][$i]['modifiers'])) { $o['items'][$i]['modifiers'] = array(); }
        }
        $o['table'] = $o['table_id'] ? DB::first('SELECT * FROM tables WHERE id = :t', array('t' => (int)$o['table_id'])) : null;
        $o['status_label'] = isset(self::$statusLabels[$o['status']]) ? self::$statusLabels[$o['status']] : $o['status'];
        return $o;
    }

    /** Cambio de estado con bitácora. Devuelve true si cambió. */
    public static function setStatus($restaurantId, $orderId, $status, $userId = 0, $note = '')
    {
        if (!in_array($status, self::$statuses, true)) { return false; }
        $o = DB::first('SELECT id, status FROM orders WHERE id = :id AND restaurant_id = :r',
            array('id' => (int)$orderId, 'r' => (int)$restaurantId));
        if (!$o || $o['status'] === $status) { return false; }

        $data = array('status' => $status);
        $now = date('Y-m-d H:i:s');
        if ($status === 'preparing' ) { $data['accepted_at']  = $now; }
        if ($status === 'ready')      { $data['ready_at']     = $now; }
        if ($status === 'delivered')  { $data['delivered_at'] = $now; }
        if ($status === 'paid')       { $data['paid_at'] = $now; $data['payment_status'] = 'paid'; }
        if ($status === 'cancelled' && $note !== '') { $data['cancel_reason'] = mb_substr($note, 0, 255); }

        DB::update('orders', $data, 'id = :id', array('id' => (int)$orderId));
        DB::insert('order_events', array(
            'order_id' => (int)$orderId, 'from_status' => $o['status'], 'to_status' => $status,
            'user_id' => (int)$userId ?: null, 'note' => mb_substr($note, 0, 255), 'created_at' => $now,
        ));

        if (in_array($status, array('paid', 'cancelled'), true)) {
            self::releaseTableIfDone($restaurantId, (int)$orderId);
        }
        if ($status === 'paid') {
            Customer::registerPayment((int)$orderId);
        }
        return true;
    }

    private static function releaseTableIfDone($restaurantId, $orderId)
    {
        $tableId = (int)DB::value('SELECT table_id FROM orders WHERE id = :id', array('id' => $orderId), 0);
        if (!$tableId) { return; }
        $pending = (int)DB::value(
            "SELECT COUNT(*) FROM orders WHERE table_id = :t AND status IN ('new','preparing','ready','delivered')",
            array('t' => $tableId), 0);
        if ($pending === 0) {
            DB::update('tables', array('status' => 'free'), 'id = :id', array('id' => $tableId));
            DB::run("UPDATE service_calls SET status='done', resolved_at=NOW() WHERE table_id = :t AND status='open'", array('t' => $tableId));
        }
    }

    /** Pedidos activos para la pantalla de cocina. */
    public static function kitchenBoard($restaurantId)
    {
        $rows = DB::all(
            "SELECT o.*, t.name AS table_name FROM orders o
             LEFT JOIN tables t ON t.id = o.table_id
             WHERE o.restaurant_id = :r AND o.status IN ('new','preparing','ready')
             ORDER BY o.placed_at ASC",
            array('r' => (int)$restaurantId)
        );
        $board = array('new' => array(), 'preparing' => array(), 'ready' => array());
        foreach ($rows as $o) {
            $o = self::hydrate($o);
            $o['minutes'] = (int)floor((time() - strtotime($o['placed_at'])) / 60);
            $board[$o['status']][] = $o;
        }
        return $board;
    }

    public static function recent($restaurantId, $limit = 40, array $filters = array())
    {
        $limit = max(1, min(200, (int)$limit));
        $where = array('o.restaurant_id = :r');
        $params = array('r' => (int)$restaurantId);
        if (!empty($filters['status'])) { $where[] = 'o.status = :st'; $params['st'] = $filters['status']; }
        if (!empty($filters['mode']))   { $where[] = 'o.mode = :md';   $params['md'] = $filters['mode']; }
        if (!empty($filters['from']))   { $where[] = 'o.placed_at >= :f'; $params['f'] = $filters['from'] . ' 00:00:00'; }
        if (!empty($filters['to']))     { $where[] = 'o.placed_at <= :t2'; $params['t2'] = $filters['to'] . ' 23:59:59'; }
        if (!empty($filters['q']))      { $where[] = '(o.code LIKE :q OR o.customer_name LIKE :q2 OR o.customer_phone LIKE :q3)';
                                          $like = '%' . $filters['q'] . '%';
                                          $params['q'] = $like; $params['q2'] = $like; $params['q3'] = $like; }
        return DB::all(
            'SELECT o.*, t.name AS table_name FROM orders o
             LEFT JOIN tables t ON t.id = o.table_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY o.placed_at DESC LIMIT ' . $limit,
            $params
        );
    }

    /** Marca de cambio para el flujo SSE: última actualización del restaurante. */
    public static function pulse($restaurantId)
    {
        $row = DB::first(
            "SELECT COALESCE(MAX(updated_at), '1970-01-01') AS last_change, COUNT(*) AS active
             FROM orders WHERE restaurant_id = :r AND status IN ('new','preparing','ready')",
            array('r' => (int)$restaurantId)
        );
        $calls = (int)DB::value("SELECT COUNT(*) FROM service_calls WHERE restaurant_id = :r AND status='open'",
            array('r' => (int)$restaurantId), 0);
        return array(
            'last_change' => $row ? $row['last_change'] : '',
            'active'      => $row ? (int)$row['active'] : 0,
            'calls'       => $calls,
            'hash'        => md5(($row ? $row['last_change'] . '|' . $row['active'] : '') . '|' . $calls),
        );
    }

    /** Texto del pedido formateado para WhatsApp. */
    public static function whatsappText(array $restaurant, array $order)
    {
        $lines = array();
        $lines[] = '*Pedido ' . $order['code'] . '* · ' . $restaurant['name'];
        if (!empty($order['table'])) { $lines[] = 'Mesa: ' . $order['table']['name']; }
        $lines[] = 'Modo: ' . self::modeLabel($order['mode']);
        $lines[] = '';
        foreach ($order['items'] as $it) {
            $lines[] = $it['qty'] . '× ' . $it['name_snapshot'] . '  ' . Money::format($it['line_total'], $restaurant['currency']);
            foreach ((array)$it['modifiers'] as $m) {
                $lines[] = '   · ' . $m['name'] . ((float)$m['price'] > 0 ? ' (+' . Money::format($m['price'], $restaurant['currency']) . ')' : '');
            }
            if ($it['notes'] !== '') { $lines[] = '   Nota: ' . $it['notes']; }
        }
        $lines[] = '';
        $lines[] = 'Subtotal: ' . Money::format($order['subtotal'], $restaurant['currency']);
        if ((float)$order['discount'] > 0)     { $lines[] = 'Descuento: -' . Money::format($order['discount'], $restaurant['currency']); }
        if ((float)$order['delivery_fee'] > 0) { $lines[] = 'Envío: ' . Money::format($order['delivery_fee'], $restaurant['currency']); }
        if ((float)$order['tip'] > 0)          { $lines[] = 'Propina: ' . Money::format($order['tip'], $restaurant['currency']); }
        $lines[] = '*Total: ' . Money::format($order['total'], $restaurant['currency']) . '*';
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
