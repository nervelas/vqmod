<?php
declare(strict_types=1);

namespace App\Servicios;

use App\Core\Database;
use App\Core\Mail;
use App\Core\Notificador;
use App\Core\Settings;
use App\Models\Academico;
use App\Models\Alumno;
use App\Models\Cobranza;

/**
 * Recordatorios de cobro: 3 dias antes del vencimiento, el dia del vencimiento
 * y cada 7 dias en mora. Correo + notificacion interna + enlace de WhatsApp.
 */
final class Recordatorios
{
    /** @return array{enviados:int, omitidos:int} */
    public static function procesar(?string $hoy = null): array
    {
        $hoy = $hoy ?: date('Y-m-d');
        $previo = max(0, Settings::int('recordatorio_previo_dias', 3));
        $cada = max(1, Settings::int('recordatorio_mora_cada', 7));
        $enviados = 0;
        $omitidos = 0;

        $cargos = Database::all(
            'SELECT c.*, a.nombres, a.apellidos, a.codigo
             FROM cargos c
             JOIN alumnos a ON a.id = c.alumno_id
             JOIN inscripciones i ON i.alumno_id = a.id AND i.ciclo_id = c.ciclo_id AND i.estado = \'activo\'
             WHERE c.estado IN (\'pendiente\',\'parcial\') AND c.ciclo_id = :ciclo
               AND c.fecha_vencimiento BETWEEN :desde AND :hasta',
            [
                'ciclo' => Academico::cicloActivoId(),
                'desde' => date('Y-m-d', strtotime($hoy . ' -365 days')),
                'hasta' => date('Y-m-d', strtotime($hoy . ' +' . $previo . ' days')),
            ]
        );

        foreach ($cargos as $cargo) {
            $saldo = Cobranza::saldo($cargo);
            if ($saldo <= 0) {
                continue;
            }
            $vence = (string)$cargo['fecha_vencimiento'];
            $dias = (int)floor((strtotime($hoy) - strtotime($vence)) / 86400);
            $tipo = null;
            if ($dias === -$previo && $previo > 0) {
                $tipo = 'previo';
            } elseif ($dias === 0) {
                $tipo = 'vence';
            } elseif ($dias > 0 && $dias % $cada === 0) {
                $tipo = 'mora-' . $dias;
            }
            if ($tipo === null) {
                continue;
            }
            $ya = (int)Database::value(
                'SELECT COUNT(*) FROM recordatorios WHERE cargo_id = :c AND tipo = :t',
                ['c' => (int)$cargo['id'], 't' => $tipo],
                0
            );
            if ($ya > 0) {
                $omitidos++;
                continue;
            }
            if (self::enviar($cargo, $saldo, $tipo)) {
                $enviados++;
            } else {
                $omitidos++;
            }
            Database::run(
                'INSERT INTO recordatorios (cargo_id, tipo, canal) VALUES (:c, :t, :ca)',
                ['c' => (int)$cargo['id'], 't' => $tipo, 'ca' => 'correo']
            );
        }
        return ['enviados' => $enviados, 'omitidos' => $omitidos];
    }

    private static function enviar(array $cargo, float $saldo, string $tipo): bool
    {
        $alumnoId = (int)$cargo['alumno_id'];
        $nombreAlumno = trim((string)$cargo['nombres'] . ' ' . (string)$cargo['apellidos']);
        $encargados = Database::all(
            'SELECT * FROM encargados WHERE alumno_id = :a ORDER BY principal DESC, orden',
            ['a' => $alumnoId]
        );
        if ($encargados === []) {
            return false;
        }
        $vars = self::variables($nombreAlumno, $encargados[0], $saldo, (string)$cargo['fecha_vencimiento'], (string)$cargo['descripcion']);
        $asunto = match (true) {
            $tipo === 'previo' => 'Recordatorio de pago proximo a vencer',
            $tipo === 'vence'  => 'Su pago vence hoy',
            default            => 'Saldo pendiente en mora',
        };
        $cuerpo = plantilla(
            (string)Settings::get('plantilla_correo', '<p>Estimado/a {encargado}, {alumno} tiene un saldo de {monto} con vencimiento {vence}.</p>'),
            $vars
        );
        $entregado = false;
        foreach ($encargados as $enc) {
            if (!empty($enc['user_id'])) {
                $entregado = true;
                Notificador::crear(
                    (int)$enc['user_id'],
                    $asunto,
                    $nombreAlumno . ' · ' . moneda($saldo) . ' · vence ' . fecha((string)$cargo['fecha_vencimiento']),
                    'portal/cuenta'
                );
            }
            $correo = (string)($enc['email'] ?? '');
            if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $entregado = Mail::enviar($correo, (string)$enc['nombre'], $asunto, $cuerpo) || $entregado;
            }
        }
        // Se considera entregado si al menos llego por un canal (portal o correo).
        return $entregado;
    }

    /** Variables disponibles en las plantillas configurables. */
    public static function variables(string $alumno, array $encargado, float $saldo, string $vence, string $concepto = ''): array
    {
        return [
            'alumno'    => $alumno,
            'encargado' => (string)($encargado['nombre'] ?? ''),
            'monto'     => moneda($saldo),
            'vence'     => fecha($vence),
            'concepto'  => $concepto,
            'colegio'   => (string)Settings::get('colegio_nombre', 'EduPortal'),
            'portal'    => url_absoluta('portal'),
        ];
    }

    /** Genera el enlace de WhatsApp para un cargo concreto. */
    public static function enlaceWhatsApp(int $alumnoId, float $saldo, string $vence): string
    {
        $alumno = Alumno::porId($alumnoId);
        $enc = Alumno::encargadoPrincipal($alumnoId);
        if (!$alumno || !$enc) {
            return '';
        }
        $texto = plantilla(
            (string)Settings::get('plantilla_wa', 'Estimado/a {encargado}, le recordamos el saldo de {alumno} por {monto} con vencimiento {vence}. {colegio}'),
            self::variables(Alumno::nombre($alumno), $enc, $saldo, $vence)
        );
        return wa_link((string)($enc['telefono'] ?? ''), $texto);
    }
}
