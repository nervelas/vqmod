<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Panel;

use MenuGold\Core\Request;
use MenuGold\Models\Order;
use MenuGold\Models\Report;
use MenuGold\Vendor\Pdf\Pdf;
use MenuGold\Vendor\Xlsx\XlsxWriter;

/**
 * Reportes de ventas con graficas y exportacion a PDF y Excel.
 */
class Reportes extends Base
{
    /** @return array{0:string,1:string} */
    private function rango(): array
    {
        $desde = Request::date('desde') ?: date('Y-m-01');
        $hasta = Request::date('hasta') ?: date('Y-m-d');
        if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];
        return [$desde, $hasta];
    }

    private function todo(string $desde, string $hasta): array
    {
        $rep = new Report($this->rid);
        return [
            'total'      => $rep->totalRango($desde, $hasta),
            'porDia'     => $rep->ventasPorDia($desde, $hasta),
            'porHora'    => $rep->ventasPorHora($desde, $hasta),
            'topMas'     => $rep->topProductos($desde, $hasta, 10, 'DESC'),
            'topMenos'   => $rep->topProductos($desde, $hasta, 10, 'ASC'),
            'categorias' => $rep->ventasPorCategoria($desde, $hasta),
            'modos'      => $rep->ventasPorModo($desde, $hasta),
            'meseros'    => $rep->ventasPorMesero($desde, $hasta),
            'prep'       => $rep->tiempoPreparacion($desde, $hasta),
            'anulados'   => $rep->anulados($desde, $hasta),
        ];
    }

    public function index(): void
    {
        $this->exigir('reportes');
        [$desde, $hasta] = $this->rango();
        $this->panel('panel/reportes', $this->todo($desde, $hasta) + [
            'desde' => $desde, 'hasta' => $hasta,
        ]);
    }

    public function datos(): void
    {
        $this->exigir('reportes');
        [$desde, $hasta] = $this->rango();
        $this->ok($this->todo($desde, $hasta));
    }

    // =================================================================
    public function excel(): void
    {
        $this->exigir('reportes');
        [$desde, $hasta] = $this->rango();
        $d = $this->todo($desde, $hasta);
        $s = (string)$this->r['simbolo'];

        $x = new XlsxWriter();
        $x->setAutor((string)$this->r['nombre']);

        $x->hoja('Resumen', [
            ['Concepto', 'Valor'],
            ['Restaurante', (string)$this->r['nombre']],
            ['Periodo', dt($desde, 'd/m/Y') . ' al ' . dt($hasta, 'd/m/Y')],
            ['Pedidos vendidos', (int)$d['total']['pedidos']],
            ['Ventas totales', (float)$d['total']['total']],
            ['Ticket promedio', (float)$d['total']['ticket']],
            ['Propinas', (float)$d['total']['propinas']],
            ['Descuentos', (float)$d['total']['descuentos']],
            ['Impuestos', (float)$d['total']['impuestos']],
            ['Envíos', (float)$d['total']['envios']],
            ['Tiempo promedio de preparación (min)', round((float)$d['prep']['promedio'], 1)],
            ['Pedidos anulados', count($d['anulados'])],
        ], [38, 20], ['texto', 'moneda']);

        $filas = [['Fecha', 'Pedidos', 'Ventas']];
        foreach ($d['porDia'] as $f) $filas[] = [dt((string)$f['dia'], 'd/m/Y'), (int)$f['pedidos'], (float)$f['total']];
        $x->hoja('Ventas por día', $filas, [16, 12, 16], ['texto', 'entero', 'moneda']);

        $filas = [['Platillo', 'Unidades', 'Ventas']];
        foreach ($d['topMas'] as $f) $filas[] = [(string)$f['nombre'], (int)$f['unidades'], (float)$f['total']];
        $x->hoja('Más vendidos', $filas, [42, 12, 16], ['texto', 'entero', 'moneda']);

        $filas = [['Platillo', 'Unidades', 'Ventas']];
        foreach ($d['topMenos'] as $f) $filas[] = [(string)$f['nombre'], (int)$f['unidades'], (float)$f['total']];
        $x->hoja('Menos vendidos', $filas, [42, 12, 16], ['texto', 'entero', 'moneda']);

        $filas = [['Categoría', 'Unidades', 'Ventas']];
        foreach ($d['categorias'] as $f) $filas[] = [(string)$f['categoria'], (int)$f['unidades'], (float)$f['total']];
        $x->hoja('Por categoría', $filas, [30, 12, 16], ['texto', 'entero', 'moneda']);

        $filas = [['Modo', 'Pedidos', 'Ventas']];
        foreach ($d['modos'] as $f) $filas[] = [Order::etiquetaModo((string)$f['modo']), (int)$f['pedidos'], (float)$f['total']];
        $x->hoja('Por modo', $filas, [22, 12, 16], ['texto', 'entero', 'moneda']);

        $filas = [['Mesero', 'Pedidos', 'Ventas', 'Ticket promedio']];
        foreach ($d['meseros'] as $f) $filas[] = [(string)$f['mesero'], (int)$f['pedidos'], (float)$f['total'], (float)$f['ticket']];
        $x->hoja('Por mesero', $filas, [28, 12, 16, 18], ['texto', 'entero', 'moneda', 'moneda']);

        $filas = [['Hora', 'Pedidos']];
        foreach ($d['porHora'] as $h => $n) $filas[] = [str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00', $n];
        $x->hoja('Horas pico', $filas, [12, 12], ['texto', 'entero']);

        $filas = [['Código', 'Fecha', 'Total', 'Motivo', 'Usuario']];
        foreach ($d['anulados'] as $f) {
            $filas[] = [(string)$f['codigo'], dt((string)$f['creado']), (float)$f['total'],
                        (string)$f['motivo_anulacion'], (string)($f['usuario'] ?? '')];
        }
        $x->hoja('Anulados', $filas, [14, 18, 14, 40, 22], ['texto', 'texto', 'moneda', 'texto', 'texto']);

        $this->download($x->output(),
            'reporte-' . str_slug((string)$this->r['nombre']) . '-' . $desde . '-a-' . $hasta . '.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    // =================================================================
    public function pdf(): void
    {
        $this->exigir('reportes');
        [$desde, $hasta] = $this->rango();
        $d = $this->todo($desde, $hasta);
        $s = (string)$this->r['simbolo'];

        $pdf = new Pdf('LETTER', 'P', 40);
        $pdf->meta('titulo', 'Reporte de ventas · ' . (string)$this->r['nombre']);
        $pdf->addPage();
        $w = $pdf->anchoUtil();
        $x = $pdf->margen();
        $y = 44.0;

        // Encabezado
        $pdf->setRelleno('#141414');
        $pdf->rect(0, 0, $pdf->ancho(), 92, 'F');
        $pdf->setColorTexto('#D4AF37');
        $pdf->setFuente('times-b', 19);
        $pdf->cell($x, 26, $w, (string)$this->r['nombre'], 'L', 24);
        $pdf->setColorTexto('#F7F3EA');
        $pdf->setFuente('helvetica', 10);
        $pdf->cell($x, 52, $w, 'Reporte de ventas · ' . dt($desde, 'd/m/Y') . ' al ' . dt($hasta, 'd/m/Y'), 'L', 14);
        $pdf->setFuente('helvetica', 8);
        $pdf->setColorTexto('#A9A398');
        $pdf->cell($x, 68, $w, 'Generado el ' . dt(date('Y-m-d H:i:s')) . ' por ' . \MenuGold\Core\Auth::nombre(), 'L', 12);
        $y = 116.0;

        // Tarjetas de resumen
        $kpis = [
            ['Ventas totales', $s . number_format((float)$d['total']['total'], 2)],
            ['Pedidos', (string)(int)$d['total']['pedidos']],
            ['Ticket promedio', $s . number_format((float)$d['total']['ticket'], 2)],
            ['Propinas', $s . number_format((float)$d['total']['propinas'], 2)],
        ];
        $cw = ($w - 3 * 8) / 4;
        foreach ($kpis as $i => $k) {
            $kx = $x + $i * ($cw + 8);
            $pdf->setRelleno('#F7F3EA');
            $pdf->setTrazo('#E0DACD', 0.6);
            $pdf->roundRect($kx, $y, $cw, 52, 7, 'FD');
            $pdf->setColorTexto('#8A8578');
            $pdf->setFuente('helvetica', 7.5);
            $pdf->cell($kx + 8, $y + 9, $cw - 16, $k[0], 'L', 10);
            $pdf->setColorTexto('#141414');
            $pdf->setFuente('helvetica-b', 13);
            $pdf->cell($kx + 8, $y + 24, $cw - 16, $k[1], 'L', 16);
        }
        $y += 70;

        $tabla = function (string $titulo, array $cabeceras, array $filas, array $anchos, array $alineado) use ($pdf, $x, $w, &$y): void {
            if (!$filas) return;
            // Salta de página si no cabe el título y al menos tres filas
            if ($y + 90 > $pdf->alto() - $pdf->margen()) { $pdf->addPage(); $y = $pdf->margen(); }
            $pdf->setColorTexto('#141414');
            $pdf->setFuente('helvetica-b', 11);
            $y += $pdf->cell($x, $y, $w, $titulo, 'L', 18);
            $pdf->setTrazo('#D4AF37', 1);
            $pdf->line($x, $y, $x + 44, $y);
            $y += 8;

            $pdf->setRelleno('#F2EEE4');
            $pdf->rect($x, $y, $w, 18, 'F');
            $pdf->setFuente('helvetica-b', 8);
            $pdf->setColorTexto('#5F5B53');
            $cx = $x;
            foreach ($cabeceras as $i => $c) {
                $pdf->cell($cx + 5, $y, $anchos[$i] - 10, $c, $alineado[$i], 18);
                $cx += $anchos[$i];
            }
            $y += 18;

            $pdf->setFuente('helvetica', 8.5);
            $pdf->setColorTexto('#2A2822');
            foreach ($filas as $n => $fila) {
                if ($y + 18 > $pdf->alto() - $pdf->margen()) { $pdf->addPage(); $y = $pdf->margen(); }
                if ($n % 2 === 1) {
                    $pdf->setRelleno('#FAF8F3');
                    $pdf->rect($x, $y, $w, 16, 'F');
                    $pdf->setColorTexto('#2A2822');
                }
                $cx = $x;
                foreach ($fila as $i => $celda) {
                    $pdf->cell($cx + 5, $y, $anchos[$i] - 10, $pdf->truncar((string)$celda, $anchos[$i] - 10), $alineado[$i], 16);
                    $cx += $anchos[$i];
                }
                $pdf->setTrazo('#EDE8DC', 0.4);
                $pdf->line($x, $y + 16, $x + $w, $y + 16);
                $y += 16;
            }
            $y += 18;
        };

        $f = [];
        foreach ($d['topMas'] as $t) $f[] = [(string)$t['nombre'], (int)$t['unidades'], $s . number_format((float)$t['total'], 2)];
        $tabla('Platillos más vendidos', ['Platillo', 'Unidades', 'Ventas'], $f, [$w * 0.6, $w * 0.18, $w * 0.22], ['L', 'R', 'R']);

        $f = [];
        foreach ($d['categorias'] as $t) $f[] = [(string)$t['categoria'], (int)$t['unidades'], $s . number_format((float)$t['total'], 2)];
        $tabla('Ventas por categoría', ['Categoría', 'Unidades', 'Ventas'], $f, [$w * 0.6, $w * 0.18, $w * 0.22], ['L', 'R', 'R']);

        $f = [];
        foreach ($d['modos'] as $t) $f[] = [Order::etiquetaModo((string)$t['modo']), (int)$t['pedidos'], $s . number_format((float)$t['total'], 2)];
        $tabla('Ventas por modo de pedido', ['Modo', 'Pedidos', 'Ventas'], $f, [$w * 0.6, $w * 0.18, $w * 0.22], ['L', 'R', 'R']);

        $f = [];
        foreach ($d['meseros'] as $t) {
            $f[] = [(string)$t['mesero'], (int)$t['pedidos'], $s . number_format((float)$t['total'], 2), $s . number_format((float)$t['ticket'], 2)];
        }
        $tabla('Ventas por mesero', ['Mesero', 'Pedidos', 'Ventas', 'Ticket'], $f, [$w * 0.44, $w * 0.16, $w * 0.20, $w * 0.20], ['L', 'R', 'R', 'R']);

        $f = [];
        foreach ($d['porDia'] as $t) $f[] = [dt((string)$t['dia'], 'd/m/Y'), (int)$t['pedidos'], $s . number_format((float)$t['total'], 2)];
        $tabla('Ventas por día', ['Fecha', 'Pedidos', 'Ventas'], $f, [$w * 0.6, $w * 0.18, $w * 0.22], ['L', 'R', 'R']);

        if ($d['anulados']) {
            $f = [];
            foreach ($d['anulados'] as $t) {
                $f[] = [(string)$t['codigo'], dt((string)$t['creado'], 'd/m/Y H:i'),
                        $s . number_format((float)$t['total'], 2), (string)$t['motivo_anulacion']];
            }
            $tabla('Pedidos anulados', ['Código', 'Fecha', 'Total', 'Motivo'],
                $f, [$w * 0.16, $w * 0.22, $w * 0.16, $w * 0.46], ['L', 'L', 'R', 'L']);
        }

        $this->inline($pdf->output(),
            'reporte-' . str_slug((string)$this->r['nombre']) . '-' . $desde . '.pdf', 'application/pdf');
    }
}
