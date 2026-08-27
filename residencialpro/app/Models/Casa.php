<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Casa
{
    public static function porId(int $id): ?array
    {
        return DB::uno(
            'SELECT c.*, f.nombre AS fase, ca.nombre AS calle
             FROM casas c
             LEFT JOIN fases f  ON f.id = c.fase_id
             LEFT JOIN calles ca ON ca.id = c.calle_id
             WHERE c.id = :id',
            ['id' => $id]
        );
    }

    public static function porCodigo(string $codigo): ?array
    {
        return DB::uno('SELECT * FROM casas WHERE codigo = :c', ['c' => $codigo]);
    }

    /** Listado con filtros y saldo calculado. */
    public static function listar(array $filtros = [], int $limite = 500, int $desplazamiento = 0): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filtros['fase'])) {
            $where[] = 'c.fase_id = :fase';
            $params['fase'] = (int) $filtros['fase'];
        }
        if (!empty($filtros['calle'])) {
            $where[] = 'c.calle_id = :calle';
            $params['calle'] = (int) $filtros['calle'];
        }
        if (!empty($filtros['estado'])) {
            $where[] = 'c.estado = :estado';
            $params['estado'] = (string) $filtros['estado'];
        }
        if (!empty($filtros['buscar'])) {
            $where[] = '(c.codigo LIKE :b OR EXISTS (SELECT 1 FROM residentes r WHERE r.casa_id = c.id AND r.nombre LIKE :b))';
            $params['b'] = '%' . $filtros['buscar'] . '%';
        }
        if (!empty($filtros['morosas'])) {
            $where[] = '(SELECT COALESCE(SUM(g.monto + g.mora - g.descuento - g.pagado),0) FROM cargos g
                         WHERE g.casa_id = c.id AND g.estado IN ("pendiente","parcial")) > 0.009';
        }
        $sql = 'SELECT c.*, f.nombre AS fase, ca.nombre AS calle,
                  (SELECT COALESCE(SUM(g.monto + g.mora - g.descuento - g.pagado),0) FROM cargos g
                   WHERE g.casa_id = c.id AND g.estado IN ("pendiente","parcial")) AS saldo,
                  (SELECT r.nombre FROM residentes r WHERE r.casa_id = c.id AND r.activo = 1
                   ORDER BY (r.tipo = "propietario") DESC, r.id ASC LIMIT 1) AS residente,
                  (SELECT r.telefono FROM residentes r WHERE r.casa_id = c.id AND r.activo = 1
                   ORDER BY (r.tipo = "propietario") DESC, r.id ASC LIMIT 1) AS telefono,
                  (SELECT MIN(g.fecha_vence) FROM cargos g
                   WHERE g.casa_id = c.id AND g.estado IN ("pendiente","parcial")) AS vence_mas_antiguo
                FROM casas c
                LEFT JOIN fases f   ON f.id = c.fase_id
                LEFT JOIN calles ca ON ca.id = c.calle_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY f.orden, ca.orden, LENGTH(c.codigo), c.codigo
                LIMIT ' . max(1, $limite) . ' OFFSET ' . max(0, $desplazamiento);
        return DB::todos($sql, $params);
    }

    public static function contar(array $filtros = []): int
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filtros['fase'])) {
            $where[] = 'fase_id = :fase';
            $params['fase'] = (int) $filtros['fase'];
        }
        if (!empty($filtros['estado'])) {
            $where[] = 'estado = :estado';
            $params['estado'] = (string) $filtros['estado'];
        }
        return (int) DB::valor('SELECT COUNT(*) FROM casas WHERE ' . implode(' AND ', $where), $params, 0);
    }

    public static function saldo(int $casaId): float
    {
        return (float) DB::valor(
            'SELECT COALESCE(SUM(monto + mora - descuento - pagado),0) FROM cargos
             WHERE casa_id = :c AND estado IN ("pendiente","parcial")',
            ['c' => $casaId],
            0
        );
    }

    /** Días de mora del cargo vencido más antiguo (0 si está solvente). */
    public static function diasMora(int $casaId): int
    {
        $f = DB::valor(
            'SELECT MIN(fecha_vence) FROM cargos
             WHERE casa_id = :c AND estado IN ("pendiente","parcial") AND fecha_vence < CURDATE()',
            ['c' => $casaId]
        );
        if (!$f) {
            return 0;
        }
        return max(0, (int) floor((time() - strtotime((string) $f)) / 86400));
    }

    public static function solvente(int $casaId): bool
    {
        return self::saldo($casaId) <= 0.009;
    }

    public static function residentes(int $casaId, bool $soloActivos = true): array
    {
        return DB::todos(
            'SELECT * FROM residentes WHERE casa_id = :c ' . ($soloActivos ? 'AND activo = 1 ' : '')
            . 'ORDER BY (tipo = "propietario") DESC, nombre',
            ['c' => $casaId]
        );
    }

    public static function propietario(int $casaId): ?array
    {
        return DB::uno(
            'SELECT * FROM residentes WHERE casa_id = :c AND activo = 1
             ORDER BY (tipo = "propietario") DESC, id ASC LIMIT 1',
            ['c' => $casaId]
        );
    }

    public static function vehiculos(int $casaId): array
    {
        return DB::todos('SELECT * FROM vehiculos WHERE casa_id = :c AND activo = 1 ORDER BY placa', ['c' => $casaId]);
    }

    public static function fases(): array
    {
        return DB::todos('SELECT * FROM fases WHERE activo = 1 ORDER BY orden, nombre');
    }

    public static function calles(?int $faseId = null): array
    {
        if ($faseId !== null && $faseId > 0) {
            return DB::todos('SELECT * FROM calles WHERE fase_id = :f ORDER BY orden, nombre', ['f' => $faseId]);
        }
        return DB::todos(
            'SELECT ca.*, f.nombre AS fase FROM calles ca
             LEFT JOIN fases f ON f.id = ca.fase_id ORDER BY f.orden, ca.orden, ca.nombre'
        );
    }

    /** Etiqueta corta: "Casa C-12 · Fase Los Robles" */
    public static function etiqueta(array $casa): string
    {
        $tipo = match ($casa['tipo'] ?? 'casa') {
            'apartamento' => 'Apartamento',
            'lote'        => 'Lote',
            'local'       => 'Local',
            default       => 'Casa',
        };
        $txt = $tipo . ' ' . ($casa['codigo'] ?? '');
        if (!empty($casa['fase'])) {
            $txt .= ' · ' . $casa['fase'];
        }
        return $txt;
    }

    /** Opciones para <select>. */
    public static function opciones(): array
    {
        return DB::todos(
            'SELECT c.id, c.codigo, f.nombre AS fase FROM casas c
             LEFT JOIN fases f ON f.id = c.fase_id
             ORDER BY f.orden, LENGTH(c.codigo), c.codigo'
        );
    }

    /** Recalcula el indicador de restricción de servicios por morosidad. */
    public static function actualizarRestriccion(int $casaId, int $diasCorte): void
    {
        $dias = self::diasMora($casaId);
        $r    = ($diasCorte > 0 && $dias >= $diasCorte) ? 1 : 0;
        DB::actualizar('casas', ['restringida' => $r], 'id = :id', ['id' => $casaId]);
    }
}
