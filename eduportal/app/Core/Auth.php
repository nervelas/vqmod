<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    public const ROLES = ['superadmin', 'secretaria', 'docente', 'padre'];

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;
        $id = Session::get('uid');
        if (!$id || !Database::isConnected()) {
            return self::$user = null;
        }
        $row = Database::one(
            'SELECT id, nombre, email, rol, telefono, foto, activo, tema, modo_oscuro, twofa, sesion_serie, debe_cambiar
             FROM users WHERE id = :id',
            ['id' => (int)$id]
        );
        if (!$row || (int)$row['activo'] !== 1) {
            Session::destroy();
            return self::$user = null;
        }
        // Cierre de sesion en todos los dispositivos: la serie debe coincidir.
        if (($row['sesion_serie'] ?? null) && Session::get('serie') !== $row['sesion_serie']) {
            Session::destroy();
            return self::$user = null;
        }
        return self::$user = $row;
    }

    public static function check(): bool { return self::user() !== null; }
    public static function id(): ?int { $u = self::user(); return $u ? (int)$u['id'] : null; }
    public static function rol(): ?string { $u = self::user(); return $u['rol'] ?? null; }
    public static function nombre(): string { $u = self::user(); return (string)($u['nombre'] ?? ''); }

    public static function is(string ...$roles): bool
    {
        $r = self::rol();
        return $r !== null && in_array($r, $roles, true);
    }

    public static function isAdmin(): bool { return self::is('superadmin'); }
    public static function isStaff(): bool { return self::is('superadmin', 'secretaria'); }

    /** Permisos por modulo verificados del lado servidor en cada peticion. */
    public static function can(string $permiso): bool
    {
        $rol = self::rol();
        if ($rol === null) {
            return false;
        }
        if ($rol === 'superadmin') {
            return true;
        }
        $mapa = [
            'secretaria' => [
                'alumnos.ver', 'alumnos.editar', 'encargados.editar',
                'cobranza.ver', 'cobranza.editar', 'pagos.aprobar',
                'reportes.ver', 'avisos.ver', 'avisos.editar',
                'asistencia.ver', 'preinscripciones.ver', 'calendario.editar',
                'mensajes.ver',
            ],
            'docente' => [
                'alumnos.ver', 'notas.ver', 'notas.editar',
                'asistencia.ver', 'asistencia.editar',
                'tareas.ver', 'tareas.editar', 'avisos.ver', 'avisos.editar',
                'mensajes.ver',
            ],
            'padre' => [
                'portal.ver', 'mensajes.ver',
            ],
        ];
        return in_array($permiso, $mapa[$rol] ?? [], true);
    }

    public static function attempt(string $email, string $password): array
    {
        $row = Database::one('SELECT * FROM users WHERE email = :e', ['e' => mb_strtolower($email)]);
        if (!$row) {
            return ['ok' => false, 'motivo' => 'credenciales'];
        }
        if ((int)$row['activo'] !== 1) {
            return ['ok' => false, 'motivo' => 'inactivo'];
        }
        if (!password_verify($password, (string)$row['password_hash'])) {
            return ['ok' => false, 'motivo' => 'credenciales'];
        }
        if (password_needs_rehash((string)$row['password_hash'], self::algo(), self::algoOpts())) {
            Database::run('UPDATE users SET password_hash = :h WHERE id = :id', [
                'h'  => self::hash($password),
                'id' => (int)$row['id'],
            ]);
        }
        return ['ok' => true, 'user' => $row];
    }

    public static function login(array $user, bool $nuevaSerie = true): void
    {
        Session::regenerate();
        Csrf::rotate();
        $serie = $user['sesion_serie'] ?? null;
        if ($nuevaSerie || !$serie) {
            $serie = bin2hex(random_bytes(16));
            Database::run('UPDATE users SET sesion_serie = :s WHERE id = :id', ['s' => $serie, 'id' => (int)$user['id']]);
        }
        Session::set('uid', (int)$user['id']);
        Session::set('serie', $serie);
        Session::set('rol', $user['rol']);
        Database::run('UPDATE users SET ultimo_acceso = :t WHERE id = :id', [
            't'  => date('Y-m-d H:i:s'),
            'id' => (int)$user['id'],
        ]);
        self::$user = null;
        self::$resolved = false;
        Audit::log('login', 'users', (int)$user['id'], 'Inicio de sesion');
    }

    public static function logout(): void
    {
        $id = self::id();
        if ($id) {
            Audit::log('logout', 'users', $id, 'Cierre de sesion');
        }
        Session::destroy();
        self::$user = null;
        self::$resolved = true;
    }

    public static function logoutEverywhere(int $userId): void
    {
        Database::run('UPDATE users SET sesion_serie = :s WHERE id = :id', [
            's'  => bin2hex(random_bytes(16)),
            'id' => $userId,
        ]);
    }

    public static function algo(): string
    {
        return defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)
            ? PASSWORD_ARGON2ID
            : PASSWORD_BCRYPT;
    }

    public static function algoOpts(): array
    {
        if (self::algo() === PASSWORD_BCRYPT) {
            return ['cost' => 12];
        }
        return ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2];
    }

    public static function hash(string $password): string
    {
        return password_hash($password, self::algo(), self::algoOpts());
    }

    /** IDs de alumnos que el usuario actual puede ver (control por propiedad). */
    public static function alumnosPermitidos(): ?array
    {
        $rol = self::rol();
        if ($rol === 'superadmin' || $rol === 'secretaria') {
            return null; // sin restriccion
        }
        $uid = (int)self::id();
        if ($rol === 'padre') {
            $rows = Database::all('SELECT DISTINCT alumno_id FROM encargados WHERE user_id = :u', ['u' => $uid]);
            return array_map(static fn($r) => (int)$r['alumno_id'], $rows);
        }
        if ($rol === 'docente') {
            $rows = Database::all(
                'SELECT DISTINCT i.alumno_id
                 FROM inscripciones i
                 JOIN asignaciones a ON a.seccion_id = i.seccion_id AND a.ciclo_id = i.ciclo_id
                 WHERE a.docente_id = :u',
                ['u' => $uid]
            );
            $ids = array_map(static fn($r) => (int)$r['alumno_id'], $rows);
            $guia = Database::all(
                'SELECT DISTINCT i.alumno_id FROM inscripciones i
                 JOIN secciones s ON s.id = i.seccion_id
                 WHERE s.docente_guia_id = :u',
                ['u' => $uid]
            );
            foreach ($guia as $g) {
                $ids[] = (int)$g['alumno_id'];
            }
            return array_values(array_unique($ids));
        }
        return [];
    }

    public static function puedeVerAlumno(int $alumnoId): bool
    {
        $permitidos = self::alumnosPermitidos();
        return $permitidos === null || in_array($alumnoId, $permitidos, true);
    }

    public static function puedeUsarAsignacion(int $asignacionId): bool
    {
        if (self::is('superadmin')) {
            return true;
        }
        if (!self::is('docente')) {
            return false;
        }
        return (int)Database::value(
            'SELECT COUNT(*) FROM asignaciones WHERE id = :a AND docente_id = :u',
            ['a' => $asignacionId, 'u' => (int)self::id()],
            0
        ) > 0;
    }

    public static function puedeUsarSeccion(int $seccionId): bool
    {
        if (self::is('superadmin', 'secretaria')) {
            return true;
        }
        if (!self::is('docente')) {
            return false;
        }
        $uid = (int)self::id();
        $n = (int)Database::value(
            'SELECT COUNT(*) FROM asignaciones WHERE seccion_id = :s AND docente_id = :u',
            ['s' => $seccionId, 'u' => $uid],
            0
        );
        if ($n > 0) {
            return true;
        }
        return (int)Database::value(
            'SELECT COUNT(*) FROM secciones WHERE id = :s AND docente_guia_id = :u',
            ['s' => $seccionId, 'u' => $uid],
            0
        ) > 0;
    }
}
