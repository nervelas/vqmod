<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tz;
use App\Services\AnalyticsService;
use App\Services\ReportService;

/** Analítica: embudo, fuentes, periodos, horas pico, ocupación e ingresos. */
final class AnalyticsController extends A3Controller
{
    public function index(Request $req, array $p): Response
    {
        $tz = $this->bizTz();
        $today = $this->today();
        $defFrom = Tz::formatTs(Clock::now() - 29 * 86400, $tz, 'Y-m-d');
        $from = $this->dateParam($req, 'desde', $defFrom);
        $to = $this->dateParam($req, 'hasta', $today);
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $group = $req->str('agrupar', 5);
        $group = in_array($group, ['day', 'week', 'month'], true) ? $group : 'day';
        $scope = $this->scope();
        $filters = ['group' => $group];
        if ($req->int('evento') > 0) {
            $filters['event_type_id'] = $req->int('evento');
        }
        $hostId = $scope ?? $req->int('anfitrion');
        if ($hostId > 0) {
            $filters['host_id'] = $hostId;
        }
        $error = null;
        $report = null;
        try {
            $report = AnalyticsService::report($from, $to, $filters);
        } catch (\Throwable $e) {
            Logger::error('Analítica', $e);
            $error = 'No pudimos calcular el reporte en este momento. Inténtalo de nuevo en unos minutos.';
        }
        $ranges = [
            '7 días' => [Tz::formatTs(Clock::now() - 6 * 86400, $tz, 'Y-m-d'), $today],
            '30 días' => [$defFrom, $today],
            '90 días' => [Tz::formatTs(Clock::now() - 89 * 86400, $tz, 'Y-m-d'), $today],
            'Este mes' => [substr($today, 0, 8) . '01', $today],
        ];
        return $this->page('admin/analytics/index', [
            'report' => $report, 'error' => $error, 'from' => $from, 'to' => $to, 'group' => $group, 'eventId' => (int) ($filters['event_type_id'] ?? 0), 'hostId' => (int) ($filters['host_id'] ?? 0),
            'events' => $this->events(), 'hosts' => $this->hosts(), 'scoped' => $scope !== null, 'ranges' => $ranges,
        ], '/admin/analitica', 'Analítica', ['js/admin-analytics.js']);
    }

    public function export(Request $req, array $p): Response
    {
        $kind = $req->str('tipo', 12);
        $from = $this->dateParam($req, 'desde', '');
        $to = $this->dateParam($req, 'hasta', '');
        if (!in_array($kind, ['bookings', 'clients', 'payments', 'analytics'], true) || $from === '' || $to === '') {
            return $this->fail($req, 'Elige el tipo de reporte y un rango de fechas válido.', '/admin/analitica');
        }
        try {
            $csv = ReportService::csv($kind, $from, $to);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($req, $e->getMessage(), '/admin/analitica');
        }
        $this->audit('report.export', 'report', $kind, $from . ' a ' . $to);
        return Response::download($csv, 'text/csv', 'reporte-' . $kind . '-' . $from . '-' . $to . '.csv');
    }
}
