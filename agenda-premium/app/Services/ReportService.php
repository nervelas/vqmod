<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Db;
use App\Core\Fmt;
use App\Core\Settings;
use App\Core\Tz;
use App\Core\Validator;

/**
 * Exportaciones CSV (UTF-8 con BOM, separador ',' y saltos CRLF; Excel en español lo abre bien por el BOM al usar
 * "Datos > Desde texto/CSV"; si se abre con doble clic en una configuración regional que usa ';', elegir coma como delimitador).
 * Las celdas de texto que empiezan con = + - @ (o tabulador/retorno) llevan prefijo ' para neutralizar fórmulas.
 */
final class ReportService
{
    private const LABELS = [
        'pending' => 'Pendiente', 'confirmed' => 'Confirmada', 'cancelled' => 'Cancelada', 'completed' => 'Completada', 'no_show' => 'No asistió', 'rejected' => 'Rechazada',
        'none' => 'Sin pago', 'partial' => 'Parcial', 'paid' => 'Pagada', 'refunded' => 'Reembolsada', 'verified' => 'Verificado',
    ];

    /** @throws \InvalidArgumentException */
    public static function csv(string $kind, string $fromDate, string $toDate): string
    {
        if (!Validator::date($fromDate) || !Validator::date($toDate) || $fromDate > $toDate) {
            throw new \InvalidArgumentException('Elige un rango de fechas válido.');
        }
        $tz = Settings::tz();
        $a = Tz::localToUtc($fromDate . ' 00:00:00', $tz);
        $b = Tz::localToUtc(gmdate('Y-m-d', strtotime($toDate . ' UTC') + 86400) . ' 00:00:00', $tz);
        switch ($kind) {
            case 'bookings':
                $head = ['ID', 'Fecha', 'Hora', 'Duración (min)', 'Cita', 'Anfitrión', 'Cliente', 'Correo', 'Teléfono', 'Estado', 'Precio', 'Descuento', 'Total', 'Pagado', 'Estado de pago', 'Origen', 'Campaña', 'Creada'];
                $data = [];
                foreach (Db::all('SELECT b.*, e.name AS ev, h.name AS hn FROM bookings b JOIN event_types e ON e.id = b.event_type_id LEFT JOIN hosts h ON h.id = b.host_id WHERE b.starts_at >= ? AND b.starts_at < ? ORDER BY b.starts_at, b.id', [$a, $b]) as $r) {
                    $data[] = [(int) $r['id'], Tz::format((string) $r['starts_at'], $tz, 'd/m/Y'), Tz::format((string) $r['starts_at'], $tz, 'H:i'), (int) $r['duration'], $r['ev'], $r['hn'], $r['guest_name'], $r['guest_email'], $r['guest_phone'],
                        self::LABELS[$r['status']] ?? $r['status'], (string) $r['price'], (string) $r['discount'], (string) $r['total'], (string) $r['paid_amount'], self::LABELS[$r['payment_status']] ?? $r['payment_status'],
                        $r['utm_source'] ?: $r['referrer_host'], $r['utm_campaign'], Tz::format((string) $r['created_at'], $tz, 'd/m/Y H:i')];
                }
                break;
            case 'clients':
                $head = ['ID', 'Nombre', 'Correo', 'Teléfono', 'NIT', 'Etiquetas', 'Origen', 'No asistencias', 'Bloqueado', 'Registrado'];
                $data = [];
                foreach (Db::all('SELECT * FROM clients WHERE created_at >= ? AND created_at < ? AND anonymized_at IS NULL ORDER BY id', [$a, $b]) as $r) {
                    $data[] = [(int) $r['id'], $r['name'], $r['email'], $r['phone'], $r['nit'], $r['tags'], $r['source'], (int) $r['noshow_count'], (int) $r['blocked'] === 1 ? 'Sí' : 'No', Tz::format((string) $r['created_at'], $tz, 'd/m/Y H:i')];
                }
                break;
            case 'payments':
                $head = ['ID', 'Fecha', 'Cita', 'Cliente', 'Método', 'Estado', 'Monto', 'Referencia', 'Nota'];
                $data = [];
                foreach (Db::all('SELECT p.*, c.name AS cn FROM payments p LEFT JOIN clients c ON c.id = p.client_id WHERE p.created_at >= ? AND p.created_at < ? ORDER BY p.created_at, p.id', [$a, $b]) as $r) {
                    $data[] = [(int) $r['id'], Tz::format((string) $r['created_at'], $tz, 'd/m/Y H:i'), $r['booking_id'] !== null ? (int) $r['booking_id'] : '', $r['cn'], PaymentService::methodLabel((string) $r['method']),
                        $r['status'] === 'refunded' ? 'Reembolso' : (self::LABELS[$r['status']] ?? ($r['status'] === 'pending' ? 'Pendiente' : 'Rechazado')), (string) $r['amount'], $r['reference'], $r['note']];
                }
                break;
            case 'analytics':
                $rep = AnalyticsService::report($fromDate, $toDate, ['group' => 'day']);
                $head = ['Fecha', 'Citas', 'Canceladas', 'No asistió'];
                $data = [];
                foreach ($rep['by_period']['rows'] as $r) {
                    $data[] = [$r['period'], $r['bookings'], $r['cancelled'], $r['no_show']];
                }
                $data[] = [];
                $data[] = ['Fuente', 'Visitas', 'Reservaron', 'Citas', 'Conversión %'];
                foreach ($rep['by_source'] as $r) {
                    $data[] = [$r['source'], $r['views'], $r['booked_visits'], $r['bookings'], $r['conversion_pct']];
                }
                break;
            default:
                throw new \InvalidArgumentException('Ese tipo de reporte no existe.');
        }
        $out = "\xEF\xBB\xBF" . self::line($head);
        foreach ($data as $row) {
            $out .= self::line($row);
        }
        return $out;
    }

