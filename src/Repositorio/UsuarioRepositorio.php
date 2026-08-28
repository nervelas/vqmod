<?php
declare(strict_types=1);

namespace Fel\Repositorio;

use Fel\Core\Db;

/**
 * Usuarios del sistema.
 *
 * Un usuario con empresa_id NULL y rol 'superadmin' es el administrador de la
 * plataforma (usted): ve todas las empresas y las da de alta. Los demas
 * usuarios pertenecen a una empresa y solo ven la suya.
 */
final class UsuarioRepositorio
{
    public const SUPERADMIN = 'superadmin';
    public const ADMIN      = 'admin';
    public const OPERADOR   = 'operador';

    public function crear(
        string $usuario,
        string $clave,
        string $nombre,
        string $rol = self::OPERADOR,
        ?int $empresaId = null,
    ): int {
        $sentencia = Db::conexion()->prepare(
            'INSERT INTO fel_usuarios (empresa_id, usuario, clave_hash, nombre, rol, creado_en)
             VALUES (:empresa_id, :usuario, :hash, :nombre, :rol, :creado_en)'
        );

        $sentencia->execute([
            'empresa_id' => $rol === self::SUPERADMIN ? null : $empresaId,
            'usuario'    => $usuario,
            'hash'       => password_hash($clave, PASSWORD_BCRYPT, ['cost' => 12]),
            'nombre'     => $nombre !== '' ? $nombre : $usuario,
            'rol'        => in_array($rol, [self::SUPERADMIN, self::ADMIN, self::OPERADOR], true)
                ? $rol
                : self::OPERADOR,
            'creado_en'  => date('Y-m-d H:i:s'),
        ]);

        return (int) Db::conexion()->lastInsertId();
    }

    public function cambiarClave(string $usuario, string $clave): bool
    {
        $sentencia = Db::conexion()->prepare(
            'UPDATE fel_usuarios SET clave_hash = :hash WHERE usuario = :usuario'
        );
        $sentencia->execute([
            'hash'    => password_hash($clave, PASSWORD_BCRYPT, ['cost' => 12]),
            'usuario' => $usuario,
        ]);

        return $sentencia->rowCount() > 0;
    }

    public function cambiarEstado(int $id, bool $activo): void
    {
        $sentencia = Db::conexion()->prepare('UPDATE fel_usuarios SET activo = :activo WHERE id = :id');
        $sentencia->execute(['activo' => $activo ? 1 : 0, 'id' => $id]);
    }

    public function existe(string $usuario): bool
    {
        $sentencia = Db::conexion()->prepare('SELECT 1 FROM fel_usuarios WHERE usuario = :usuario LIMIT 1');
        $sentencia->execute(['usuario' => $usuario]);

        return $sentencia->fetchColumn() !== false;
    }

    public function total(): int
    {
        return (int) Db::conexion()->query('SELECT COUNT(*) FROM fel_usuarios')->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    public function porEmpresa(?int $empresaId): array
    {
        if ($empresaId === null) {
            $sentencia = Db::conexion()->query(
                'SELECT u.*, e.nombre_interno AS empresa
                 FROM fel_usuarios u
                 LEFT JOIN fel_empresas e ON e.id = u.empresa_id
                 ORDER BY e.nombre_interno, u.usuario'
            );

            return $sentencia === false ? [] : $sentencia->fetchAll();
        }

        $sentencia = Db::conexion()->prepare(
            'SELECT * FROM fel_usuarios WHERE empresa_id = :empresa ORDER BY usuario'
        );
        $sentencia->execute(['empresa' => $empresaId]);

        return $sentencia->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function buscar(int $id): ?array
    {
        $sentencia = Db::conexion()->prepare('SELECT * FROM fel_usuarios WHERE id = :id');
        $sentencia->execute(['id' => $id]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }
}
