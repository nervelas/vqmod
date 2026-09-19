<?php
/**
 * Kaptor - Autenticacion y usuarios.
 *
 * Claves siempre con password_hash()/password_verify(), bloqueo por intentos
 * fallidos y regeneracion del identificador de sesión al entrar.
 */
declare(strict_types=1);

final class Auth
{
    /** Nombre de la cookie de "mantener la sesión iniciada". */
    public const COOKIE_RECUERDO = 'kaptor_recuerdo';

    /** Duración de esa cookie, en días. */
    public const DIAS_RECUERDO = 30;

    private static ?array $usuario = null;
    private static bool $resuelto = false;
    private static bool $tablaLista = false;

    /** Usuario de la sesión actual, o null si no ha iniciado sesión. */
    public static function usuario(): ?array
    {
        if (self::$resuelto) { return self::$usuario; }
        self::$resuelto = true;

        $id = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($id <= 0) {
            // Sin sesión activa: puede haber una cookie de "mantener sesión".
            $fila = self::recuperarPorCookie();
            return self::$usuario = $fila;
        }

        $fila = BD::fila('SELECT * FROM `cr_usuarios` WHERE `id` = ? AND `activo` = 1', [$id]);
        if (!$fila) {
            unset($_SESSION['usuario_id']);
            return self::$usuario = null;
        }
        return self::$usuario = $fila;
    }

    /** ¿Hay una sesión iniciada? */
    public static function autenticado(): bool
    {
        return self::usuario() !== null;
    }

    /** ¿El usuario actual es administrador? */
    public static function esAdmin(): bool
    {
        $u = self::usuario();
        return $u !== null && $u['rol'] === 'admin';
    }

    /** Identificador del usuario actual (0 si es anonimo). */
    public static function id(): int
    {
        $u = self::usuario();
        return $u ? (int) $u['id'] : 0;
    }

