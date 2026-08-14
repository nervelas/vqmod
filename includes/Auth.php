<?php
/**
 * Administrator authentication.
 */

declare(strict_types=1);

class Auth
{
    /** Attempt login by username-or-email + password. Returns true on success. */
    public static function attempt(string $login, string $password): bool
    {
        $admin = Database::first(
            'SELECT * FROM admins WHERE (username = :l OR email = :l) AND is_active = 1 LIMIT 1',
            ['l' => $login]
        );
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            // Constant-ish time: run a dummy verify when user not found.
            if (!$admin) { password_verify($password, '$2y$10$usesomesillystringforsalt0000000000000000000000000000000'); }
            return false;
        }

        // Rehash if algorithm/cost changed.
        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('admins', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => $admin['id']]);
        }

        session_regenerate_id(true);
        $_SESSION['admin_id']    = (int)$admin['id'];
        $_SESSION['admin_name']  = $admin['name'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_login_at'] = time();

        Database::update('admins', ['last_login' => date('Y-m-d H:i:s')], ['id' => $admin['id']]);
        self::log('login', 'Inicio de sesión');
        return true;
    }

    public static function check(): bool
    {
        if (empty($_SESSION['admin_id'])) { return false; }
        // Idle timeout: 2 hours.
        if (isset($_SESSION['admin_login_at']) && (time() - (int)$_SESSION['admin_login_at']) > 7200) {
            self::logout();
            return false;
        }
        $_SESSION['admin_login_at'] = time();
        return true;
    }

    public static function user(): ?array
    {
        if (!self::check()) { return null; }
        return [
            'id'    => $_SESSION['admin_id'],
            'name'  => $_SESSION['admin_name'] ?? '',
            'email' => $_SESSION['admin_email'] ?? '',
        ];
    }

    /** Guard: redirect to login if not authenticated. */
    public static function require(): void
    {
        if (!self::check()) {
            redirect('admin/login.php');
        }
    }

    public static function logout(): void
    {
        if (!empty($_SESSION['admin_id'])) {
            self::log('logout', 'Cierre de sesión');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /** Write an entry to the admin activity log. */
    public static function log(string $action, string $detail = ''): void
    {
        try {
            Database::insert('admin_logs', [
                'admin_id' => $_SESSION['admin_id'] ?? null,
                'action'   => $action,
                'detail'   => mb_substr($detail, 0, 255),
                'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            error_log('admin_log failed: ' . $e->getMessage());
        }
    }
}
