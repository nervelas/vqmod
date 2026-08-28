<?php
declare(strict_types=1);

namespace MenuGold\Controllers\Panel;

use MenuGold\Core\Image;
use MenuGold\Core\Request;
use MenuGold\Models\Restaurant;
use MenuGold\Models\RestaurantTable;
use MenuGold\Vendor\Pdf\Pdf;
use MenuGold\Vendor\QrCode\QrCode;

/**
 * Generacion de codigos QR: vista previa, PNG y hojas imprimibles en PDF
 * con tres disenos elegantes y tres tamanos.
 */
class Qr extends Base
{
    public const DISENOS = [
        'tarjeta' => 'Tarjeta de mesa',
        'tent'    => 'Tent card (se dobla)',
        'sticker' => 'Sticker / calcomanía',
    ];
    public const TAMANOS = [
        'a6'    => ['A6 (10.5 × 14.8 cm)', 'A6'],
        'cm10'  => ['Cuadrado 10 × 10 cm', 'CM10'],
        'carta' => ['Carta (varios por hoja)', 'LETTER'],
    ];

    public function index(): void
    {
        $this->exigir('mesas');
        $m = (new RestaurantTable())->forRestaurant($this->rid);
        $mesas = $m->activas();
        foreach ($mesas as &$x) {
            $x['token'] = $m->token($x);
            $x['url']   = Restaurant::urlMenu($this->r, (string)$x['nombre'], $x['token']);
        }
        unset($x);

        $this->panel('panel/qr', [
            'mesas'      => $mesas,
            'urlGeneral' => Restaurant::urlMenu($this->r),
            'disenos'    => self::DISENOS,
            'tamanos'    => self::TAMANOS,
        ]);
    }

    /** PNG del QR de una mesa (id = 0 para el QR general). */
    public function png(array $p = []): void
    {
        $this->exigir('mesas');
        $id = (int)($p['id'] ?? 0);
        $url = $this->urlDe($id);
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        $escala = max(3, min(20, (int)($_GET['escala'] ?? 8)));
        $png = QrCode::png($url, $escala, 3, '#141414', '#FFFFFF', 'M');
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: image/png');
        header('Cache-Control: private, max-age=600');
        header('Content-Disposition: inline; filename="qr-' . ($id > 0 ? 'mesa-' . $id : 'general') . '.png"');
        echo $png;
        exit;
    }

    private function urlDe(int $tableId): string
    {
        if ($tableId <= 0) return Restaurant::urlMenu($this->r);
        $m = (new RestaurantTable())->forRestaurant($this->rid);
        $mesa = $m->findOrFail($tableId);
        return Restaurant::urlMenu($this->r, (string)$mesa['nombre'], $m->token($mesa));
    }

    // =================================================================
    //  PDF imprimible
    // =================================================================
    public function pdf(): void
    {
        $this->exigir('mesas');
        $diseno = Request::enum('diseno', array_keys(self::DISENOS), 'tarjeta');
        $tamano = Request::enum('tamano', array_keys(self::TAMANOS), 'a6');
        $incluirGeneral = Request::bool('general');
        $idsSel = array_map('intval', Request::arr('mesas'));

        $m = (new RestaurantTable())->forRestaurant($this->rid);
        $mesas = $m->activas();
        if ($idsSel) {
            $mesas = array_values(array_filter($mesas, static fn($x) => in_array((int)$x['id'], $idsSel, true)));
        }

        $piezas = [];
        if ($incluirGeneral || !$mesas) {
            $piezas[] = ['titulo' => 'Nuestro menú', 'sub' => 'Escanea y pide', 'url' => Restaurant::urlMenu($this->r)];
        }
        foreach ($mesas as $x) {
            $piezas[] = [
                'titulo' => (string)$x['nombre'],
                'sub'    => 'Escanea para pedir desde tu mesa',
                'url'    => Restaurant::urlMenu($this->r, (string)$x['nombre'], $m->token($x)),
            ];
        }
        if (!$piezas) {
            flash('error', 'No hay mesas activas para generar los QR.');
            redirect('panel/qr');
        }

        $pdf = $tamano === 'carta'
            ? $this->hojaCarta($piezas, $diseno)
            : $this->hojaIndividual($piezas, $diseno, self::TAMANOS[$tamano][1]);

        $nombre = 'qr-' . str_slug((string)$this->r['nombre']) . '-' . $diseno . '-' . $tamano . '.pdf';
        $this->inline($pdf, $nombre, 'application/pdf');
    }

    // ------------------------------------------------------------- diseños
    /** Una pieza por página, al tamaño elegido. */
    private function hojaIndividual(array $piezas, string $diseno, string $tamPdf): string
    {
        $pdf = new Pdf($tamPdf, 'P', 0);
        $pdf->meta('titulo', 'Códigos QR · ' . (string)$this->r['nombre']);
        foreach ($piezas as $p) {
            $pdf->addPage($tamPdf, 'P');
            if ($diseno === 'tent') {
                // Dos caras iguales, se dobla por la mitad
                $mitad = $pdf->alto() / 2;
                $this->pieza($pdf, $p, 0, 0, $pdf->ancho(), $mitad, true);
                $this->pieza($pdf, $p, 0, $mitad, $pdf->ancho(), $mitad, false);
                $pdf->setTrazo('#B9B2A2', 0.7);
                $pdf->dashed(10, $mitad, $pdf->ancho() - 10, $mitad, 4, 4);
                $pdf->setFuente('helvetica', 6.5);
                $pdf->setColorTexto('#9A9384');
                $pdf->cell(0, $mitad + 3, $pdf->ancho(), 'DOBLAR AQUÍ', 'C', 8);
            } else {
                $this->pieza($pdf, $p, 0, 0, $pdf->ancho(), $pdf->alto(), false);
            }
        }
        return $pdf->output();
    }

