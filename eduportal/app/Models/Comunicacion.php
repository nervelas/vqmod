<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

final class Comunicacion
{
    /** Avisos visibles para el usuario actual (o para un alumno concreto). */
    public static function avisosPara(?int $alumnoId = null, int $limite = 20): array
    {
        $ahora = date('Y-m-d H:i:s');
        $cond = ['a.activo = 1', '(a.publicar_en IS NULL OR a.publicar_en <= :ahora)', '(a.caduca_en IS NULL OR a.caduca_en >= :ahora2)'];
        $p = ['ahora' => $ahora, 'ahora2' => $ahora];
        $rol = Auth::rol();

        if (Auth::is('superadmin', 'secretaria')) {
            $filtro = '1 = 1';
        } else {
            $partes = ["a.destino = 'todos'"];
            if ($rol !== null) {
                $partes[] = "(a.destino = 'rol' AND a.destino_rol = :rol)";
                $p['rol'] = $rol;
            }
            $ctx = self::contextoAlumnos($alumnoId);
            if ($ctx['secciones'] !== []) {
                $partes[] = "(a.destino = 'seccion' AND a.destino_id IN (" . implode(',', $ctx['secciones']) . '))';
            }
            if ($ctx['grados'] !== []) {
                $partes[] = "(a.destino = 'grado' AND a.destino_id IN (" . implode(',', $ctx['grados']) . '))';
            }
            if ($ctx['niveles'] !== []) {
                $partes[] = "(a.destino = 'nivel' AND a.destino_id IN (" . implode(',', $ctx['niveles']) . '))';
            }
            if ($ctx['alumnos'] !== []) {
                $partes[] = "(a.destino = 'alumno' AND a.destino_id IN (" . implode(',', $ctx['alumnos']) . '))';
            }
            $filtro = '(' . implode(' OR ', $partes) . ')';
        }
        $cond[] = $filtro;

        $uid = Auth::id();
        $sel = 'a.*, u.nombre AS autor';
        $join = 'LEFT JOIN users u ON u.id = a.autor_id';
        if ($uid) {
            $sel .= ', (SELECT COUNT(*) FROM aviso_lecturas al WHERE al.aviso_id = a.id AND al.user_id = :uid) AS leido';
            $p['uid'] = $uid;
        } else {
            $sel .= ', 0 AS leido';
        }
        return Database::all(
            'SELECT ' . $sel . ' FROM avisos a ' . $join . '
             WHERE ' . implode(' AND ', $cond) . '
             ORDER BY COALESCE(a.publicar_en, a.creado_en) DESC
             LIMIT ' . max(1, min(100, $limite)),
            $p
        );
    }

    /** @return array{secciones:array,grados:array,niveles:array,alumnos:array} */
    private static function contextoAlumnos(?int $alumnoId): array
    {
        $ids = $alumnoId !== null ? [$alumnoId] : (Auth::alumnosPermitidos() ?? []);
        $out = ['secciones' => [], 'grados' => [], 'niveles' => [], 'alumnos' => []];
        if ($ids === []) {
            return $out;
        }
        $lista = implode(',', array_map('intval', $ids));
        $filas = Database::all(
            'SELECT i.alumno_id, i.seccion_id, s.grado_id, g.nivel_id
             FROM inscripciones i
             JOIN secciones s ON s.id = i.seccion_id
             JOIN grados g ON g.id = s.grado_id
             WHERE i.alumno_id IN (' . $lista . ') AND i.ciclo_id = :c',
            ['c' => Academico::cicloActivoId()]
        );
        foreach ($filas as $f) {
            $out['alumnos'][] = (int)$f['alumno_id'];
            $out['secciones'][] = (int)$f['seccion_id'];
            $out['grados'][] = (int)$f['grado_id'];
            $out['niveles'][] = (int)$f['nivel_id'];
        }
        foreach ($out as $k => $v) {
            $out[$k] = array_values(array_unique($v));
        }
        return $out;
    }

    public static function marcarLeido(int $avisoId, int $userId): void
    {
        $existe = Database::value(
            'SELECT id FROM aviso_lecturas WHERE aviso_id = :a AND user_id = :u',
            ['a' => $avisoId, 'u' => $userId]
        );
        if (!$existe) {
            Database::run('INSERT INTO aviso_lecturas (aviso_id, user_id) VALUES (:a, :u)', ['a' => $avisoId, 'u' => $userId]);
        }
    }

    public static function lecturas(int $avisoId): int
    {
        return (int)Database::value('SELECT COUNT(*) FROM aviso_lecturas WHERE aviso_id = :a', ['a' => $avisoId], 0);
    }

    public static function eventos(?string $desde = null, ?string $hasta = null, bool $soloPublicos = false): array
    {
        $desde = $desde ?: date('Y-m-01');
        $hasta = $hasta ?: date('Y-m-t', strtotime('+2 months'));
        $w = ['fecha_inicio <= :h', '(COALESCE(fecha_fin, fecha_inicio) >= :d)'];
        $p = ['h' => $hasta, 'd' => $desde];
        if ($soloPublicos) {
            $w[] = 'publico = 1';
        }
        return Database::all(
            'SELECT * FROM eventos WHERE ' . implode(' AND ', $w) . ' ORDER BY fecha_inicio',
            $p
        );
    }

    public static function tareasDe(array $asignacionIds, int $limite = 50): array
    {
        if ($asignacionIds === []) {
            return [];
        }
        $ids = implode(',', array_map('intval', $asignacionIds));
        return Database::all(
            'SELECT t.*, m.nombre AS materia, CONCAT(g.nombre, \' \', s.nombre) AS grupo
             FROM tareas t
             JOIN asignaciones a ON a.id = t.asignacion_id
             JOIN materias m ON m.id = a.materia_id
             JOIN secciones s ON s.id = a.seccion_id
             JOIN grados g ON g.id = s.grado_id
             WHERE t.asignacion_id IN (' . $ids . ')
             ORDER BY t.fecha_entrega DESC, t.id DESC
             LIMIT ' . max(1, min(200, $limite))
        );
    }

