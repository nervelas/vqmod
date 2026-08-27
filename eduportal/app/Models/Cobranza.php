<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Settings;

/**
 * Nucleo de cobranza. Todos los montos se recalculan en el servidor;
 * nunca se confia en los valores enviados por el cliente.
 */
final class Cobranza
{
    public static function saldo(array $cargo): float
    {
        return round(
            (float)$cargo['monto'] - (float)$cargo['descuento'] + (float)$cargo['mora'] - (float)$cargo['pagado'],
            2
        );
    }

    public static function conceptos(?int $cicloId = null, bool $soloActivos = true): array
    {
        $sql = 'SELECT c.*, n.nombre AS nivel FROM conceptos c
                LEFT JOIN niveles n ON n.id = c.nivel_id
                WHERE c.ciclo_id = :c' . ($soloActivos ? ' AND c.activo = 1' : '') . '
                ORDER BY c.tipo, c.nombre';
        return Database::all($sql, ['c' => $cicloId ?: Academico::cicloActivoId()]);
    }

    /** Descuento aplicable a un alumno para un concepto, calculado en servidor. */
    public static function descuentoPara(int $alumnoId, array $concepto, int $cicloId, ?float $becaPct = null): float
    {
        $monto = (float)$concepto['monto'];
        $pct = 0.0;
        if ((int)$concepto['aplica_beca'] === 1) {
            if ($becaPct === null) {
                $becaPct = (float)Database::value(
                    'SELECT beca_pct FROM inscripciones WHERE alumno_id = :a AND ciclo_id = :c',
                    ['a' => $alumnoId, 'c' => $cicloId],
                    0
                );
            }
            $pct += max(0.0, min(100.0, $becaPct));
        }
        if ((int)$concepto['aplica_hermanos'] === 1) {
            $descHermanos = Settings::float('descuento_hermanos', 0);
            if ($descHermanos > 0 && Alumno::hermanosActivos($alumnoId, $cicloId) > 1) {
                $pct += $descHermanos;
            }
        }
        $pct = max(0.0, min(100.0, $pct));
        return round($monto * $pct / 100, 2);
    }

    public static function fechaVencimiento(array $concepto, int $anio, int $mes): string
    {
        $dia = max(1, min(28, (int)$concepto['dia_vencimiento']));
        $mes = max(1, min(12, $mes));
        return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
    }

    /**
     * Genera los cargos de un rango de meses (la colegiatura es mensual).
     * @return array{creados:int, omitidos:int, meses:int}
     */
    public static function generarRango(int $cicloId, int $anio, int $mesDesde, int $mesHasta, ?int $conceptoId = null): array
    {
        $mesDesde = max(1, min(12, $mesDesde));
        $mesHasta = max($mesDesde, min(12, $mesHasta));
        $creados = 0;
        $omitidos = 0;
        for ($m = $mesDesde; $m <= $mesHasta; $m++) {
            $r = self::generarMes($cicloId, $anio, $m, $conceptoId);
            $creados += $r['creados'];
            $omitidos += $r['omitidos'];
        }
        return ['creados' => $creados, 'omitidos' => $omitidos, 'meses' => $mesHasta - $mesDesde + 1];
    }

