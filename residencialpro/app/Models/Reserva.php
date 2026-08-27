<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Notificar;

final class Reserva
{
    public static function areas(bool $soloActivas = true): array
    {
        return DB::todos('SELECT * FROM areas ' . ($soloActivas ? 'WHERE activo = 1 ' : '') . 'ORDER BY nombre');
    }

    public static function area(int $id): ?array
    {
        return DB::uno('SELECT * FROM areas WHERE id = :id', ['id' => $id]);
    }

    public static function porId(int $id): ?array
    {
        return DB::uno(
            'SELECT r.*, a.nombre AS area, a.deposito, c.codigo AS casa
             FROM reservas r
             LEFT JOIN areas a ON a.id = r.area_id
             LEFT JOIN casas c ON c.id = r.casa_id
             WHERE r.id = :id',
            ['id' => $id]
        );
    }

    public static function delMes(int $areaId, string $anioMes): array
    {
        return DB::todos(
            'SELECT r.*, c.codigo AS casa FROM reservas r
             LEFT JOIN casas c ON c.id = r.casa_id
             WHERE r.area_id = :a AND DATE_FORMAT(r.fecha, "%Y-%m") = :m
               AND r.estado IN ("pendiente","aprobada","completada")
             ORDER BY r.fecha, r.hora_desde',
            ['a' => $areaId, 'm' => $anioMes]
        );
    }

