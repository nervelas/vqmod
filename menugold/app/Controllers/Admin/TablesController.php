<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Audit;
use MenuGold\Core\DB;
use MenuGold\Core\Pdf;
use MenuGold\Core\Qr;
use MenuGold\Core\Session;
use MenuGold\Core\Url;
use MenuGold\Models\Restaurant;
use MenuGold\Models\TableModel;

class TablesController extends BaseController
{
    protected $ability = 'tables';

    public function index()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        return $this->view('admin/tables/index', array(
            'tables' => TableModel::forRestaurant($this->rid(), false),
            'usage'  => Restaurant::usage($this->rid()),
        ));
    }

    public function edit(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $id = $params['id'] === 'nueva' ? 0 : (int)$params['id'];
        $table = $id > 0 ? $this->own('tables', $id) : null;
        if ($id > 0 && !$table) { return $this->notFound('Esa mesa no existe.'); }

        if (!$this->request->isPost()) {
            return $this->view('admin/tables/edit', array('table' => $table));
        }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $name = $this->request->str('name', '');
        if ($name === '') {
            Session::flash('error', 'La mesa necesita un nombre.');
            return $this->redirect('/panel/mesas');
        }
        if (!$table && Restaurant::limitReached($this->rid(), 'tables')) {
            Session::flash('error', 'Alcanzaste el límite de mesas de tu plan.');
            return $this->redirect('/panel/mesas');
        }

        $data = array(
            'name'      => mb_substr($name, 0, 60),
            'zone'      => $this->request->str('zone', ''),
            'seats'     => max(1, min(60, $this->request->int('seats', 4))),
            'is_active' => $this->request->bool('is_active') ? 1 : 0,
        );

        if ($table) {
            // Regenerar el token invalida los QR impresos: se pide explícitamente.
            if ($this->request->bool('regenerate')) {
                $data['qr_token'] = TableModel::newToken();
            }
            DB::update('tables', $data, 'id = :id AND restaurant_id = :r', array('id' => $id, 'r' => $this->rid()));
            Audit::log('table_updated', 'table', $id, array('name' => $data['name']));
        } else {
            $data['restaurant_id'] = $this->rid();
            $data['qr_token'] = TableModel::newToken();
            $data['sort'] = 1 + (int)DB::value('SELECT COALESCE(MAX(sort),0) FROM tables WHERE restaurant_id = :r', array('r' => $this->rid()), 0);
            $id = DB::insert('tables', $data);
            Audit::log('table_created', 'table', $id, array('name' => $data['name']));
        }
        Session::flash('success', 'Mesa guardada.');
        return $this->redirect('/panel/mesas');
    }

    public function delete(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $t = $this->own('tables', (int)$params['id']);
        if (!$t) { return $this->notFound('Esa mesa no existe.'); }
        DB::delete('tables', 'id = :id AND restaurant_id = :r', array('id' => (int)$t['id'], 'r' => $this->rid()));
        Audit::log('table_deleted', 'table', (int)$t['id'], array('name' => $t['name']));
        Session::flash('success', 'Mesa eliminada. Su código QR dejó de funcionar.');
        return $this->redirect('/panel/mesas');
    }

    /** Crea varias mesas de una sola vez. */
    public function bulk()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $bad = $this->guardCsrf();
        if ($bad) { return $bad; }

        $count  = max(1, min(60, $this->request->int('count', 10)));
        $prefix = $this->request->str('prefix', 'Mesa');
        $zone   = $this->request->str('zone', '');
        $seats  = max(1, min(60, $this->request->int('seats', 4)));
        $start  = 1 + (int)DB::value('SELECT COALESCE(MAX(sort),0) FROM tables WHERE restaurant_id = :r', array('r' => $this->rid()), 0);

        $made = 0;
        for ($i = 0; $i < $count; $i++) {
            if (Restaurant::limitReached($this->rid(), 'tables')) { break; }
            DB::insert('tables', array(
                'restaurant_id' => $this->rid(),
                'name'     => trim($prefix . ' ' . ($start + $i)),
                'zone'     => $zone,
                'seats'    => $seats,
                'qr_token' => TableModel::newToken(),
                'sort'     => $start + $i,
            ));
            $made++;
        }
        Audit::log('tables_bulk', 'table', 0, array('created' => $made));
        Session::flash($made > 0 ? 'success' : 'error',
            $made > 0 ? 'Se crearon ' . $made . ' mesas con su código QR.' : 'No se pudo crear ninguna mesa: revisa el límite de tu plan.');
        return $this->redirect('/panel/mesas');
    }

    /** Hoja de QR en pantalla, lista para imprimir desde el navegador. */
    public function qrSheet()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $tables = TableModel::forRestaurant($this->rid());
        $codes = array();
        foreach ($tables as $t) {
            $codes[] = array(
                'table' => $t,
                'url'   => TableModel::url($this->restaurant, $t),
                'png'   => Qr::dataUri(TableModel::url($this->restaurant, $t), 6),
            );
        }
        return $this->view('admin/tables/qr-sheet', array(
            'codes'      => $codes,
            'generalUrl' => TableModel::generalUrl($this->restaurant),
            'generalPng' => Qr::dataUri(TableModel::generalUrl($this->restaurant), 7),
        ));
    }

    /**
     * PDF imprimible con los códigos QR, en tres formatos:
     * tarjeta de mesa (tent card), etiqueta y tarjeta de bolsillo.
     */
    public function qrPdf()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        $format = $this->request->str('formato', 'tent');
        $tables = TableModel::forRestaurant($this->rid());
        $onlyId = $this->request->int('mesa', 0);
        if ($onlyId > 0) {
            $tables = array_values(array_filter($tables, function ($t) use ($onlyId) { return (int)$t['id'] === $onlyId; }));
        }
        if (!$tables) {
            Session::flash('error', 'Primero crea al menos una mesa.');
            return $this->redirect('/panel/mesas');
        }

        $pdf = new Pdf('A4');
        $pdf->setTitle('Códigos QR · ' . $this->restaurant['name']);
        $gold = $this->restaurant['primary_color'] !== '' ? $this->restaurant['primary_color'] : '#D8B26E';
        $logoFile = $this->restaurant['logo'] !== '' ? \MenuGold\Core\Image::file($this->restaurant['logo'], 480) : null;

        if ($format === 'tent') {
            foreach ($tables as $t) {
                $this->tentCard($pdf, $t, $gold, $logoFile);
                $pdf->addPage();
            }
        } elseif ($format === 'sticker') {
            $this->grid($pdf, $tables, $gold, 3, 4, 60, $logoFile);
        } else {
            $this->grid($pdf, $tables, $gold, 2, 3, 85, $logoFile);
        }

        Audit::log('qr_pdf', 'table', 0, array('format' => $format, 'count' => count($tables)));
        return $pdf->response('qr-' . $this->restaurant['slug'] . '-' . $format . '.pdf', true);
    }

    /** Tarjeta de mesa a dos caras que se dobla por la mitad. */
    private function tentCard(Pdf $pdf, array $table, $gold, $logoFile)
    {
        $url = TableModel::url($this->restaurant, $table);
        $matrix = Qr::matrix($url);

        $pdf->setFillColor('#0C0B09');
        $pdf->rect(0, 0, 210, 297, 'F');
        $pdf->setDrawColor($gold);
        $pdf->setLineWidth(0.2);
        $pdf->line(15, 148.5, 195, 148.5);   // línea de doblez

        // Se imprimen dos caras idénticas, una invertida al doblar.
        foreach (array(0, 148.5) as $offset) {
            $y = $offset + 20;
            if ($logoFile) {
                $pdf->image($logoFile, 90, $y, 30, 30);
                $y += 36;
            }
            $pdf->setFillColor($gold);
            $pdf->setFont('Times', 'B', 24);
            $pdf->text(15, $y, $this->restaurant['name'], 'C', 180);
            $y += 9;

            $pdf->setFillColor('#F4EDE1');
            $pdf->setFont('Helvetica', '', 9);
            $pdf->text(15, $y, 'ESCANEA Y PIDE DESDE TU MESA', 'C', 180);
            $y += 8;

            $qrSize = 62;
            $pdf->setFillColor('#FFFFFF');
            $pdf->rect((210 - ($qrSize + 8)) / 2, $y, $qrSize + 8, $qrSize + 8, 'F', 4);
            $pdf->qr($matrix, (210 - $qrSize) / 2, $y + 4, $qrSize, '#000000', 2);
            $y += $qrSize + 16;

            $pdf->setFillColor($gold);
            $pdf->setFont('Times', 'B', 30);
            $pdf->text(15, $y, $table['name'], 'C', 180);
            $y += 8;
            $pdf->setFillColor('#8A8378');
            $pdf->setFont('Helvetica', '', 7);
            $pdf->text(15, $y, 'Menú digital MenúGold', 'C', 180);
        }
    }

    /** Cuadrícula de etiquetas o tarjetas. */
    private function grid(Pdf $pdf, array $tables, $gold, $cols, $rows, $cell, $logoFile)
    {
        $marginX = (210 - $cols * $cell) / 2;
        $marginY = 18;
        $gapY = (297 - $marginY * 2 - $rows * $cell) / max(1, $rows - 1);
        $perPage = $cols * $rows;
        $i = 0;

        foreach ($tables as $t) {
            if ($i > 0 && $i % $perPage === 0) { $pdf->addPage(); }
            $slot = $i % $perPage;
            $col = $slot % $cols;
            $row = intdiv($slot, $cols);
            $x = $marginX + $col * $cell;
            $y = $marginY + $row * ($cell + $gapY);

            $pdf->setFillColor('#0C0B09');
            $pdf->rect($x, $y, $cell - 4, $cell - 4, 'F', 6);
            $pdf->setDrawColor($gold);
            $pdf->setLineWidth(0.25);
            $pdf->rect($x, $y, $cell - 4, $cell - 4, 'D', 6);

            $inner = $cell - 4;
            $pdf->setFillColor($gold);
            $pdf->setFont('Times', 'B', $cell > 70 ? 13 : 10);
            $pdf->text($x, $y + 9, $this->restaurant['name'], 'C', $inner);

            $qrSize = $inner - 26;
            $pdf->setFillColor('#FFFFFF');
            $pdf->rect($x + ($inner - $qrSize - 4) / 2, $y + 13, $qrSize + 4, $qrSize + 4, 'F', 3);
            $pdf->qr(Qr::matrix(TableModel::url($this->restaurant, $t)), $x + ($inner - $qrSize) / 2, $y + 15, $qrSize, '#000000', 2);

            $pdf->setFillColor('#F4EDE1');
            $pdf->setFont('Helvetica', 'B', $cell > 70 ? 11 : 9);
            $pdf->text($x, $y + $inner - 4, $t['name'], 'C', $inner);
            $i++;
        }
    }
}