    public static function tareasDeAlumno(int $alumnoId, int $limite = 30): array
    {
        $asigs = Database::all(
            'SELECT a.id FROM inscripciones i
             JOIN asignaciones a ON a.seccion_id = i.seccion_id AND a.ciclo_id = i.ciclo_id
             WHERE i.alumno_id = :al AND i.ciclo_id = :c',
            ['al' => $alumnoId, 'c' => Academico::cicloActivoId()]
        );
        if ($asigs === []) {
            return [];
        }
        $ids = implode(',', array_map(static fn($a) => (int)$a['id'], $asigs));
        return Database::all(
            'SELECT t.*, m.nombre AS materia, u.nombre AS docente,
                    e.estado AS entrega_estado, e.entregado_en, e.archivo AS entrega_archivo
             FROM tareas t
             JOIN asignaciones a ON a.id = t.asignacion_id
             JOIN materias m ON m.id = a.materia_id
             LEFT JOIN users u ON u.id = a.docente_id
             LEFT JOIN tarea_entregas e ON e.tarea_id = t.id AND e.alumno_id = :al
             WHERE t.asignacion_id IN (' . $ids . ')
             ORDER BY t.fecha_entrega DESC, t.id DESC
             LIMIT ' . max(1, min(200, $limite)),
            ['al' => $alumnoId]
        );
    }

    /** Conversacion entre dos usuarios (docente <-> encargado). */
    public static function conversacion(int $userA, int $userB, int $limite = 100): array
    {
        return Database::all(
            'SELECT m.*, u.nombre AS remitente
             FROM mensajes m JOIN users u ON u.id = m.de_id
             WHERE (m.de_id = :a AND m.para_id = :b) OR (m.de_id = :b2 AND m.para_id = :a2)
             ORDER BY m.creado_en
             LIMIT ' . max(1, min(300, $limite)),
            ['a' => $userA, 'b' => $userB, 'b2' => $userB, 'a2' => $userA]
        );
    }

    public static function hilos(int $userId): array
    {
        return Database::all(
            'SELECT otro.id AS user_id, otro.nombre, otro.rol,
                    MAX(m.creado_en) AS ultimo,
                    SUM(CASE WHEN m.para_id = :u AND m.leido_en IS NULL THEN 1 ELSE 0 END) AS no_leidos
             FROM mensajes m
             JOIN users otro ON otro.id = CASE WHEN m.de_id = :u2 THEN m.para_id ELSE m.de_id END
             WHERE m.de_id = :u3 OR m.para_id = :u4
             GROUP BY otro.id, otro.nombre, otro.rol
             ORDER BY ultimo DESC',
            ['u' => $userId, 'u2' => $userId, 'u3' => $userId, 'u4' => $userId]
        );
    }

    public static function marcarConversacionLeida(int $userId, int $otroId): void
    {
        Database::run(
            'UPDATE mensajes SET leido_en = :t WHERE para_id = :u AND de_id = :o AND leido_en IS NULL',
            ['t' => date('Y-m-d H:i:s'), 'u' => $userId, 'o' => $otroId]
        );
    }

    public static function mensajesNoLeidos(int $userId): int
    {
        return (int)Database::value(
            'SELECT COUNT(*) FROM mensajes WHERE para_id = :u AND leido_en IS NULL',
            ['u' => $userId],
            0
        );
    }

    /** Usuarios con los que el usuario actual puede conversar. */
    public static function contactos(int $userId, string $rol): array
    {
        $ciclo = Academico::cicloActivoId();
        if ($rol === 'padre') {
            return Database::all(
                'SELECT DISTINCT u.id, u.nombre, u.rol
                 FROM encargados e
                 JOIN inscripciones i ON i.alumno_id = e.alumno_id AND i.ciclo_id = :c
                 JOIN asignaciones a ON a.seccion_id = i.seccion_id AND a.ciclo_id = i.ciclo_id
                 JOIN users u ON u.id = a.docente_id
                 WHERE e.user_id = :u AND u.activo = 1
                 ORDER BY u.nombre',
                ['c' => $ciclo, 'u' => $userId]
            );
        }
        if ($rol === 'docente') {
            return Database::all(
                'SELECT DISTINCT u.id, u.nombre, u.rol
                 FROM asignaciones a
                 JOIN inscripciones i ON i.seccion_id = a.seccion_id AND i.ciclo_id = a.ciclo_id
                 JOIN encargados e ON e.alumno_id = i.alumno_id
                 JOIN users u ON u.id = e.user_id
                 WHERE a.docente_id = :u AND a.ciclo_id = :c AND u.activo = 1
                 ORDER BY u.nombre',
                ['u' => $userId, 'c' => $ciclo]
            );
        }
        return Database::all(
            'SELECT id, nombre, rol FROM users WHERE activo = 1 AND id <> :u ORDER BY nombre LIMIT 300',
            ['u' => $userId]
        );
    }

    public static function puedeEscribirA(int $userId, string $rol, int $destino): bool
    {
        if ($rol === 'superadmin' || $rol === 'secretaria') {
            return (int)Database::value('SELECT COUNT(*) FROM users WHERE id = :d AND activo = 1', ['d' => $destino], 0) > 0;
        }
        foreach (self::contactos($userId, $rol) as $c) {
            if ((int)$c['id'] === $destino) {
                return true;
            }
        }
        return false;
    }
}
