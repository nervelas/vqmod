<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Autenticacion y control de acceso.
 * Roles: superadmin | dueno | admin | cocina | mesero
 */
final class Auth
{
    public const ROLES = ['superadmin', 'dueno', 'admin', 'cocina', 'mesero'];

    /** Permisos por rol. '*' = todo dentro de su restaurante. */
    public const PERMISOS = [
        'superadmin' => ['*', 'plataforma'],
        'dueno'      => ['*'],
        'admin'      => ['menu', 'mesas', 'pedidos', 'kds', 'mesero', 'clientes', 'cupones', 'reportes', 'config', 'usuarios', 'auditoria'],
        'cocina'     => ['kds', 'pedidos.ver'],
        'mesero'     => ['mesero', 'pedidos', 'kds', 'clientes', 'menu.ver'],
    ];

    private static ?array $user = null;
    private static bool $loaded = false;

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$loaded) return self::$user;
        self::$loaded = true;
        $id = (int)Session::get('user_id', 0);
        if ($id <= 0) return null;
        try {
            $u = DB::one(
                'SELECT u.*, r.slug AS restaurante_slug, r.nombre AS restaurante_nombre, r.estado AS restaurante_estado
                 FROM users u LEFT JOIN restaurants r ON r.id = u.restaurant_id
                 WHERE u.id = :id AND u.activo = 1 LIMIT 1',
                ['id' => $id]
            );
        } catch (\Throwable $e) {
            return null;
        }
        if (!$u) { Session::destroy(); return null; }
        self::$user = $u;
        return $u;
    }

    public static function check(): bool { return self::user() !== null; }
    public static function id(): int { return (int)(self::user()['id'] ?? 0); }
    public static function rol(): string { return (string)(self::user()['rol'] ?? ''); }
    public static function nombre(): string { return (string)(self::user()['nombre'] ?? ''); }

    public static function restaurantId(): int
    {
        return (int)(self::user()['restaurant_id'] ?? 0);
    }

    public static function isSuper(): bool { return self::rol() === 'superadmin'; }
    public static function isOwner(): bool { return in_array(self::rol(), ['dueno', 'admin'], true); }

    public static function can(string $permiso): bool
    {
        $rol = self::rol();
        if ($rol === '') return false;
        if ($rol === 'superadmin') return true;
        $lista = self::PERMISOS[$rol] ?? [];
        if (in_array('*', $lista, true) && $permiso !== 'plataforma') return true;
        if (in_array($permiso, $lista, true)) return true;
        // permiso.ver satisface permiso base de solo lectura
        $base = explode('.', $permiso)[0];
        return in_array($base, $lista, true);
    }

    public static function require(string $permiso): void
    {
        if (!self::check()) {
            Session::set('_intended', Request::fullUrl());
            redirect('ingresar');
        }
        if (!self::can($permiso)) {
            throw HttpException::forbidden();
        }
    }

    /**
     * Intento de ingreso. Devuelve [ok, mensaje].
     * @return array{0:bool,1:string}
     */
    public static function attempt(string $identidad, string $password, bool $recordar = false): array
    {
        $ip  = client_ip();
        $key = 'login:' . $ip . ':' . mb_strtolower($identidad);
        $rl  = RateLimit::hit($key, 5, 900);
        if (!$rl['permitido']) {
            $min = (int)ceil($rl['espera'] / 60);
            return [false, "Demasiados intentos fallidos. Intenta de nuevo en {$min} minuto(s)."];
        }

        $u = DB::one(
            'SELECT * FROM users WHERE (email = :a OR usuario = :b) LIMIT 1',
            ['a' => mb_strtolower($identidad), 'b' => $identidad]
        );
        $generico = 'Usuario o contraseña incorrectos.';
        if (!$u) {
            password_verify($password, '$2y$12$usuarioinexistenteusuarioinexistenteusuarioinexisten');
            return [false, $generico];
        }
        if (!password_verify($password, (string)$u['password_hash'])) {
            return [false, $generico];
        }
        if ((int)$u['activo'] !== 1) {
            return [false, 'Tu usuario está desactivado. Contacta al administrador.'];
        }
        if (!empty($u['restaurant_id'])) {
            $r = DB::one('SELECT estado, vence_el, nombre FROM restaurants WHERE id=:id', ['id' => (int)$u['restaurant_id']]);
            if (!$r) return [false, 'El restaurante asignado ya no existe.'];
            if ($r['estado'] === 'suspendido') {
                return [false, 'La cuenta del restaurante está suspendida. Contacta a soporte.'];
            }
            if (!empty($r['vence_el']) && strtotime((string)$r['vence_el']) < strtotime(date('Y-m-d'))) {
                return [false, 'La suscripción del restaurante venció el ' . dt((string)$r['vence_el'], 'd/m/Y') . '. Contacta a soporte.'];
            }
        }

        if (Security::needsRehash((string)$u['password_hash'])) {
            DB::update('users', ['password_hash' => Security::hashPassword($password)], 'id=:id', ['id' => (int)$u['id']]);
        }

        RateLimit::clear($key);
        self::login($u, $recordar);
        return [true, 'Bienvenido, ' . $u['nombre'] . '.'];
    }

    public static function login(array $u, bool $recordar = false): void
    {
        Session::regenerate();
        Csrf::rotate();
        Session::set('user_id', (int)$u['id']);
        Session::set('user_rol', (string)$u['rol']);
        Session::set('user_rest', (int)($u['restaurant_id'] ?? 0));
        Session::set('_last', time());
        self::$user = null;
        self::$loaded = false;

        DB::update('users', [
            'ultimo_acceso' => date('Y-m-d H:i:s'),
            'ultima_ip'     => client_ip(),
        ], 'id=:id', ['id' => (int)$u['id']]);

        if ($recordar) {
            $token = Security::randomToken(32);
            DB::insert('remember_tokens', [
                'user_id'    => (int)$u['id'],
                'token_hash' => hash('sha256', $token),
                'expira'     => date('Y-m-d H:i:s', time() + 2592000),
                'creado'     => date('Y-m-d H:i:s'),
            ]);
            setcookie('mg_remember', (int)$u['id'] . ':' . $token, [
                'expires' => time() + 2592000,
                'path'    => App::basePath() === '' ? '/' : App::basePath() . '/',
                'secure'  => App::isSecure(), 'httponly' => true, 'samesite' => 'Lax',
            ]);
        }
        Audit::log('ingreso', 'users', (int)$u['id']);
    }

    /** Reingreso automatico mediante cookie "recordarme". */
    public static function tryRemember(): void
    {
        if (self::check() || empty($_COOKIE['mg_remember'])) return;
        $parts = explode(':', (string)$_COOKIE['mg_remember'], 2);
        if (count($parts) !== 2) return;
        [$uid, $token] = $parts;
        try {
            $row = DB::one(
                'SELECT * FROM remember_tokens WHERE user_id=:u AND token_hash=:t AND expira > NOW() LIMIT 1',
                ['u' => (int)$uid, 't' => hash('sha256', $token)]
            );
            if (!$row) return;
            $u = DB::one('SELECT * FROM users WHERE id=:id AND activo=1', ['id' => (int)$uid]);
            if ($u) self::login($u);
        } catch (\Throwable $e) {}
    }

    public static function logout(): void
    {
        if (self::check()) Audit::log('salida', 'users', self::id());
        if (!empty($_COOKIE['mg_remember'])) {
            $parts = explode(':', (string)$_COOKIE['mg_remember'], 2);
            if (count($parts) === 2) {
                try { DB::delete('remember_tokens', 'user_id=:u AND token_hash=:t', ['u' => (int)$parts[0], 't' => hash('sha256', $parts[1])]); } catch (\Throwable $e) {}
            }
            setcookie('mg_remember', '', ['expires' => time() - 3600, 'path' => App::basePath() === '' ? '/' : App::basePath() . '/']);
        }
        Session::destroy();
        self::$user = null;
        self::$loaded = false;
    }

    /** Panel al que se envia a cada rol tras ingresar. */
    public static function homeFor(?array $u = null): string
    {
        $u = $u ?? self::user();
        switch ((string)($u['rol'] ?? '')) {
            case 'superadmin': return 'super';
            case 'cocina':     return 'panel/cocina';
            case 'mesero':     return 'panel/mesero';
            default:           return 'panel';
        }
    }

    public static function refresh(): void { self::$user = null; self::$loaded = false; }
}
