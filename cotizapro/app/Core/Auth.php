<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public const ROLE_SUPER  = 'superadmin';
    public const ROLE_ADMIN  = 'admin';
    public const ROLE_SELLER = 'vendedor';
    public const ROLE_VIEWER = 'visor';

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        App::startSession();
        $id = (int) ($_SESSION['uid'] ?? 0);
        if ($id <= 0) {
            return self::$user = null;
        }
        // Ata la sesión al navegador para dificultar el robo de cookies.
        if (($_SESSION['fp'] ?? '') !== Security::fingerprint()) {
            self::logout();
            return self::$user = null;
        }
        $u = DB::one(
            'SELECT u.*, c.slug AS company_slug, c.name AS company_name, c.status AS company_status,
                    c.expires_at AS company_expires
             FROM users u
             LEFT JOIN companies c ON c.id = u.company_id
             WHERE u.id = ? AND u.status = "activo" LIMIT 1',
            [$id]
        );
        if (!$u) {
            self::logout();
            return self::$user = null;
        }
        return self::$user = $u;
    }

    public static function id(): int
    {
        return (int) (self::user()['id'] ?? 0);
    }

    public static function companyId(): ?int
    {
        $c = self::user()['company_id'] ?? null;
        return $c ? (int) $c : null;
    }

    public static function role(): string
    {
        return (string) (self::user()['role'] ?? '');
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isSuper(): bool
    {
        return self::role() === self::ROLE_SUPER;
    }

    public static function isAdmin(): bool
    {
        return self::role() === self::ROLE_ADMIN;
    }

    public static function isSeller(): bool
    {
        return self::role() === self::ROLE_SELLER;
    }

    public static function isViewer(): bool
    {
        return self::role() === self::ROLE_VIEWER;
    }

    /** ¿Puede escribir (crear/editar/borrar) en el panel de empresa? */
    public static function canWrite(): bool
    {
        return in_array(self::role(), [self::ROLE_ADMIN, self::ROLE_SELLER], true);
    }

    public static function canManageCompany(): bool
    {
        return self::role() === self::ROLE_ADMIN;
    }

    public static function login(array $user, bool $twoFactorPending = false): void
    {
        App::startSession();
        session_regenerate_id(true);
        if ($twoFactorPending) {
            $_SESSION['2fa_uid'] = (int) $user['id'];
            $_SESSION['2fa_at']  = time();
            return;
        }
        unset($_SESSION['2fa_uid'], $_SESSION['2fa_at']);
        $_SESSION['uid']   = (int) $user['id'];
        $_SESSION['fp']    = Security::fingerprint();
        $_SESSION['_last'] = time();
        self::$user = null;
        self::$loaded = false;
        DB::update('users', ['last_login_at' => nowSql(), 'last_login_ip' => App::ip()], 'id = :id', ['id' => (int) $user['id']]);
    }

    public static function logout(): void
    {
        App::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 42000] + $p);
        }
        session_destroy();
        self::$user = null;
        self::$loaded = true;
    }

    /** Exige sesión iniciada; si no, va al login. */
    public static function require(): array
    {
        $u = self::user();
        if (!$u) {
            App::startSession();
            $_SESSION['intended'] = Request::path();
            if (Request::isAjax()) {
                jsonOut(['ok' => false, 'error' => 'Sesión no iniciada'], 401);
            }
            redirect('/entrar');
        }
        return $u;
    }

    /** Exige panel de empresa activo (y no vencido). */
    public static function requireCompany(): array
    {
        $u = self::require();
        if ($u['role'] === self::ROLE_SUPER) {
            redirect('/super');
        }
        if (!$u['company_id']) {
            self::logout();
            redirect('/entrar');
        }
        if ($u['company_status'] !== 'activa') {
            ErrorHandler::render(403);
        }
        if (!empty($u['company_expires']) && strtotime((string) $u['company_expires']) < strtotime('today')) {
            Flash::error('La suscripción de la empresa venció. Contacte al administrador de la plataforma.');
            ErrorHandler::render(402);
        }
        return $u;
    }

    public static function requireSuper(): array
    {
        $u = self::require();
        if ($u['role'] !== self::ROLE_SUPER) {
            ErrorHandler::render(403);
        }
        return $u;
    }

    /** Exige uno de los roles indicados dentro del panel de empresa. */
    public static function requireRole(string ...$roles): array
    {
        $u = self::requireCompany();
        if (!in_array($u['role'], $roles, true)) {
            if (Request::isAjax()) {
                jsonOut(['ok' => false, 'error' => 'No tiene permisos para esta acción.'], 403);
            }
            ErrorHandler::render(403);
        }
        return $u;
    }

    /** Un vendedor solo ve lo suyo: devuelve el id a filtrar o null. */
    public static function ownerFilter(): ?int
    {
        return self::isSeller() ? self::id() : null;
    }
}
