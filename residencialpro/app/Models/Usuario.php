<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\DB;

final class Usuario
{
    public static function porId(int $id): ?array
    {
        return DB::uno('SELECT * FROM usuarios WHERE id = :id', ['id' => $id]);
    }

    public static function porIdentificador(string $identificador): ?array
    {
        return DB::uno(
            'SELECT * FROM usuarios WHERE (usuario = :i OR correo = :i) AND activo = 1 LIMIT 1',
            ['i' => $identificador]
        );
    }

    public static function porCorreo(string $correo): ?array
    {
        return DB::uno('SELECT * FROM usuarios WHERE correo = :c LIMIT 1', ['c' => $correo]);
    }

    public static function listar(array $filtros = [], int $limite = 200): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filtros['rol'])) {
            $where[] = 'u.rol = :r';
            $params['r'] = (string) $filtros['rol'];
        }
        if (!empty($filtros['buscar'])) {
            $where[] = '(u.nombre LIKE :b OR u.usuario LIKE :b OR u.correo LIKE :b)';
            $params['b'] = '%' . $filtros['buscar'] . '%';
        }
        return DB::todos(
            'SELECT u.*, (SELECT GROUP_CONCAT(c.codigo ORDER BY c.codigo SEPARATOR ", ")
                          FROM residentes r INNER JOIN casas c ON c.id = r.casa_id
                          WHERE r.usuario_id = u.id AND r.activo = 1) AS casas
             FROM usuarios u WHERE ' . implode(' AND ', $where) . '
             ORDER BY FIELD(u.rol,"admin","junta","contabilidad","garita","residente"), u.nombre
             LIMIT ' . (int) $limite,
            $params
        );
    }

    public static function crear(array $d): int
    {
        $id = DB::insertar('usuarios', [
            'rol'           => (string) $d['rol'],
            'nombre'        => mb_substr((string) $d['nombre'], 0, 140),
            'usuario'       => (string) $d['usuario'],
            'correo'        => !empty($d['correo']) ? (string) $d['correo'] : null,
            'telefono'      => $d['telefono'] ?? null,
            'password_hash' => Auth::hash((string) $d['clave']),
            'activo'        => isset($d['activo']) ? (int) $d['activo'] : 1,
        ]);
        Auditoria::registrar('crear_usuario', 'usuarios', $id, $d['nombre'] . ' (' . $d['rol'] . ')');
        return $id;
    }

    public static function actualizar(int $id, array $d): void
    {
        $datos = [
            'rol'      => (string) $d['rol'],
            'nombre'   => mb_substr((string) $d['nombre'], 0, 140),
            'usuario'  => (string) $d['usuario'],
            'correo'   => !empty($d['correo']) ? (string) $d['correo'] : null,
            'telefono' => $d['telefono'] ?? null,
            'activo'   => isset($d['activo']) ? (int) $d['activo'] : 1,
        ];
        if (!empty($d['clave'])) {
            $datos['password_hash'] = Auth::hash((string) $d['clave']);
        }
        DB::actualizar('usuarios', $datos, 'id = :id', ['id' => $id]);
        Auditoria::registrar('editar_usuario', 'usuarios', $id, (string) $d['nombre']);
    }

    public static function usuarioDisponible(string $usuario, int $excluirId = 0): bool
    {
        $r = DB::valor('SELECT id FROM usuarios WHERE usuario = :u AND id <> :x', ['u' => $usuario, 'x' => $excluirId]);
        return $r === null || $r === false;
    }

    public static function correoDisponible(string $correo, int $excluirId = 0): bool
    {
        if ($correo === '') {
            return true;
        }
        $r = DB::valor('SELECT id FROM usuarios WHERE correo = :c AND id <> :x', ['c' => $correo, 'x' => $excluirId]);
        return $r === null || $r === false;
    }

    /** Sugiere un nombre de usuario libre a partir del nombre completo. */
    public static function sugerirUsuario(string $nombre, string $sufijo = ''): string
    {
        $base = slug($nombre);
        $base = str_replace('-', '.', $base);
        $base = mb_substr($base !== '' ? $base : 'usuario', 0, 40);
        if ($sufijo !== '') {
            $base .= '.' . slug($sufijo);
        }
        $intento = $base;
        $n = 1;
        while (!self::usuarioDisponible($intento)) {
            $intento = $base . $n;
            $n++;
        }
        return $intento;
    }

    public static function notificaciones(int $usuarioId, int $limite = 20): array
    {
        return DB::todos(
            'SELECT * FROM notificaciones WHERE usuario_id = :u ORDER BY id DESC LIMIT ' . (int) $limite,
            ['u' => $usuarioId]
        );
    }

    public static function marcarNotificacionesLeidas(int $usuarioId): void
    {
        DB::q(
            'UPDATE notificaciones SET leido_en = NOW() WHERE usuario_id = :u AND leido_en IS NULL',
            ['u' => $usuarioId]
        );
    }
}
