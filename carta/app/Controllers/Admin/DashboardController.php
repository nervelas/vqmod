<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\DB;
use MenuGold\Models\Order;
use MenuGold\Models\Report;
use MenuGold\Models\Settings;

class DashboardController extends BaseController
{
    protected $ability = 'orders';

    public function index()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        $today = date('Y-m-d');
        $byDay = Report::byDay(date('Y-m-d', strtotime('-13 days')), $today);

        // Serie de 14 días sin huecos, para la gráfica.
        $series = array();
        for ($i = 13; $i >= 0; $i--) { $series[date('Y-m-d', strtotime('-' . $i . ' days'))] = 0.0; }
        foreach ($byDay as $row) {
            if (isset($series[$row['d']])) { $series[$row['d']] = (float)$row['revenue']; }
        }

        return $this->view('admin/dashboard', array(
            'summary' => Report::summary($today, $today),
            'month'   => Report::summary(date('Y-m-01'), $today),
            'series'  => $series,
            'top'     => Report::topProducts(date('Y-m-01'), $today, 6),
            'active'  => Order::recent(8),
            'calls'   => DB::all("SELECT sc.*, t.name AS table_name FROM mg_service_calls sc
                                   LEFT JOIN mg_tables t ON t.id = sc.table_id
                                   WHERE sc.status = 'open' ORDER BY sc.created_at"),
            'setup'   => $this->checklist(),
        ));
    }

    /** Puesta en marcha: lo que todavía falta configurar. */
    private function checklist()
    {
        return array(
            'identity' => Settings::get('name') !== '' && Settings::get('name') !== 'Mi restaurante',
            'photos'   => Settings::get('logo') !== '' && Settings::get('cover') !== '',
            'menu'     => (int)DB::value('SELECT COUNT(*) FROM mg_products', array(), 0) > 0,
            'tables'   => (int)DB::value('SELECT COUNT(*) FROM mg_tables', array(), 0) > 0,
            'hours'    => (int)DB::value('SELECT COUNT(*) FROM mg_hours', array(), 0) > 0,
        );
    }
}
