<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Money;
use MenuGold\Core\Pdf;
use MenuGold\Core\Xlsx;
use MenuGold\Models\Order;
use MenuGold\Models\Report;
use MenuGold\Models\Settings;

class ReportsController extends BaseController
{
    protected $ability = 'reports';

    private function range()
    {
        $from = $this->request->str('desde', date('Y-m-01'));
        $to   = $this->request->str('hasta', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = date('Y-m-01'); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $to   = date('Y-m-d'); }
        if ($from > $to) { $t = $from; $from = $to; $to = $t; }
        return array($from, $to);
    }

    public function index()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        list($from, $to) = $this->range();

        return $this->view('admin/reports', array(
            'from'     => $from,
            'to'       => $to,
            'summary'  => Report::summary($from, $to),
            'byDay'    => Report::byDay($from, $to),
            'byHour'   => Report::byHour($from, $to),
            'byCat'    => Report::byCategory($from, $to),
            'byMode'   => Report::byMode($from, $to),
            'byWaiter' => Report::byWaiter($from, $to),
            'topUp'    => Report::topProducts($from, $to, 10, false),
            'topDown'  => Report::topProducts($from, $to, 10, true),
            'timings'  => Report::timings($from, $to),
        ));
    }

    public function data()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        list($from, $to) = $this->range();
        return $this->ok(array(
            'summary' => Report::summary($from, $to),
            'byDay'   => Report::byDay($from, $to),
        ));
    }

    public function excel()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        list($from, $to) = $this->range();

        $summary = Report::summary($from, $to);
        $sheets = array();

        $sheets['Resumen'] = array(
            array('Restaurante', Settings::get('name')),
            array('Periodo', $from . ' a ' . $to),
            array(),
            array('Indicador', 'Valor'),
            array('Pedidos', $summary['orders']),
            array('Ventas', (float)$summary['revenue']),
            array('Ticket promedio', (float)$summary['ticket']),
            array('Propinas', (float)$summary['tips']),
            array('Descuentos', (float)$summary['discounts']),
            array('Anulados', $summary['cancelled']),
        );

        $day = array(array('Fecha', 'Pedidos', 'Ventas'));
        foreach (Report::byDay($from, $to) as $r) { $day[] = array($r['d'], (int)$r['orders'], (float)$r['revenue']); }
        $sheets['Por día'] = $day;

        $cat = array(array('Categoría', 'Unidades', 'Ventas'));
        foreach (Report::byCategory($from, $to) as $r) { $cat[] = array($r['name'], (int)$r['qty'], (float)$r['revenue']); }
        $sheets['Por categoría'] = $cat;

        $top = array(array('Platillo', 'Unidades', 'Ventas'));
        foreach (Report::topProducts($from, $to, 50) as $r) { $top[] = array($r['name'], (int)$r['qty'], (float)$r['revenue']); }
        $sheets['Más vendidos'] = $top;

        $orders = array(array('Código', 'Fecha', 'Modo', 'Estado', 'Mesa', 'Subtotal', 'Descuento', 'Propina', 'Total', 'Pago'));
        foreach (Order::recent(200, array('from' => $from, 'to' => $to)) as $o) {
            $orders[] = array($o['code'], $o['placed_at'], Order::modeLabel($o['mode']),
                isset(Order::$statusLabels[$o['status']]) ? Order::$statusLabels[$o['status']] : $o['status'],
                (string)$o['table_name'], (float)$o['subtotal'], (float)$o['discount'],
                (float)$o['tip'], (float)$o['total'], $o['payment_method']);
        }
        $sheets['Pedidos'] = $orders;

        return Xlsx::response($sheets, 'reporte-' . $from . '-' . $to . '.xlsx');
    }

    public function pdf()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        list($from, $to) = $this->range();
        $cur = Settings::get('currency', 'Q');

        $summary  = Report::summary($from, $to);
        $byCat    = Report::byCategory($from, $to);
        $top      = Report::topProducts($from, $to, 10);
        $timings  = Report::timings($from, $to);
        $byDay    = Report::byDay($from, $to);

        $pdf = new Pdf('A4');
        $pdf->setTitle('Reporte ' . Settings::get('name'));
        $gold = '#B08A3E';

        $pdf->setFillColor('#0C0B09');
        $pdf->rect(0, 0, 210, 46, 'F');
        $pdf->setFillColor('#D8B26E');
        $pdf->setFont('Times', 'B', 22);
        $pdf->text(18, 22, Settings::get('name'));
        $pdf->setFillColor('#F4EDE1');
        $pdf->setFont('Helvetica', '', 9);
        $pdf->text(18, 30, 'Reporte de ventas · ' . $from . ' a ' . $to);
        $pdf->text(18, 36, 'Generado el ' . date('d/m/Y H:i'));

        $y = 60;
        $pdf->setFillColor('#111111');
        $pdf->setFont('Helvetica', 'B', 11);
        $pdf->text(18, $y, 'Resumen del periodo');
        $y += 8;

        $cards = array(
            array('Ventas', Money::format($summary['revenue'], $cur)),
            array('Pedidos', (string)$summary['orders']),
            array('Ticket promedio', Money::format($summary['ticket'], $cur)),
            array('Propinas', Money::format($summary['tips'], $cur)),
        );
        $cw = 43;
        foreach ($cards as $i => $c) {
            $x = 18 + $i * ($cw + 2);
            $pdf->setFillColor('#F6F1E7');
            $pdf->rect($x, $y, $cw, 22, 'F', 3);
            $pdf->setFillColor('#7A6438');
            $pdf->setFont('Helvetica', '', 7);
            $pdf->text($x, $y + 7, mb_strtoupper($c[0]), 'C', $cw);
            $pdf->setFillColor('#111111');
            $pdf->setFont('Helvetica', 'B', 12);
            $pdf->text($x, $y + 16, $c[1], 'C', $cw);
        }
        $y += 32;

        $y = $this->pdfTable($pdf, $y, 'Ventas por categoría', array('Categoría', 'Unidades', 'Ventas'),
            array_map(function ($r) use ($cur) {
                return array($r['name'], (string)(int)$r['qty'], Money::format($r['revenue'], $cur));
            }, $byCat), array(100, 30, 44));

        $y = $this->pdfTable($pdf, $y + 6, 'Los diez más vendidos', array('Platillo', 'Unidades', 'Ventas'),
            array_map(function ($r) use ($cur) {
                return array($r['name'], (string)(int)$r['qty'], Money::format($r['revenue'], $cur));
            }, $top), array(100, 30, 44));

        // Gráfica de barras sencilla, dibujada a mano.
        if ($byDay) {
            if ($y > 210) { $pdf->addPage(); $y = 25; }
            $pdf->setFillColor('#111111');
            $pdf->setFont('Helvetica', 'B', 11);
            $pdf->text(18, $y, 'Ventas por día');
            $y += 6;
            $max = 0;
            foreach ($byDay as $d) { $max = max($max, (float)$d['revenue']); }
            $chartH = 40;
            $chartW = 174;
            $n = count($byDay);
            $bw = min(8, ($chartW / max(1, $n)) - 1.4);
            $pdf->setDrawColor('#DDD6C6');
            $pdf->setLineWidth(0.2);
            $pdf->line(18, $y + $chartH, 192, $y + $chartH);
            foreach ($byDay as $i => $d) {
                $h = $max > 0 ? ($chartH * ((float)$d['revenue'] / $max)) : 0;
                $x = 18 + $i * ($chartW / max(1, $n));
                $pdf->setFillColor($gold);
                $pdf->rect($x, $y + $chartH - $h, $bw, max(0.4, $h), 'F');
            }
            $pdf->setFillColor('#7A6438');
            $pdf->setFont('Helvetica', '', 6);
            $pdf->text(18, $y + $chartH + 4, substr($byDay[0]['d'], 5));
            $pdf->text(18, $y + $chartH + 4, substr($byDay[$n - 1]['d'], 5), 'R', $chartW);
            $y += $chartH + 12;
        }

        if ($timings['to_ready'] !== null) {
            $pdf->setFillColor('#111111');
            $pdf->setFont('Helvetica', '', 9);
            $pdf->text(18, $y, 'Tiempo medio hasta que sale de cocina: ' . $timings['to_ready'] . ' min · Hasta el cobro: '
                . ($timings['to_close'] !== null ? $timings['to_close'] . ' min' : 'sin datos'));
        }

        return $pdf->response('reporte-' . $from . '-' . $to . '.pdf', true);
    }

    private function pdfTable(Pdf $pdf, $y, $title, array $head, array $rows, array $widths)
    {
        if ($y > 250) { $pdf->addPage(); $y = 25; }
        $pdf->setFillColor('#111111');
        $pdf->setFont('Helvetica', 'B', 11);
        $pdf->text(18, $y, $title);
        $y += 7;

        $pdf->setFillColor('#7A6438');
        $pdf->setFont('Helvetica', 'B', 7.5);
        $x = 18;
        foreach ($head as $i => $h) {
            $pdf->text($x, $y, mb_strtoupper($h), $i === 0 ? 'L' : 'R', $widths[$i]);
            $x += $widths[$i];
        }
        $y += 2;
        $pdf->setDrawColor('#E3DCCB');
        $pdf->setLineWidth(0.2);
        $pdf->line(18, $y, 192, $y);
        $y += 4.5;

        $pdf->setFont('Helvetica', '', 8.5);
        foreach ($rows as $r) {
            if ($y > 278) { $pdf->addPage(); $y = 25; $pdf->setFont('Helvetica', '', 8.5); }
            $x = 18;
            $pdf->setFillColor('#222222');
            foreach ($r as $i => $cell) {
                $text = $i === 0 ? mb_substr((string)$cell, 0, 46) : (string)$cell;
                $pdf->text($x, $y, $text, $i === 0 ? 'L' : 'R', $widths[$i]);
                $x += $widths[$i];
            }
            $y += 5.2;
        }
        if (!$rows) {
            $pdf->setFillColor('#999999');
            $pdf->text(18, $y, 'Sin datos en el periodo.');
            $y += 5.2;
        }
        return $y;
    }
}
