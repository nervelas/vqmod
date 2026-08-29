<?php
namespace MenuGold\Models;

use MenuGold\Core\DB;

final class Customer
{
    /** Crea o actualiza el cliente a partir del teléfono del pedido. */
    public static function touch(array $payload)
    {
        $phone = preg_replace('/\D+/', '', (string)(isset($payload['phone']) ? $payload['phone'] : ''));
        if ($phone === '') { return null; }
        $name = mb_substr(trim((string)(isset($payload['name']) ? $payload['name'] : '')), 0, 160);
        $addr = mb_substr(trim((string)(isset($payload['address']) ? $payload['address'] : '')), 0, 255);

        $existing = DB::first('SELECT id FROM mg_customers WHERE phone = :p LIMIT 1', array('p' => $phone));
        if ($existing) {
            $data = array('last_order_at' => date('Y-m-d H:i:s'));
            if ($name !== '') { $data['name'] = $name; }
            if ($addr !== '') { $data['address'] = $addr; }
            DB::update('mg_customers', $data, 'id = :id', array('id' => (int)$existing['id']));
            return (int)$existing['id'];
        }
        return DB::insert('mg_customers', array(
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
        $o = DB::first('SELECT customer_id, total FROM mg_orders WHERE id = :id', array('id' => (int)$orderId));
        if (!$o || empty($o['customer_id'])) { return; }
        $porCien = Settings::int('loyalty_points_per_100', 0);
        $puntos = $porCien > 0 ? (int)floor(((float)$o['total'] / 100) * $porCien) : 0;
        DB::run(
            'UPDATE mg_customers SET orders_count = orders_count + 1, total_spent = total_spent + :t, points = points + :p
             WHERE id = :id',
            array('t' => (float)$o['total'], 'p' => $puntos, 'id' => (int)$o['customer_id'])
        );
    }

    public static function search($q = '', $limit = 60)
    {
        $limit = max(1, min(200, (int)$limit));
        if ($q !== '') {
            $like = '%' . $q . '%';
            return DB::all(
                'SELECT * FROM mg_customers WHERE name LIKE :a OR phone LIKE :b
                 ORDER BY last_order_at DESC, id DESC LIMIT ' . $limit,
                array('a' => $like, 'b' => $like)
            );
        }
        return DB::all('SELECT * FROM mg_customers ORDER BY last_order_at DESC, id DESC LIMIT ' . $limit);
    }

    public static function find($id)
    {
        return DB::first('SELECT * FROM mg_customers WHERE id = :id', array('id' => (int)$id));
    }

    public static function orders($customerId, $limit = 30)
    {
        $limit = max(1, min(100, (int)$limit));
        return DB::all('SELECT * FROM mg_orders WHERE customer_id = :c ORDER BY placed_at DESC LIMIT ' . $limit,
            array('c' => (int)$customerId));
    }
}