    public static function listar(array $filtros = [], int $limite = 100): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filtros['estado'])) {
            $where[] = 'r.estado = :e';
            $params['e'] = (string) $filtros['estado'];
        }
        if (!empty($filtros['area'])) {
            $where[] = 'r.area_id = :a';
            $params['a'] = (int) $filtros['area'];
        }
        if (!empty($filtros['casa'])) {
            $where[] = 'r.casa_id = :c';
            $params['c'] = (int) $filtros['casa'];
        }
        if (!empty($filtros['desde'])) {
            $where[] = 'r.fecha >= :d';
            $params['d'] = (string) $filtros['desde'];
        }
        return DB::todos(
            'SELECT r.*, a.nombre AS area, c.codigo AS casa,
                    (SELECT re.nombre FROM residentes re WHERE re.casa_id = r.casa_id AND re.activo = 1
                     ORDER BY (re.tipo="propietario") DESC, re.id LIMIT 1) AS residente
             FROM reservas r
             LEFT JOIN areas a ON a.id = r.area_id
             LEFT JOIN casas c ON c.id = r.casa_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY r.fecha DESC, r.hora_desde DESC LIMIT ' . (int) $limite,
            $params
        );
    }

    public static function pendientes(): int
    {
        return (int) DB::valor('SELECT COUNT(*) FROM reservas WHERE estado = "pendiente"', [], 0);
    }

    public static function deLaSemana(): int
    {
        return (int) DB::valor(
            'SELECT COUNT(*) FROM reservas WHERE estado IN ("aprobada","pendiente")
             AND fecha BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)',
            [],
            0
        );
    }

    /** ¿Hay traslape con otra reserva vigente? */
    public static function hayTraslape(int $areaId, string $fecha, string $desde, string $hasta, int $excluirId = 0): bool
    {
        $r = DB::valor(
            'SELECT id FROM reservas
             WHERE area_id = :a AND fecha = :f AND estado IN ("pendiente","aprobada","completada")
               AND id <> :x AND (hora_desde < :hasta AND hora_hasta > :desde) LIMIT 1',
            ['a' => $areaId, 'f' => $fecha, 'x' => $excluirId, 'desde' => $desde, 'hasta' => $hasta]
        );
        return $r !== null && $r !== false;
    }

    /**
     * Solicita una reserva. Valida horario, traslape, morosidad y cobra si aplica.
     * Devuelve ['ok'=>bool,'mensaje'=>string,'id'=>int]
     */
    public static function solicitar(int $areaId, int $casaId, string $fecha, string $desde, string $hasta, int $personas, string $motivo): array
    {
        $area = self::area($areaId);
        if ($area === null || (int) $area['activo'] !== 1) {
            return ['ok' => false, 'mensaje' => 'El área común no está disponible.', 'id' => 0];
        }
        if (strtotime($fecha) < strtotime(date('Y-m-d'))) {
            return ['ok' => false, 'mensaje' => 'No es posible reservar en una fecha pasada.', 'id' => 0];
        }
        $dias = array_filter(array_map('trim', explode(',', (string) $area['dias'])), static fn($d) => $d !== '');
        if ($dias !== [] && !in_array((string) (int) date('w', (int) strtotime($fecha)), $dias, true)) {
            return ['ok' => false, 'mensaje' => 'El área no está disponible ese día de la semana.', 'id' => 0];
        }
        if ($desde < substr((string) $area['hora_desde'], 0, 5) || $hasta > substr((string) $area['hora_hasta'], 0, 5)) {
            return ['ok' => false, 'mensaje' => 'El horario solicitado está fuera del horario permitido ('
                . substr((string) $area['hora_desde'], 0, 5) . ' a ' . substr((string) $area['hora_hasta'], 0, 5) . ').', 'id' => 0];
        }
        if ($hasta <= $desde) {
            return ['ok' => false, 'mensaje' => 'La hora final debe ser posterior a la inicial.', 'id' => 0];
        }
        if ((int) $area['capacidad'] > 0 && $personas > (int) $area['capacidad']) {
            return ['ok' => false, 'mensaje' => 'La capacidad máxima del área es de ' . (int) $area['capacidad'] . ' personas.', 'id' => 0];
        }
        if (self::hayTraslape($areaId, $fecha, $desde . ':00', $hasta . ':00')) {
            return ['ok' => false, 'mensaje' => 'Ya existe una reserva en ese horario. Elija otro.', 'id' => 0];
        }
        if ((int) $area['bloquea_mora'] === 1 && !Casa::solvente($casaId)) {
            return ['ok' => false, 'mensaje' => 'La vivienda presenta saldo pendiente. Regularice su estado de cuenta para reservar.', 'id' => 0];
        }

        $costo  = round((float) $area['costo'], 2);
        $estado = $area['aprobacion'] === 'automatica' ? 'aprobada' : 'pendiente';
        $id = DB::insertar('reservas', [
            'area_id'    => $areaId,
            'casa_id'    => $casaId,
            'usuario_id' => Auth::id() ?: null,
            'fecha'      => $fecha,
            'hora_desde' => $desde . ':00',
            'hora_hasta' => $hasta . ':00',
            'personas'   => max(1, $personas),
            'motivo'     => mb_substr($motivo, 0, 190),
            'costo'      => $costo,
            'estado'     => $estado,
        ]);
        if ($estado === 'aprobada' && $costo > 0) {
            self::cobrar($id);
        }
        Auditoria::registrar('reserva', 'reservas', $id, $area['nombre'] . ' ' . $fecha);
        Notificar::rol(['admin'], 'Nueva reserva de área', $area['nombre'] . ' — ' . date('d/m/Y', (int) strtotime($fecha)), '/admin/reservas');
        return [
            'ok'      => true,
            'mensaje' => $estado === 'aprobada'
                ? 'Su reserva quedó confirmada.'
                : 'Su solicitud fue enviada. La administración le confirmará en breve.',
            'id'      => $id,
        ];
    }

    public static function aprobar(int $id): bool
    {
        $r = self::porId($id);
        if ($r === null || $r['estado'] !== 'pendiente') {
            return false;
        }
        DB::actualizar('reservas', ['estado' => 'aprobada'], 'id = :id', ['id' => $id]);
        if ((float) $r['costo'] > 0) {
            self::cobrar($id);
        }
        Notificar::casa((int) $r['casa_id'], 'Reserva confirmada', $r['area'] . ' — ' . date('d/m/Y', (int) strtotime((string) $r['fecha'])), '/portal/reservas');
        Auditoria::registrar('aprobar_reserva', 'reservas', $id, (string) $r['area']);
        return true;
    }

    public static function rechazar(int $id, string $motivo): bool
    {
        $r = self::porId($id);
        if ($r === null || !in_array($r['estado'], ['pendiente', 'aprobada'], true)) {
            return false;
        }
        DB::actualizar('reservas', [
            'estado'         => 'rechazada',
            'motivo_rechazo' => mb_substr($motivo, 0, 255),
        ], 'id = :id', ['id' => $id]);
        if (!empty($r['cargo_id'])) {
            Cuota::anularCargo((int) $r['cargo_id'], 'Reserva rechazada');
        }
        Notificar::casa((int) $r['casa_id'], 'Reserva no aprobada', $motivo, '/portal/reservas');
        Auditoria::registrar('rechazar_reserva', 'reservas', $id, $motivo);
        return true;
    }

    public static function cancelar(int $id): bool
    {
        $r = self::porId($id);
        if ($r === null || in_array($r['estado'], ['cancelada', 'completada'], true)) {
            return false;
        }
        DB::actualizar('reservas', ['estado' => 'cancelada'], 'id = :id', ['id' => $id]);
        if (!empty($r['cargo_id'])) {
            Cuota::anularCargo((int) $r['cargo_id'], 'Reserva cancelada');
        }
        Auditoria::registrar('cancelar_reserva', 'reservas', $id, (string) $r['area']);
        return true;
    }

    /** Genera el cargo al estado de cuenta de la casa. */
    private static function cobrar(int $id): void
    {
        $r = self::porId($id);
        if ($r === null || !empty($r['cargo_id']) || (float) $r['costo'] <= 0) {
            return;
        }
        $cargoId = Cuota::crearCargo(
            (int) $r['casa_id'],
            'Reserva de ' . $r['area'] . ' — ' . date('d/m/Y', (int) strtotime((string) $r['fecha'])),
            (float) $r['costo'],
            date('Y-m-d', strtotime('+10 days')),
            null,
            'reserva',
            $id
        );
        DB::actualizar('reservas', ['cargo_id' => $cargoId], 'id = :id', ['id' => $id]);
    }
}
