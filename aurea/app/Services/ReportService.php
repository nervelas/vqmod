<?php
declare(strict_types=1);

namespace Aurea\Services;

use Aurea\Core\Db;

final class ReportService
{
    /** Cláusula de alcance por profesional para usuarios con rol Profesional. */
    private static function scope(?int $prof, string $alias = 'a'): array
    {
        return $prof ? [' AND ' . $alias . '.professional_id=?', [$prof]] : ['', []];
    }

    public static function summary(string $from, string $to, ?int $prof = null): array
    {
        [$sc, $sp] = self::scope($prof);
        $f = $from . ' 00:00:00'; $t = $to . ' 23:59:59';
        $row = Db::one("SELECT COUNT(*) total,
            SUM(status='completed') completed, SUM(status='confirmed') confirmed, SUM(status='pending') pending,
            SUM(status='cancelled') cancelled, SUM(status='no_show') no_show,
            COALESCE(SUM(CASE WHEN status IN ('confirmed','completed') THEN total END),0) revenue_est
            FROM appointments a WHERE a.start_at BETWEEN ? AND ? AND a.status<>'rescheduled'" . $sc, array_merge([$f, $t], $sp)) ?? [];
        $paid = (float)Db::val("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN appointments a ON a.id=p.appointment_id
            WHERE p.status='confirmed' AND p.paid_at BETWEEN ? AND ?" . $sc, array_merge([$f, $t], $sp));
        $closed = (int)$row['completed'] + (int)$row['no_show'];
        $row['attendance'] = $closed > 0 ? round((int)$row['completed'] / $closed * 100, 1) : null;
        $row['revenue_paid'] = $paid;
        return $row;
    }

    public static function byProfessional(string $from, string $to, ?int $prof = null): array
    {
        [$sc, $sp] = self::scope($prof);
        return Db::all("SELECT p.name, COUNT(*) citas, SUM(a.status='completed') completadas, SUM(a.status='no_show') no_show, SUM(a.status='cancelled') canceladas,
            COALESCE(SUM(CASE WHEN a.status IN ('confirmed','completed') THEN a.total END),0) ingresos
            FROM appointments a JOIN professionals p ON p.id=a.professional_id
            WHERE a.start_at BETWEEN ? AND ? AND a.status<>'rescheduled'" . $sc . ' GROUP BY p.id,p.name ORDER BY citas DESC', array_merge([$from . ' 00:00:00', $to . ' 23:59:59'], $sp));
    }

    public static function byService(string $from, string $to, ?int $prof = null): array
    {
        [$sc, $sp] = self::scope($prof);
        return Db::all("SELECT s.name, COUNT(*) citas, SUM(a.status='completed') completadas, SUM(a.status='no_show') no_show, SUM(a.status='cancelled') canceladas,
            COALESCE(SUM(CASE WHEN a.status IN ('confirmed','completed') THEN a.total END),0) ingresos
            FROM appointments a JOIN services s ON s.id=a.service_id
            WHERE a.start_at BETWEEN ? AND ? AND a.status<>'rescheduled'" . $sc . ' GROUP BY s.id,s.name ORDER BY citas DESC', array_merge([$from . ' 00:00:00', $to . ' 23:59:59'], $sp));
    }

    /** Horas pico: matriz día de la semana (0-6) x hora. */
    public static function peakHours(string $from, string $to, ?int $prof = null): array
    {
        [$sc, $sp] = self::scope($prof);
        $rows = Db::all("SELECT DAYOFWEEK(a.start_at)-1 wd, HOUR(a.start_at) h, COUNT(*) n FROM appointments a
            WHERE a.start_at BETWEEN ? AND ? AND a.status IN ('pending','confirmed','completed','no_show')" . $sc . ' GROUP BY wd,h', array_merge([$from . ' 00:00:00', $to . ' 23:59:59'], $sp));
        $m = [];
        foreach ($rows as $r) { $m[(int)$r['wd']][(int)$r['h']] = (int)$r['n']; }
        return $m;
    }

    /** Clientes nuevos (primera cita en el periodo) vs recurrentes. */
    public static function newVsReturning(string $from, string $to, ?int $prof = null): array
    {
        [$sc, $sp] = self::scope($prof);
        $f = $from . ' 00:00:00'; $t = $to . ' 23:59:59';
        $rows = Db::all("SELECT a.client_id, (SELECT MIN(x.start_at) FROM appointments x WHERE x.client_id=a.client_id AND x.status IN ('confirmed','completed','no_show','pending')) first_at
            FROM appointments a WHERE a.start_at BETWEEN ? AND ? AND a.status IN ('confirmed','completed','no_show','pending')" . $sc . ' GROUP BY a.client_id', array_merge([$f, $t], $sp));
        $new = 0; $ret = 0;
        foreach ($rows as $r) { if ($r['first_at'] >= $f) { $new++; } else { $ret++; } }
        return ['new' => $new, 'returning' => $ret];
    }

    public static function appointmentsCsv(string $from, string $to, ?int $prof = null): string
    {
        [$sc, $sp] = self::scope($prof);
        $rows = Db::all("SELECT a.id, a.start_at, a.status, c.name cliente, CONCAT('+',c.phone_cc,' ',c.phone) tel, s.name servicio, p.name profesional,
            a.total, a.payment_status, a.source FROM appointments a JOIN clients c ON c.id=a.client_id JOIN services s ON s.id=a.service_id JOIN professionals p ON p.id=a.professional_id
            WHERE a.start_at BETWEEN ? AND ?" . $sc . ' ORDER BY a.start_at', array_merge([$from . ' 00:00:00', $to . ' 23:59:59'], $sp));
        $out = [];
        foreach ($rows as $r) {
            $out[] = [$r['id'], fdatetime($r['start_at']), BookingService::STATUSES[$r['status']] ?? $r['status'], $r['cliente'], $r['tel'], $r['servicio'], $r['profesional'], number_format((float)$r['total'], 2, '.', ''), $r['payment_status'], $r['source']];
        }
        return \Aurea\Core\Util::csv(['ID', 'Fecha y hora', 'Estado', 'Cliente', 'Teléfono', 'Servicio', 'Profesional', 'Total', 'Pago', 'Origen'], $out);
    }
}
