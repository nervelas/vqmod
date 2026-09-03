<?php
declare(strict_types=1);

/** Autenticacion del panel de administracion. */
final class Auth
{
    private const KEY = '_admin_user';
    private const MAX_ATTEMPTS = 6;
    private const LOCK_SECONDS = 600;

    public static function attempt(string $username, string $password): bool
    {
        if (self::isLocked()) {
            return false;
        }

        $user = Database::first(
            'SELECT * FROM users WHERE (username = :u OR email = :u) AND status = 1 LIMIT 1',
            ['u' => $username]
        );

        if ($user === null || !password_verify($password, (string) $user['password'])) {
            self::registerFailure();
            return false;
        }

        if (password_needs_rehash((string) $user['password'], PASSWORD_DEFAULT)) {
            Database::update('users', ['password' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $user['id']]);
        }

        session_regenerate_id(true);
        unset($_SESSION['_login_fails'], $_SESSION['_login_lock']);

        $_SESSION[self::KEY] = [
            'id'    => (int) $user['id'],
            'name'  => (string) $user['name'],
            'username' => (string) $user['username'],
            'email' => (string) $user['email'],
            'role'  => (string) $user['role'],
        ];

        Database::update('users', ['last_login' => Database::now()], 'id = :id', ['id' => $user['id']]);
        return true;
    }

    public static function user(): ?array
    {
        $u = $_SESSION[self::KEY] ?? null;
        return is_array($u) ? $u : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u !== null && ($u['role'] ?? '') === 'admin';
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('admin/login.php');
        }
    }

    public static function logout(): void
    {
        unset($_SESSION[self::KEY]);
        session_regenerate_id(true);
    }

    public static function isLocked(): bool
    {
        $lock = (int) ($_SESSION['_login_lock'] ?? 0);
        return $lock > time();
    }

    public static function lockRemaining(): int
    {
        return max(0, (int) ($_SESSION['_login_lock'] ?? 0) - time());
    }

    private static function registerFailure(): void
    {
        $fails = (int) ($_SESSION['_login_fails'] ?? 0) + 1;
        $_SESSION['_login_fails'] = $fails;
        if ($fails >= self::MAX_ATTEMPTS) {
            $_SESSION['_login_lock']  = time() + self::LOCK_SECONDS;
            $_SESSION['_login_fails'] = 0;
        }
    }
}
