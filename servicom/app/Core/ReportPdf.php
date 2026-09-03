<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Company;
use App\Models\Quote;

/** Informe de gestión en PDF con el mismo lenguaje técnico del sistema. */
final class ReportPdf
{
    public static function render(array $company, string $from, string $to, array $r): string
    {
        $fake = [
            'number' => 'INFORME DE GESTIÓN',
            'contact_name' => '',
            'currency_symbol' => (string) $company['currency_symbol'],
            'track_token' => '',
        ];
        $theme  = Company::theme($company);
        $accent = Img::hex2rgb($theme['accent']);
        $ink    = Img::hex2rgb($theme['ink']);
        $steel  = [90, 100, 112];

        $pdf = self::doc($company, $fake, $accent, $ink, $steel);
        $fs  = Pdf::font('sans');
        $fsb = Pdf::font('sansb');
        $ft  = Pdf::font('title');
        $sym = (string) $company['currency_symbol'];
        $w   = 179.4;

        $pdf->SetXY(16, $pdf->GetY());
        $pdf->SetFont($fsb, '', 6.4);
        $pdf->SetTextColor($steel[0], $steel[1], $steel[2]);
        $pdf->Cell($w, 4, Pdf::track('PERIODO ' . fechaCorta($from) . ' — ' . fechaCorta($to)), 0, 2, 'L');
        $pdf->SetY($pdf->GetY() + 3);

        // Indicadores principales en retícula de 4.
        $kpis = [
            ['MONTO COTIZADO', money($r['quoted'], $sym)],
            ['MONTO GANADO',   money($r['won'], $sym)],
            ['CONVERSIÓN',     qty($r['conv']) . ' %'],
            ['TICKET PROMEDIO', money($r['avgTicket'], $sym)],
        ];
        $cw = $w / 4;
        $ky = $pdf->GetY();
        foreach ($kpis as $i => [$k, $v]) {
            $x = 16 + $cw * $i;
            $pdf->SetXY($x, $ky);
            $pdf->SetFont($fsb, '', 5.8);
            $pdf->SetTextColor($steel[0], $steel[1], $steel[2]);
            $pdf->Cell($cw - 3, 4, Pdf::track($k), 0, 2, 'L');
            $pdf->SetFont($ft, '', 14);
            $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
            $pdf->Cell($cw - 3, 7, $v, 0, 0, 'L');
            $pdf->SetDrawColor($accent[0], $accent[1], $accent[2]);
            $pdf->SetLineWidth(0.7);
            $pdf->Line($x, $ky - 1.6, $x + 10, $ky - 1.6);
        }
        $pdf->SetLineWidth(0.2);
        $pdf->SetY($ky + 16);

        self::table($pdf, 'COTIZACIONES POR ESTADO', ['Estado', 'Cantidad', 'Monto'],
            array_map(static fn ($k) => [
                Quote::STATUSES[$k]['label'],
                (string) (int) ($r['byStatus'][$k]['n'] ?? 0),
                money((float) ($r['byStatus'][$k]['monto'] ?? 0), $sym),
            ], array_keys(Quote::STATUSES)), [90, 40, 49.4], $ink, $steel, $accent);

        if ($r['sellers']) {
            self::table($pdf, 'RANKING DE VENDEDORES', ['Vendedor', 'Cotiz.', 'Ganadas', 'Monto ganado'],
                array_map(static fn ($s) => [(string) $s['name'], (string) (int) $s['n'], (string) (int) $s['ganadas'], money((float) $s['ganado'], $sym)], $r['sellers']),
                [79.4, 26, 26, 48], $ink, $steel, $accent);
        }
        if ($r['products']) {
            self::table($pdf, 'PRODUCTOS MÁS COTIZADOS', ['Código', 'Producto', 'Veces', 'Monto'],
                array_map(static fn ($p) => [(string) $p['code'], str_limit((string) $p['name'], 52), (string) (int) $p['veces'], money((float) $p['monto'], $sym)], array_slice($r['products'], 0, 15)),
                [30, 89.4, 20, 40], $ink, $steel, $accent, [0, 1]);
        }
        if ($r['lost']) {
            self::table($pdf, 'MOTIVOS DE PÉRDIDA', ['Motivo', 'Cantidad', 'Monto'],
                array_map(static fn ($l) => [(string) $l['motivo'], (string) (int) $l['n'], money((float) $l['monto'], $sym)], $r['lost']),
                [90, 40, 49.4], $ink, $steel, $accent);
        }

        $dir = STORAGE_PATH . '/uploads/reportes';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/informe-' . $from . '-' . $to . '.pdf';
        $pdf->Output($file, 'F');
        return $file;
    }

