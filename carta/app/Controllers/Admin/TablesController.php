<?php
namespace MenuGold\Controllers\Admin;

use MenuGold\Core\Audit;
use MenuGold\Core\DB;
use MenuGold\Core\Image;
use MenuGold\Core\Pdf;
use MenuGold\Core\Qr;
use MenuGold\Core\Response;
use MenuGold\Core\Session;
use MenuGold\Models\Settings;
use MenuGold\Models\TableModel;

class TablesController extends BaseController
{
    protected $ability = 'tables';

    public function index()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        if ($this->request->isPost()) {
            $bad = $this->guardCsrf();
            if ($bad) { return $bad; }
            return $this->bulk();
        }

        $tables = TableModel::all(false);
        $codes = array();
        foreach ($tables as $t) {
            $codes[(int)$t['id']] = Qr::dataUri(TableModel::url($t), 5);
        }
        return $this->view('admin/tables/index', array(
            'tables'     => $tables,
            'codes'      => $codes,
            'generalUrl' => TableModel::generalUrl(),
            'generalPng' => Qr::dataUri(TableModel::generalUrl(), 7),
        ));
    }

    public function edit(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $id = $params['id'] === 'nueva' ? 0 : (int)$params['id'];
        $table = $id > 0 ? $this->row('mg_tables', $id) : null;
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

        $data = array(
            'name'      => mb_substr($name, 0, 80),
            'zone'      => mb_substr($this->request->str('zone', ''), 0, 80),
            'seats'     => max(1, min(60, $this->request->int('seats', 4))),
            'is_active' => $this->request->bool('is_active') ? 1 : 0,
        );

        if ($table) {
            // Regenerar el token invalida los QR ya impresos: se pide a propósito.
            if ($this->request->bool('regenerate')) { $data['qr_token'] = TableModel::newToken(); }
            DB::update('mg_tables', $data, 'id = :id', array('id' => $id));
            Audit::log('table_updated', 'table', $id, array('name' => $data['name']));
        } else {
            $data['qr_token'] = TableModel::newToken();
            $data['sort'] = 1 + (int)DB::value('SELECT COALESCE(MAX(sort),0) FROM mg_tables', array(), 0);
            $id = DB::insert('mg_tables', $data);
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

        $t = $this->row('mg_tables', (int)$params['id']);
        if (!$t) { return $this->notFound('Esa mesa no existe.'); }
        DB::delete('mg_tables', 'id = :id', array('id' => (int)$t['id']));
        Audit::log('table_deleted', 'table', (int)$t['id'], array('name' => $t['name']));
        Session::flash('success', 'Mesa eliminada. Su código QR dejó de funcionar.');
        return $this->redirect('/panel/mesas');
    }

    /** Crea varias mesas de una sola vez. */
    public function bulk()
    {
        $count  = max(1, min(80, $this->request->int('count', 10)));
        $prefix = $this->request->str('prefix', 'Mesa');
        $zone   = $this->request->str('zone', '');
        $seats  = max(1, min(60, $this->request->int('seats', 4)));
        $start  = 1 + (int)DB::value('SELECT COALESCE(MAX(sort),0) FROM mg_tables', array(), 0);

        for ($i = 0; $i < $count; $i++) {
            DB::insert('mg_tables', array(
                'name'     => trim($prefix . ' ' . ($start + $i)),
                'zone'     => mb_substr($zone, 0, 80),
                'seats'    => $seats,
                'qr_token' => TableModel::newToken(),
                'sort'     => $start + $i,
            ));
        }
        Audit::log('tables_bulk', 'table', 0, array('created' => $count));
        Session::flash('success', 'Se crearon ' . $count . ' mesas con su código QR.');
        return $this->redirect('/panel/mesas');
    }

    public function qrPng(array $params)
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }
        $t = $this->row('mg_tables', (int)$params['id']);
        if (!$t) { return $this->notFound('Esa mesa no existe.'); }
        $png = Qr::png(TableModel::url($t), max(4, min(14, $this->request->int('s', 9))));
        return Response::make($png, 200, array('Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=600'));
    }

    /**
     * PDF imprimible con los códigos QR, en tres formatos:
     * tarjeta de mesa que se dobla, tarjeta de bolsillo y etiqueta adhesiva.
     */
    public function qrPdf()
    {
        $stop = $this->guard();
        if ($stop) { return $stop; }

        $format = $this->request->str('formato', 'tent');
        $tables = TableModel::all();
        $onlyId = $this->request->int('mesa', 0);
        if ($onlyId > 0) {
            $tables = array_values(array_filter($tables, function ($t) use ($onlyId) { return (int)$t['id'] === $onlyId; }));
        }
        if (!$tables) {
            Session::flash('error', 'Primero crea al menos una mesa.');
            return $this->redirect('/panel/mesas');
        }

        $pdf = new Pdf('A4');
        $pdf->setTitle('Códigos QR · ' . Settings::get('name'));
        $gold = Settings::get('primary_color', '#D8B26E');
        $logo = Settings::get('logo');
        $logoFile = $logo !== '' ? Image::file($logo, 480) : null;

        if ($format === 'tent') {
            foreach ($tables as $i => $t) {
                if ($i > 0) { $pdf->addPage(); }
                $this->tentCard($pdf, $t, $gold, $logoFile);
            }
        } elseif ($format === 'sticker') {
            $this->grid($pdf, $tables, $gold, 3, 4, 60);
        } else {
            $this->grid($pdf, $tables, $gold, 2, 3, 85);
        }

        Audit::log('qr_pdf', 'table', 0, array('format' => $format, 'count' => count($tables)));
        return $pdf->response('codigos-qr-' . $format . '.pdf', true);
    }

    /** Tarjeta a dos caras que se dobla por la mitad y se para sola. */
    private function tentCard(Pdf $pdf, array $table, $gold, $logoFile)
    {
        $matrix = Qr::matrix(TableModel::url($table));
        $nombre = Settings::get('name');

        $pdf->setFillColor('#0C0B09');
        $pdf->rect(0, 0, 210, 297, 'F');
        $pdf->setDrawColor($gold);
        $pdf->setLineWidth(0.2);
        $pdf->line(15, 148.5, 195, 148.5);   // línea de doblez

        foreach (array(0, 148.5) as $offset) {
            $y = $offset + 20;
            if ($logoFile) {
                $pdf->image($logoFile, 90, $y, 30, 30);
                $y += 36;
            }
            $pdf->setFillColor($gold);
            $pdf->setFont('Times', 'B', 24);
            $pdf->text(15, $y, $nombre, 'C', 180);
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
        }
    }

    /** Cuadrícula de tarjetas o etiquetas. */
    private function grid(Pdf $pdf, array $tables, $gold, $cols, $rows, $cell)
    {
        $nombre = Settings::get('name');
        $marginX = (210 - $cols * $cell) / 2;
        $marginY = 18;
        $gapY = (297 - $marginY * 2 - $rows * $cell) / max(1, $rows - 1);
        $perPage = $cols * $rows;
        $i = 0;

        foreach ($tables as $t) {
            if ($i > 0 && $i % $perPage === 0) { $pdf->addPage(); }
            $slot = $i % $perPage;
            $x = $marginX + ($slot % $cols) * $cell;
            $y = $marginY + intdiv($slot, $cols) * ($cell + $gapY);

            $pdf->setFillColor('#0C0B09');
            $pdf->rect($x, $y, $cell - 4, $cell - 4, 'F', 6);
            $pdf->setDrawColor($gold);
            $pdf->setLineWidth(0.25);
            $pdf->rect($x, $y, $cell - 4, $cell - 4, 'D', 6);

            $inner = $cell - 4;
            $pdf->setFillColor($gold);
            $pdf->setFont('Times', 'B', $cell > 70 ? 13 : 10);
            $pdf->text($x, $y + 9, $nombre, 'C', $inner);

            $qrSize = $inner - 26;
            $pdf->setFillColor('#FFFFFF');
            $pdf->rect($x + ($inner - $qrSize - 4) / 2, $y + 13, $qrSize + 4, $qrSize + 4, 'F', 3);
            $pdf->qr(Qr::matrix(TableModel::url($t)), $x + ($inner - $qrSize) / 2, $y + 15, $qrSize, '#000000', 2);

            $pdf->setFillColor('#F4EDE1');
            $pdf->setFont('Helvetica', 'B', $cell > 70 ? 11 : 9);
            $pdf->text($x, $y + $inner - 4, $t['name'], 'C', $inner);
            $i++;
        }
    }
}
