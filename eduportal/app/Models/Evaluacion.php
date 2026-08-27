<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Settings;

final class Evaluacion
{
    public static function pondZona(): float { return Settings::float('pond_zona', 60); }
    public static function pondExamen(): float { return Settings::float('pond_examen', 40); }
    public static function notaMinima(): float { return Settings::float('nota_minima', 60); }
    public static function notaMaxima(): float { return Settings::float('nota_maxima', 100); }

    public static function actividades(int $asignacionId, int $periodoId): array
    {
        return Database::all(
            'SELECT * FROM actividades WHERE asignacion_id = :a AND periodo_id = :p ORDER BY tipo DESC, id',
            ['a' => $asignacionId, 'p' => $periodoId]
        );
    }

    public static function actividad(int $id): ?array
    {
        return Database::one('SELECT * FROM actividades WHERE id = :id', ['id' => $id]);
    }

    /** Suma de ponderaciones por tipo, para validar la configuracion del docente. */
    public static function ponderacionUsada(int $asignacionId, int $periodoId): array
    {
        $r = Database::all(
            'SELECT tipo, SUM(ponderacion) AS total FROM actividades
             WHERE asignacion_id = :a AND periodo_id = :p GROUP BY tipo',
            ['a' => $asignacionId, 'p' => $periodoId]
        );
        $out = ['zona' => 0.0, 'examen' => 0.0];
        foreach ($r as $f) {
            $out[$f['tipo']] = round((float)$f['total'], 2);
        }
        return $out;
    }

    /** Cuadricula de captura: alumnos x actividades con sus punteos. */
    public static function cuadricula(int $asignacionId, int $periodoId, int $seccionId): array
    {
        $alumnos = Alumno::deSeccion($seccionId);
        $actividades = self::actividades($asignacionId, $periodoId);
        $ids = array_map(static fn($a) => (int)$a['id'], $actividades);
        $notas = [];
        if ($ids !== []) {
            $filas = Database::all(
                'SELECT n.actividad_id, n.alumno_id, n.punteo FROM notas n
                 WHERE n.actividad_id IN (' . implode(',', $ids) . ')'
            );
            foreach ($filas as $f) {
                $notas[(int)$f['alumno_id']][(int)$f['actividad_id']] = $f['punteo'] === null ? null : (float)$f['punteo'];
            }
        }
        $periodo = Database::all('SELECT * FROM notas_periodo WHERE asignacion_id = :a AND periodo_id = :p', [
            'a' => $asignacionId, 'p' => $periodoId,
        ]);
        $resumen = [];
        foreach ($periodo as $np) {
            $resumen[(int)$np['alumno_id']] = $np;
        }
        return ['alumnos' => $alumnos, 'actividades' => $actividades, 'notas' => $notas, 'resumen' => $resumen];
    }

