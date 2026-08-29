<?php
namespace MenuGold\Core;

final class Session
{
    /** @var bool */
    private static $started = false;

    public static function start()
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }
        $dir = MG_STORAGE . '/sessions';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        $secure = Url::scheme() === 'https';
        session_name('mgsid');
        $params = array(
            'lifetime' => 0,
            'path'     => Url::basePath() === '' ? '/' : Url::basePath() . '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        );
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($params);
        } else {
            session_set_cookie_params($params['lifetime'], $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        @session_start();
        self::$started = true;

        $ttl = (int)Config::get('security.session_ttl', 7200);
        $now = time();
        if (isset($_SESSION['_last']) && ($now - (int)$_SESSION['_last']) > $ttl) {
            self::destroy();
            @session_start();
        }
        $_SESSION['_last'] = $now;

        // Rotación periódica del identificador de sesión.
        if (!isset($_SESSION['_born'])) {
            $_SESSION['_born'] = $now;
        } elseif ($now - (int)$_SESSION['_born'] > 1800) {
            self::regenerate();
        }
    }

    public static function regenerate()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
            $_SESSION['_born'] = time();
        }
    }

    public static function get($key, $default = null)
    {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }

    public static function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public static function has($key)
    {
        return isset($_SESSION[$key]);
    }

    public static function forget($key)
    {
        unset($_SESSION[$key]);
    }

    public static function pull($key, $default = null)
    {
        $v = self::get($key, $default);
        self::forget($key);
        return $v;
    }

    public static function destroy()
    {
        $_SESSION = array();
        if (ini_get('session.use_cookies') && !headers_sent()) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], !empty($p['secure']), !empty($p['httponly']));
        }
        @session_destroy();
    }

    /** Mensajes de un solo uso para la interfaz. */
    public static function flash($type, $message = null)
    {
        if ($message === null) {
            $all = self::get('_flash', array());
            self::forget('_flash');
            return isset($all[$type]) ? $all[$type] : array();
        }
        $all = self::get('_flash', array());
        $all[$type][] = $message;
        self::set('_flash', $all);
        return null;
    }

    public static function flashAll()
    {
        $all = self::get('_flash', array());
        self::forget('_flash');
        return is_array($all) ? $all : array();
    }
}
