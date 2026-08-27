<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\DB;

final class Egreso
{
    public static function categorias(bool $soloActivas = true): array
    {
        return DB::todos('SELECT * FROM categorias_egreso ' . ($soloActivas ? 'WHERE activo = 1 ' : '') . 'ORDER BY nombre');
    }

    public static function proveedores(bool $soloActivos = true): array
    {
        return DB::todos('SELECT * FROM proveedores ' . ($soloActivos ? 'WHERE activo = 1 ' : '') . 'ORDER BY nombre');
    }

    public static function cuentas(bool $soloActivas = true): array
    {
        return DB::todos('SELECT * FROM cuentas ' . ($soloActivas ? 'WHERE activo = 1 ' : '') . 'ORDER BY tipo, nombre');
    }

    public static function porId(int $id): ?array
    {
        return DB::uno(
            'SELECT e.*, c.nombre AS categoria, p.nombre AS proveedor, cu.nombre AS cuenta
             FROM egresos e
             LEFT JOIN categorias_egreso c ON c.id = e.categoria_id
             LEFT JOIN proveedores p ON p.id = e.proveedor_id
             LEFT JOIN cuentas cu ON cu.id = e.cuenta_id
             WHERE e.id = :id',
            ['id' => $id]
        );
    }

    public static function listar(array $filtros = [], int $limite = 200): array
    {
        $where  = ['e.estado = "registrado"'];
        $params = [];
        if (!empty($filtros['desde'])) {
            $where[] = 'e.fecha >= :d';
            $params['d'] = (string) $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[] = 'e.fecha <= :h';
            $params['h'] = (string) $filtros['hasta'];
        }
        if (!empty($filtros['categoria'])) {
            $where[] = 'e.categoria_id = :c';
            $params['c'] = (int) $filtros['categoria'];
        }
        if (!empty($filtros['proveedor'])) {
            $where[] = 'e.proveedor_id = :p';
            $params['p'] = (int) $filtros['proveedor'];
        }
        if (!empty($filtros['buscar'])) {
            $where[] = '(e.descripcion LIKE :b OR e.documento LIKE :b)';
            $params['b'] = '%' . $filtros['buscar'] . '%';
        }
        return DB::todos(
            'SELECT e.*, c.nombre AS categoria, c.color, p.nombre AS proveedor, cu.nombre AS cuenta
             FROM egresos e
             LEFT JOIN categorias_egreso c ON c.id = e.categoria_id
             LEFT JOIN proveedores p ON p.id = e.proveedor_id
             LEFT JOIN cuentas cu ON cu.id = e.cuenta_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY e.fecha DESC, e.id DESC LIMIT ' . (int) $limite,
            $params
        );
    }

    public static function guardar(array $d, int $id = 0): int
    {
        $datos = [
            'categoria_id' => !empty($d['categoria_id']) ? (int) $d['categoria_id'] : null,
            'proveedor_id' => !empty($d['proveedor_id']) ? (int) $d['proveedor_id'] : null,
            'cuenta_id'    => !empty($d['cuenta_id']) ? (int) $d['cuenta_id'] : null,
            'fecha'        => (string) $d['fecha'],
            'monto'        => round((float) $d['monto'], 2),
            'descripcion'  => mb_substr((string) $d['descripcion'], 0, 190),
            'documento'    => $d['documento'] ?? null,
            'metodo'       => (string) ($d['metodo'] ?? 'transferencia'),
        ];
        if (!empty($d['archivo'])) {
            $datos['archivo'] = $d['archivo'];
        }
        if ($id > 0) {
            DB::actualizar('egresos', $datos, 'id = :id', ['id' => $id]);
            Auditoria::registrar('editar_egreso', 'egresos', $id, $datos['descripcion']);
            return $id;
        }
        $datos['usuario_id'] = Auth::id() ?: null;
        $nuevo = DB::insertar('egresos', $datos);
        Auditoria::registrar('crear_egreso', 'egresos', $nuevo, $datos['descripcion'] . ' Q' . number_format($datos['monto'], 2));
        return $nuevo;
    }

