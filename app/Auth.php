<?php
/**
 * Authentication, authorization and login rate limiting.
 */
class Auth
{
    private static ?array $user = null;

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user ?: null;
        }
        $id = $_SESSION['uid'] ?? null;
        if (!$id) {
            self::$user = [];
            return null;
        }
        $row = Database::one(
            "SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.status = 'active'",
            [$id]
        );
        self::$user = $row ?: [];
        return $row ?: null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isSuper(): bool
    {
        $u = self::user();
        return $u && $u['role_slug'] === 'super_admin';
    }

    /** Does the current user hold a permission? Super admin holds all. */
    public static function can(string $permission): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        if ($u['role_slug'] === 'super_admin') {
            return true;
        }
        $has = Database::scalar(
            "SELECT 1 FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ? AND p.slug = ? LIMIT 1",
            [$u['role_id'], $permission]
        );
        return (bool)$has;
    }

    public static function require(string $permission): void
    {
        if (!self::can($permission)) {
            http_response_code(403);
            exit('403 · No tiene permisos para acceder a este módulo.');
        }
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect(base_url('admin/login.php'));
        }
    }

    // ---- Login flow ---------------------------------------------------------

    public static function rateLimited(string $ip): bool
    {
        $max      = (int)Settings::get('login_max_attempts', 8);
        $lockMin  = (int)Settings::get('login_lockout_min', 15);
        $count = (int)Database::scalar(
            "SELECT COUNT(*) FROM login_attempts
             WHERE ip = ? AND success = 0 AND created_at > (NOW() - INTERVAL ? MINUTE)",
            [$ip, $lockMin]
        );
        return $count >= $max;
    }

    public static function recordAttempt(string $ip, ?string $username, bool $success): void
    {
        Database::q(
            "INSERT INTO login_attempts (ip, username, success) VALUES (?,?,?)",
            [$ip, $username, $success ? 1 : 0]
        );
    }

    public static function attempt(string $identifier, string $password): bool
    {
        $user = Database::one(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active' LIMIT 1",
            [$identifier, $identifier]
        );
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::q("UPDATE users SET password_hash = ? WHERE id = ?",
                [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        Security::regenerate();
        $_SESSION['uid'] = (int)$user['id'];
        Database::q("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
        self::$user = null;
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
