<?php
namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Security;
use MenuGold\Core\Url;

/** Mesas y sus códigos QR firmados. */
final class TableModel
{
    public static function forRestaurant($restaurantId, $onlyActive = true)
    {
        $sql = 'SELECT * FROM tables WHERE restaurant_id = :r' . ($onlyActive ? ' AND is_active = 1' : '') . ' ORDER BY sort, id';
        return DB::all($sql, array('r' => (int)$restaurantId));
    }

    public static function find($restaurantId, $id)
    {
        return DB::first('SELECT * FROM tables WHERE id = :id AND restaurant_id = :r LIMIT 1',
            array('id' => (int)$id, 'r' => (int)$restaurantId));
    }

    public static function findByToken($restaurantId, $token)
    {
        return DB::first('SELECT * FROM tables WHERE qr_token = :t AND restaurant_id = :r LIMIT 1',
            array('t' => (string)$token, 'r' => (int)$restaurantId));
    }

    public static function newToken()
    {
        return Security::randomToken(12);
    }

    /** Firma HMAC que impide fabricar enlaces de mesa a mano. */
    public static function signature(array $table)
    {
        return Security::sign('table:' . (int)$table['id'] . ':' . $table['qr_token']);
    }

    public static function verify(array $table, $signature)
    {
        return Security::verifySignature('table:' . (int)$table['id'] . ':' . $table['qr_token'], (string)$signature);
    }

    /** URL absoluta que se codifica en el QR de la mesa. */
    public static function url(array $restaurant, array $table)
    {
        return Url::abs('/r/' . $restaurant['slug'] . '/m/' . $table['qr_token']) . '?k=' . self::signature($table);
    }

    public static function generalUrl(array $restaurant)
    {
        return Url::abs('/r/' . $restaurant['slug']);
    }

    /** Estado de las mesas para la pantalla del mesero. */
    public static function board($restaurantId)
    {
        $tables = self::forRestaurant($restaurantId);
        $open = DB::all(
            "SELECT table_id, COUNT(*) AS orders_count, SUM(total) AS total, MIN(placed_at) AS since,
                    SUM(status = 'ready') AS ready_count, SUM(status = 'new') AS new_count
             FROM orders
             WHERE restaurant_id = :r AND status IN ('new','preparing','ready','delivered') AND table_id IS NOT NULL
             GROUP BY table_id",
            array('r' => (int)$restaurantId)
        );
        $map = array();
        foreach ($open as $o) { $map[(int)$o['table_id']] = $o; }
        $calls = DB::all("SELECT table_id, type FROM service_calls WHERE restaurant_id = :r AND status = 'open'", array('r' => (int)$restaurantId));
        $callMap = array();
        foreach ($calls as $c) { $callMap[(int)$c['table_id']][] = $c['type']; }

        foreach ($tables as $i => $t) {
            $id = (int)$t['id'];
            $tables[$i]['open_orders'] = isset($map[$id]) ? (int)$map[$id]['orders_count'] : 0;
            $tables[$i]['open_total']  = isset($map[$id]) ? (float)$map[$id]['total'] : 0.0;
            $tables[$i]['since']       = isset($map[$id]) ? $map[$id]['since'] : null;
            $tables[$i]['ready_count'] = isset($map[$id]) ? (int)$map[$id]['ready_count'] : 0;
            $tables[$i]['calls']       = isset($callMap[$id]) ? $callMap[$id] : array();
        }
        return $tables;
    }
}
