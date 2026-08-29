<?php
namespace MenuGold\Models;

use MenuGold\Core\DB;
use MenuGold\Core\Str;

final class Restaurant
{
    /** @var array<int,array> */
    private static $cache = array();

    public static function find($id, $fresco = false)
    {
        $id = (int)$id;
        if ($id <= 0) { return null; }
        if ($fresco || !isset(self::$cache[$id])) {
            self::$cache[$id] = DB::first('SELECT * FROM restaurants WHERE id = :id LIMIT 1', array('id' => $id));
        }
        return self::$cache[$id];
    }

    /** Olvida la copia en memoria tras modificar el restaurante. */
    public static function forget($id = null)
    {
        if ($id === null) { self::$cache = array(); }
        else { unset(self::$cache[(int)$id]); }
    }

    public static function findBySlug($slug)
    {
        $row = DB::first('SELECT * FROM restaurants WHERE slug = :s LIMIT 1', array('s' => (string)$slug));
        if ($row) { self::$cache[(int)$row['id']] = $row; }
        return $row;
    }

    public static function allActive()
    {
        return DB::all("SELECT * FROM restaurants ORDER BY name ASC");
    }

    /** ¿El restaurante puede recibir visitas públicas? */
    public static function isPublic(array $r)
    {
        if ($r['status'] === 'suspended') { return false; }
        if (!empty($r['plan_expires_at']) && $r['plan_expires_at'] < date('Y-m-d')) { return false; }
        return true;
    }

    /** Horario del día y estado abierto/cerrado en la zona horaria del local. */
    public static function openState(array $r)
    {
        $tz = new \DateTimeZone(!empty($r['timezone']) ? $r['timezone'] : 'America/Guatemala');
        $now = new \DateTime('now', $tz);
        $weekday = (int)$now->format('w');
        $row = DB::first('SELECT * FROM restaurant_hours WHERE restaurant_id = :r AND weekday = :w LIMIT 1',
            array('r' => (int)$r['id'], 'w' => $weekday));
        if (!$row || (int)$row['is_closed'] === 1 || empty($row['opens_at']) || empty($row['closes_at'])) {
            return array('open' => false, 'label' => 'Cerrado hoy', 'opens_at' => null, 'closes_at' => null);
        }
        $t = $now->format('H:i:s');
        $opens  = $row['opens_at'];
        $closes = $row['closes_at'];
        // Cierre después de medianoche (ej. 18:00 → 01:00).
        $open = ($closes > $opens) ? ($t >= $opens && $t < $closes) : ($t >= $opens || $t < $closes);
        return array(
            'open'      => $open,
            'label'     => $open ? 'Abierto ahora' : 'Cerrado',
            'opens_at'  => substr($opens, 0, 5),
            'closes_at' => substr($closes, 0, 5),
        );
    }

    public static function hours($restaurantId)
    {
        $rows = DB::all('SELECT * FROM restaurant_hours WHERE restaurant_id = :r ORDER BY weekday', array('r' => (int)$restaurantId));
        $byDay = array();
        foreach ($rows as $r) { $byDay[(int)$r['weekday']] = $r; }
        return $byDay;
    }

    public static function settings($restaurantId)
    {
        $out = array();
        foreach (DB::all('SELECT skey, svalue FROM restaurant_settings WHERE restaurant_id = :r', array('r' => (int)$restaurantId)) as $r) {
            $out[$r['skey']] = $r['svalue'];
        }
        return $out;
    }

    public static function setSetting($restaurantId, $key, $value)
    {
        DB::run('INSERT INTO restaurant_settings (restaurant_id, skey, svalue) VALUES (:r, :k, :v)
                 ON DUPLICATE KEY UPDATE svalue = :v2',
            array('r' => (int)$restaurantId, 'k' => $key, 'v' => $value, 'v2' => $value));
    }

    public static function uniqueSlug($name, $ignoreId = 0)
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;
        while (DB::value('SELECT id FROM restaurants WHERE slug = :s AND id <> :i LIMIT 1', array('s' => $slug, 'i' => (int)$ignoreId))) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    /** Modos de servicio habilitados como arreglo. */
    public static function modes(array $r)
    {
        $m = array_filter(array_map('trim', explode(',', (string)$r['service_modes'])));
        return $m ? $m : array('dine_in');
    }

    public static function langs(array $r)
    {
        $l = array_filter(array_map('trim', explode(',', (string)$r['langs'])));
        return $l ? $l : array('es');
    }

    /** Uso actual frente a los límites del plan. */
    public static function usage($restaurantId)
    {
        $r = self::find($restaurantId);
        $plan = $r && $r['plan_id'] ? DB::first('SELECT * FROM plans WHERE id = :p', array('p' => (int)$r['plan_id'])) : null;
        $monthStart = date('Y-m-01 00:00:00');
        return array(
            'plan'     => $plan,
            'products' => (int)DB::value('SELECT COUNT(*) FROM products WHERE restaurant_id = :r', array('r' => (int)$restaurantId), 0),
            'tables'   => (int)DB::value('SELECT COUNT(*) FROM tables WHERE restaurant_id = :r', array('r' => (int)$restaurantId), 0),
            'users'    => (int)DB::value('SELECT COUNT(*) FROM users WHERE restaurant_id = :r', array('r' => (int)$restaurantId), 0),
            'orders'   => (int)DB::value('SELECT COUNT(*) FROM orders WHERE restaurant_id = :r AND placed_at >= :d',
                            array('r' => (int)$restaurantId, 'd' => $monthStart), 0),
        );
    }

    /** ¿Se alcanzó el límite del plan para un recurso? */
    public static function limitReached($restaurantId, $resource)
    {
        $u = self::usage($restaurantId);
        if (!$u['plan']) { return false; }
        $map = array('products' => 'max_products', 'tables' => 'max_tables', 'users' => 'max_users', 'orders' => 'max_orders_month');
        if (!isset($map[$resource])) { return false; }
        $max = (int)$u['plan'][$map[$resource]];
        return $max > 0 && $u[$resource] >= $max;
    }
}
