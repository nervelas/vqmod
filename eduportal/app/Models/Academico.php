<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Academico
{
    public static function cicloActivo(): ?array
    {
        $c = Database::one('SELECT * FROM ciclos WHERE activo = 1 ORDER BY id DESC LIMIT 1');
        return $c ?: Database::one('SELECT * FROM ciclos ORDER BY id DESC LIMIT 1');
    }

    public static function cicloActivoId(): int
    {
        $c = self::cicloActivo();
        return $c ? (int)$c['id'] : 0;
    }

    public static function ciclos(): array
    {
        return Database::all('SELECT * FROM ciclos ORDER BY id DESC');
    }

    public static function niveles(): array
    {
        return Database::all('SELECT * FROM niveles ORDER BY orden, id');
    }

    public static function grados(?int $nivelId = null): array
    {
        if ($nivelId) {
            return Database::all(
                'SELECT g.*, n.nombre AS nivel FROM grados g JOIN niveles n ON n.id = g.nivel_id
                 WHERE g.nivel_id = :n ORDER BY g.orden, g.id',
                ['n' => $nivelId]
            );
        }
        return Database::all(
            'SELECT g.*, n.nombre AS nivel, n.orden AS nivel_orden
             FROM grados g JOIN niveles n ON n.id = g.nivel_id
             ORDER BY n.orden, g.orden, g.id'
        );
    }

    public static function secciones(?int $cicloId = null): array
    {
        $cicloId = $cicloId ?: self::cicloActivoId();
        return Database::all(
            'SELECT s.*, g.nombre AS grado, g.nivel_id, n.nombre AS nivel,
                    CONCAT(g.nombre, \' \', s.nombre) AS etiqueta,
                    (SELECT COUNT(*) FROM inscripciones i WHERE i.seccion_id = s.id AND i.estado = \'activo\') AS inscritos,
                    u.nombre AS guia
             FROM secciones s
             JOIN grados g ON g.id = s.grado_id
             JOIN niveles n ON n.id = g.nivel_id
             LEFT JOIN users u ON u.id = s.docente_guia_id
             WHERE s.ciclo_id = :c
             ORDER BY n.orden, g.orden, s.nombre',
            ['c' => $cicloId]
        );
    }

    public static function seccion(int $id): ?array
    {
        return Database::one(
            'SELECT s.*, g.nombre AS grado, g.nivel_id, n.nombre AS nivel,
                    CONCAT(g.nombre, \' \', s.nombre) AS etiqueta
             FROM secciones s
             JOIN grados g ON g.id = s.grado_id
             JOIN niveles n ON n.id = g.nivel_id
             WHERE s.id = :id',
            ['id' => $id]
        );
    }

    public static function materias(): array
    {
        return Database::all(
            'SELECT m.*, n.nombre AS nivel FROM materias m
             LEFT JOIN niveles n ON n.id = m.nivel_id
             WHERE m.activo = 1 ORDER BY m.nombre'
        );
    }

    public static function periodos(?int $cicloId = null): array
    {
        $cicloId = $cicloId ?: self::cicloActivoId();
        return Database::all('SELECT * FROM periodos WHERE ciclo_id = :c ORDER BY orden, id', ['c' => $cicloId]);
    }

    public static function periodoActual(?int $cicloId = null): ?array
    {
        $cicloId = $cicloId ?: self::cicloActivoId();
        $hoy = date('Y-m-d');
        $p = Database::one(
            'SELECT * FROM periodos WHERE ciclo_id = :c AND fecha_inicio <= :h AND fecha_fin >= :h2 ORDER BY orden LIMIT 1',
            ['c' => $cicloId, 'h' => $hoy, 'h2' => $hoy]
        );
        if ($p) {
            return $p;
        }
        return Database::one('SELECT * FROM periodos WHERE ciclo_id = :c AND cerrado = 0 ORDER BY orden LIMIT 1', ['c' => $cicloId])
            ?: Database::one('SELECT * FROM periodos WHERE ciclo_id = :c ORDER BY orden DESC LIMIT 1', ['c' => $cicloId]);
    }

    /** Asignaciones docente-materia-grado con filtros opcionales. */
    public static function asignaciones(array $filtros = []): array
    {
        $w = ['a.ciclo_id = :c'];
        $p = ['c' => $filtros['ciclo_id'] ?? self::cicloActivoId()];
        if (!empty($filtros['docente_id'])) {
            $w[] = 'a.docente_id = :d';
            $p['d'] = (int)$filtros['docente_id'];
        }
        if (!empty($filtros['seccion_id'])) {
            $w[] = 'a.seccion_id = :s';
            $p['s'] = (int)$filtros['seccion_id'];
        }
        if (!empty($filtros['materia_id'])) {
            $w[] = 'a.materia_id = :m';
            $p['m'] = (int)$filtros['materia_id'];
        }
        return Database::all(
            'SELECT a.*, m.nombre AS materia, s.nombre AS seccion, g.nombre AS grado,
                    n.nombre AS nivel, n.orden AS nivel_orden, g.orden AS grado_orden,
                    u.nombre AS docente,
                    CONCAT(g.nombre, \' \', s.nombre) AS grupo
             FROM asignaciones a
             JOIN materias m ON m.id = a.materia_id
             JOIN secciones s ON s.id = a.seccion_id
             JOIN grados g ON g.id = s.grado_id
             JOIN niveles n ON n.id = g.nivel_id
             LEFT JOIN users u ON u.id = a.docente_id
             WHERE ' . implode(' AND ', $w) . '
             ORDER BY n.orden, g.orden, s.nombre, m.nombre',
            $p
        );
    }

    public static function asignacion(int $id): ?array
    {
        $r = self::asignaciones([]);
        foreach ($r as $a) {
            if ((int)$a['id'] === $id) {
                return $a;
            }
        }
        return Database::one(
            'SELECT a.*, m.nombre AS materia, s.nombre AS seccion, g.nombre AS grado,
                    CONCAT(g.nombre, \' \', s.nombre) AS grupo
             FROM asignaciones a
             JOIN materias m ON m.id = a.materia_id
             JOIN secciones s ON s.id = a.seccion_id
             JOIN grados g ON g.id = s.grado_id
             WHERE a.id = :id',
            ['id' => $id]
        );
    }

    public static function docentes(): array
    {
        return Database::all("SELECT id, nombre, email FROM users WHERE rol = 'docente' AND activo = 1 ORDER BY nombre");
    }
}
