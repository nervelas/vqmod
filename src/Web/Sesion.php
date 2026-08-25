<?php
declare(strict_types=1);

namespace Fel\Web;

use Fel\Core\Config;
use Fel\Core\Db;

/**
 * Sesion, autenticacion y proteccion CSRF de la interfaz web.
 */
final class Sesion
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $minutos = (int) Config::get('app.sesion_minutos', 120);

        session_set_cookie_params([
            'lifetime' => $minutos * 60,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (($_SERVER['HTTPS'] ?? '') !== '') || (($_SERVER['SERVER_PORT'] ?? '') === '443'),
        ]);

        session_name('felsid');
        session_start();

        if (isset($_SESSION['ultimo_uso']) && time() - (int) $_SESSION['ultimo_uso'] > $minutos * 60) {
            self::cerrar();
            session_start();
        }

        $_SESSION['ultimo_uso'] = time();
    }

    public static function intentarIngreso(string $usuario, string $clave): bool
    {
        $sentencia = Db::conexion()->prepare(
            'SELECT * FROM fel_usuarios WHERE usuario = :usuario AND activo = 1 LIMIT 1'
        );
        $sentencia->execute(['usuario' => $usuario]);
        $fila = $sentencia->fetch();

        // Se verifica siempre contra un hash, exista o no el usuario, para que
        // el tiempo de respuesta no revele cuales usuarios existen.
        $hash = $fila === false
            ? '$2y$12$C6UzMDM.H6dfI/f/IKcEe.OGCn5aOZfHTJ9jVfXcCYKAqiEDLpBOu'
            : (string) $fila['clave_hash'];

        $coincide = password_verify($clave, $hash);

        if ($fila === false || !$coincide) {
            return false;
        }

        session_regenerate_id(true);

        $_SESSION['usuario'] = [
            'id'      => (int) $fila['id'],
            'usuario' => (string) $fila['usuario'],
            'nombre'  => (string) $fila['nombre'],
            'rol'     => (string) $fila['rol'],
        ];

        return true;
    }

    public static function cerrar(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /** @return array<string,mixed>|null */
    public static function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function autenticado(): bool
    {
        return self::usuario() !== null;
    }

    public static function esAdmin(): bool
    {
        return (self::usuario()['rol'] ?? '') === 'admin';
    }

    public static function tokenCsrf(): string
    {
        if (!isset($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf'];
    }

    public static function csrfValido(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf'])
            && hash_equals((string) $_SESSION['csrf'], $token);
    }
}
