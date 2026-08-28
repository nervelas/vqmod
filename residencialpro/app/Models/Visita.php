<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Config;
use App\Core\DB;
use App\Core\Notificar;

/**
 * Pre-registro de visitas (códigos QR firmados) y control de accesos en garita.
 */
final class Visita
{
    // --------------------------------------------------------- Pre-registro

    /** Crea un pre-registro y devuelve la fila completa. */
    public static function preRegistrar(array $datos): array
    {
        $casaId = (int) $datos['casa_id'];
        $desde  = (string) ($datos['valido_desde'] ?? date('Y-m-d H:i:s'));
        $hasta  = (string) ($datos['valido_hasta'] ?? date('Y-m-d H:i:s', time() + 86400));
        $codigo = self::codigoUnico();
        $id = DB::insertar('preregistros', [
            'casa_id'      => $casaId,
            'usuario_id'   => Auth::id() ?: null,
            'visitante'    => mb_substr((string) $datos['visitante'], 0, 140),
            'dpi'          => $datos['dpi'] ?? null,
            'placa'        => !empty($datos['placa']) ? strtoupper(preg_replace('/\s+/', '', (string) $datos['placa'])) : null,
            'motivo'       => $datos['motivo'] ?? null,
            'codigo'       => $codigo,
            'firma'        => self::firma($codigo, $hasta),
            'recurrente'   => !empty($datos['recurrente']) ? 1 : 0,
            'dias'         => $datos['dias'] ?? null,
            'hora_desde'   => $datos['hora_desde'] ?? null,
            'hora_hasta'   => $datos['hora_hasta'] ?? null,
            'valido_desde' => $desde,
            'valido_hasta' => $hasta,
            'max_usos'     => max(1, (int) ($datos['max_usos'] ?? 1)),
        ]);
        Auditoria::registrar('preregistro', 'preregistros', $id, 'Visitante ' . $datos['visitante']);
        return (array) DB::uno('SELECT * FROM preregistros WHERE id = :id', ['id' => $id]);
    }

    public static function codigoUnico(): string
    {
        do {
            $c = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $existe = DB::valor('SELECT id FROM preregistros WHERE codigo = :c', ['c' => $c]);
        } while ($existe);
        return $c;
    }

    /** Firma HMAC del código: impide falsificar un QR. */
    public static function firma(string $codigo, string $vence): string
    {
        $llave = (string) Config::get('app.llave', 'residencialpro');
        return hash_hmac('sha256', $codigo . '|' . $vence, $llave);
    }

    /** Contenido que se codifica en el QR. */
    public static function tokenQr(array $prereg): string
    {
        $exp = (int) strtotime((string) $prereg['valido_hasta']);
        $firma = substr(self::firma((string) $prereg['codigo'], (string) $prereg['valido_hasta']), 0, 20);
        return 'RP1.' . $prereg['codigo'] . '.' . $exp . '.' . $firma;
    }

