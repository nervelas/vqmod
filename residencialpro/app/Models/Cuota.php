<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\DB;

/**
 * Conceptos de cobro, generación de cargos, mora y estado de cuenta.
 * Todos los montos se recalculan siempre en el servidor.
 */
final class Cuota
{
    public static function conceptos(bool $soloActivos = true): array
    {
        return DB::todos(
            'SELECT * FROM conceptos ' . ($soloActivos ? 'WHERE activo = 1 ' : '') . 'ORDER BY orden, nombre'
        );
    }

    public static function concepto(int $id): ?array
    {
        return DB::uno('SELECT * FROM conceptos WHERE id = :id', ['id' => $id]);
    }

    /** Monto que corresponde a una casa según el tipo de cálculo del concepto. */
    public static function montoPara(array $concepto, array $casa): float
    {
        $base = (float) $concepto['monto'];
        return match ($concepto['calculo']) {
            'coeficiente' => round($base * ((float) $casa['coeficiente']) / 100, 2),
            'metros'      => round($base * (float) $casa['metros'], 2),
            default       => round($base, 2),
        };
    }

    /** ¿El concepto corresponde al período indicado (aaaa-mm)? */
    public static function correspondePeriodo(array $concepto, string $periodo): bool
    {
        $mes = (int) substr($periodo, 5, 2);
        return match ($concepto['periodicidad']) {
            'mensual'    => true,
            'bimestral'  => $mes % 2 === 1,
            'trimestral' => in_array($mes, [1, 4, 7, 10], true),
            'anual'      => $mes === 1,
            default      => false,
        };
    }

