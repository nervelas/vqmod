<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Money;
use MenuGold\Core\Pdf;
use MenuGold\Core\Xlsx;
use MenuGold\Models\Order;
use MenuGold\Models\Report;

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
        $rid = $this->rid();

        return $this->view('admin/reports', array(
            'from'     => $from,
            'to'       => $to,
            'summary'  => Report::summary($rid, $from, $to),
            'byDay'    => Report::byDay($rid, $from, $to),
            'byHour'   => Report::byHour($rid, $from, $to),
            'byCat'    => Report::byCategory($rid, $from, $to),
            'byMode'   => Report::byMode($rid, $from, $to),
            'byWaiter' => Report::byWaiter($rid, $from, $to),
            'topUp'    => Report::topProducts($rid, $from, $to, 10, false),
            'topDown'  => Report::topProducts($rid, $from, $to, 10, true),
            'timings'  => Report::timings($rid, $from, $to),
        ));
    }

    public function data()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        list($from, $to) = $this->range();
        return $this->ok(array(
            'summary' => Report::summary($this->rid(), $from, $to),
            'byDay'   => Report::byDay($this->rid(), $from, $to),
        ));
    }

    public function excel()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        list($from, $to) = $this->range();
        $rid = $this->rid();

        $summary = Report::summary($rid, $from, $to);
        $sheets = array();

        $sheets['Resumen'] = array(
            array('Restaurante', $this->restaurant['name']),
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
        foreach (Report::byDay($rid, $from, $to) as $r) { $day[] = array($r['d'], (int)$r['orders'], (float)$r['revenue']); }
        $sheets['Por día'] = $day;

        $cat = array(array('Categoría', 'Unidades', 'Ventas'));
        foreach (Report::byCategory($rid, $from, $to) as $r) { $cat[] = array($r['name'], (int)$r['qty'], (float)$r['revenue']); }
        $sheets['Por categoría'] = $cat;

        $top = array(array('Platillo', 'Unidades', 'Ventas'));
        foreach (Report::topProducts($rid, $from, $to, 50) as $r) { $top[] = array($r['name'], (int)$r['qty'], (float)$r['revenue']); }
        $sheets['Más vendidos'] = $top;

        $orders = array(array('Código', 'Fecha', 'Modo', 'Estado', 'Mesa', 'Subtotal', 'Descuento', 'Propina', 'Total', 'Pago'));
        foreach (Order::recent($rid, 200, array('from' => $from, 'to' => $to)) as $o) {
            $orders[] = array($o['code'], $o['placed_at'], Order::modeLabel($o['mode']),
                isset(Order::$statusLabels[$o['status']]) ? Order::$statusLabels[$o['status']] : $o['status'],
                (string)$o['table_name'], (float)$o['subtotal'], (float)$o['discount'],
                (float)$o['tip'], (float)$o['total'], $o['payment_method']);
        }
        $sheets['Pedidos'] = $orders;

        return Xlsx::response($sheets, 'reporte-' . $this->restaurant['slug'] . '-' . $from . '-' . $to . '.xlsx');
    }

    public function pdf()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        list($from, $to) = $this->range();
        $rid = $this->rid();
        $cur = $this->restaurant['currency'];

        $summary  = Report::summary($rid, $from, $to);
        $byCat    = Report::byCategory($rid, $from, $to);
        $top      = Report::topProducts($rid, $from, $to, 10);
        $timings  = Report::timings($rid, $from, $to);
        $byDay    = Report::byDay($rid, $from, $to);

        $pdf = new Pdf('A4');
        $pdf->setTitle('Reporte ' . $this->restaurant['name']);
        $gold = '#B08A3E';

        $pdf->setFillColor('#0C0B09');
        $pdf->rect(0, 0, 210, 46, 'F');
        $pdf->setFillColor('#D8B26E');
        $pdf->setFont('Times', 'B', 22);
        $pdf->text(18, 22, $this->restaurant['name']);
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
            $pdf->text(18, $y, 'Tiempo medio de preparación: ' . $timings['to_ready'] . ' min · Entrega: '
                . ($timings['to_deliver'] !== null ? $timings['to_deliver'] . ' min' : 'sin datos'));
        }

        return $pdf->response('reporte-' . $this->restaurant['slug'] . '.pdf', true);
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
