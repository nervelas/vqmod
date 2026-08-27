<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Log;
use App\Core\Notificar;

/**
 * Registro, aprobación y aplicación de pagos.
 * La distribución del dinero sobre los cargos ocurre siempre en el servidor.
 */
final class Pago
{
    public static function porId(int $id): ?array
    {
        return DB::uno(
            'SELECT p.*, c.codigo AS casa, f.nombre AS fase
             FROM pagos p
             LEFT JOIN casas c ON c.id = p.casa_id
             LEFT JOIN fases f ON f.id = c.fase_id
             WHERE p.id = :id',
            ['id' => $id]
        );
    }

    public static function porVerificacion(string $hash): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $hash)) {
            return null;
        }
        return DB::uno(
            'SELECT p.*, c.codigo AS casa FROM pagos p
             LEFT JOIN casas c ON c.id = p.casa_id
             WHERE p.verificacion = :v AND p.estado = "aprobado"',
            ['v' => $hash]
        );
    }

    public static function detalle(int $pagoId): array
    {
        return DB::todos('SELECT * FROM pagos_detalle WHERE pago_id = :p ORDER BY id', ['p' => $pagoId]);
    }

    public static function listar(array $filtros = [], int $limite = 100, int $desplazamiento = 0): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filtros['estado'])) {
            $where[] = 'p.estado = :e';
            $params['e'] = (string) $filtros['estado'];
        }
        if (!empty($filtros['casa'])) {
            $where[] = 'p.casa_id = :c';
            $params['c'] = (int) $filtros['casa'];
        }
        if (!empty($filtros['desde'])) {
            $where[] = 'p.fecha >= :d';
            $params['d'] = (string) $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[] = 'p.fecha <= :h';
            $params['h'] = (string) $filtros['hasta'];
        }
        if (!empty($filtros['metodo'])) {
            $where[] = 'p.metodo = :m';
            $params['m'] = (string) $filtros['metodo'];
        }
        if (!empty($filtros['buscar'])) {
            $where[] = '(p.recibo LIKE :b OR p.referencia LIKE :b OR c.codigo LIKE :b)';
            $params['b'] = '%' . $filtros['buscar'] . '%';
        }
        return DB::todos(
            'SELECT p.*, c.codigo AS casa,
                    (SELECT r.nombre FROM residentes r WHERE r.casa_id = p.casa_id AND r.activo = 1
                     ORDER BY (r.tipo="propietario") DESC, r.id LIMIT 1) AS residente
             FROM pagos p
             LEFT JOIN casas c ON c.id = p.casa_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.fecha DESC, p.id DESC
             LIMIT ' . max(1, $limite) . ' OFFSET ' . max(0, $desplazamiento),
            $params
        );
    }

    public static function contar(array $filtros = []): int
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filtros['estado'])) {
            $where[] = 'estado = :e';
            $params['e'] = (string) $filtros['estado'];
        }
        if (!empty($filtros['casa'])) {
            $where[] = 'casa_id = :c';
            $params['c'] = (int) $filtros['casa'];
        }
        return (int) DB::valor('SELECT COUNT(*) FROM pagos WHERE ' . implode(' AND ', $where), $params, 0);
    }

    public static function pendientesRevision(): int
    {
        return (int) DB::valor('SELECT COUNT(*) FROM pagos WHERE estado = "revision"', [], 0);
    }

    /**
     * Registra un pago.
     * $asignaciones: [cargo_id => monto]. Si viene vacío, se aplica a los cargos
     * más antiguos automáticamente.
     */
    public static function registrar(array $datos, array $asignaciones = [], bool $aprobar = true): int
    {
        $casaId = (int) $datos['casa_id'];
        $monto  = round((float) $datos['monto'], 2);
        if ($monto <= 0) {
            throw new \InvalidArgumentException('El monto del pago debe ser mayor que cero.');
        }
        return DB::transaccion(static function () use ($datos, $asignaciones, $aprobar, $casaId, $monto): int {
            $pagoId = DB::insertar('pagos', [
                'casa_id'        => $casaId,
                'recibo'         => null,
                'fecha'          => (string) ($datos['fecha'] ?? date('Y-m-d')),
                'monto'          => $monto,
                'metodo'         => (string) ($datos['metodo'] ?? 'transferencia'),
                'referencia'     => $datos['referencia'] ?? null,
                'banco'          => $datos['banco'] ?? null,
                'cuenta_id'      => !empty($datos['cuenta_id']) ? (int) $datos['cuenta_id'] : null,
                'comprobante'    => $datos['comprobante'] ?? null,
                'estado'         => $aprobar ? 'aprobado' : 'revision',
                'notas'          => $datos['notas'] ?? null,
                'registrado_por' => Auth::id() ?: null,
            ]);
            if ($aprobar) {
                self::aplicar($pagoId, $asignaciones);
            }
            Auditoria::registrar($aprobar ? 'registrar_pago' : 'pago_en_revision', 'pagos', $pagoId,
                'Casa ' . $casaId . ' — Q' . number_format($monto, 2));
            return $pagoId;
        });
    }

    /** Aprueba un pago en revisión. */
    public static function aprobar(int $pagoId, array $asignaciones = []): bool
    {
        $p = self::porId($pagoId);
        if ($p === null || $p['estado'] !== 'revision') {
            return false;
        }
        DB::transaccion(static function () use ($pagoId, $asignaciones): void {
            DB::actualizar('pagos', [
                'estado'       => 'aprobado',
                'aprobado_por' => Auth::id() ?: null,
                'aprobado_en'  => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $pagoId]);
            self::aplicar($pagoId, $asignaciones);
        });
        Auditoria::registrar('aprobar_pago', 'pagos', $pagoId, 'Comprobante aprobado');
        $p = self::porId($pagoId);
        if ($p !== null) {
            Notificar::casa(
                (int) $p['casa_id'],
                'Su pago fue aprobado',
                'Recibo ' . ($p['recibo'] ?? '') . ' por Q' . number_format((float) $p['monto'], 2),
                '/portal/estado-cuenta'
            );
        }
        return true;
    }

    public static function rechazar(int $pagoId, string $motivo): bool
    {
        $p = self::porId($pagoId);
        if ($p === null || $p['estado'] !== 'revision') {
            return false;
        }
        DB::actualizar('pagos', [
            'estado'         => 'rechazado',
            'motivo_rechazo' => mb_substr($motivo, 0, 255),
            'aprobado_por'   => Auth::id() ?: null,
            'aprobado_en'    => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $pagoId]);
        Auditoria::registrar('rechazar_pago', 'pagos', $pagoId, $motivo);
        Notificar::casa(
            (int) $p['casa_id'],
            'Su comprobante fue rechazado',
            $motivo,
            '/portal/estado-cuenta'
        );
        return true;
    }

    /** Anula un pago aprobado y revierte la aplicación sobre los cargos. */
    public static function anular(int $pagoId, string $motivo): bool
    {
        $p = self::porId($pagoId);
        if ($p === null || $p['estado'] !== 'aprobado') {
            return false;
        }
        DB::transaccion(static function () use ($pagoId, $motivo): void {
            foreach (self::detalle($pagoId) as $d) {
                if (empty($d['cargo_id'])) {
                    continue;
                }
                $cargo = Cuota::cargo((int) $d['cargo_id']);
                if ($cargo === null) {
                    continue;
                }
                $pagado = max(0, round((float) $cargo['pagado'] - (float) $d['monto'], 2));
                DB::actualizar('cargos', [
                    'pagado' => $pagado,
                    'estado' => $pagado <= 0.009 ? 'pendiente' : 'parcial',
                ], 'id = :id', ['id' => (int) $cargo['id']]);
            }
            DB::eliminar('pagos_detalle', 'pago_id = :p', ['p' => $pagoId]);
            DB::actualizar('pagos', [
                'estado' => 'anulado',
                'notas'  => 'ANULADO: ' . mb_substr($motivo, 0, 200),
            ], 'id = :id', ['id' => $pagoId]);
        });
        Auditoria::registrar('anular_pago', 'pagos', $pagoId, $motivo);
        return true;
    }

    /**
     * Aplica el monto del pago sobre los cargos y numera el recibo.
     * Si no se indican asignaciones, se cubren los cargos más antiguos.
     */
    private static function aplicar(int $pagoId, array $asignaciones = []): void
    {
        $pago = DB::uno('SELECT * FROM pagos WHERE id = :id', ['id' => $pagoId]);
        if ($pago === null) {
            return;
        }
        $casaId    = (int) $pago['casa_id'];
        $restante  = round((float) $pago['monto'], 2);
        DB::eliminar('pagos_detalle', 'pago_id = :p', ['p' => $pagoId]);

        Cuota::recalcularMora($casaId);

        $cargos = [];
        if ($asignaciones !== []) {
            foreach ($asignaciones as $cargoId => $m) {
                $c = Cuota::cargo((int) $cargoId);
                if ($c !== null && (int) $c['casa_id'] === $casaId && $c['estado'] !== 'anulado') {
                    $cargos[] = ['cargo' => $c, 'solicitado' => round((float) $m, 2)];
                }
            }
        } else {
            foreach (Cuota::cargos($casaId, 'pendientes') as $c) {
                $cargos[] = ['cargo' => $c, 'solicitado' => null];
            }
        }

        foreach ($cargos as $item) {
            if ($restante <= 0.009) {
                break;
            }
            $c     = $item['cargo'];
            $saldo = Cuota::saldoCargo($c);
            if ($saldo <= 0.009) {
                continue;
            }
            $aplicar = $item['solicitado'] !== null ? min($item['solicitado'], $saldo) : $saldo;
            $aplicar = round(min($aplicar, $restante), 2);
            if ($aplicar <= 0.009) {
                continue;
            }
            $pagado = round((float) $c['pagado'] + $aplicar, 2);
            $nuevoSaldo = round((float) $c['monto'] + (float) $c['mora'] - (float) $c['descuento'] - $pagado, 2);
            DB::actualizar('cargos', [
                'pagado' => $pagado,
                'estado' => $nuevoSaldo <= 0.009 ? 'pagado' : 'parcial',
            ], 'id = :id', ['id' => (int) $c['id']]);
            DB::insertar('pagos_detalle', [
                'pago_id'  => $pagoId,
                'cargo_id' => (int) $c['id'],
                'concepto' => (string) $c['descripcion'],
                'monto'    => $aplicar,
            ]);
            $restante = round($restante - $aplicar, 2);
        }

        if ($restante > 0.009) {
            DB::insertar('pagos_detalle', [
                'pago_id'  => $pagoId,
                'cargo_id' => null,
                'concepto' => 'Abono a cuenta (saldo a favor)',
                'monto'    => $restante,
            ]);
        }

        DB::actualizar('pagos', [
            'recibo'       => $pago['recibo'] ?: self::siguienteRecibo(),
            'verificacion' => $pago['verificacion'] ?: bin2hex(random_bytes(16)),
        ], 'id = :id', ['id' => $pagoId]);

        Casa::actualizarRestriccion($casaId, (int) Ajustes::num('corte_dias', 90));
    }

    /** Saldo a favor acumulado de una casa. */
    public static function saldoAFavor(int $casaId): float
    {
        return (float) DB::valor(
            'SELECT COALESCE(SUM(d.monto),0) FROM pagos_detalle d
             INNER JOIN pagos p ON p.id = d.pago_id
             WHERE p.casa_id = :c AND p.estado = "aprobado" AND d.cargo_id IS NULL',
            ['c' => $casaId],
            0
        );
    }

    public static function siguienteRecibo(): string
    {
        $prefijo = Ajustes::get('recibo_prefijo', '');
        $ultimo  = (string) DB::valor(
            'SELECT recibo FROM pagos WHERE recibo IS NOT NULL ORDER BY id DESC LIMIT 1',
            [],
            ''
        );
        $n = 0;
        if ($ultimo !== '' && preg_match('/(\d+)$/', $ultimo, $m)) {
            $n = (int) $m[1];
        }
        $n = max($n, (int) Ajustes::num('recibo_inicial', 0)) + 1;
        return $prefijo . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }

    /** Recaudación aprobada en un rango. */
    public static function recaudado(string $desde, string $hasta): float
    {
        return (float) DB::valor(
            'SELECT COALESCE(SUM(monto),0) FROM pagos WHERE estado = "aprobado" AND fecha BETWEEN :d AND :h',
            ['d' => $desde, 'h' => $hasta],
            0
        );
    }

    public static function porMetodo(string $desde, string $hasta): array
    {
        return DB::todos(
            'SELECT metodo, COUNT(*) AS n, COALESCE(SUM(monto),0) AS total FROM pagos
             WHERE estado = "aprobado" AND fecha BETWEEN :d AND :h GROUP BY metodo ORDER BY total DESC',
            ['d' => $desde, 'h' => $hasta]
        );
    }
}