    public static function anular(int $id, string $motivo): bool
    {
        DB::actualizar('egresos', ['estado' => 'anulado'], 'id = :id', ['id' => $id]);
        Auditoria::registrar('anular_egreso', 'egresos', $id, $motivo);
        return true;
    }

    public static function total(string $desde, string $hasta): float
    {
        return (float) DB::valor(
            'SELECT COALESCE(SUM(monto),0) FROM egresos WHERE estado = "registrado" AND fecha BETWEEN :d AND :h',
            ['d' => $desde, 'h' => $hasta],
            0
        );
    }

    public static function porCategoria(string $desde, string $hasta): array
    {
        return DB::todos(
            'SELECT COALESCE(c.nombre,"Sin categoría") AS categoria, COALESCE(c.color,"#8A8F8B") AS color,
                    COALESCE(SUM(e.monto),0) AS total
             FROM egresos e
             LEFT JOIN categorias_egreso c ON c.id = e.categoria_id
             WHERE e.estado = "registrado" AND e.fecha BETWEEN :d AND :h
             GROUP BY c.id, c.nombre, c.color ORDER BY total DESC',
            ['d' => $desde, 'h' => $hasta]
        );
    }

    /** Presupuesto anual contra ejecución real. */
    public static function presupuestoVsReal(int $anio): array
    {
        return DB::todos(
            'SELECT c.id, c.nombre AS categoria, c.color,
                    COALESCE(p.monto,0) AS presupuesto,
                    COALESCE((SELECT SUM(e.monto) FROM egresos e
                              WHERE e.categoria_id = c.id AND e.estado = "registrado"
                                AND YEAR(e.fecha) = :a),0) AS ejecutado
             FROM categorias_egreso c
             LEFT JOIN presupuestos p ON p.categoria_id = c.id AND p.anio = :a
             WHERE c.activo = 1
             ORDER BY c.nombre',
            ['a' => $anio]
        );
    }

    /** Saldo actual de cada cuenta: saldo inicial + ingresos - egresos. */
    public static function saldosCuentas(): array
    {
        $cuentas = self::cuentas();
        foreach ($cuentas as &$c) {
            $ing = (float) DB::valor(
                'SELECT COALESCE(SUM(monto),0) FROM pagos WHERE estado = "aprobado" AND cuenta_id = :c',
                ['c' => (int) $c['id']],
                0
            );
            $egr = (float) DB::valor(
                'SELECT COALESCE(SUM(monto),0) FROM egresos WHERE estado = "registrado" AND cuenta_id = :c',
                ['c' => (int) $c['id']],
                0
            );
            $c['ingresos'] = round($ing, 2);
            $c['egresos']  = round($egr, 2);
            $c['saldo']    = round((float) $c['saldo_inicial'] + $ing - $egr, 2);
        }
        return $cuentas;
    }

    public static function saldoTotal(): float
    {
        $t = 0.0;
        foreach (self::saldosCuentas() as $c) {
            $t += (float) $c['saldo'];
        }
        return round($t, 2);
    }

    /** Flujo mensual de los últimos N meses. */
    public static function flujo(int $meses = 12): array
    {
        $salida = [];
        for ($i = $meses - 1; $i >= 0; $i--) {
            $mes   = date('Y-m', strtotime("-{$i} months"));
            $desde = $mes . '-01';
            $hasta = date('Y-m-t', (int) strtotime($desde));
            $salida[] = [
                'periodo'  => $mes,
                'etiqueta' => ucfirst(mb_substr(mesNombre((int) substr($mes, 5, 2)), 0, 3)) . ' ' . substr($mes, 2, 2),
                'ingresos' => Pago::recaudado($desde, $hasta),
                'egresos'  => self::total($desde, $hasta),
            ];
        }
        return $salida;
    }
}
