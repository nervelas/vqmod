<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** Indicadores del tablero. */
final class Reporte
{
    public static function tablero(): array
    {
        $periodo = date('Y-m');
        $desde   = $periodo . '-01';
        $hasta   = date('Y-m-t');

        $recaudado = Pago::recaudado($desde, $hasta);
        $esperado  = Cuota::esperadoPeriodo($periodo);
        $morosidad = Cuota::resumenMorosidad(Cuota::morosidad());
        $totalCasas = (int) DB::valor('SELECT COUNT(*) FROM casas', [], 0);
        $carteraTotal = (float) DB::valor(
            'SELECT COALESCE(SUM(monto + mora - descuento - pagado),0) FROM cargos WHERE estado IN ("pendiente","parcial")',
            [],
            0
        );
        $emitidoTotal = (float) DB::valor(
            'SELECT COALESCE(SUM(monto + mora - descuento),0) FROM cargos WHERE estado <> "anulado"',
            [],
            0
        );

        return [
            'periodo'          => $periodo,
            'recaudado'        => $recaudado,
            'esperado'         => $esperado,
            'efectividad'      => $esperado > 0 ? round($recaudado * 100 / $esperado, 1) : 0.0,
            'saldo_bancos'     => Egreso::saldoTotal(),
            'egresos_mes'      => Egreso::total($desde, $hasta),
            'cartera'          => round($carteraTotal, 2),
            'morosidad_pct'    => $emitidoTotal > 0 ? round($carteraTotal * 100 / $emitidoTotal, 1) : 0.0,
            'casas_morosas'    => (int) $morosidad['casas'],
            'casas_total'      => $totalCasas,
            'visitas_hoy'      => Visita::deHoy(),
            'adentro'          => (int) DB::valor('SELECT COUNT(*) FROM visitas WHERE salida IS NULL', [], 0),
            'incidencias'      => Comunicacion::abiertas(),
            'reservas_semana'  => Reserva::deLaSemana(),
            'reservas_pend'    => Reserva::pendientes(),
            'comprobantes'     => Pago::pendientesRevision(),
            'antiguedad'       => $morosidad,
        ];
    }

    /** Serie de recaudación de los últimos meses para las gráficas. */
    public static function serieRecaudacion(int $meses = 12): array
    {
        $out = [];
        for ($i = $meses - 1; $i >= 0; $i--) {
            $p     = date('Y-m', strtotime("-{$i} months"));
            $desde = $p . '-01';
            $hasta = date('Y-m-t', (int) strtotime($desde));
            $out[] = [
                'periodo'   => $p,
                'etiqueta'  => ucfirst(mb_substr(mesNombre((int) substr($p, 5, 2)), 0, 3)) . ' ' . substr($p, 2, 2),
                'recaudado' => Pago::recaudado($desde, $hasta),
                'esperado'  => Cuota::esperadoPeriodo($p),
            ];
        }
        return $out;
    }

    public static function visitasPorDia(int $dias = 14): array
    {
        $filas = DB::todos(
            'SELECT DATE(entrada) AS dia, COUNT(*) AS n FROM visitas
             WHERE entrada >= DATE_SUB(CURDATE(), INTERVAL :d DAY)
             GROUP BY DATE(entrada) ORDER BY dia',
            ['d' => $dias]
        );
        $mapa = [];
        foreach ($filas as $f) {
            $mapa[(string) $f['dia']] = (int) $f['n'];
        }
        $out = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $out[] = ['dia' => $d, 'etiqueta' => date('d/m', (int) strtotime($d)), 'n' => $mapa[$d] ?? 0];
        }
        return $out;
    }

    public static function auditoria(array $filtros = [], int $limite = 200): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filtros['accion'])) {
            $where[] = 'accion = :a';
            $params['a'] = (string) $filtros['accion'];
        }
        if (!empty($filtros['desde'])) {
            $where[] = 'DATE(creado_en) >= :d';
            $params['d'] = (string) $filtros['desde'];
        }
        if (!empty($filtros['buscar'])) {
            $where[] = '(usuario LIKE :b OR detalle LIKE :b OR accion LIKE :b)';
            $params['b'] = '%' . $filtros['buscar'] . '%';
        }
        return DB::todos(
            'SELECT * FROM auditoria WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . (int) $limite,
            $params
        );
    }
}
