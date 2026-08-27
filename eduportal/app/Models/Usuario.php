<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

final class Usuario
{
    public static function porId(int $id): ?array
    {
        return Database::one('SELECT * FROM users WHERE id = :id', ['id' => $id]);
    }

    public static function porEmail(string $email): ?array
    {
        return Database::one('SELECT * FROM users WHERE email = :e', ['e' => mb_strtolower(trim($email))]);
    }

    public static function listar(array $f = [], int $limite = 50, int $offset = 0): array
    {
        $w = ['1 = 1'];
        $p = [];
        if (!empty($f['rol'])) {
            $w[] = 'rol = :r';
            $p['r'] = (string)$f['rol'];
        }
        if (!empty($f['q'])) {
            $w[] = '(nombre LIKE :q OR email LIKE :q2)';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], (string)$f['q']) . '%';
            $p['q'] = $like; $p['q2'] = $like;
        }
        if (isset($f['activo']) && $f['activo'] !== '') {
            $w[] = 'activo = :a';
            $p['a'] = (int)$f['activo'];
        }
        return Database::all(
            'SELECT id, nombre, email, rol, telefono, activo, ultimo_acceso, creado_en
             FROM users WHERE ' . implode(' AND ', $w) . '
             ORDER BY nombre LIMIT ' . max(1, $limite) . ' OFFSET ' . max(0, $offset),
            $p
        );
    }

    public static function contar(array $f = []): int
    {
        $w = ['1 = 1'];
        $p = [];
        if (!empty($f['rol'])) {
            $w[] = 'rol = :r';
            $p['r'] = (string)$f['rol'];
        }
        if (!empty($f['q'])) {
            $w[] = '(nombre LIKE :q OR email LIKE :q2)';
            $like = '%' . (string)$f['q'] . '%';
            $p['q'] = $like; $p['q2'] = $like;
        }
        return (int)Database::value('SELECT COUNT(*) FROM users WHERE ' . implode(' AND ', $w), $p, 0);
    }

    public static function crear(string $nombre, string $email, string $password, string $rol, string $telefono = ''): int
    {
        return Database::insert(
            'INSERT INTO users (nombre, email, password_hash, rol, telefono, activo)
             VALUES (:n, :e, :p, :r, :t, 1)',
            [
                'n' => mb_substr($nombre, 0, 120),
                'e' => mb_strtolower(trim($email)),
                'p' => Auth::hash($password),
                'r' => $rol,
                't' => mb_substr($telefono, 0, 40),
            ]
        );
    }

    public static function emailDisponible(string $email, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :e';
        $p = ['e' => mb_strtolower(trim($email))];
        if ($exceptoId) {
            $sql .= ' AND id <> :id';
            $p['id'] = $exceptoId;
        }
        return (int)Database::value($sql, $p, 0) === 0;
    }

    public static function generarPassword(int $largo = 12): string
    {
        $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $min = 'abcdefghijkmnopqrstuvwxyz';
        $num = '23456789';
        $sim = '!@#$%&*';
        $todo = $abc . $min . $num . $sim;
        $out = $abc[random_int(0, strlen($abc) - 1)]
             . $min[random_int(0, strlen($min) - 1)]
             . $num[random_int(0, strlen($num) - 1)]
             . $sim[random_int(0, strlen($sim) - 1)];
        for ($i = strlen($out); $i < $largo; $i++) {
            $out .= $todo[random_int(0, strlen($todo) - 1)];
        }
        return str_shuffle($out);
    }

    public static function hijosCount(int $userId): int
    {
        return (int)Database::value(
            'SELECT COUNT(DISTINCT alumno_id) FROM encargados WHERE user_id = :u',
            ['u' => $userId],
            0
        );
    }
}