    /**
     * Genera los cargos automáticos del período para todas las casas.
     * Devuelve ['creados' => n, 'omitidos' => n, 'monto' => total]
     */
    public static function generarPeriodo(string $periodo, ?int $conceptoId = null): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            throw new \InvalidArgumentException('Período no válido.');
        }
        $conceptos = self::conceptos();
        $manual    = ($conceptoId !== null && $conceptoId > 0);
        if ($manual) {
            $conceptos = array_values(array_filter($conceptos, static fn($c) => (int) $c['id'] === $conceptoId));
        } else {
            $conceptos = array_values(array_filter($conceptos, static fn($c) => (int) $c['automatico'] === 1));
        }
        $casas = DB::todos('SELECT * FROM casas ORDER BY id');
        $creados = 0;
        $omitidos = 0;
        $total = 0.0;

        DB::transaccion(static function () use ($conceptos, $casas, $periodo, $manual, &$creados, &$omitidos, &$total): void {
            foreach ($conceptos as $c) {
                if (!$manual && !self::correspondePeriodo($c, $periodo)) {
                    continue;
                }
                $dia = max(1, min(28, (int) $c['dia_vence']));
                $emision = $periodo . '-01';
                $vence   = $periodo . '-' . str_pad((string) $dia, 2, '0', STR_PAD_LEFT);
                foreach ($casas as $casa) {
                    $existe = DB::valor(
                        'SELECT id FROM cargos WHERE casa_id = :c AND concepto_id = :k AND periodo = :p',
                        ['c' => (int) $casa['id'], 'k' => (int) $c['id'], 'p' => $periodo]
                    );
                    if ($existe) {
                        $omitidos++;
                        continue;
                    }
                    $monto = self::montoPara($c, $casa);
                    if ($monto <= 0) {
                        $omitidos++;
                        continue;
                    }
                    DB::insertar('cargos', [
                        'casa_id'       => (int) $casa['id'],
                        'concepto_id'   => (int) $c['id'],
                        'periodo'       => $periodo,
                        'descripcion'   => $c['nombre'] . ' — ' . $periodo,
                        'monto'         => $monto,
                        'fecha_emision' => $emision,
                        'fecha_vence'   => $vence,
                        'estado'        => 'pendiente',
                        'origen'        => 'automatico',
                    ]);
                    $creados++;
                    $total += $monto;
                }
            }
        });
        Auditoria::registrar('generar_cargos', 'cargos', null, "Período {$periodo}: {$creados} cargos por " . number_format($total, 2));
        return ['creados' => $creados, 'omitidos' => $omitidos, 'monto' => $total];
    }

    /**
     * Recalcula la mora de los cargos vencidos. Siempre en el servidor.
     * Devuelve la cantidad de cargos actualizados.
     */
    public static function recalcularMora(?int $casaId = null): int
    {
        $where  = 'g.estado IN ("pendiente","parcial") AND g.fecha_vence < CURDATE()';
        $params = [];
        if ($casaId !== null && $casaId > 0) {
            $where .= ' AND g.casa_id = :c';
            $params['c'] = $casaId;
        }
        $cargos = DB::todos(
            'SELECT g.*, k.mora_tipo, k.mora_valor FROM cargos g
             LEFT JOIN conceptos k ON k.id = g.concepto_id
             WHERE ' . $where,
            $params
        );
        $moraGlobalTipo  = Ajustes::get('mora_tipo', 'porcentaje');
        $moraGlobalValor = Ajustes::num('mora_valor', 2);
        $tope            = Ajustes::num('mora_tope_porcentaje', 100);
        $actualizados    = 0;

        foreach ($cargos as $g) {
            $tipo  = $g['mora_tipo']  ?? $moraGlobalTipo;
            $valor = $g['mora_valor'] !== null ? (float) $g['mora_valor'] : $moraGlobalValor;
            if ($tipo === 'ninguna' || $valor <= 0) {
                continue;
            }
            $dias  = (int) floor((time() - strtotime((string) $g['fecha_vence'])) / 86400);
            if ($dias <= (int) Ajustes::num('mora_dias_gracia', 0)) {
                continue;
            }
            $meses = max(1, (int) ceil($dias / 30));
            $saldoBase = (float) $g['monto'] - (float) $g['descuento'] - (float) $g['pagado'];
            if ($saldoBase <= 0) {
                continue;
            }
            $mora = $tipo === 'fijo'
                ? round($valor * $meses, 2)
                : round($saldoBase * ($valor / 100) * $meses, 2);
            $maximo = round($saldoBase * $tope / 100, 2);
            if ($maximo > 0 && $mora > $maximo) {
                $mora = $maximo;
            }
            if (abs($mora - (float) $g['mora']) > 0.004) {
                DB::actualizar('cargos', ['mora' => $mora], 'id = :id', ['id' => (int) $g['id']]);
                $actualizados++;
            }
        }
        return $actualizados;
    }

    /** Cargos de una casa. */
    public static function cargos(int $casaId, string $filtro = 'pendientes', int $limite = 300): array
    {
        $where = 'casa_id = :c';
        if ($filtro === 'pendientes') {
            $where .= ' AND estado IN ("pendiente","parcial")';
        } elseif ($filtro === 'pagados') {
            $where .= ' AND estado = "pagado"';
        } elseif ($filtro === 'vigentes') {
            $where .= ' AND estado <> "anulado"';
        }
        return DB::todos(
            'SELECT * FROM cargos WHERE ' . $where . ' ORDER BY fecha_vence ASC, id ASC LIMIT ' . (int) $limite,
            ['c' => $casaId]
        );
    }

    public static function cargo(int $id): ?array
    {
        return DB::uno('SELECT * FROM cargos WHERE id = :id', ['id' => $id]);
    }

    public static function saldoCargo(array $cargo): float
    {
        return round((float) $cargo['monto'] + (float) $cargo['mora'] - (float) $cargo['descuento'] - (float) $cargo['pagado'], 2);
    }

    /** Crea un cargo manual (multa, reserva, cuota extraordinaria...). */
    public static function crearCargo(int $casaId, string $descripcion, float $monto, string $vence, ?int $conceptoId = null, string $origen = 'manual', ?int $referenciaId = null): int
    {
        $id = DB::insertar('cargos', [
            'casa_id'       => $casaId,
            'concepto_id'   => $conceptoId,
            'periodo'       => null,
            'descripcion'   => mb_substr($descripcion, 0, 190),
            'monto'         => round($monto, 2),
            'fecha_emision' => date('Y-m-d'),
            'fecha_vence'   => $vence,
            'estado'        => 'pendiente',
            'origen'        => $origen,
            'referencia_id' => $referenciaId,
        ]);
        Auditoria::registrar('crear_cargo', 'cargos', $id, $descripcion . ' — ' . number_format($monto, 2));
        return $id;
    }

    public static function anularCargo(int $cargoId, string $motivo): bool
    {
        $c = self::cargo($cargoId);
        if ($c === null || (float) $c['pagado'] > 0) {
            return false;
        }
        DB::actualizar('cargos', ['estado' => 'anulado'], 'id = :id', ['id' => $cargoId]);
        Auditoria::registrar('anular_cargo', 'cargos', $cargoId, $motivo);
        return true;
    }

    /** Antigüedad de saldos por tramos para una casa. */
    public static function antiguedad(int $casaId): array
    {
        $filas = DB::todos(
            'SELECT fecha_vence, (monto + mora - descuento - pagado) AS saldo FROM cargos
             WHERE casa_id = :c AND estado IN ("pendiente","parcial")',
            ['c' => $casaId]
        );
        $t = ['corriente' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'd120' => 0.0, 'total' => 0.0];
        $hoy = time();
        foreach ($filas as $f) {
            $s = (float) $f['saldo'];
            if ($s <= 0) {
                continue;
            }
            $dias = (int) floor(($hoy - strtotime((string) $f['fecha_vence'])) / 86400);
            if ($dias <= 0)        { $t['corriente'] += $s; }
            elseif ($dias <= 30)   { $t['d30']  += $s; }
            elseif ($dias <= 60)   { $t['d60']  += $s; }
            elseif ($dias <= 90)   { $t['d90']  += $s; }
            else                   { $t['d120'] += $s; }
            $t['total'] += $s;
        }
        return array_map(static fn($v) => round($v, 2), $t);
    }

    /** Reporte de morosidad de todo el residencial. */
    public static function morosidad(array $filtros = []): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filtros['fase'])) {
            $where[] = 'c.fase_id = :f';
            $params['f'] = (int) $filtros['fase'];
        }
        if (!empty($filtros['calle'])) {
            $where[] = 'c.calle_id = :ca';
            $params['ca'] = (int) $filtros['calle'];
        }
        $sql = 'SELECT c.id, c.codigo, c.estado AS estado_casa, c.restringida,
                       f.nombre AS fase, ca.nombre AS calle,
                       (SELECT r.nombre FROM residentes r WHERE r.casa_id = c.id AND r.activo = 1
                        ORDER BY (r.tipo="propietario") DESC, r.id LIMIT 1) AS residente,
                       (SELECT r.telefono FROM residentes r WHERE r.casa_id = c.id AND r.activo = 1
                        ORDER BY (r.tipo="propietario") DESC, r.id LIMIT 1) AS telefono,
                       (SELECT r.correo FROM residentes r WHERE r.casa_id = c.id AND r.activo = 1
                        ORDER BY (r.tipo="propietario") DESC, r.id LIMIT 1) AS correo,
                       COALESCE(SUM(CASE WHEN g.estado IN ("pendiente","parcial")
                            THEN g.monto + g.mora - g.descuento - g.pagado ELSE 0 END),0) AS saldo,
                       MIN(CASE WHEN g.estado IN ("pendiente","parcial") THEN g.fecha_vence END) AS vence,
                       COALESCE(SUM(CASE WHEN g.estado IN ("pendiente","parcial") THEN g.mora ELSE 0 END),0) AS mora
                FROM casas c
                LEFT JOIN fases f   ON f.id = c.fase_id
                LEFT JOIN calles ca ON ca.id = c.calle_id
                LEFT JOIN cargos g  ON g.casa_id = c.id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY c.id, c.codigo, c.estado, c.restringida, f.nombre, ca.nombre, f.orden, ca.orden
                HAVING saldo > 0.009
                ORDER BY saldo DESC';
        $filas = DB::todos($sql, $params);
        $hoy = time();
        foreach ($filas as &$f) {
            $f['dias'] = $f['vence'] ? max(0, (int) floor(($hoy - strtotime((string) $f['vence'])) / 86400)) : 0;
            $f['antiguedad'] = self::antiguedad((int) $f['id']);
        }
        return $filas;
    }

    /** Totales globales de morosidad por tramo. */
    public static function resumenMorosidad(array $filas): array
    {
        $t = ['corriente' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'd120' => 0.0, 'total' => 0.0, 'casas' => 0];
        foreach ($filas as $f) {
            foreach (['corriente', 'd30', 'd60', 'd90', 'd120', 'total'] as $k) {
                $t[$k] += (float) $f['antiguedad'][$k];
            }
            $t['casas']++;
        }
        foreach (['corriente', 'd30', 'd60', 'd90', 'd120', 'total'] as $k) {
            $t[$k] = round($t[$k], 2);
        }
        return $t;
    }

    /** Emisión esperada del mes (suma de cargos emitidos en el período). */
    public static function esperadoPeriodo(string $periodo): float
    {
        return (float) DB::valor(
            'SELECT COALESCE(SUM(monto + mora - descuento),0) FROM cargos
             WHERE periodo = :p AND estado <> "anulado"',
            ['p' => $periodo],
            0
        );
    }
}
