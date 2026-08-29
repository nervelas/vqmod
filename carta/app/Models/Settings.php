<?php
namespace MenuGold\Models;

use MenuGold\Core\DB;

/**
 * Los ajustes del restaurante: identidad, tema, modos de servicio, pagos.
 *
 * Es una tabla clave/valor con caché en memoria, así que leer un ajuste en
 * mitad de una vista no cuesta una consulta más.
 */
final class Settings
{
    /** @var array|null */
    private static $cache = null;

    /** Valores de fábrica: todo lo que la aplicación espera encontrar. */
    public static function defaults()
    {
        return array(
            'name'            => 'Mi restaurante',
            'tagline'         => '',
            'description'     => '',
            'logo'            => '',
            'cover'           => '',
            'phone'           => '',
            'whatsapp'        => '',
            'email'           => '',
            'address'         => '',
            'city'            => '',
            'map_url'         => '',
            'review_url'      => '',
            'instagram'       => '',
            'facebook'        => '',
            'currency'        => 'Q',
            'tax_rate'        => '0',
            'tax_included'    => '1',
            'tip_enabled'     => '1',
            'tip_options'     => '10,15,20',
            'service_modes'   => 'dine_in,takeaway,delivery',
            'order_mode'      => 'order',      // order | catalog | whatsapp
            'theme'           => 'brasa',
            'font_combo'      => 'editorial',
            'primary_color'   => '#D8B26E',
            'accent_color'    => '#C4502B',
            'lang_default'    => 'es',
            'langs'           => 'es,en',
            'timezone'        => 'America/Guatemala',
            'bank_info'       => '',
            'payment_link'    => '',
            'payment_methods' => 'efectivo,tarjeta,transferencia',
            'min_delivery'    => '0',
            'printer_width'   => '80',
            'kds_sound'       => '1',
            'kds_late_min'    => '18',
            'smtp_host'       => '',
            'smtp_port'       => '587',
            'smtp_user'       => '',
            'smtp_pass'       => '',
            'smtp_secure'     => 'tls',
            'smtp_from'       => '',
        );
    }

    public static function all()
    {
        if (self::$cache !== null) { return self::$cache; }
        $out = self::defaults();
        try {
            foreach (DB::all('SELECT `key`, `value` FROM mg_settings') as $row) {
                $out[$row['key']] = $row['value'];
            }
        } catch (\Throwable $e) {
            // Antes de instalar todavía no hay tabla: los valores de fábrica bastan.
        }
        self::$cache = $out;
        return $out;
    }

    public static function get($key, $default = null)
    {
        $all = self::all();
        if (array_key_exists($key, $all) && $all[$key] !== '') { return $all[$key]; }
        if ($default !== null) { return $default; }
        return array_key_exists($key, $all) ? $all[$key] : '';
    }

    public static function int($key, $default = 0)
    {
        $v = self::get($key, (string)$default);
        return is_numeric($v) ? (int)$v : $default;
    }

    public static function float($key, $default = 0.0)
    {
        $v = self::get($key, (string)$default);
        return is_numeric($v) ? (float)$v : $default;
    }

    public static function bool($key, $default = false)
    {
        $v = self::get($key, $default ? '1' : '0');
        return $v === '1' || $v === 'true' || $v === 1 || $v === true;
    }

    /** Lista separada por comas, ya limpia. */
    public static function list($key)
    {
        $raw = (string)self::get($key, '');
        if ($raw === '') { return array(); }
        return array_values(array_filter(array_map('trim', explode(',', $raw)), 'strlen'));
    }

    public static function set($key, $value)
    {
        DB::run(
            'INSERT INTO mg_settings (`key`, `value`) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            array('k' => (string)$key, 'v' => (string)$value)
        );
        if (self::$cache !== null) { self::$cache[$key] = (string)$value; }
    }

    public static function setMany(array $pairs)
    {
        foreach ($pairs as $k => $v) { self::set($k, $v); }
    }

    public static function forget()
    {
        self::$cache = null;
    }

    /** ¿Se aceptan pedidos, o el menú es solo catálogo? */
    public static function takesOrders()
    {
        return self::get('order_mode', 'order') === 'order';
    }

    public static function modes()
    {
        $m = self::list('service_modes');
        return $m ? $m : array('dine_in');
    }

    public static function hasMode($mode)
    {
        return in_array($mode, self::modes(), true);
    }

    /** Horario de hoy y si está abierto ahora mismo. */
    public static function openNow()
    {
        $tz = new \DateTimeZone(self::get('timezone', 'America/Guatemala'));
        $now = new \DateTime('now', $tz);
        $weekday = (int)$now->format('w');
        $row = DB::first('SELECT * FROM mg_hours WHERE weekday = :d', array('d' => $weekday));
        if (!$row || (int)$row['is_closed'] === 1) {
            return array('open' => false, 'opens_at' => '', 'closes_at' => '');
        }
        $hm = $now->format('H:i:s');
        $abre = (string)$row['opens_at'];
        $cierra = (string)$row['closes_at'];
        // Un cierre pasada la medianoche (01:00) sigue siendo el mismo turno.
        $abierto = $cierra > $abre
            ? ($hm >= $abre && $hm < $cierra)
            : ($hm >= $abre || $hm < $cierra);
        return array('open' => $abierto, 'opens_at' => $abre, 'closes_at' => $cierra);
    }

    public static function hours()
    {
        $out = array();
        foreach (DB::all('SELECT * FROM mg_hours ORDER BY weekday') as $r) {
            $out[(int)$r['weekday']] = $r;
        }
        return $out;
    }
}
