<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Notificador;

final class Asistencia
{
    public const ESTADOS = ['presente', 'ausente', 'tarde', 'justificado'];

    public static function delDia(int $seccionId, string $fecha): array
    {
        $alumnos = Alumno::deSeccion($seccionId);
        $filas = Database::all(
            'SELECT alumno_id, estado, nota FROM asistencia WHERE seccion_id = :s AND fecha = :f',
            ['s' => $seccionId, 'f' => $fecha]
        );
        $map = [];
        foreach ($filas as $f) {
            $map[(int)$f['alumno_id']] = $f;
        }
        return ['alumnos' => $alumnos, 'registro' => $map];
    }

    public static function guardar(int $seccionId, string $fecha, array $estados, array $notas = []): int
    {
        if (!Auth::puedeUsarSeccion($seccionId)) {
            return 0;
        }
        $validos = Alumno::deSeccion($seccionId);
        $permitidos = array_map(static fn($a) => (int)$a['id'], $validos);
        $n = 0;
        Database::begin();
        try {
            foreach ($estados as $alumnoId => $estado) {
                $alumnoId = (int)$alumnoId;
                if (!in_array($alumnoId, $permitidos, true) || !in_array($estado, self::ESTADOS, true)) {
                    continue;
                }
                $nota = mb_substr((string)($notas[$alumnoId] ?? ''), 0, 180);
                $existe = Database::value(
                    'SELECT id FROM asistencia WHERE alumno_id = :a AND fecha = :f',
                    ['a' => $alumnoId, 'f' => $fecha]
                );
                if ($existe) {
                    Database::run(
                        'UPDATE asistencia SET estado = :e, nota = :n, seccion_id = :s, registrado_por = :u WHERE id = :id',
                        ['e' => $estado, 'n' => $nota, 's' => $seccionId, 'u' => Auth::id(), 'id' => (int)$existe]
                    );
                } else {
                    Database::run(
                        'INSERT INTO asistencia (alumno_id, seccion_id, fecha, estado, nota, registrado_por)
                         VALUES (:a, :s, :f, :e, :n, :u)',
                        ['a' => $alumnoId, 's' => $seccionId, 'f' => $fecha, 'e' => $estado, 'n' => $nota, 'u' => Auth::id()]
                    );
                }
                $n++;
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
        self::revisarAlertas(array_keys($estados));
        return $n;
    }

    /** Alerta automatica al encargado tras 3 ausencias injustificadas. */
    public static function revisarAlertas(array $alumnoIds): void
    {
        foreach ($alumnoIds as $id) {
            $id = (int)$id;
            $n = (int)Database::value(
                'SELECT COUNT(*) FROM asistencia
                 WHERE alumno_id = :a AND estado = \'ausente\' AND fecha >= :d',
                ['a' => $id, 'd' => date('Y-m-01')],
                0
            );
            if ($n < 3 || $n % 3 !== 0) {
                continue;
            }
            $alumno = Database::one('SELECT nombres, apellidos FROM alumnos WHERE id = :a', ['a' => $id]);
            if (!$alumno) {
                continue;
            }
            $nombre = trim($alumno['nombres'] . ' ' . $alumno['apellidos']);
            $encargados = Database::all(
                'SELECT DISTINCT user_id FROM encargados WHERE alumno_id = :a AND user_id IS NOT NULL',
                ['a' => $id]
            );
            foreach ($encargados as $e) {
                Notificador::crear(
                    (int)$e['user_id'],
                    'Alerta de inasistencias',
                    $nombre . ' acumula ' . $n . ' ausencias injustificadas este mes.',
                    'portal/asistencia?alumno=' . $id
                );
            }
        }
    }

    public static function resumenMensual(int $alumnoId, int $anio, int $mes): array
    {
        $inicio = sprintf('%04d-%02d-01', $anio, $mes);
        $fin = date('Y-m-t', strtotime($inicio));
        $filas = Database::all(
            'SELECT estado, COUNT(*) AS n FROM asistencia
             WHERE alumno_id = :a AND fecha BETWEEN :i AND :f GROUP BY estado',
            ['a' => $alumnoId, 'i' => $inicio, 'f' => $fin]
        );
        $out = array_fill_keys(self::ESTADOS, 0);
        foreach ($filas as $f) {
            $out[$f['estado']] = (int)$f['n'];
        }
        $out['total'] = array_sum($out);
        return $out;
    }

    public static function detalleMes(int $alumnoId, int $anio, int $mes): array
    {
        $inicio = sprintf('%04d-%02d-01', $anio, $mes);
        $fin = date('Y-m-t', strtotime($inicio));
        return Database::all(
            'SELECT fecha, estado, nota FROM asistencia
             WHERE alumno_id = :a AND fecha BETWEEN :i AND :f ORDER BY fecha',
            ['a' => $alumnoId, 'i' => $inicio, 'f' => $fin]
        );
    }

    public static function reporteSeccion(int $seccionId, int $anio, int $mes): array
    {
        $inicio = sprintf('%04d-%02d-01', $anio, $mes);
        $fin = date('Y-m-t', strtotime($inicio));
        return Database::all(
            'SELECT a.id, a.codigo, a.nombres, a.apellidos,
                    SUM(CASE WHEN s.estado = \'presente\' THEN 1 ELSE 0 END) AS presente,
                    SUM(CASE WHEN s.estado = \'ausente\' THEN 1 ELSE 0 END) AS ausente,
                    SUM(CASE WHEN s.estado = \'tarde\' THEN 1 ELSE 0 END) AS tarde,
                    SUM(CASE WHEN s.estado = \'justificado\' THEN 1 ELSE 0 END) AS justificado
             FROM inscripciones i
             JOIN alumnos a ON a.id = i.alumno_id
             LEFT JOIN asistencia s ON s.alumno_id = a.id AND s.fecha BETWEEN :i AND :f
             WHERE i.seccion_id = :s AND i.estado = \'activo\'
             GROUP BY a.id, a.codigo, a.nombres, a.apellidos
             ORDER BY a.apellidos, a.nombres',
            ['s' => $seccionId, 'i' => $inicio, 'f' => $fin]
        );
    }

    public static function porcentajeDia(?string $fecha = null): array
    {
        $fecha = $fecha ?: date('Y-m-d');
        $filas = Database::all(
            'SELECT estado, COUNT(*) AS n FROM asistencia WHERE fecha = :f GROUP BY estado',
            ['f' => $fecha]
        );
        $out = array_fill_keys(self::ESTADOS, 0);
        foreach ($filas as $f) {
            $out[$f['estado']] = (int)$f['n'];
        }
        $total = array_sum($out);
        $out['total'] = $total;
        $out['porcentaje'] = $total > 0
            ? round((($out['presente'] + $out['tarde'] + $out['justificado']) / $total) * 100, 1)
            : 0.0;
        return $out;
    }
}
