<?php
declare(strict_types=1);

namespace Aurea\Core;

/** Sesión del panel, login con límite de intentos, 2FA y permisos por rol. */
final class Auth
{
    public const ROLES = ['admin' => 'Administrador', 'professional' => 'Profesional', 'reception' => 'Recepción'];

    // Capacidades por rol. 'own' => solo datos propios (se aplica en cada consulta).
    private const ABILITIES = [
        'admin' => ['*'],
        'reception' => ['dashboard', 'agenda', 'appointments', 'clients', 'payments', 'waitlist', 'messages', 'reviews', 'reports_basic', 'packages_sell'],
        'professional' => ['dashboard', 'agenda', 'appointments', 'clients', 'schedule_own', 'messages', 'reviews', 'reports_basic', 'profile'],
    ];

    private static ?array $user = null;

    public static function startSession(Request $r): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) { return; }
        $path = AUREA_ROOT . '/storage/sessions';
        if (is_dir($path) && is_writable($path)) { session_save_path($path); }
        session_name('aurea_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => base_path() === '' ? '/' : base_path() . '/',
            'secure' => $r->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '28800');
        session_start();
        $idle = max(5, Settings::int('session_idle_minutes', 120)) * 60;
        if (!empty($_SESSION['uid']) && !empty($_SESSION['last']) && time() - (int)$_SESSION['last'] > $idle) {
            self::logout();
            session_start();
            $_SESSION['flash_info'] = 'Tu sesión expiró por inactividad.';
        }
        if (!empty($_SESSION['uid'])) { $_SESSION['last'] = time(); }
    }

    public static function hash(string $pw): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            try { return password_hash($pw, PASSWORD_ARGON2ID); } catch (\Throwable $e) { /* usar bcrypt */ }
        }
        return password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function strongPassword(string $pw): ?string
    {
        if (strlen($pw) < 10) { return 'La contraseña debe tener al menos 10 caracteres.'; }
        if (!preg_match('/[a-z]/', $pw) || !preg_match('/[A-Z]/', $pw) || !preg_match('/\d/', $pw)) {
            return 'Usa mayúsculas, minúsculas y números en la contraseña.';
        }
        return null;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) { return self::$user ?: null; }
        if (empty($_SESSION['uid'])) { return null; }
        $u = Db::one('SELECT id,name,email,role,professional_id,totp_enabled,active FROM users WHERE id=?', [(int)$_SESSION['uid']]);
        if (!$u || !(int)$u['active']) {
            self::$user = [];
            unset($_SESSION['uid']);
            return null;
        }
        return self::$user = $u;
    }

    public static function id(): int { return (int)(self::user()['id'] ?? 0); }
    public static function role(): string { return (string)(self::user()['role'] ?? ''); }

    public static function can(string $ability): bool
    {
        $u = self::user();
        if (!$u) { return false; }
        $a = self::ABILITIES[$u['role']] ?? [];
        return in_array('*', $a, true) || in_array($ability, $a, true);
    }

    /** id del profesional al que está limitado el usuario (null = ve todos). */
    public static function scopeProfessional(): ?int
    {
        $u = self::user();
        if ($u && $u['role'] === 'professional') {
            return (int)($u['professional_id'] ?? 0) ?: -1;
        }
        return null;
    }

    public static function recordAttempt(string $ip, string $username, bool $ok): void
    {
        Db::insert('login_attempts', ['ip' => $ip, 'username' => mb_strtolower(substr($username, 0, 190)), 'success' => $ok ? 1 : 0, 'created_at' => date('Y-m-d H:i:s')]);
    }

    /** ¿Bloqueado por demasiados intentos fallidos? (IP+usuario: 5 / IP: 25 en 15 min) */
    public static function isLocked(string $ip, string $username): bool
    {
        $since = date('Y-m-d H:i:s', time() - 900);
        $u = mb_strtolower(substr($username, 0, 190));
        $a = (int)Db::val('SELECT COUNT(*) FROM login_attempts WHERE ip=? AND username=? AND success=0 AND created_at>=?', [$ip, $u, $since]);
        if ($a >= 5) { return true; }
        $b = (int)Db::val('SELECT COUNT(*) FROM login_attempts WHERE ip=? AND success=0 AND created_at>=?', [$ip, $since]);
        return $b >= 25;
    }

    /**
     * Paso 1 del login. Devuelve: 'ok' | '2fa' | 'locked' | 'invalid'.
     */
    public static function attempt(Request $r, string $email, string $password): string
    {
        $ip = $r->ip();
        $email = mb_strtolower(trim($email));
        if (self::isLocked($ip, $email)) { return 'locked'; }
        $u = Db::one('SELECT * FROM users WHERE email=? AND active=1', [$email]);
        // Verificación aunque no exista el usuario, para igualar tiempos de respuesta
        $hash = $u['password_hash'] ?? '$2y$12$abcdefghijklmnopqrstuuJ1gX0m9pYk4p0xq6c0H4mN7e6rYb3Ie';
        $okPw = password_verify($password, $hash);
        if (!$u || !$okPw) {
            self::recordAttempt($ip, $email, false);
            return 'invalid';
        }
        if ((int)$u['totp_enabled']) {
            session_regenerate_id(true);
            $_SESSION['pending_2fa'] = (int)$u['id'];
            $_SESSION['pending_2fa_at'] = time();
            return '2fa';
        }
        self::finishLogin($r, $u);
        return 'ok';
    }

    public static function verify2fa(Request $r, string $code): bool
    {
        $uid = (int)($_SESSION['pending_2fa'] ?? 0);
        if (!$uid || time() - (int)($_SESSION['pending_2fa_at'] ?? 0) > 300) { return false; }
        $u = Db::one('SELECT * FROM users WHERE id=? AND active=1', [$uid]);
        if (!$u) { return false; }
        $key = '2fa:' . $uid;
        if (self::isLocked($r->ip(), $key)) { return false; }
        if (!Totp::verify((string)$u['totp_secret'], $code)) {
            self::recordAttempt($r->ip(), $key, false);
            return false;
        }
        unset($_SESSION['pending_2fa'], $_SESSION['pending_2fa_at']);
        self::finishLogin($r, $u);
        return true;
    }

    private static function finishLogin(Request $r, array $u): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        $_SESSION['last'] = time();
        unset($_SESSION['csrf']);
        self::$user = null;
        self::recordAttempt($r->ip(), (string)$u['email'], true);
        Db::update('users', (int)$u['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        if (password_needs_rehash((string)$u['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
            // Rehash oportunista no necesario para el flujo; se omite para no bloquear el login
        }
        Audit::log('login', 'user', (int)$u['id'], '', $u);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => (bool)$p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
            }
            session_destroy();
        }
        self::$user = null;
    }
}
