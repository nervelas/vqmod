<?php
namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Security;
use MenuGold\Core\Url;

/** Mesas y sus códigos QR firmados. */
final class TableModel
{
    public static function all($onlyActive = true)
    {
        return DB::all('SELECT * FROM mg_tables' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY sort, id');
    }

    public static function find($id)
    {
        return DB::first('SELECT * FROM mg_tables WHERE id = :id LIMIT 1', array('id' => (int)$id));
    }

    public static function findByToken($token)
    {
        return DB::first('SELECT * FROM mg_tables WHERE qr_token = :t LIMIT 1', array('t' => (string)$token));
    }

    public static function newToken()
    {
        return Security::randomToken(12);
    }

    /** Firma HMAC: sin ella no se puede fabricar el enlace de una mesa a mano. */
    public static function signature(array $table)
    {
        return Security::sign('table:' . (int)$table['id'] . ':' . $table['qr_token']);
    }

    public static function verify(array $table, $signature)
    {
        return Security::verifySignature('table:' . (int)$table['id'] . ':' . $table['qr_token'], (string)$signature);
    }

    /** URL absoluta que se codifica en el QR de la mesa. */
    public static function url(array $table)
    {
        return Url::abs('/mesa/' . $table['qr_token']) . '?k=' . self::signature($table);
    }

    public static function generalUrl()
    {
        return Url::abs('/');
    }

    /** Estado de las mesas para la pantalla del mesero. */
    public static function board()
    {
        $tables = self::all();
        $open = DB::all(
            "SELECT table_id, COUNT(*) AS orders_count, SUM(total) AS total, MIN(placed_at) AS since,
                    SUM(status = 'ready') AS ready_count, SUM(status = 'new') AS new_count
             FROM mg_orders
             WHERE status IN ('new','cooking','ready','served') AND table_id IS NOT NULL
             GROUP BY table_id"
        );
        $map = array();
        foreach ($open as $o) { $map[(int)$o['table_id']] = $o; }
        $calls = DB::all("SELECT table_id, type FROM mg_service_calls WHERE status = 'open'");
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

    /** Pedidos abiertos de una mesa, para cobrar. */
    public static function openOrders($tableId)
    {
        $rows = DB::all(
            "SELECT * FROM mg_orders WHERE table_id = :t AND status IN ('new','cooking','ready','served')
             ORDER BY placed_at",
            array('t' => (int)$tableId)
        );
        foreach ($rows as $i => $o) { $rows[$i] = Order::hydrate($o); }
        return $rows;
    }
}
