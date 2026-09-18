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
    private static ?array $usuario = null;
    private static bool $resuelto = false;

    /** Usuario de la sesión actual, o null si no ha iniciado sesión. */
    public static function usuario(): ?array
    {
        if (self::$resuelto) { return self::$usuario; }
        self::$resuelto = true;

        $id = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($id <= 0) { return self::$usuario = null; }

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
    public static function entrar(string $identificador, string $clave, bool $soloAdmin = false): array
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

        return ['ok' => true];
    }

    /** Cierra la sesión actual por completo. */
    public static function salir(): void
    {
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
     * Depende del ajuste "acceso_publico" (libre o solo con cuenta).
     */
    public static function puedeExtraer(): bool
    {
        return Ajustes::activo('acceso_publico', true) || self::autenticado();
    }
}