    /**
     * Valida un token QR o un código de 6 dígitos.
     * Devuelve ['ok'=>bool,'mensaje'=>string,'prereg'=>?array,'casa'=>?array]
     */
    public static function validar(string $entrada): array
    {
        $entrada = trim($entrada);
        $codigo  = '';
        if (str_starts_with($entrada, 'RP1.')) {
            $partes = explode('.', $entrada);
            if (count($partes) !== 4) {
                return ['ok' => false, 'mensaje' => 'El código QR no tiene un formato válido.', 'prereg' => null, 'casa' => null];
            }
            $codigo = $partes[1];
        } elseif (preg_match('/^\d{6}$/', $entrada)) {
            $codigo = $entrada;
        } else {
            return ['ok' => false, 'mensaje' => 'Código no reconocido. Solicite el código de 6 dígitos.', 'prereg' => null, 'casa' => null];
        }

        $p = DB::uno('SELECT * FROM preregistros WHERE codigo = :c', ['c' => $codigo]);
        if ($p === null) {
            return ['ok' => false, 'mensaje' => 'No existe una autorización con ese código.', 'prereg' => null, 'casa' => null];
        }
        // Verificación de firma (solo para QR).
        if (str_starts_with($entrada, 'RP1.')) {
            $partes = explode('.', $entrada);
            $esperada = substr(self::firma((string) $p['codigo'], (string) $p['valido_hasta']), 0, 20);
            if (!hash_equals($esperada, $partes[3])) {
                Auditoria::registrar('qr_invalido', 'preregistros', (int) $p['id'], 'Firma no coincide');
                return ['ok' => false, 'mensaje' => 'El código QR fue alterado. No lo acepte.', 'prereg' => null, 'casa' => null];
            }
        }
        if ($p['estado'] === 'cancelado') {
            return ['ok' => false, 'mensaje' => 'El residente canceló esta autorización.', 'prereg' => $p, 'casa' => null];
        }
        $ahora = time();
        if ($ahora < strtotime((string) $p['valido_desde'])) {
            return ['ok' => false, 'mensaje' => 'La autorización todavía no está vigente (desde ' . date('d/m/Y H:i', strtotime((string) $p['valido_desde'])) . ').', 'prereg' => $p, 'casa' => null];
        }
        if ($ahora > strtotime((string) $p['valido_hasta'])) {
            DB::actualizar('preregistros', ['estado' => 'vencido'], 'id = :id', ['id' => (int) $p['id']]);
            return ['ok' => false, 'mensaje' => 'La autorización venció el ' . date('d/m/Y H:i', strtotime((string) $p['valido_hasta'])) . '.', 'prereg' => $p, 'casa' => null];
        }
        if ((int) $p['usos'] >= (int) $p['max_usos'] && (int) $p['recurrente'] === 0) {
            return ['ok' => false, 'mensaje' => 'Este código ya fue utilizado.', 'prereg' => $p, 'casa' => null];
        }
        if ((int) $p['recurrente'] === 1) {
            $dias = array_filter(array_map('trim', explode(',', (string) $p['dias'])), static fn($d) => $d !== '');
            if ($dias !== [] && !in_array((string) (int) date('w'), $dias, true)) {
                return ['ok' => false, 'mensaje' => 'Hoy no está autorizado el ingreso de esta persona.', 'prereg' => $p, 'casa' => null];
            }
            $h = date('H:i:s');
            if (!empty($p['hora_desde']) && !empty($p['hora_hasta']) && ($h < $p['hora_desde'] || $h > $p['hora_hasta'])) {
                return ['ok' => false, 'mensaje' => 'Fuera del horario autorizado (' . substr((string) $p['hora_desde'], 0, 5) . ' a ' . substr((string) $p['hora_hasta'], 0, 5) . ').', 'prereg' => $p, 'casa' => null];
            }
        }
        $casa = Casa::porId((int) $p['casa_id']);
        return ['ok' => true, 'mensaje' => 'Autorización válida.', 'prereg' => $p, 'casa' => $casa];
    }

    // ------------------------------------------------------------- Ingresos

    /** Registra el ingreso. Devuelve el id de la visita. */
    public static function registrarEntrada(array $datos, ?array $prereg = null): int
    {
        $casaId = !empty($datos['casa_id']) ? (int) $datos['casa_id'] : null;
        $id = DB::insertar('visitas', [
            'casa_id'     => $casaId,
            'prereg_id'   => $prereg !== null ? (int) $prereg['id'] : null,
            'tipo'        => (string) ($datos['tipo'] ?? 'visita'),
            'visitante'   => mb_substr((string) $datos['visitante'], 0, 140),
            'dpi'         => $datos['dpi'] ?? null,
            'placa'       => !empty($datos['placa']) ? strtoupper(preg_replace('/\s+/', '', (string) $datos['placa'])) : null,
            'vehiculo'    => $datos['vehiculo'] ?? null,
            'personas'    => max(1, (int) ($datos['personas'] ?? 1)),
            'motivo'      => $datos['motivo'] ?? null,
            'foto'        => $datos['foto'] ?? null,
            'entrada'     => date('Y-m-d H:i:s'),
            'guardia_in'  => Auth::id() ?: null,
            'autorizado'  => $prereg !== null ? 1 : (int) ($datos['autorizado'] ?? 1),
            'uuid'        => bin2hex(random_bytes(16)),
            'notas'       => $datos['notas'] ?? null,
        ]);
        if ($prereg !== null) {
            DB::q(
                'UPDATE preregistros SET usos = usos + 1,
                 estado = CASE WHEN recurrente = 0 AND usos + 1 >= max_usos THEN "usado" ELSE estado END
                 WHERE id = :id',
                ['id' => (int) $prereg['id']]
            );
        }
        if ($casaId !== null && Ajustes::esVerdadero('avisar_visita', true)) {
            Notificar::casa(
                $casaId,
                'Su visita llegó a garita',
                $datos['visitante'] . (!empty($datos['placa']) ? ' · placa ' . $datos['placa'] : ''),
                '/portal/visitas'
            );
        }
        Auditoria::registrar('ingreso_garita', 'visitas', $id, (string) $datos['visitante']);
        return $id;
    }

