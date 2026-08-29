<?php
namespace MenuGold\Core;

/** Sesión del personal del restaurante. Cuatro roles, sin superadministrador. */
final class Auth
{
    const ROLE_OWNER   = 'owner';
    const ROLE_MANAGER = 'manager';
    const ROLE_KITCHEN = 'kitchen';
    const ROLE_WAITER  = 'waiter';

    /** @var array|null */
    private static $user = null;
    /** @var bool */
    private static $loaded = false;

    public static function user()
    {
        if (self::$loaded) { return self::$user; }
        self::$loaded = true;
        $id = (int)Session::get('uid', 0);
        if ($id <= 0) { return null; }
        $row = DB::first('SELECT * FROM mg_users WHERE id = :id AND is_active = 1 LIMIT 1', array('id' => $id));
        if (!$row) {
            Session::forget('uid');
            return null;
        }
        // La huella evita que sirva una cookie de sesión robada desde otro navegador.
        $fp = Session::get('ufp');
        if ($fp !== null && !hash_equals((string)$fp, self::fingerprint())) {
            Session::destroy();
            return null;
        }
        self::$user = $row;
        return $row;
    }

    public static function fingerprint()
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        return hash('sha256', $ua . '|' . Config::get('security.app_key', ''));
    }

    public static function login(array $user)
    {
        Session::regenerate();
        Session::set('uid', (int)$user['id']);
        Session::set('ufp', self::fingerprint());
        Csrf::rotate();
        self::$user = null;
        self::$loaded = false;
        DB::update('mg_users', array('last_login_at' => date('Y-m-d H:i:s')), 'id = :id', array('id' => (int)$user['id']));
    }

    public static function logout()
    {
        Session::destroy();
        self::$user = null;
        self::$loaded = true;
    }

    public static function check() { return self::user() !== null; }

    public static function id()
    {
        $u = self::user();
        return $u ? (int)$u['id'] : 0;
    }

    public static function role()
    {
        $u = self::user();
        return $u ? (string)$u['role'] : '';
    }

    public static function name()
    {
        $u = self::user();
        return $u ? (string)$u['name'] : '';
    }

    /** Jerarquía de permisos: cada rol incluye lo que puede hacer. */
    public static function can($ability)
    {
        $role = self::role();
        if ($role === '') { return false; }
        $matrix = array(
            self::ROLE_OWNER   => array('menu', 'orders', 'kds', 'waiter', 'tables', 'reports', 'settings', 'customers', 'users', 'backup'),
            self::ROLE_MANAGER => array('menu', 'orders', 'kds', 'waiter', 'tables', 'reports', 'customers'),
            self::ROLE_KITCHEN => array('kds', 'orders'),
            self::ROLE_WAITER  => array('waiter', 'orders', 'tables'),
        );
        return isset($matrix[$role]) && in_array($ability, $matrix[$role], true);
    }

    /** Página de inicio según el rol, tras iniciar sesión. */
    public static function homeFor($role)
    {
        switch ($role) {
            case self::ROLE_KITCHEN: return '/panel/cocina';
            case self::ROLE_WAITER:  return '/panel/mesero';
            default:                 return '/panel';
        }
    }
}
