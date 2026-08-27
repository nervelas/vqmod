<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Settings;

final class Kpi
{
    public static function panel(): array
    {
        $ciclo = Academico::cicloActivoId();
        $hoy = date('Y-m-d');
        $mesIni = date('Y-m-01');
        $mesFin = date('Y-m-t');

        $ingresosMes = (float)Database::value(
            'SELECT COALESCE(SUM(monto),0) FROM pagos WHERE estado = \'aprobado\' AND fecha BETWEEN :i AND :f',
            ['i' => $mesIni, 'f' => $mesFin],
            0
        );
        $meta = Settings::float('meta_ingresos', 0);

        $morosidad = (float)Database::value(
            'SELECT COALESCE(SUM(monto - descuento + mora - pagado),0) FROM cargos
             WHERE ciclo_id = :c AND estado IN (\'pendiente\',\'parcial\') AND fecha_vencimiento < :h',
            ['c' => $ciclo, 'h' => $hoy],
            0
        );
        $porCobrar = (float)Database::value(
            'SELECT COALESCE(SUM(monto - descuento + mora - pagado),0) FROM cargos
             WHERE ciclo_id = :c AND estado IN (\'pendiente\',\'parcial\')',
            ['c' => $ciclo],
            0
        );
        $facturado = (float)Database::value(
            'SELECT COALESCE(SUM(monto - descuento + mora),0) FROM cargos WHERE ciclo_id = :c AND estado <> \'anulado\'',
            ['c' => $ciclo],
            0
        );
        $alumnosActivos = (int)Database::value(
            'SELECT COUNT(*) FROM inscripciones WHERE ciclo_id = :c AND estado = \'activo\'',
            ['c' => $ciclo],
            0
        );
        $pendientesAprobacion = (int)Database::value(
            'SELECT COUNT(*) FROM pagos WHERE estado = \'revision\'',
            [],
            0
        );
        $preinscripciones = (int)Database::value(
            'SELECT COUNT(*) FROM preinscripciones WHERE estado = \'nueva\'',
            [],
            0
        );
        $asistencia = Asistencia::porcentajeDia($hoy);
        $proximos = Cobranza::proximosVencimientos(7);

        return [
            'ingresos_mes'   => round($ingresosMes, 2),
            'meta'           => $meta,
            'meta_pct'       => $meta > 0 ? min(100, round($ingresosMes / $meta * 100, 1)) : 0.0,
            'morosidad'      => round($morosidad, 2),
            'morosidad_pct'  => $facturado > 0 ? round($morosidad / $facturado * 100, 1) : 0.0,
            'por_cobrar'     => round($porCobrar, 2),
            'alumnos'        => $alumnosActivos,
            'asistencia'     => $asistencia,
            'por_aprobar'    => $pendientesAprobacion,
            'preinscritos'   => $preinscripciones,
            'proximos'       => $proximos,
            'proximos_total' => round(array_sum(array_map(
                static fn($c) => (float)$c['monto'] - (float)$c['descuento'] + (float)$c['mora'] - (float)$c['pagado'],
                $proximos
            )), 2),
        ];
    }

    public static function morosidadPorGrado(?int $cicloId = null): array
    {
        return Database::all(
            'SELECT CONCAT(g.nombre, \' \', s.nombre) AS grupo,
                    COALESCE(SUM(c.monto - c.descuento + c.mora - c.pagado),0) AS saldo,
                    COUNT(DISTINCT c.alumno_id) AS alumnos
             FROM cargos c
             JOIN inscripciones i ON i.alumno_id = c.alumno_id AND i.ciclo_id = c.ciclo_id
             JOIN secciones s ON s.id = i.seccion_id
             JOIN grados g ON g.id = s.grado_id
             WHERE c.ciclo_id = :c AND c.estado IN (\'pendiente\',\'parcial\') AND c.fecha_vencimiento < :h
             GROUP BY g.nombre, s.nombre
             ORDER BY saldo DESC',
            ['c' => $cicloId ?: Academico::cicloActivoId(), 'h' => date('Y-m-d')]
        );
    }

    public static function distribucionAlumnos(?int $cicloId = null): array
    {
        return Database::all(
            'SELECT n.nombre AS nivel, COUNT(*) AS total
             FROM inscripciones i
             JOIN secciones s ON s.id = i.seccion_id
             JOIN grados g ON g.id = s.grado_id
             JOIN niveles n ON n.id = g.nivel_id
             WHERE i.ciclo_id = :c AND i.estado = \'activo\'
             GROUP BY n.id, n.nombre ORDER BY n.orden',
            ['c' => $cicloId ?: Academico::cicloActivoId()]
        );
    }

    public static function asistenciaUltimosDias(int $dias = 14): array
    {
        $out = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $f = date('Y-m-d', strtotime("-{$i} days"));
            $r = Asistencia::porcentajeDia($f);
            if ($r['total'] === 0) {
                continue;
            }
            $out[] = ['fecha' => $f, 'porcentaje' => $r['porcentaje']];
        }
        return $out;
    }
}
