<?php
namespace MenuGold\Models;

use MenuGold\Core\DB;

final class Customer
{
    /** Crea o actualiza el cliente a partir del teléfono del pedido. */
    public static function touch($restaurantId, array $payload)
    {
        $phone = preg_replace('/\D+/', '', (string)(isset($payload['phone']) ? $payload['phone'] : ''));
        if ($phone === '') { return null; }
        $name = mb_substr(trim((string)(isset($payload['name']) ? $payload['name'] : '')), 0, 120);
        $addr = mb_substr(trim((string)(isset($payload['address']) ? $payload['address'] : '')), 0, 255);

        $existing = DB::first('SELECT id FROM customers WHERE restaurant_id = :r AND phone = :p LIMIT 1',
            array('r' => (int)$restaurantId, 'p' => $phone));
        if ($existing) {
            $data = array('last_order_at' => date('Y-m-d H:i:s'));
            if ($name !== '') { $data['name'] = $name; }
            if ($addr !== '') { $data['address'] = $addr; }
            DB::update('customers', $data, 'id = :id', array('id' => (int)$existing['id']));
            return (int)$existing['id'];
        }
        return DB::insert('customers', array(
            'restaurant_id' => (int)$restaurantId,
            'name'          => $name,
            'phone'         => $phone,
            'address'       => $addr,
            'last_order_at' => date('Y-m-d H:i:s'),
            'created_at'    => date('Y-m-d H:i:s'),
        ));
    }

    /** Suma el pedido cobrado al historial y a los puntos del cliente. */
    public static function registerPayment($orderId)
    {
        $o = DB::first('SELECT customer_id, total, restaurant_id FROM orders WHERE id = :id', array('id' => (int)$orderId));
        if (!$o || empty($o['customer_id'])) { return; }
        $settings = Restaurant::settings((int)$o['restaurant_id']);
        $pointsPer = isset($settings['loyalty_points_per_100']) ? (int)$settings['loyalty_points_per_100'] : 0;
        $points = $pointsPer > 0 ? (int)floor(((float)$o['total'] / 100) * $pointsPer) : 0;
        DB::run(
            'UPDATE customers SET orders_count = orders_count + 1, total_spent = total_spent + :t, points = points + :p
             WHERE id = :id',
            array('t' => (float)$o['total'], 'p' => $points, 'id' => (int)$o['customer_id'])
        );
    }

    public static function search($restaurantId, $q = '', $limit = 60)
    {
        $limit = max(1, min(200, (int)$limit));
        if ($q !== '') {
            $like = '%' . $q . '%';
            return DB::all(
                'SELECT * FROM customers WHERE restaurant_id = :r AND (name LIKE :a OR phone LIKE :b)
                 ORDER BY last_order_at DESC LIMIT ' . $limit,
                array('r' => (int)$restaurantId, 'a' => $like, 'b' => $like)
            );
        }
        return DB::all('SELECT * FROM customers WHERE restaurant_id = :r ORDER BY last_order_at DESC LIMIT ' . $limit,
            array('r' => (int)$restaurantId));
    }
}