    /**
     * Intenta iniciar sesión con usuario o correo.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function entrar(string $identificador, string $clave, bool $soloAdmin = false, bool $recordar = false): array
    {
        $identificador = trim($identificador);

        if (Seguridad::intentosFallidos() >= 8) {
            return ['ok' => false, 'error' => 'Demasiados intentos fallidos. Espera 15 minutos e inténtalo de nuevo.'];
        }
        if ($identificador === '' || $clave === '') {
            return ['ok' => false, 'error' => 'Escribe tu usuario y tu contraseña.'];
        }

        $fila = BD::fila(
            'SELECT * FROM `cr_usuarios` WHERE (`usuario` = ? OR `email` = ?) LIMIT 1',
            [$identificador, mb_strtolower($identificador)]
        );

        // Se compara siempre contra un hash para no filtrar si el usuario existe.
        $hash = $fila['clave_hash'] ?? '$2y$10$usuarioinexistenteusuarioinexistenteusuarioinexiste00000000';
        $valido = password_verify($clave, $hash);

        if (!$fila || !$valido) {
            Seguridad::registrarIntento($identificador, false);
            return ['ok' => false, 'error' => 'Usuario o contraseña incorrectos.'];
        }
        if ((int) $fila['activo'] !== 1) {
            Seguridad::registrarIntento($identificador, false);
            return ['ok' => false, 'error' => 'Esta cuenta esta desactivada.'];
        }
        if ($soloAdmin && $fila['rol'] !== 'admin') {
            Seguridad::registrarIntento($identificador, false);
            return ['ok' => false, 'error' => 'Esta cuenta no tiene acceso al panel de administración.'];
        }

        // Rehash si el coste por defecto de PHP ha cambiado.
        if (password_needs_rehash($fila['clave_hash'], PASSWORD_DEFAULT)) {
            BD::actualizar('cr_usuarios', ['clave_hash' => password_hash($clave, PASSWORD_DEFAULT)], '`id` = ?', [$fila['id']]);
        }

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int) $fila['id'];
        $_SESSION['_creada']    = time();
        self::$usuario = $fila;
        self::$resuelto = true;

        BD::actualizar('cr_usuarios', ['ultimo_acceso' => date('Y-m-d H:i:s')], '`id` = ?', [$fila['id']]);
        Seguridad::registrarIntento($identificador, true);

        // "Mantener la sesión iniciada": cookie de larga duración.
        if ($recordar) { self::recordar((int) $fila['id']); }

        return ['ok' => true];
    }

    // ==========================================================================
    //  "MANTENER LA SESIÓN INICIADA" (cookie de recuerdo)
    // ==========================================================================

    /**
     * Crea la tabla de recuerdos si no existe.
     *
     * Se hace aquí, y no solo en el instalador, para que las instalaciones
     * antiguas ganen la función al actualizar los archivos.
     */
    private static function asegurarTabla(): bool
    {
        if (self::$tablaLista) { return true; }
        try {
            BD::ejecutar(
                'CREATE TABLE IF NOT EXISTS `cr_recuerdos` (
                    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `usuario_id`   INT UNSIGNED NOT NULL,
                    `selector`     CHAR(32)     NOT NULL,
                    `verificador`  CHAR(64)     NOT NULL,
                    `expira`       DATETIME     NOT NULL,
                    `creado`       DATETIME     NOT NULL,
                    `agente`       VARCHAR(190) NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_selector` (`selector`),
                    KEY `idx_usuario` (`usuario_id`),
                    KEY `idx_expira` (`expira`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            self::$tablaLista = true;
        } catch (Throwable $e) {
            error_log('Kaptor / tabla de recuerdos: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /** Parámetros de la cookie, iguales a los de la sesión. */
    private static function opcionesCookie(int $expira): array
    {
        return [
            'expires'  => $expira,
            'path'     => '/',
            'domain'   => '',
            'secure'   => cr_es_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    /**
     * Guarda un recuerdo nuevo para el usuario indicado.
     *
     * La cookie lleva "selector:verificador". En la base de datos solo se
     * guarda el hash del verificador, de modo que robar la tabla no permite
     * suplantar a nadie.
     */
    private static function recordar(int $usuarioId): void
    {
        if (!self::asegurarTabla()) { return; }

        $selector    = bin2hex(random_bytes(16));
        $verificador = bin2hex(random_bytes(32));
        $expira      = time() + (self::DIAS_RECUERDO * 86400);

        try {
            BD::insertar('cr_recuerdos', [
                'usuario_id'  => $usuarioId,
                'selector'    => $selector,
                'verificador' => hash('sha256', $verificador),
                'expira'      => date('Y-m-d H:i:s', $expira),
                'creado'      => date('Y-m-d H:i:s'),
                'agente'      => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 190),
            ]);
        } catch (Throwable $e) {
            error_log('Kaptor / guardar recuerdo: ' . $e->getMessage());
            return;
        }

        // Limpieza oportunista de los recuerdos caducados.
        try {
            BD::ejecutar('DELETE FROM `cr_recuerdos` WHERE `expira` < ?', [date('Y-m-d H:i:s')]);
        } catch (Throwable $e) { /* sin importancia */ }

        setcookie(self::COOKIE_RECUERDO, $selector . ':' . $verificador, self::opcionesCookie($expira));
    }

    /**
     * Intenta reabrir la sesión a partir de la cookie de recuerdo.
     *
     * @return array<string,mixed>|null
     */
    private static function recuperarPorCookie(): ?array
    {
        $cookie = (string) ($_COOKIE[self::COOKIE_RECUERDO] ?? '');
        if ($cookie === '' || !str_contains($cookie, ':')) { return null; }

        [$selector, $verificador] = explode(':', $cookie, 2);
        if (strlen($selector) !== 32 || strlen($verificador) !== 64) {
            self::olvidar();
            return null;
        }

        if (!self::asegurarTabla()) { return null; }

        try {
            $recuerdo = BD::fila(
                'SELECT * FROM `cr_recuerdos` WHERE `selector` = ? LIMIT 1',
                [$selector]
            );
        } catch (Throwable $e) {
            error_log('Kaptor / leer recuerdo: ' . $e->getMessage());
            return null;
        }

        if (!$recuerdo || strtotime((string) $recuerdo['expira']) < time()) {
            self::olvidar();
            return null;
        }
        if (!hash_equals((string) $recuerdo['verificador'], hash('sha256', $verificador))) {
            // La cookie no cuadra: se anulan todos los recuerdos del usuario.
            self::olvidar((int) $recuerdo['usuario_id']);
            return null;
        }

        $fila = BD::fila('SELECT * FROM `cr_usuarios` WHERE `id` = ? AND `activo` = 1', [(int) $recuerdo['usuario_id']]);
        if (!$fila) {
            self::olvidar((int) $recuerdo['usuario_id']);
            return null;
        }

        // Se abre la sesión y se rota el recuerdo (un solo uso por cookie).
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int) $fila['id'];
        $_SESSION['_creada']    = time();

        try {
            BD::ejecutar('DELETE FROM `cr_recuerdos` WHERE `id` = ?', [(int) $recuerdo['id']]);
        } catch (Throwable $e) { /* sin importancia */ }
        self::recordar((int) $fila['id']);

        return $fila;
    }

    /**
     * Borra la cookie de recuerdo y, si se indica un usuario, todos los
     * recuerdos guardados para él.
     */
    private static function olvidar(int $usuarioId = 0): void
    {
        $cookie = (string) ($_COOKIE[self::COOKIE_RECUERDO] ?? '');
        if ($cookie !== '' && str_contains($cookie, ':') && self::asegurarTabla()) {
            [$selector] = explode(':', $cookie, 2);
            try {
                BD::ejecutar('DELETE FROM `cr_recuerdos` WHERE `selector` = ?', [$selector]);
            } catch (Throwable $e) { /* sin importancia */ }
        }
        if ($usuarioId > 0 && self::asegurarTabla()) {
            try {
                BD::ejecutar('DELETE FROM `cr_recuerdos` WHERE `usuario_id` = ?', [$usuarioId]);
            } catch (Throwable $e) { /* sin importancia */ }
        }

        unset($_COOKIE[self::COOKIE_RECUERDO]);
        setcookie(self::COOKIE_RECUERDO, '', self::opcionesCookie(time() - 42000));
    }

    /** Cierra la sesión actual por completo. */
    public static function salir(): void
    {
        self::olvidar(self::id());
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        @session_destroy();
        self::$usuario = null;
        self::$resuelto = true;
    }

    /**
     * Crea un usuario nuevo.
     *
     * @return array{ok:bool,id?:int,error?:string}
     */
    public static function crear(string $usuario, string $email, string $clave, string $rol = 'usuario', string $nombre = '', bool $activo = true): array
    {
        $usuario = trim($usuario);
        $email   = mb_strtolower(trim($email));
        $rol     = $rol === 'admin' ? 'admin' : 'usuario';

        if (!preg_match('/^[a-zA-Z0-9._\-]{3,64}$/', $usuario)) {
            return ['ok' => false, 'error' => 'El usuario debe tener entre 3 y 64 caracteres (letras, números, punto, guion o guion bajo).'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'El correo electrónico no es válido.'];
        }
        if (strlen($clave) < 8) {
            return ['ok' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres.'];
        }
        $existe = BD::valor('SELECT COUNT(*) FROM `cr_usuarios` WHERE `usuario` = ? OR `email` = ?', [$usuario, $email], 0);
        if ((int) $existe > 0) {
            return ['ok' => false, 'error' => 'Ya existe una cuenta con ese usuario o correo.'];
        }

        $id = BD::insertar('cr_usuarios', [
            'usuario'    => $usuario,
            'email'      => $email,
            'nombre'     => $nombre !== '' ? mb_substr($nombre, 0, 120) : null,
            'clave_hash' => password_hash($clave, PASSWORD_DEFAULT),
            'rol'        => $rol,
            'activo'     => $activo ? 1 : 0,
            'creado'     => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'id' => $id];
    }

    /** Obliga a tener sesión de administrador; si no, manda al login del panel. */
    public static function exigirAdmin(): void
    {
        if (self::esAdmin()) { return; }
        cr_redirigir('admin/login.php');
    }

    /**
     * ¿Puede el visitante actual lanzar una extracción?
     * Kaptor es de uso privado: hace falta haber iniciado sesión. Las cuentas
     * las crea el administrador desde el panel; no hay registro público.
     */
    public static function puedeExtraer(): bool
    {
        return self::autenticado();
    }
}
