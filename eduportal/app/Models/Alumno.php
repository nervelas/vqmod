<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

final class Alumno
{
    public static function buscar(array $f = [], int $limite = 25, int $offset = 0): array
    {
        [$w, $p] = self::filtros($f);
        $sql = 'SELECT a.*, i.seccion_id, i.beca_pct, s.nombre AS seccion, g.nombre AS grado,
                       n.nombre AS nivel, CONCAT(g.nombre, \' \', s.nombre) AS grupo,
                       CONCAT(a.apellidos, \', \', a.nombres) AS nombre_completo
                FROM alumnos a
                LEFT JOIN inscripciones i ON i.alumno_id = a.id AND i.ciclo_id = :ciclo
                LEFT JOIN secciones s ON s.id = i.seccion_id
                LEFT JOIN grados g ON g.id = s.grado_id
                LEFT JOIN niveles n ON n.id = g.nivel_id
                WHERE ' . implode(' AND ', $w) . '
                ORDER BY a.apellidos, a.nombres
                LIMIT ' . max(1, $limite) . ' OFFSET ' . max(0, $offset);
        return Database::all($sql, $p);
    }

    public static function contar(array $f = []): int
    {
        [$w, $p] = self::filtros($f);
        unset($p['ciclo2']);
        $sql = 'SELECT COUNT(*) FROM alumnos a
                LEFT JOIN inscripciones i ON i.alumno_id = a.id AND i.ciclo_id = :ciclo
                LEFT JOIN secciones s ON s.id = i.seccion_id
                LEFT JOIN grados g ON g.id = s.grado_id
                WHERE ' . implode(' AND ', $w);
        return (int)Database::value($sql, $p, 0);
    }

    /** @return array{0:array<int,string>,1:array<string,mixed>} */
    private static function filtros(array $f): array
    {
        $w = ['1 = 1'];
        $p = ['ciclo' => (int)($f['ciclo_id'] ?? Academico::cicloActivoId())];

        if (!empty($f['q'])) {
            $w[] = '(a.nombres LIKE :q OR a.apellidos LIKE :q2 OR a.codigo LIKE :q3)';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], (string)$f['q']) . '%';
            $p['q'] = $like; $p['q2'] = $like; $p['q3'] = $like;
        }
        if (!empty($f['seccion_id'])) {
            $w[] = 'i.seccion_id = :sec';
            $p['sec'] = (int)$f['seccion_id'];
        }
        if (!empty($f['grado_id'])) {
            $w[] = 's.grado_id = :gra';
            $p['gra'] = (int)$f['grado_id'];
        }
        if (!empty($f['nivel_id'])) {
            $w[] = 'g.nivel_id = :niv';
            $p['niv'] = (int)$f['nivel_id'];
        }
        if (!empty($f['estado'])) {
            $w[] = 'a.estado = :est';
            $p['est'] = (string)$f['estado'];
        }
        // Control de acceso por propiedad
        $permitidos = $f['_sin_restriccion'] ?? false ? null : Auth::alumnosPermitidos();
        if (is_array($permitidos)) {
            if ($permitidos === []) {
                $w[] = '1 = 0';
            } else {
                $ids = implode(',', array_map('intval', $permitidos));
                $w[] = 'a.id IN (' . $ids . ')';
            }
        }
        return [$w, $p];
    }