    private static function line(array $cells): string
    {
        return implode(',', array_map([self::class, 'cell'], $cells)) . "\r\n";
    }

    private static function cell($v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }
        $s = (string) $v;
        if ($s !== '' && strpbrk($s[0], "=+-@\t\r") !== false) {
            $s = "'" . $s;
        }
        return preg_match('/[",\r\n]/', $s) ? '"' . str_replace('"', '""', $s) . '"' : $s;
    }

    /** Correo con los KPIs de la semana pasada (lunes a domingo), una sola vez por semana. Nunca lanza hacia el cron. */
    public static function weeklySummary(): void
    {
        if (!Settings::bool('weekly_summary')) {
            return;
        }
        $to = trim((string) Settings::get('admin_notify_email', ''));
        if (!Validator::email($to)) {
            return;
        }
        $tz = Settings::tz();
        $today = Tz::formatTs(Clock::now(), $tz, 'Y-m-d');
        $t = strtotime($today . ' UTC');
        $monday = gmdate('Y-m-d', $t - ((int) gmdate('N', $t) - 1) * 86400);
        $from = gmdate('Y-m-d', strtotime($monday . ' UTC') - 7 * 86400);
        $until = gmdate('Y-m-d', strtotime($monday . ' UTC') - 86400);
        // Reserva atómica de la semana: solo un proceso gana el UPDATE.
        Db::exec("INSERT IGNORE INTO settings (k, v, updated_at) VALUES ('weekly_summary_last', '', ?)", [Clock::utc()]);
        if (Db::exec("UPDATE settings SET v = ?, updated_at = ? WHERE k = 'weekly_summary_last' AND (v IS NULL OR v <> ?)", [$from, Clock::utc(), $from]) !== 1) {
            return;
        }
        Settings::flush();
        $k = AnalyticsService::report($from, $until)['kpis'];
        $row = static function (string $label, string $val, array $m): string {
            $d = $m['delta_pct'] === null ? 'nuevo' : (($m['delta_pct'] > 0 ? '+' : '') . str_replace('.', ',', (string) $m['delta_pct']) . ' %');
            return '<tr><td style="padding:6px 12px 6px 0">' . e($label) . '</td><td style="padding:6px 12px;text-align:right"><strong>' . e($val) . '</strong></td><td style="padding:6px 0;color:#777">' . e($d) . ' vs. semana anterior</td></tr>';
        };
        $pct = static fn (float $v): string => str_replace('.', ',', (string) $v) . ' %';
        $biz = (string) Settings::get('business_name', 'tu negocio');
        $range = Fmt::dateShort(Tz::localToUtc($from . ' 12:00:00', $tz), $tz) . ' al ' . Fmt::dateShort(Tz::localToUtc($until . ' 12:00:00', $tz), $tz);
        $html = '<p>Este es el resumen de <strong>' . e($biz) . '</strong> del ' . e($range) . '.</p><table role="presentation" cellpadding="0" cellspacing="0">'
            . $row('Citas', (string) $k['bookings']['value'], $k['bookings'])
            . $row('Citas confirmadas o completadas', (string) $k['confirmed']['value'], $k['confirmed'])
            . $row('Cancelaciones', (string) $k['cancelled']['value'], $k['cancelled'])
            . $row('No asistieron', (string) $k['no_show']['value'], $k['no_show'])
            . $row('Visitas a tu página de reservas', (string) $k['views']['value'], $k['views'])
            . $row('Conversión', $pct((float) $k['conversion_pct']['value']), $k['conversion_pct'])
            . $row('Ingresos verificados', Fmt::money($k['revenue']['value']), $k['revenue'])
            . '</table>';
        try {
            Mailer::queue($to, null, 'Resumen semanal de ' . $biz, Mailer::layout('Resumen de la semana', $html), 'Resumen semanal de ' . $biz . ' (' . $range . '): ' . $k['bookings']['value'] . ' citas, ' . $k['cancelled']['value'] . ' cancelaciones, ' . $k['no_show']['value'] . ' inasistencias, ingresos ' . Fmt::money($k['revenue']['value']) . '.');
        } catch (\Throwable $e) {
            Db::exec("UPDATE settings SET v = '' WHERE k = 'weekly_summary_last'"); // si no se pudo encolar, se reintenta en el próximo ciclo
            Settings::flush();
            \App\Core\Logger::error('Resumen semanal: no se pudo encolar el correo', $e);
        }
    }
}
