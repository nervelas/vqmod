<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private const OPCIONES_HASH = ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2];

    public static function hash(string $clave): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($clave, PASSWORD_ARGON2ID, self::OPCIONES_HASH);
        }
        return password_hash($clave, PASSWORD_DEFAULT);
    }

    public static function verificar(string $clave, string $hash): bool
    {
        return password_verify($clave, $hash);
    }

    public static function usuario(): ?array
    {
        $u = $_SESSION['usuario'] ?? null;
        return is_array($u) ? $u : null;
    }

    public static function id(): int
    {
        return (int) (self::usuario()['id'] ?? 0);
    }

    public static function rol(): string
    {
        return (string) (self::usuario()['rol'] ?? '');
    }

    public static function invitado(): bool
    {
        return self::usuario() === null;
    }

    public static function es(string ...$roles): bool
    {
        return in_array(self::rol(), $roles, true);
    }

    /** Roles con acceso al panel administrativo. */
    public static function esStaff(): bool
    {
        return self::es('admin', 'junta', 'contabilidad');
    }

    public static function entrar(array $usuario): void
    {
        Sesion::regenerar();
        $_SESSION['usuario'] = [
            'id'          => (int) $usuario['id'],
            'nombre'      => (string) $usuario['nombre'],
            'usuario'     => (string) $usuario['usuario'],
            'correo'      => (string) ($usuario['correo'] ?? ''),
            'rol'         => (string) $usuario['rol'],
            'tema'        => (string) ($usuario['tema'] ?? 'verde-oro'),
            'modo_oscuro' => (int) ($usuario['modo_oscuro'] ?? 0),
            'onboarding'  => (int) ($usuario['onboarding'] ?? 0),
        ];
        $_SESSION['_actividad'] = time();
        $_SESSION['casas'] = self::casasDe((int) $usuario['id']);
        DB::actualizar('usuarios', ['ultimo_acceso' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $usuario['id']]);
        Auditoria::registrar('acceso', 'usuarios', (int) $usuario['id'], 'Inicio de sesión');
    }

    public static function salir(): void
    {
        $id = self::id();
        if ($id > 0) {
            Auditoria::registrar('salida', 'usuarios', $id, 'Cierre de sesión');
        }
        Sesion::destruir();
    }

    /** IDs de casas asociadas a un usuario residente. */
    public static function casasDe(int $usuarioId): array
    {
        if ($usuarioId <= 0) {
            return [];
        }
        try {
            $filas = DB::todos(
                'SELECT DISTINCT casa_id FROM residentes WHERE usuario_id = :u AND activo = 1',
                ['u' => $usuarioId]
            );
            return array_map(static fn($f) => (int) $f['casa_id'], $filas);
        } catch (\Throwable) {
            return [];
        }
    }

    public static function casas(): array
    {
        $c = $_SESSION['casas'] ?? null;
        if (!is_array($c)) {
            $c = self::casasDe(self::id());
            $_SESSION['casas'] = $c;
        }
        return $c;
    }

    public static function casaActual(): int
    {
        $c = self::casas();
        $sel = (int) ($_SESSION['casa_actual'] ?? 0);
        if ($sel > 0 && in_array($sel, $c, true)) {
            return $sel;
        }
        return $c[0] ?? 0;
    }

    public static function puedeVerCasa(int $casaId): bool
    {
        if (self::es('admin', 'junta', 'contabilidad', 'garita')) {
            return true;
        }
        return in_array($casaId, self::casas(), true);
    }

    /** Fuerza pertenencia de la casa al residente; aborta si no. */
    public static function exigirCasa(int $casaId): void
    {
        if (!self::puedeVerCasa($casaId)) {
            Auditoria::registrar('acceso_denegado', 'casas', $casaId, 'Intento de ver casa ajena');
            Respuesta::abortar(403, 'No tiene permiso para consultar esta vivienda.');
        }
    }

    public static function politicaClave(string $clave): ?string
    {
        if (mb_strlen($clave) < 10) {
            return 'La contraseña debe tener al menos 10 caracteres.';
        }
        if (!preg_match('/[A-Za-zÁÉÍÓÚáéíóúÑñ]/u', $clave) || !preg_match('/\d/', $clave)) {
            return 'La contraseña debe combinar letras y números.';
        }
        $comunes = ['1234567890', 'contrasena', 'password12', 'admin12345', 'qwertyuiop'];
        if (in_array(mb_strtolower($clave), $comunes, true)) {
            return 'La contraseña es demasiado común. Elija otra.';
        }
        return null;
    }
}