    public static function registrarSalida(int $visitaId): bool
    {
        $v = DB::uno('SELECT * FROM visitas WHERE id = :id', ['id' => $visitaId]);
        if ($v === null || $v['salida'] !== null) {
            return false;
        }
        DB::actualizar('visitas', [
            'salida'      => date('Y-m-d H:i:s'),
            'guardia_out' => Auth::id() ?: null,
        ], 'id = :id', ['id' => $visitaId]);
        Auditoria::registrar('salida_garita', 'visitas', $visitaId, (string) $v['visitante']);
        return true;
    }

    public static function adentro(): array
    {
        return DB::todos(
            'SELECT v.*, c.codigo AS casa FROM visitas v
             LEFT JOIN casas c ON c.id = v.casa_id
             WHERE v.salida IS NULL ORDER BY v.entrada DESC LIMIT 200'
        );
    }

    public static function listar(array $filtros = [], int $limite = 100, int $desplazamiento = 0): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filtros['casa'])) {
            $where[] = 'v.casa_id = :c';
            $params['c'] = (int) $filtros['casa'];
        }
        if (!empty($filtros['desde'])) {
            $where[] = 'DATE(v.entrada) >= :d';
            $params['d'] = (string) $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[] = 'DATE(v.entrada) <= :h';
            $params['h'] = (string) $filtros['hasta'];
        }
        if (!empty($filtros['placa'])) {
            $where[] = 'v.placa LIKE :p';
            $params['p'] = '%' . strtoupper((string) $filtros['placa']) . '%';
        }
        if (!empty($filtros['buscar'])) {
            $where[] = '(v.visitante LIKE :b OR v.dpi LIKE :b OR v.placa LIKE :b)';
            $params['b'] = '%' . $filtros['buscar'] . '%';
        }
        if (!empty($filtros['tipo'])) {
            $where[] = 'v.tipo = :t';
            $params['t'] = (string) $filtros['tipo'];
        }
        return DB::todos(
            'SELECT v.*, c.codigo AS casa, u.nombre AS guardia
             FROM visitas v
             LEFT JOIN casas c ON c.id = v.casa_id
             LEFT JOIN usuarios u ON u.id = v.guardia_in
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY v.entrada DESC
             LIMIT ' . max(1, $limite) . ' OFFSET ' . max(0, $desplazamiento),
            $params
        );
    }

    public static function contar(array $filtros = []): int
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filtros['casa'])) {
            $where[] = 'casa_id = :c';
            $params['c'] = (int) $filtros['casa'];
        }
        if (!empty($filtros['desde'])) {
            $where[] = 'DATE(entrada) >= :d';
            $params['d'] = (string) $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[] = 'DATE(entrada) <= :h';
            $params['h'] = (string) $filtros['hasta'];
        }
        return (int) DB::valor('SELECT COUNT(*) FROM visitas WHERE ' . implode(' AND ', $where), $params, 0);
    }

    public static function deHoy(): int
    {
        return (int) DB::valor('SELECT COUNT(*) FROM visitas WHERE DATE(entrada) = CURDATE()', [], 0);
    }

    /** Autocompletado por placa registrada en el residencial. */
    public static function buscarPlaca(string $placa): ?array
    {
        $placa = strtoupper(preg_replace('/\s+/', '', $placa) ?? '');
        if (strlen($placa) < 3) {
            return null;
        }
        return DB::uno(
            'SELECT v.*, c.codigo AS casa, c.id AS casa_id FROM vehiculos v
             INNER JOIN casas c ON c.id = v.casa_id
             WHERE v.placa = :p AND v.activo = 1 LIMIT 1',
            ['p' => $placa]
        );
    }

    /** Últimos visitantes con ese DPI para autocompletar. */
    public static function historialVisitante(string $dpi): ?array
    {
        $dpi = preg_replace('/\D+/', '', $dpi) ?? '';
        if (strlen($dpi) < 8) {
            return null;
        }
        return DB::uno(
            'SELECT visitante, placa, vehiculo FROM visitas WHERE dpi = :d ORDER BY id DESC LIMIT 1',
            ['d' => $dpi]
        );
    }

    public static function preregistrosDe(int $casaId, int $limite = 50): array
    {
        return DB::todos(
            'SELECT * FROM preregistros WHERE casa_id = :c ORDER BY id DESC LIMIT ' . (int) $limite,
            ['c' => $casaId]
        );
    }

    public static function preregistrosVigentes(): array
    {
        return DB::todos(
            'SELECT p.*, c.codigo AS casa FROM preregistros p
             LEFT JOIN casas c ON c.id = p.casa_id
             WHERE p.estado = "activo" AND p.valido_hasta >= NOW()
             ORDER BY p.valido_desde ASC LIMIT 100'
        );
    }
}
