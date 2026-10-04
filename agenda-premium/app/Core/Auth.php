<?php
declare(strict_types=1);

namespace App\Core;

/** Autenticación, roles y permisos del panel. */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    /** Áreas del panel a las que puede entrar cada rol. */
    private const AREAS = [
        'admin' => ['*'],
        'reception' => ['dashboard', 'bookings', 'calendar', 'clients', 'messages', 'payments', 'waitlist', 'search', 'profile'],
        'host' => ['dashboard', 'bookings', 'calendar', 'availability', 'clients', 'messages', 'search', 'profile', 'calendars'],
    ];

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = (int) Session::get('uid', 0);
            if ($id > 0) {
                $u = Db::one('SELECT * FROM users WHERE id = ? AND active = 1', [$id]);
                self::$user = $u ?: null;
                if (!$u) {
                    Session::forget('uid');
                }
            }
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): string
    {
        $u = self::user();
        return $u ? (string) $u['role'] : '';
    }

    public static function can(string $area): bool
    {
        $r = self::role();
        if ($r === '') {
            return false;
        }
        $a = self::AREAS[$r] ?? [];
        return in_array('*', $a, true) || in_array($area, $a, true);
    }

    /** id del anfitrión ligado al usuario (rol host) o null. */
    public static function hostId(): ?int
    {
        $u = self::user();
        if (!$u) {
            return null;
        }
        $id = Db::val('SELECT id FROM hosts WHERE user_id = ?', [$u['id']]);
        return $id === null ? null : (int) $id;
    }

    /**
     * Filtro de alcance: null = ve todo (admin/recepción); int = solo ese anfitrión (rol host).
     * Un anfitrión sin ficha ligada recibe 0 (no ve nada).
     */
    public static function scopedHostId(): ?int
    {
        if (self::role() !== 'host') {
            return null;
        }
        return self::hostId() ?? 0;
    }

    /** ¿Puede el usuario actual operar sobre un anfitrión dado? (protección IDOR) */
    public static function canAccessHost(int $hostId): bool
    {
        $s = self::scopedHostId();
        return $s === null || $s === $hostId;
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::set('uid', $userId);
        Session::forget('pending_uid');
        Session::set('_csrf', Str::token(16));
        Db::update('users', ['last_login_at' => Clock::utc()], 'id = ?', [$userId]);
        self::$loaded = false;
        self::$user = null;
    }

    public static function logout(): void
    {
        self::$user = null;
        self::$loaded = false;
        Session::destroy();
    }

    /** Número de fallos recientes (15 min) para este correo o esta IP. */
    public static function isLockedOut(string $email, string $ip): bool
    {
        $since = Clock::utc(Clock::now() - 900);
        $byEmail = (int) Db::val('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND created_at >= ?', [$email, $since]);
        $byIp = (int) Db::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at >= ?', [$ip, $since]);
        return $byEmail >= 5 || $byIp >= 20;
    }

    private static function record(string $email, string $ip, bool $ok): void
    {
        Db::insert('login_attempts', ['email' => substr($email, 0, 190), 'ip' => substr($ip, 0, 45), 'success' => $ok ? 1 : 0, 'created_at' => Clock::utc()]);
    }

    /**
     * Primer paso: correo y contraseña.
     * @return array{ok:bool, totp?:bool, error?:string}
     */
    public static function attempt(string $email, string $password, string $ip): array
    {
        $email = strtolower(trim($email));
        if (self::isLockedOut($email, $ip)) {
            return ['ok' => false, 'error' => 'Demasiados intentos. Espera 15 minutos e inténtalo de nuevo.'];
        }
        $u = Db::one('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
        $valid = $u && !empty($u['password_hash']) && password_verify($password, (string) $u['password_hash']);
        if (!$valid) {
            if (!$u) {
                // consume tiempo similar para no revelar si el correo existe
                password_verify($password, '$2y$12$ZjvK6mYZm1xhJoh3ixwEYebedzzhsBme7SDxW/TpcbA81DSfo1w32');
            }
            self::record($email, $ip, false);
            return ['ok' => false, 'error' => 'Correo o contraseña incorrectos.'];
        }
        if (password_needs_rehash((string) $u['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
            Db::update('users', ['password_hash' => Crypto::hashPassword($password)], 'id = ?', [$u['id']]);
        }
        if ((int) $u['totp_enabled'] === 1) {
            Session::set('pending_uid', (int) $u['id']);
            Session::set('pending_at', Clock::now());
            return ['ok' => true, 'totp' => true];
        }
        self::record($email, $ip, true);
        self::login((int) $u['id']);
        return ['ok' => true];
    }

    /** Segundo paso (2FA TOTP opcional). */
    public static function completeTotp(string $code, string $ip): array
    {
        $uid = (int) Session::get('pending_uid', 0);
        $at = (int) Session::get('pending_at', 0);
        $u = $uid ? Db::one('SELECT * FROM users WHERE id = ? AND active = 1', [$uid]) : null;
        if (!$u || Clock::now() - $at > 600) {
            Session::forget('pending_uid');
            return ['ok' => false, 'error' => 'La sesión de verificación caducó. Inicia sesión de nuevo.', 'restart' => true];
        }
        if (self::isLockedOut((string) $u['email'], $ip)) {
            return ['ok' => false, 'error' => 'Demasiados intentos. Espera 15 minutos e inténtalo de nuevo.'];
        }
        $secret = (string) $u['totp_secret'];
        if (strncmp($secret, 'v1:', 3) === 0) {
            $secret = (string) Crypto::decrypt($secret);
        }
        if (!Totp::verify($secret, $code)) {
            self::record((string) $u['email'], $ip, false);
            return ['ok' => false, 'error' => 'El código no es correcto.'];
        }
        self::record((string) $u['email'], $ip, true);
        self::login($uid);
        return ['ok' => true];
    }

    public static function audit(string $action, ?string $entity = null, $entityId = null, ?string $detail = null): void
    {
        global $__request;
        $u = self::user();
        try {
            Db::insert('audit_log', [
                'user_id' => $u['id'] ?? null,
                'user_label' => $u ? substr($u['name'] . ' <' . $u['email'] . '>', 0, 160) : 'sistema',
                'action' => substr($action, 0, 60),
                'entity' => $entity,
                'entity_id' => $entityId === null ? null : substr((string) $entityId, 0, 40),
                'detail' => $detail === null ? null : substr($detail, 0, 500),
                'ip_trunc' => $__request instanceof Request ? Str::ipTrunc($__request->ip()) : null,
                'created_at' => Clock::utc(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo registrar la auditoría', $e);
        }
    }
}