    /** Varias piezas por hoja carta, con líneas de corte. */
    private function hojaCarta(array $piezas, string $diseno): string
    {
        $pdf = new Pdf('LETTER', 'P', 0);
        $pdf->meta('titulo', 'Códigos QR · ' . (string)$this->r['nombre']);
        $cols = $diseno === 'sticker' ? 3 : 2;
        $filas = $diseno === 'sticker' ? 4 : 2;
        $porHoja = $cols * $filas;
        $margen = 22.0;
        $w = ($pdf->ancho() - 2 * $margen) / $cols;
        $h = ($pdf->alto() - 2 * $margen) / $filas;

        foreach ($piezas as $i => $p) {
            $idx = $i % $porHoja;
            if ($idx === 0) $pdf->addPage('LETTER', 'P');
            $c = $idx % $cols;
            $f = intdiv($idx, $cols);
            $x = $margen + $c * $w;
            $y = $margen + $f * $h;
            $this->pieza($pdf, $p, $x + 4, $y + 4, $w - 8, $h - 8, false);
            // Guías de corte
            $pdf->setTrazo('#D8D3C6', 0.4);
            $pdf->dashed($x, $y, $x + $w, $y, 3, 3);
            $pdf->dashed($x, $y + $h, $x + $w, $y + $h, 3, 3);
            $pdf->dashed($x, $y, $x, $y + $h, 3, 3);
            $pdf->dashed($x + $w, $y, $x + $w, $y + $h, 3, 3);
        }
        return $pdf->output();
    }

    /**
     * Dibuja una pieza QR completa con la marca del restaurante.
     * Estética: fondo del tema, marco dorado, logo, QR sobre panel claro.
     */
    private function pieza(Pdf $pdf, array $p, float $x, float $y, float $w, float $h, bool $invertida): void
    {
        $fondo  = (string)($this->r['color_fondo'] ?? '#141414');
        $oro    = (string)($this->r['color_primario'] ?? '#D4AF37');
        $claro  = $this->esClaro($fondo);
        $texto  = $claro ? '#1D1B17' : '#F7F3EA';
        $tenue  = $claro ? '#6B6559' : '#A9A398';

        $pad = min(16.0, $w * 0.06);
        $pdf->setRelleno($fondo);
        $pdf->rect($x, $y, $w, $h, 'F');

        // Marco dorado doble
        $pdf->setTrazo($oro, 1.2);
        $pdf->roundRect($x + $pad * 0.55, $y + $pad * 0.55, $w - $pad * 1.1, $h - $pad * 1.1, 8, 'D');
        $pdf->setTrazo($oro, 0.4);
        $pdf->roundRect($x + $pad * 0.55 + 3, $y + $pad * 0.55 + 3, $w - $pad * 1.1 - 6, $h - $pad * 1.1 - 6, 6, 'D');

        $cy = $y + $pad * 1.35;
        $anchoUtil = $w - $pad * 2.2;
        $izq = $x + $pad * 1.1;

        // Logo
        $logo = (string)($this->r['logo'] ?? '');
        if ($logo !== '' && is_file(Image::path($logo)) && $h > 200) {
            $lw = min(38.0, $w * 0.16);
            $lh = $pdf->imageHeight(Image::path($logo), $lw);
            if ($lh > 0 && $pdf->image(Image::path($logo), $x + ($w - $lw) / 2, $cy, $lw)) {
                $cy += $lh + 8;
            }
        }

        // Nombre del restaurante
        $pdf->setColorTexto($oro);
        $tamNombre = max(9.0, min(19.0, $w * 0.062));
        $pdf->setFuente('times-b', $tamNombre);
        $nombre = $pdf->truncar((string)$this->r['nombre'], $anchoUtil);
        $cy += $pdf->cell($izq, $cy, $anchoUtil, $nombre, 'C', $tamNombre * 1.25);

        // Filete decorativo
        $pdf->setTrazo($oro, 0.6);
        $fw = min(70.0, $anchoUtil * 0.4);
        $pdf->line($x + ($w - $fw) / 2, $cy + 4, $x + ($w + $fw) / 2, $cy + 4);
        $cy += 12;

        // QR sobre panel claro
        [$mod, $rects] = QrCode::rects($p['url'], 'M');
        $espacioTexto = 46.0 + ($h > 300 ? 14 : 0);
        $lado = min($anchoUtil * 0.82, $h - ($cy - $y) - $espacioTexto);
        $lado = max(52.0, $lado);
        $panel = $lado + 14;
        $px = $x + ($w - $panel) / 2;
        $pdf->setRelleno('#FFFFFF');
        $pdf->roundRect($px, $cy, $panel, $panel, 7, 'F');
        $pdf->qr($rects, $mod, $px + 7, $cy + 7, $lado, '#141414');
        $cy += $panel + 10;

        // Nombre de la mesa
        $pdf->setColorTexto($texto);
        $tamMesa = max(11.0, min(22.0, $w * 0.075));
        $pdf->setFuente('helvetica-b', $tamMesa);
        $cy += $pdf->cell($izq, $cy, $anchoUtil, mb_strtoupper($p['titulo']), 'C', $tamMesa * 1.2);

        // Instrucción
        $pdf->setColorTexto($tenue);
        $pdf->setFuente('helvetica', max(6.5, min(9.0, $w * 0.031)));
        $pdf->cell($izq, $cy + 1, $anchoUtil, $p['sub'], 'C', 11);
    }

    private function esClaro(string $hex): bool
    {
        [$r, $g, $b] = Image::hex2rgb($hex);
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150;
    }
}