    /**
     * Guarda un punteo validando rango contra la ponderacion de la actividad.
     * @return array{ok:bool, error?:string, total?:float, zona?:float, examen?:float}
     */
    public static function guardarNota(int $actividadId, int $alumnoId, ?float $punteo): array
    {
        $act = self::actividad($actividadId);
        if (!$act) {
            return ['ok' => false, 'error' => 'La actividad no existe.'];
        }
        if (!Auth::puedeUsarAsignacion((int)$act['asignacion_id']) && !Auth::is('superadmin')) {
            return ['ok' => false, 'error' => 'No tiene permisos sobre esta materia.'];
        }
        $periodo = Database::one('SELECT * FROM periodos WHERE id = :p', ['p' => (int)$act['periodo_id']]);
        if ($periodo && (int)$periodo['cerrado'] === 1 && !Auth::is('superadmin')) {
            return ['ok' => false, 'error' => 'El periodo esta cerrado.'];
        }
        if (!Auth::puedeVerAlumno($alumnoId)) {
            return ['ok' => false, 'error' => 'El alumno no pertenece a sus grupos.'];
        }
        $max = (float)$act['ponderacion'];
        if ($punteo !== null && ($punteo < 0 || $punteo > $max)) {
            return ['ok' => false, 'error' => 'El punteo debe estar entre 0 y ' . rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.') . '.'];
        }
        $existe = Database::value(
            'SELECT id FROM notas WHERE actividad_id = :a AND alumno_id = :al',
            ['a' => $actividadId, 'al' => $alumnoId]
        );
        if ($existe) {
            Database::run(
                'UPDATE notas SET punteo = :p, actualizado_por = :u, actualizado_en = :t WHERE id = :id',
                ['p' => $punteo, 'u' => Auth::id(), 't' => date('Y-m-d H:i:s'), 'id' => (int)$existe]
            );
        } else {
            Database::run(
                'INSERT INTO notas (actividad_id, alumno_id, punteo, actualizado_por, actualizado_en)
                 VALUES (:a, :al, :p, :u, :t)',
                ['a' => $actividadId, 'al' => $alumnoId, 'p' => $punteo, 'u' => Auth::id(), 't' => date('Y-m-d H:i:s')]
            );
        }
        $r = self::recalcular($alumnoId, (int)$act['asignacion_id'], (int)$act['periodo_id']);
        return ['ok' => true] + $r;
    }

    /** Recalcula zona, examen y total del alumno en la materia y periodo. */
    public static function recalcular(int $alumnoId, int $asignacionId, int $periodoId): array
    {
        $filas = Database::all(
            'SELECT a.tipo, a.ponderacion, n.punteo
             FROM actividades a
             LEFT JOIN notas n ON n.actividad_id = a.id AND n.alumno_id = :al
             WHERE a.asignacion_id = :a AND a.periodo_id = :p',
            ['al' => $alumnoId, 'a' => $asignacionId, 'p' => $periodoId]
        );
        $zona = 0.0;
        $examen = 0.0;
        foreach ($filas as $f) {
            $v = $f['punteo'] === null ? 0.0 : (float)$f['punteo'];
            if ($f['tipo'] === 'examen') {
                $examen += $v;
            } else {
                $zona += $v;
            }
        }
        $zona   = round(min($zona, self::pondZona()), 2);
        $examen = round(min($examen, self::pondExamen()), 2);
        $total  = round(min($zona + $examen, self::notaMaxima()), 2);

        $existe = Database::value(
            'SELECT id FROM notas_periodo WHERE alumno_id = :al AND asignacion_id = :a AND periodo_id = :p',
            ['al' => $alumnoId, 'a' => $asignacionId, 'p' => $periodoId]
        );
        if ($existe) {
            Database::run(
                'UPDATE notas_periodo SET zona = :z, examen = :e, total = :t, actualizado_en = :f WHERE id = :id',
                ['z' => $zona, 'e' => $examen, 't' => $total, 'f' => date('Y-m-d H:i:s'), 'id' => (int)$existe]
            );
        } else {
            Database::run(
                'INSERT INTO notas_periodo (alumno_id, asignacion_id, periodo_id, zona, examen, total, actualizado_en)
                 VALUES (:al, :a, :p, :z, :e, :t, :f)',
                ['al' => $alumnoId, 'a' => $asignacionId, 'p' => $periodoId,
                 'z' => $zona, 'e' => $examen, 't' => $total, 'f' => date('Y-m-d H:i:s')]
            );
        }
        return ['zona' => $zona, 'examen' => $examen, 'total' => $total];
    }

    public static function guardarConducta(int $alumnoId, int $asignacionId, int $periodoId, ?float $conducta, string $comentario = ''): void
    {
        self::recalcular($alumnoId, $asignacionId, $periodoId);
        Database::run(
            'UPDATE notas_periodo SET conducta = :c, comentario = :co
             WHERE alumno_id = :al AND asignacion_id = :a AND periodo_id = :p',
            ['c' => $conducta, 'co' => mb_substr($comentario, 0, 255), 'al' => $alumnoId, 'a' => $asignacionId, 'p' => $periodoId]
        );
    }

    /** Boleta de un alumno: filas por materia con la nota de cada periodo. */
    public static function boleta(int $alumnoId, ?int $cicloId = null): array
    {
        $cicloId = $cicloId ?: Academico::cicloActivoId();
        $seccionId = (int)Database::value(
            'SELECT seccion_id FROM inscripciones WHERE alumno_id = :a AND ciclo_id = :c',
            ['a' => $alumnoId, 'c' => $cicloId],
            0
        );
        if ($seccionId === 0) {
            return ['periodos' => [], 'materias' => [], 'promedio' => 0.0];
        }
        $periodos = Academico::periodos($cicloId);
        $asigs = Database::all(
            'SELECT a.id, m.nombre AS materia, u.nombre AS docente
             FROM asignaciones a
             JOIN materias m ON m.id = a.materia_id
             LEFT JOIN users u ON u.id = a.docente_id
             WHERE a.seccion_id = :s AND a.ciclo_id = :c
             ORDER BY m.nombre',
            ['s' => $seccionId, 'c' => $cicloId]
        );
        $notas = [];
        if ($asigs !== []) {
            $ids = implode(',', array_map(static fn($a) => (int)$a['id'], $asigs));
            foreach (Database::all(
                'SELECT * FROM notas_periodo WHERE alumno_id = :al AND asignacion_id IN (' . $ids . ')',
                ['al' => $alumnoId]
            ) as $n) {
                $notas[(int)$n['asignacion_id']][(int)$n['periodo_id']] = $n;
            }
        }
        $materias = [];
        $sumaGeneral = 0.0;
        $contGeneral = 0;
        foreach ($asigs as $a) {
            $fila = ['materia' => $a['materia'], 'docente' => $a['docente'], 'asignacion_id' => (int)$a['id'], 'periodos' => [], 'promedio' => null];
            $suma = 0.0; $cont = 0;
            foreach ($periodos as $p) {
                $n = $notas[(int)$a['id']][(int)$p['id']] ?? null;
                $fila['periodos'][(int)$p['id']] = $n;
                if ($n !== null && $n['total'] !== null && (float)$n['total'] > 0) {
                    $suma += (float)$n['total'];
                    $cont++;
                }
            }
            if ($cont > 0) {
                $fila['promedio'] = round($suma / $cont, 2);
                $sumaGeneral += $fila['promedio'];
                $contGeneral++;
            }
            $materias[] = $fila;
        }
        return [
            'periodos' => $periodos,
            'materias' => $materias,
            'promedio' => $contGeneral > 0 ? round($sumaGeneral / $contGeneral, 2) : 0.0,
        ];
    }

    /** Promedio general por alumno de una seccion (para ranking y cuadro de honor). */
    public static function promediosSeccion(int $seccionId, ?int $cicloId = null, ?int $periodoId = null): array
    {
        $cicloId = $cicloId ?: Academico::cicloActivoId();
        $w = ['i.seccion_id = :s', 'i.ciclo_id = :c', 'i.estado = \'activo\''];
        $p = ['s' => $seccionId, 'c' => $cicloId];
        if ($periodoId) {
            $w[] = 'np.periodo_id = :p';
            $p['p'] = $periodoId;
        }
        return Database::all(
            'SELECT a.id, a.codigo, a.nombres, a.apellidos,
                    ROUND(AVG(np.total), 2) AS promedio, COUNT(np.id) AS registros
             FROM inscripciones i
             JOIN alumnos a ON a.id = i.alumno_id
             LEFT JOIN asignaciones asg ON asg.seccion_id = i.seccion_id AND asg.ciclo_id = i.ciclo_id
             LEFT JOIN notas_periodo np ON np.alumno_id = a.id AND np.asignacion_id = asg.id
             WHERE ' . implode(' AND ', $w) . '
             GROUP BY a.id, a.codigo, a.nombres, a.apellidos
             ORDER BY promedio DESC, a.apellidos',
            $p
        );
    }

    /** Cuadro de honor: mejores promedios del ciclo por grado. */
    public static function cuadroHonor(?int $cicloId = null, int $porGrupo = 3): array
    {
        $cicloId = $cicloId ?: Academico::cicloActivoId();
        $filas = Database::all(
            'SELECT a.id, a.codigo, a.nombres, a.apellidos, s.id AS seccion_id,
                    CONCAT(g.nombre, \' \', s.nombre) AS grupo,
                    ROUND(AVG(np.total), 2) AS promedio
             FROM inscripciones i
             JOIN alumnos a ON a.id = i.alumno_id
             JOIN secciones s ON s.id = i.seccion_id
             JOIN grados g ON g.id = s.grado_id
             JOIN asignaciones asg ON asg.seccion_id = s.id AND asg.ciclo_id = i.ciclo_id
             JOIN notas_periodo np ON np.alumno_id = a.id AND np.asignacion_id = asg.id
             WHERE i.ciclo_id = :c AND i.estado = \'activo\'
             GROUP BY a.id, a.codigo, a.nombres, a.apellidos, s.id, g.nombre, s.nombre
             HAVING promedio > 0
             ORDER BY grupo, promedio DESC',
            ['c' => $cicloId]
        );
        $out = [];
        foreach ($filas as $f) {
            $g = $f['grupo'];
            $out[$g] = $out[$g] ?? [];
            if (count($out[$g]) < $porGrupo) {
                $out[$g][] = $f;
            }
        }
        return $out;
    }

    public static function cerrarPeriodo(int $periodoId, bool $cerrar = true): void
    {
        Database::run('UPDATE periodos SET cerrado = :c WHERE id = :id', ['c' => $cerrar ? 1 : 0, 'id' => $periodoId]);
    }
}