    private static function doc(array $company, array $fake, array $accent, array $ink, array $steel): QuoteDoc
    {
        // Reutiliza la plantilla del documento (encabezado y pie técnicos).
        Pdf::boot();
        $pdf = new QuoteDoc('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->setDocMeta($company, $fake, 'INFORME', $accent, $ink, $steel);
        $pdf->SetCreator('CotizaPro B2B');
        $pdf->SetAuthor((string) $company['name']);
        $pdf->SetTitle('Informe de gestión');
        $pdf->SetMargins(16, 44, 16);
        $pdf->SetAutoPageBreak(true, 26);
        $pdf->SetHeaderMargin(6);
        $pdf->SetFooterMargin(12);
        $pdf->AddPage();
        return $pdf;
    }

    private static function table(QuoteDoc $pdf, string $title, array $head, array $rows, array $widths, array $ink, array $steel, array $accent, array $lefts = [0]): void
    {
        $fs  = Pdf::font('sans');
        $fsb = Pdf::font('sansb');
        if ($pdf->GetY() > 205) {
            $pdf->AddPage();
        }
        $pdf->SetY($pdf->GetY() + 4);
        $pdf->SetX(16);
        $pdf->SetFont($fsb, '', 6.4);
        $pdf->SetTextColor($accent[0], $accent[1], $accent[2]);
        $pdf->Cell(179.4, 5, Pdf::track($title), 0, 2, 'L');

        $y = $pdf->GetY() + 0.5;
        $pdf->SetFillColor($ink[0], $ink[1], $ink[2]);
        $pdf->Rect(16, $y, 179.4, 6.4, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont($fsb, '', 6.2);
        $x = 16;
        foreach ($head as $i => $h) {
            $align = in_array($i, $lefts, true) ? 'L' : 'R';
            $pdf->SetXY($x + ($align === 'L' ? 2.2 : 0), $y + 1.5);
            $pdf->Cell($widths[$i] - 2.2, 4, Pdf::track($h), 0, 0, $align);
            $x += $widths[$i];
        }
        $pdf->SetY($y + 6.4);

        foreach ($rows as $r) {
            if ($pdf->GetY() > 245) {
                $pdf->AddPage();
            }
            $ry = $pdf->GetY();
            $x = 16;
            foreach ($r as $i => $cell) {
                $align = in_array($i, $lefts, true) ? 'L' : 'R';
                $pdf->SetFont($i === 0 ? $fsb : $fs, '', 8.4);
                $pdf->SetTextColor($i === 0 ? $ink[0] : $steel[0], $i === 0 ? $ink[1] : $steel[1], $i === 0 ? $ink[2] : $steel[2]);
                $pdf->SetXY($x + ($align === 'L' ? 2.2 : 0), $ry + 1.4);
                $pdf->Cell(($widths[$i] ?? 30) - 2.2, 4, (string) $cell, 0, 0, $align);
                $x += $widths[$i] ?? 30;
            }
            $pdf->SetDrawColor(222, 225, 219);
            $pdf->Line(16, $ry + 6.6, 195.4, $ry + 6.6);
            $pdf->SetY($ry + 6.6);
        }
    }
}