    public static function porId(int $id): ?array
    {
        $ciclo = Academico::cicloActivoId();
        return Database::one(
            'SELECT a.*, i.seccion_id, i.beca_pct, i.estado AS estado_inscripcion,
                    s.nombre AS seccion, g.nombre AS grado, g.id AS grado_id, n.nombre AS nivel,
                    CONCAT(g.nombre, \' \', s.nombre) AS grupo
             FROM alumnos a
             LEFT JOIN inscripciones i ON i.alumno_id = a.id AND i.ciclo_id = :c
             LEFT JOIN secciones s ON s.id = i.seccion_id
             LEFT JOIN grados g ON g.id = s.grado_id
             LEFT JOIN niveles n ON n.id = g.nivel_id
             WHERE a.id = :id',
            ['c' => $ciclo, 'id' => $id]
        );
    }

    public static function nombre(array $a): string
    {
        return trim(($a['nombres'] ?? '') . ' ' . ($a['apellidos'] ?? ''));
    }

    public static function encargados(int $alumnoId): array
    {
        return Database::all(
            'SELECT * FROM encargados WHERE alumno_id = :a ORDER BY principal DESC, orden, id',
            ['a' => $alumnoId]
        );
    }

    public static function encargadoPrincipal(int $alumnoId): ?array
    {
        return Database::one(
            'SELECT * FROM encargados WHERE alumno_id = :a ORDER BY principal DESC, orden, id LIMIT 1',
            ['a' => $alumnoId]
        );
    }

    public static function hijosDe(int $userId): array
    {
        return Database::all(
            'SELECT DISTINCT a.*, s.nombre AS seccion, g.nombre AS grado,
                    CONCAT(g.nombre, \' \', s.nombre) AS grupo
             FROM alumnos a
             JOIN encargados e ON e.alumno_id = a.id AND e.user_id = :u
             LEFT JOIN inscripciones i ON i.alumno_id = a.id AND i.ciclo_id = :c
             LEFT JOIN secciones s ON s.id = i.seccion_id
             LEFT JOIN grados g ON g.id = s.grado_id
             ORDER BY a.apellidos, a.nombres',
            ['u' => $userId, 'c' => Academico::cicloActivoId()]
        );
    }

    public static function deSeccion(int $seccionId, ?int $cicloId = null): array
    {
        return Database::all(
            'SELECT a.id, a.codigo, a.nombres, a.apellidos, a.foto,
                    CONCAT(a.apellidos, \', \', a.nombres) AS nombre_completo
             FROM inscripciones i
             JOIN alumnos a ON a.id = i.alumno_id
             WHERE i.seccion_id = :s AND i.ciclo_id = :c AND i.estado = \'activo\'
             ORDER BY a.apellidos, a.nombres',
            ['s' => $seccionId, 'c' => $cicloId ?: Academico::cicloActivoId()]
        );
    }

    public static function historial(int $alumnoId): array
    {
        return Database::all(
            'SELECT i.*, c.nombre AS ciclo, g.nombre AS grado, s.nombre AS seccion, n.nombre AS nivel
             FROM inscripciones i
             JOIN ciclos c ON c.id = i.ciclo_id
             JOIN secciones s ON s.id = i.seccion_id
             JOIN grados g ON g.id = s.grado_id
             JOIN niveles n ON n.id = g.nivel_id
             WHERE i.alumno_id = :a
             ORDER BY c.id DESC',
            ['a' => $alumnoId]
        );
    }

    public static function siguienteCodigo(): string
    {
        $anio = date('Y');
        $n = (int)Database::value(
            'SELECT COUNT(*) FROM alumnos WHERE codigo LIKE :p',
            ['p' => $anio . '-%'],
            0
        );
        do {
            $n++;
            $codigo = $anio . '-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
            $existe = Database::value('SELECT id FROM alumnos WHERE codigo = :c', ['c' => $codigo]);
        } while ($existe && $n < 100000);
        return $codigo;
    }

    /** Cantidad de hermanos inscritos y activos en el ciclo (para descuento). */
    public static function hermanosActivos(int $alumnoId, int $cicloId): int
    {
        $userIds = Database::all(
            'SELECT DISTINCT user_id FROM encargados WHERE alumno_id = :a AND user_id IS NOT NULL',
            ['a' => $alumnoId]
        );
        if ($userIds === []) {
            return 1;
        }
        $ids = implode(',', array_map(static fn($r) => (int)$r['user_id'], $userIds));
        return max(1, (int)Database::value(
            'SELECT COUNT(DISTINCT e.alumno_id)
             FROM encargados e
             JOIN inscripciones i ON i.alumno_id = e.alumno_id AND i.ciclo_id = :c AND i.estado = \'activo\'
             WHERE e.user_id IN (' . $ids . ')',
            ['c' => $cicloId],
            1
        ));
    }
}
