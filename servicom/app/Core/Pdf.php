<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Company;
use App\Models\Quote;

/**
 * Documentos PDF con lenguaje técnico: retícula, cotas y tipografía de marca.
 * Se apoya en TCPDF (local, sin composer).
 */
final class Pdf
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        if (!defined('K_TCPDF_EXTERNAL_CONFIG')) {
            define('K_TCPDF_EXTERNAL_CONFIG', true);
            define('K_PATH_MAIN', VENDOR_PATH . '/tcpdf/');
            define('K_PATH_URL', '');
            define('K_PATH_FONTS', VENDOR_PATH . '/tcpdf/fonts/');
            define('K_PATH_CACHE', STORAGE_PATH . '/cache/');
            define('K_PATH_IMAGES', VENDOR_PATH . '/tcpdf/');
            define('K_BLANK_IMAGE', VENDOR_PATH . '/tcpdf/images/_blank.png');
            define('K_CELL_HEIGHT_RATIO', 1.25);
            define('K_TITLE_MAGNIFICATION', 1.3);
            define('K_SMALL_RATIO', 2 / 3);
            define('K_THAI_TOPCHARS', false);
            define('K_TCPDF_CALLS_IN_HTML', false);
            define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
            define('PDF_PAGE_FORMAT', 'LETTER');
            define('PDF_PAGE_ORIENTATION', 'P');
            define('PDF_UNIT', 'mm');
            define('PDF_CREATOR', 'CotizaPro B2B');
            define('PDF_AUTHOR', 'CotizaPro B2B');
            define('PDF_HEADER_TITLE', '');
            define('PDF_HEADER_STRING', '');
            define('PDF_HEADER_LOGO', '');
            define('PDF_HEADER_LOGO_WIDTH', 0);
            define('PDF_MARGIN_HEADER', 5);
            define('PDF_MARGIN_FOOTER', 10);
            define('PDF_MARGIN_TOP', 27);
            define('PDF_MARGIN_BOTTOM', 25);
            define('PDF_MARGIN_LEFT', 15);
            define('PDF_MARGIN_RIGHT', 15);
            define('PDF_FONT_NAME_MAIN', 'helvetica');
            define('PDF_FONT_SIZE_MAIN', 10);
            define('PDF_FONT_NAME_DATA', 'helvetica');
            define('PDF_FONT_SIZE_DATA', 8);
            define('PDF_FONT_MONOSPACED', 'courier');
            define('PDF_IMAGE_SCALE_RATIO', 1.25);
            define('HEAD_MAGNIFICATION', 1.1);
            define('K_TCPDF_VERSION_CHECK', false);
        }
        if (!is_dir(STORAGE_PATH . '/cache')) {
            @mkdir(STORAGE_PATH . '/cache', 0755, true);
        }
        require_once VENDOR_PATH . '/tcpdf/tcpdf.php';
        require_once __DIR__ . '/pdf/QuoteDoc.php';
        self::$booted = true;
    }

    /** Tipografías de marca embebidas; con respaldo a helvetica. */
    public static function font(string $which = 'sans'): string
    {
        $map = ['sans' => 'inter400', 'sansb' => 'inter700', 'sanssb' => 'inter600', 'title' => 'spacegrotesk700'];
        $name = $map[$which] ?? 'inter400';
        return is_file(VENDOR_PATH . '/tcpdf/fonts/' . $name . '.php') ? $name : 'helvetica';
    }

    /**
     * Genera el PDF de una cotización y lo guarda en /storage/uploads.
     * @return string ruta absoluta del archivo
     */
    public static function quote(array $company, array $quote, array $items, array $options = []): string
    {
        self::boot();
        $theme  = Company::theme($company);
        $accent = Img::hex2rgb($theme['accent']);
        $ink    = Img::hex2rgb($theme['ink']);
        $steel  = [90, 100, 112];
        $hair   = [214, 217, 210];
        $sym    = (string) ($quote['currency_symbol'] ?: 'Q');
        $isOrder = !empty($options['order']);
        $docLabel = $isOrder ? 'ORDEN DE TRABAJO' : 'COTIZACIÓN';

        $pdf = new QuoteDoc('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->setDocMeta($company, $quote, $docLabel, $accent, $ink, $steel);
        $pdf->SetCreator('CotizaPro B2B');
        $pdf->SetAuthor((string) $company['name']);
        $pdf->SetTitle($docLabel . ' ' . $quote['number']);
        $pdf->SetSubject('Cotización para ' . (string) $quote['contact_name']);
        $pdf->SetMargins(16, 44, 16);
        $pdf->SetAutoPageBreak(true, 30);
        $pdf->SetHeaderMargin(6);
        $pdf->SetFooterMargin(12);
        $pdf->setImageScale(1.25);
        $pdf->AddPage();

        $fs  = self::font('sans');
        $fsb = self::font('sansb');
        $fb  = self::font('sansb');
        $ft  = self::font('title');
        $w   = 179.4; // ancho útil en LETTER con márgenes de 16 mm

        // ---------------------------------------------------- bloque cliente
        $y = $pdf->GetY();
        $pdf->SetDrawColor($hair[0], $hair[1], $hair[2]);
        $pdf->SetLineWidth(0.2);

        $colW = $w / 2;
        $pdf->SetXY(16, $y);
        self::label($pdf, 'CLIENTE', $steel, $fsb);
        $pdf->SetFont($fb, '', 12);
        $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
        $pdf->MultiCell($colW - 6, 5.4, (string) ($quote['contact_company'] ?: $quote['contact_name']), 0, 'L');
        $pdf->SetFont($fs, '', 9);
        $pdf->SetTextColor($steel[0], $steel[1], $steel[2]);
        $lines = [];
        if ($quote['contact_company'] && $quote['contact_name']) {
            $lines[] = 'Atención: ' . $quote['contact_name'];
        }
        if ($quote['contact_nit']) {
            $lines[] = 'NIT: ' . $quote['contact_nit'];
        }
        if ($quote['contact_phone']) {
            $lines[] = 'Tel: ' . $quote['contact_phone'];
        }
        if ($quote['contact_email']) {
            $lines[] = $quote['contact_email'];
        }
        $pdf->MultiCell($colW - 6, 4.4, implode("\n", $lines), 0, 'L');
        $leftBottom = $pdf->GetY();

        // Ficha de datos del documento (retícula técnica).
        $pdf->SetXY(16 + $colW, $y);
        $meta = [
            ['NÚMERO',   (string) $quote['number']],
            ['FECHA',    fechaCorta((string) $quote['created_at'])],
            ['VÁLIDA HASTA', $quote['valid_until'] ? fechaCorta((string) $quote['valid_until']) : (int) $quote['validity_days'] . ' días'],
            ['ASESOR',   (string) ($quote['seller_name'] ?? '—')],
        ];
        $rowY = $y;
        foreach ($meta as $i => [$k, $v]) {
            $pdf->SetXY(16 + $colW, $rowY);
            $pdf->SetFont($fsb, '', 6.4);
            $pdf->SetTextColor($steel[0], $steel[1], $steel[2]);
            $pdf->Cell(30, 4, self::track($k), 0, 0, 'L');
            $pdf->SetFont($i === 0 ? $fb : $fs, '', $i === 0 ? 10.5 : 9.5);
            $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
            $pdf->Cell($colW - 30, 4, $v, 0, 0, 'R');
            $rowY += 6.6;
            $pdf->Line(16 + $colW, $rowY - 1.6, 16 + $w, $rowY - 1.6);
        }
        $pdf->SetY(max($leftBottom, $rowY) + 5);

        // ------------------------------------------------------ tabla de ítems
        $showPrices = !isset($options['show_prices']) || $options['show_prices'];
        $cols = $showPrices
            ? ['#' => 8, 'CÓDIGO' => 27, 'DESCRIPCIÓN' => 78.4, 'CANT' => 16, 'P. UNIT' => 24, 'TOTAL' => 26]
            : ['#' => 8, 'CÓDIGO' => 32, 'DESCRIPCIÓN' => 123.4, 'CANT' => 16];

        $pdf->SetY($pdf->GetY() + 1);
        $ty = $pdf->GetY();
        $pdf->SetFillColor($ink[0], $ink[1], $ink[2]);
        $pdf->Rect(16, $ty, $w, 7.4, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont($fsb, '', 6.6);
        $x = 16;
        foreach ($cols as $label => $cw) {
            $align = in_array($label, ['CANT', 'P. UNIT', 'TOTAL'], true) ? 'R' : 'L';
            $pdf->SetXY($x + ($align === 'L' ? 2.4 : 0), $ty + 1.9);
            $pdf->Cell($cw - 2.4, 4, self::track($label), 0, 0, $align);
            $x += $cw;
        }
        $pdf->SetY($ty + 7.4);

        $n = 0;
        foreach ($items as $it) {
            $n++;
            $rowStart = $pdf->GetY();
            if ($rowStart > 232) {
                $pdf->AddPage();
                $pdf->SetY($pdf->GetY() + 2);
                $rowStart = $pdf->GetY();
            }
            $descW = $cols['DESCRIPCIÓN'];
            $pdf->SetFont($fsb, '', 9.4);
            $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
            $pdf->SetXY(16 + $cols['#'] + $cols['CÓDIGO'] + 2.4, $rowStart + 2.2);
            $pdf->MultiCell($descW - 4, 4.5, (string) $it['name'], 0, 'L');
            $afterName = $pdf->GetY();
            $specs = trim((string) ($it['specs'] ?? ''));
            $note  = trim((string) ($it['notes'] ?? ''));
            $sub = trim($specs . ($specs && $note ? "\n" : '') . ($note !== '' ? 'Nota: ' . $note : ''));
            if ($sub !== '') {
                $pdf->SetFont($fs, '', 7.6);
                $pdf->SetTextColor($steel[0], $steel[1], $steel[2]);
                $pdf->SetX(16 + $cols['#'] + $cols['CÓDIGO'] + 2.4);
                $pdf->MultiCell($descW - 4, 3.6, $sub, 0, 'L');
                $afterName = $pdf->GetY();
            }
            $rowH = max(11.0, $afterName - $rowStart + 2.4);

            $pdf->SetFont($fs, '', 8.6);
            $pdf->SetTextColor($steel[0], $steel[1], $steel[2]);
            $pdf->SetXY(16 + 2.4, $rowStart + 2.4);
            $pdf->Cell($cols['#'] - 2.4, 4, str_pad((string) $n, 2, '0', STR_PAD_LEFT), 0, 0, 'L');
            $pdf->SetFont($fsb, '', 8.6);
            $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
            $pdf->SetXY(16 + $cols['#'], $rowStart + 2.4);
            $pdf->Cell($cols['CÓDIGO'] - 2, 4, (string) ($it['code'] ?: '—'), 0, 0, 'L');

            $x = 16 + $cols['#'] + $cols['CÓDIGO'] + $cols['DESCRIPCIÓN'];
            $pdf->SetFont($fs, '', 9);
            $pdf->SetXY($x, $rowStart + 2.4);
            $pdf->Cell($cols['CANT'], 4, qty((float) $it['qty']) . ' ' . mb_substr((string) $it['unit'], 0, 4), 0, 0, 'R');
            if ($showPrices) {
                $x += $cols['CANT'];
                $pdf->SetXY($x, $rowStart + 2.4);
                $unitTxt = money((float) $it['unit_price'], $sym);
                if ((float) $it['discount_pct'] > 0) {
                    $unitTxt .= ' −' . qty((float) $it['discount_pct']) . '%';
                }
                $pdf->Cell($cols['P. UNIT'], 4, $unitTxt, 0, 0, 'R');
                $x += $cols['P. UNIT'];
                $pdf->SetFont($fb, '', 9.2);
                $pdf->SetXY($x, $rowStart + 2.4);
                $pdf->Cell($cols['TOTAL'], 4, money((float) $it['line_total'], $sym), 0, 0, 'R');
            }
            $pdf->SetDrawColor($hair[0], $hair[1], $hair[2]);
            $pdf->Line(16, $rowStart + $rowH, 16 + $w, $rowStart + $rowH);
            $pdf->SetY($rowStart + $rowH);
        }

        // ------------------------------------------------------------ totales
        if ($showPrices) {
            $ty = $pdf->GetY() + 4;
            if ($ty > 218) {
                $pdf->AddPage();
                $ty = $pdf->GetY() + 2;
            }
            $bx = 16 + $w - 76;
            $rows = [['Subtotal', money((float) $quote['subtotal'], $sym)]];
            if ((float) $quote['discount_amount'] > 0) {
                $lbl = $quote['discount_type'] === 'porcentaje' ? 'Descuento (' . qty((float) $quote['discount_value']) . '%)' : 'Descuento';
                $rows[] = [$lbl, '− ' . money((float) $quote['discount_amount'], $sym)];
            }
            if ((float) $quote['tax_rate'] > 0) {
                $rows[] = [(string) ($company['tax_label'] ?: 'IVA') . ' ' . qty((float) $quote['tax_rate']) . '%', money((float) $quote['tax_amount'], $sym)];
            }
            foreach ($rows as $r) {
                $pdf->SetFont($fs, '', 9);
                $pdf->SetTextColor($steel[0], $steel[1], $steel[2]);
                $pdf->SetXY($bx, $ty);
                $pdf->Cell(44, 5, $r[0], 0, 0, 'L');
                $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
                $pdf->Cell(32, 5, $r[1], 0, 0, 'R');
                $ty += 5.6;
            }
            $pdf->SetDrawColor($accent[0], $accent[1], $accent[2]);
            $pdf->SetLineWidth(0.8);
            $pdf->Line($bx, $ty + 0.6, 16 + $w, $ty + 0.6);
            $pdf->SetLineWidth(0.2);
            $ty += 3;
            $pdf->SetXY($bx, $ty);
            $pdf->SetFont($fsb, '', 7);
            $pdf->SetTextColor($steel[0], $steel[1], $steel[2]);
            $pdf->Cell(30, 8, self::track('TOTAL'), 0, 0, 'L');
            $pdf->SetFont($ft, '', 17);
            $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
            $pdf->Cell(46, 8, money((float) $quote['total'], $sym), 0, 0, 'R');
            $pdf->SetY($ty + 12);
        }

        // -------------------------------------------------------- condiciones
        $conds = [];
        if ($quote['delivery_time']) {
            $conds[] = ['TIEMPO DE ENTREGA', (string) $quote['delivery_time']];
        }
        if ($quote['payment_terms']) {
            $conds[] = ['CONDICIONES DE PAGO', (string) $quote['payment_terms']];
        }
        $conds[] = ['VIGENCIA DE LA OFERTA', $quote['valid_until'] ? 'Hasta el ' . fechaLarga((string) $quote['valid_until']) : (int) $quote['validity_days'] . ' días calendario'];
        if ($quote['notes']) {
            $conds[] = ['OBSERVACIONES', (string) $quote['notes']];
        }
        if ($company['pdf_terms']) {
            $conds[] = ['TÉRMINOS', (string) $company['pdf_terms']];
        }

        $cy = $pdf->GetY() + 2;
        if ($cy > 200) {
            $pdf->AddPage();
            $cy = $pdf->GetY();
        }
        $pdf->SetY($cy);
        $i = 0;
        foreach ($conds as [$k, $v]) {
            $i++;
            $sy = $pdf->GetY();
            if ($sy > 236) {
                $pdf->AddPage();
                $sy = $pdf->GetY();
            }
            $pdf->SetXY(16, $sy);
            $pdf->SetFont($fsb, '', 6.4);
            $pdf->SetTextColor($accent[0], $accent[1], $accent[2]);
            $pdf->Cell(9, 4, str_pad((string) $i, 2, '0', STR_PAD_LEFT) . '/', 0, 0, 'L');
            $pdf->SetTextColor($steel[0], $steel[1], $steel[2]);
            $pdf->Cell(42, 4, self::track($k), 0, 0, 'L');
            $pdf->SetFont($fs, '', 8.8);
            $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
            $pdf->SetXY(67, $sy);
            $pdf->MultiCell($w - 51, 4.2, $v, 0, 'L');
            $pdf->SetY($pdf->GetY() + 1.6);
        }

        // ------------------------------------------------- firma + QR de rastreo
        $sy = $pdf->GetY() + 6;
        if ($sy > 214) {
            $pdf->AddPage();
            $sy = $pdf->GetY() + 4;
        }
        $pdf->SetDrawColor($hair[0], $hair[1], $hair[2]);
        $pdf->Line(16, $sy, 16 + $w, $sy);
        $sy += 7;

        $trackUrl = Quote::trackUrl($quote);
        try {
            $style = [
                'border' => false, 'vpadding' => 0, 'hpadding' => 0,
                'fgcolor' => [$ink[0], $ink[1], $ink[2]], 'bgcolor' => false, 'module_width' => 1, 'module_height' => 1,
            ];
            $pdf->write2DBarcode($trackUrl, 'QRCODE,M', 16 + $w - 26, $sy, 26, 26, $style, 'N');
        } catch (\Throwable $e) {
            ErrorHandler::log('QR no generado: ' . $e->getMessage());
        }
        $pdf->SetXY(16 + $w - 74, $sy + 3);
        $pdf->SetFont($fsb, '', 6.2);
        $pdf->SetTextColor($steel[0], $steel[1], $steel[2]);
        $pdf->MultiCell(46, 3.4, self::track('SEGUIMIENTO EN LÍNEA') . "\n", 0, 'R');
        $pdf->SetFont($fs, '', 7.4);
        $pdf->SetX(16 + $w - 74);
        $pdf->MultiCell(46, 3.4, "Escanee para ver el estado de\nesta cotización y aprobarla.", 0, 'R');

        $pdf->SetXY(16, $sy + 12);
        $pdf->SetDrawColor($ink[0], $ink[1], $ink[2]);
        $pdf->Line(16, $sy + 12, 16 + 62, $sy + 12);
        $pdf->SetXY(16, $sy + 13.4);
        $pdf->SetFont($fsb, '', 9);
        $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
        $pdf->Cell(62, 4, (string) ($quote['seller_name'] ?? $company['name']), 0, 2, 'L');
        $pdf->SetFont($fs, '', 7.6);
        $pdf->SetTextColor($steel[0], $steel[1], $steel[2]);
        $pdf->Cell(62, 3.6, trim((string) ($quote['seller_position'] ?? 'Asesor técnico')), 0, 2, 'L');
        if (!empty($quote['seller_email'])) {
            $pdf->Cell(62, 3.6, (string) $quote['seller_email'], 0, 2, 'L');
        }

        $dir = STORAGE_PATH . '/uploads/cotizaciones';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/' . preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', (string) $quote['number'])) . '-' . substr(hash('sha256', (string) $quote['track_token']), 0, 8) . '.pdf';
        $pdf->Output($file, 'F');
        return $file;
    }

    /** Texto con interletrado amplio (etiquetas técnicas). */
    public static function track(string $s): string
    {
        return implode(' ', preg_split('//u', mb_strtoupper($s), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    private static function label(\TCPDF $pdf, string $text, array $rgb, string $font): void
    {
        $pdf->SetFont($font, '', 6.4);
        $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
        $pdf->Cell(60, 4, self::track($text), 0, 2, 'L');
        $pdf->SetY($pdf->GetY() + 0.6);
    }
}