    /**
     * Genera los cargos de un mes para todos los alumnos activos del ciclo.
     * @return array{creados:int, omitidos:int}
     */
    public static function generarMes(int $cicloId, int $anio, int $mes, ?int $conceptoId = null): array
    {
        $conceptos = self::conceptos($cicloId);
        if ($conceptoId !== null) {
            $conceptos = array_values(array_filter($conceptos, static fn($c) => (int)$c['id'] === $conceptoId));
        } else {
            $conceptos = array_values(array_filter($conceptos, static fn($c) => (int)$c['recurrente'] === 1));
        }
        if ($conceptos === []) {
            return ['creados' => 0, 'omitidos' => 0];
        }
        $inscritos = Database::all(
            'SELECT i.alumno_id, i.beca_pct, s.grado_id, g.nivel_id
             FROM inscripciones i
             JOIN secciones s ON s.id = i.seccion_id
             JOIN grados g ON g.id = s.grado_id
             WHERE i.ciclo_id = :c AND i.estado = \'activo\'',
            ['c' => $cicloId]
        );
        $creados = 0;
        $omitidos = 0;
        Database::begin();
        try {
            foreach ($inscritos as $ins) {
                foreach ($conceptos as $con) {
                    if (!empty($con['nivel_id']) && (int)$con['nivel_id'] !== (int)$ins['nivel_id']) {
                        continue;
                    }
                    $mesCargo = (int)$con['recurrente'] === 1 ? $mes : 0;
                    $existe = Database::value(
                        'SELECT id FROM cargos WHERE alumno_id = :a AND concepto_id = :co AND anio = :y AND mes = :m',
                        ['a' => (int)$ins['alumno_id'], 'co' => (int)$con['id'], 'y' => $anio, 'm' => $mesCargo]
                    );
                    if ($existe) {
                        $omitidos++;
                        continue;
                    }
                    $descuento = self::descuentoPara((int)$ins['alumno_id'], $con, $cicloId, (float)$ins['beca_pct']);
                    $descripcion = $con['nombre'] . ($mesCargo > 0 ? ' - ' . mes_nombre($mesCargo) . ' ' . $anio : ' ' . $anio);
                    Database::run(
                        'INSERT INTO cargos (alumno_id, ciclo_id, concepto_id, descripcion, anio, mes,
                                             monto, descuento, mora, pagado, fecha_vencimiento, estado)
                         VALUES (:a, :c, :co, :d, :y, :m, :mo, :de, 0, 0, :fv, \'pendiente\')',
                        [
                            'a'  => (int)$ins['alumno_id'],
                            'c'  => $cicloId,
                            'co' => (int)$con['id'],
                            'd'  => $descripcion,
                            'y'  => $anio,
                            'm'  => $mesCargo,
                            'mo' => (float)$con['monto'],
                            'de' => $descuento,
                            'fv' => self::fechaVencimiento($con, $anio, $mesCargo > 0 ? $mesCargo : (int)date('n')),
                        ]
                    );
                    $creados++;
                }
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
        Audit::log('cargos.generar', 'cargos', null, "Ciclo {$cicloId} {$mes}/{$anio}: {$creados} creados, {$omitidos} omitidos");
        return ['creados' => $creados, 'omitidos' => $omitidos];
    }

    /** Recalcula la mora de los cargos vencidos. Devuelve cuantos se actualizaron. */
    public static function actualizarMoras(?string $hoy = null): int
    {
        $hoy = $hoy ?: date('Y-m-d');
        $cargos = Database::all(
            'SELECT c.*, co.mora_tipo, co.mora_valor, co.mora_gracia
             FROM cargos c
             LEFT JOIN conceptos co ON co.id = c.concepto_id
             WHERE c.estado IN (\'pendiente\', \'parcial\') AND c.fecha_vencimiento < :h',
            ['h' => $hoy]
        );
        $n = 0;
        foreach ($cargos as $c) {
            $gracia = (int)($c['mora_gracia'] ?? 0);
            $limite = date('Y-m-d', strtotime($c['fecha_vencimiento'] . ' +' . $gracia . ' days'));
            if ($hoy <= $limite) {
                continue;
            }
            $base = (float)$c['monto'] - (float)$c['descuento'] - (float)$c['pagado'];
            if ($base <= 0) {
                continue;
            }
            $tipo = (string)($c['mora_tipo'] ?? 'fijo');
            $valor = (float)($c['mora_valor'] ?? 0);
            if ($valor <= 0) {
                continue;
            }
            $mesesVencido = max(1, (int)floor((strtotime($hoy) - strtotime($limite)) / 2592000) + 1);
            $mora = $tipo === 'porcentaje'
                ? round($base * $valor / 100 * $mesesVencido, 2)
                : round($valor * $mesesVencido, 2);
            if (abs($mora - (float)$c['mora']) < 0.01) {
                continue;
            }
            Database::run('UPDATE cargos SET mora = :m WHERE id = :id', ['m' => $mora, 'id' => (int)$c['id']]);
            $n++;
        }
        return $n;
    }

    public static function cargosDe(int $alumnoId, array $filtros = []): array
    {
        $w = ['c.alumno_id = :a'];
        $p = ['a' => $alumnoId];
        if (!empty($filtros['ciclo_id'])) {
            $w[] = 'c.ciclo_id = :c';
            $p['c'] = (int)$filtros['ciclo_id'];
        }
        if (!empty($filtros['estado'])) {
            if ($filtros['estado'] === 'abierto') {
                $w[] = "c.estado IN ('pendiente','parcial')";
            } else {
                $w[] = 'c.estado = :e';
                $p['e'] = (string)$filtros['estado'];
            }
        } else {
            $w[] = "c.estado <> 'anulado'";
        }
        return Database::all(
            'SELECT c.*, co.nombre AS concepto, co.tipo
             FROM cargos c LEFT JOIN conceptos co ON co.id = c.concepto_id
             WHERE ' . implode(' AND ', $w) . '
             ORDER BY c.fecha_vencimiento, c.id',
            $p
        );
    }

    public static function estadoCuenta(int $alumnoId, ?int $cicloId = null): array
    {
        $cargos = self::cargosDe($alumnoId, ['ciclo_id' => $cicloId ?: Academico::cicloActivoId()]);
        $total = 0.0; $pagado = 0.0; $saldo = 0.0; $vencido = 0.0;
        $hoy = date('Y-m-d');
        foreach ($cargos as $c) {
            $total  += (float)$c['monto'] - (float)$c['descuento'] + (float)$c['mora'];
            $pagado += (float)$c['pagado'];
            $s = self::saldo($c);
            if ($s > 0) {
                $saldo += $s;
                if ($c['fecha_vencimiento'] < $hoy) {
                    $vencido += $s;
                }
            }
        }
        return [
            'cargos'  => $cargos,
            'total'   => round($total, 2),
            'pagado'  => round($pagado, 2),
            'saldo'   => round($saldo, 2),
            'vencido' => round($vencido, 2),
        ];
    }

    public static function cargo(int $id): ?array
    {
        return Database::one(
            'SELECT c.*, co.nombre AS concepto FROM cargos c
             LEFT JOIN conceptos co ON co.id = c.concepto_id WHERE c.id = :id',
            ['id' => $id]
        );
    }

    /**
     * Registra un pago aplicando montos a cargos concretos.
     * @param array<int,float> $aplicaciones cargo_id => monto solicitado
     * @return array{ok:bool, error?:string, pago_id?:int, recibo?:string, total?:float}
     */
    public static function registrarPago(
        int $alumnoId,
        array $aplicaciones,
        string $metodo,
        string $fecha,
        string $referencia = '',
        string $notas = '',
        string $estado = 'aprobado',
        ?string $comprobante = null,
        ?int $usuarioId = null
    ): array {
        $metodos = ['efectivo', 'transferencia', 'tarjeta', 'deposito', 'linea'];
        if (!in_array($metodo, $metodos, true)) {
            return ['ok' => false, 'error' => 'Metodo de pago no valido.'];
        }
        if (!in_array($estado, ['revision', 'aprobado'], true)) {
            return ['ok' => false, 'error' => 'Estado de pago no valido.'];
        }
        $aplicaciones = array_filter($aplicaciones, static fn($m) => (float)$m > 0);
        if ($aplicaciones === []) {
            return ['ok' => false, 'error' => 'Debe indicar al menos un cargo con monto mayor a cero.'];
        }

        Database::begin();
        try {
            $detalle = [];
            $total = 0.0;
            foreach ($aplicaciones as $cargoId => $montoSolicitado) {
                $cargo = self::cargo((int)$cargoId);
                if (!$cargo || (int)$cargo['alumno_id'] !== $alumnoId || $cargo['estado'] === 'anulado') {
                    Database::rollback();
                    return ['ok' => false, 'error' => 'Uno de los cargos seleccionados no corresponde al alumno.'];
                }
                // Recalculo en servidor: nunca mas que el saldo real.
                $saldoReal = self::saldo($cargo);
                if ($saldoReal <= 0) {
                    continue;
                }
                $monto = min(round((float)$montoSolicitado, 2), $saldoReal);
                if ($monto <= 0) {
                    continue;
                }
                $detalle[(int)$cargoId] = $monto;
                $total += $monto;
            }
            if ($detalle === []) {
                Database::rollback();
                return ['ok' => false, 'error' => 'Los cargos seleccionados ya no tienen saldo pendiente.'];
            }
            $total = round($total, 2);
            $recibo = $estado === 'aprobado' ? self::siguienteRecibo() : null;

            $pagoId = Database::insert(
                'INSERT INTO pagos (recibo_no, alumno_id, usuario_id, metodo, monto, referencia, notas, fecha, estado, comprobante)
                 VALUES (:r, :a, :u, :me, :mo, :ref, :no, :f, :es, :co)',
                [
                    'r'   => $recibo,
                    'a'   => $alumnoId,
                    'u'   => $usuarioId ?? Auth::id(),
                    'me'  => $metodo,
                    'mo'  => $total,
                    'ref' => mb_substr($referencia, 0, 90),
                    'no'  => mb_substr($notas, 0, 255),
                    'f'   => $fecha,
                    'es'  => $estado,
                    'co'  => $comprobante,
                ]
            );
            foreach ($detalle as $cargoId => $monto) {
                Database::run(
                    'INSERT INTO pago_detalle (pago_id, cargo_id, monto) VALUES (:p, :c, :m)',
                    ['p' => $pagoId, 'c' => $cargoId, 'm' => $monto]
                );
            }
            if ($estado === 'aprobado') {
                self::aplicarDetalle($pagoId);
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
        Audit::log('pago.registrar', 'pagos', $pagoId, 'Alumno ' . $alumnoId . ' - ' . moneda($total) . ' (' . $estado . ')');
        return ['ok' => true, 'pago_id' => $pagoId, 'recibo' => (string)$recibo, 'total' => $total];
    }

    /**
     * Aplica los montos del detalle a los cargos y actualiza sus estados.
     * Vuelve a limitar cada monto al saldo real del cargo: entre el envio del
     * comprobante y su aprobacion el cargo pudo saldarse por otra via, y un
     * saldo negativo falsearia el estado de cuenta.
     * @return float total realmente aplicado
     */
    public static function aplicarDetalle(int $pagoId): float
    {
        $filas = Database::all('SELECT * FROM pago_detalle WHERE pago_id = :p', ['p' => $pagoId]);
        $aplicado = 0.0;
        foreach ($filas as $d) {
            $cargo = self::cargo((int)$d['cargo_id']);
            if (!$cargo) {
                continue;
            }
            $totalCargo = round((float)$cargo['monto'] - (float)$cargo['descuento'] + (float)$cargo['mora'], 2);
            $disponible = max(0.0, round($totalCargo - (float)$cargo['pagado'], 2));
            $monto = min(round((float)$d['monto'], 2), $disponible);
            if (abs($monto - (float)$d['monto']) > 0.009) {
                Database::run('UPDATE pago_detalle SET monto = :m WHERE id = :id', ['m' => $monto, 'id' => (int)$d['id']]);
            }
            if ($monto <= 0) {
                continue;
            }
            $nuevoPagado = round((float)$cargo['pagado'] + $monto, 2);
            $estado = $nuevoPagado >= $totalCargo - 0.009 ? 'pagado' : ($nuevoPagado > 0 ? 'parcial' : 'pendiente');
            Database::run(
                'UPDATE cargos SET pagado = :p, estado = :e WHERE id = :id',
                ['p' => $nuevoPagado, 'e' => $estado, 'id' => (int)$cargo['id']]
            );
            $aplicado += $monto;
        }
        return round($aplicado, 2);
    }

    /** Revierte la aplicacion de un pago (rechazo o anulacion). */
    public static function revertirDetalle(int $pagoId): void
    {
        $filas = Database::all('SELECT * FROM pago_detalle WHERE pago_id = :p', ['p' => $pagoId]);
        foreach ($filas as $d) {
            $cargo = self::cargo((int)$d['cargo_id']);
            if (!$cargo) {
                continue;
            }
            $nuevoPagado = max(0.0, round((float)$cargo['pagado'] - (float)$d['monto'], 2));
            $totalCargo = round((float)$cargo['monto'] - (float)$cargo['descuento'] + (float)$cargo['mora'], 2);
            $estado = $nuevoPagado >= $totalCargo - 0.009 ? 'pagado' : ($nuevoPagado > 0 ? 'parcial' : 'pendiente');
            Database::run(
                'UPDATE cargos SET pagado = :p, estado = :e WHERE id = :id',
                ['p' => $nuevoPagado, 'e' => $estado, 'id' => (int)$cargo['id']]
            );
        }
    }

    public static function siguienteRecibo(): string
    {
        $prefijo = (string)Settings::get('recibo_prefijo', 'R');
        $driver = \App\Core\Config::get('db.driver', 'mysql');
        if ($driver === 'sqlite') {
            Database::run('UPDATE correlativos SET valor = valor + 1 WHERE tipo = \'recibo\'');
        } else {
            Database::run('UPDATE correlativos SET valor = LAST_INSERT_ID(valor + 1) WHERE tipo = \'recibo\'');
        }
        $valor = (int)Database::value('SELECT valor FROM correlativos WHERE tipo = \'recibo\'', [], 1);
        if ($valor <= 0) {
            $valor = 1;
            Database::run('UPDATE correlativos SET valor = 1 WHERE tipo = \'recibo\'');
        }
        return $prefijo . str_pad((string)$valor, 6, '0', STR_PAD_LEFT);
    }

    public static function pago(int $id): ?array
    {
        return Database::one(
            'SELECT p.*, a.nombres, a.apellidos, a.codigo, u.nombre AS cajero
             FROM pagos p
             JOIN alumnos a ON a.id = p.alumno_id
             LEFT JOIN users u ON u.id = p.usuario_id
             WHERE p.id = :id',
            ['id' => $id]
        );
    }

    public static function detallePago(int $pagoId): array
    {
        return Database::all(
            'SELECT d.*, c.descripcion, c.fecha_vencimiento
             FROM pago_detalle d JOIN cargos c ON c.id = d.cargo_id
             WHERE d.pago_id = :p ORDER BY c.fecha_vencimiento',
            ['p' => $pagoId]
        );
    }

    /** Reporte de morosidad con filtros. */
    public static function morosidad(array $f = []): array
    {
        $w = ["c.estado IN ('pendiente','parcial')", 'c.fecha_vencimiento < :hoy', 'i.estado = \'activo\''];
        $p = ['hoy' => date('Y-m-d'), 'ciclo' => (int)($f['ciclo_id'] ?? Academico::cicloActivoId())];
        $w[] = 'c.ciclo_id = :ciclo';
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
        return Database::all(
            'SELECT a.id, a.codigo, a.nombres, a.apellidos,
                    CONCAT(g.nombre, \' \', s.nombre) AS grupo,
                    COUNT(c.id) AS cargos_vencidos,
                    SUM(c.monto - c.descuento + c.mora - c.pagado) AS saldo,
                    MIN(c.fecha_vencimiento) AS mas_antiguo
             FROM cargos c
             JOIN alumnos a ON a.id = c.alumno_id
             JOIN inscripciones i ON i.alumno_id = a.id AND i.ciclo_id = c.ciclo_id
             JOIN secciones s ON s.id = i.seccion_id
             JOIN grados g ON g.id = s.grado_id
             WHERE ' . implode(' AND ', $w) . '
             GROUP BY a.id, a.codigo, a.nombres, a.apellidos, g.nombre, s.nombre
             HAVING saldo > 0
             ORDER BY saldo DESC',
            $p
        );
    }

    public static function ingresosPorMes(int $anio): array
    {
        $filas = Database::all(
            'SELECT MONTH(fecha) AS mes, SUM(monto) AS total
             FROM pagos WHERE estado = \'aprobado\' AND YEAR(fecha) = :y
             GROUP BY MONTH(fecha) ORDER BY mes',
            ['y' => $anio]
        );
        $out = array_fill(1, 12, 0.0);
        foreach ($filas as $f) {
            $out[(int)$f['mes']] = round((float)$f['total'], 2);
        }
        return $out;
    }

    public static function cierreCaja(string $fecha, ?int $usuarioId = null): array
    {
        $w = ['p.fecha = :f', "p.estado = 'aprobado'"];
        $p = ['f' => $fecha];
        if ($usuarioId) {
            $w[] = 'p.usuario_id = :u';
            $p['u'] = $usuarioId;
        }
        $pagos = Database::all(
            'SELECT p.*, a.codigo, a.nombres, a.apellidos, u.nombre AS cajero
             FROM pagos p JOIN alumnos a ON a.id = p.alumno_id
             LEFT JOIN users u ON u.id = p.usuario_id
             WHERE ' . implode(' AND ', $w) . '
             ORDER BY p.id',
            $p
        );
        $porMetodo = [];
        $total = 0.0;
        foreach ($pagos as $pg) {
            $porMetodo[$pg['metodo']] = round(($porMetodo[$pg['metodo']] ?? 0) + (float)$pg['monto'], 2);
            $total += (float)$pg['monto'];
        }
        return ['pagos' => $pagos, 'por_metodo' => $porMetodo, 'total' => round($total, 2)];
    }

    /** Proximos vencimientos dentro de N dias. */
    public static function proximosVencimientos(int $dias = 7): array
    {
        return Database::all(
            'SELECT c.*, a.nombres, a.apellidos, a.codigo
             FROM cargos c JOIN alumnos a ON a.id = c.alumno_id
             WHERE c.estado IN (\'pendiente\',\'parcial\')
               AND c.fecha_vencimiento BETWEEN :h AND :f
             ORDER BY c.fecha_vencimiento
             LIMIT 100',
            ['h' => date('Y-m-d'), 'f' => date('Y-m-d', strtotime("+{$dias} days"))]
        );
    }
}
