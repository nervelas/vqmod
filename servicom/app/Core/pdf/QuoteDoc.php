<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Plantilla del documento: encabezado con logo y retícula, pie numerado.
 * Se declara sólo después de que TCPDF fue incluido (ver Pdf::boot()).
 */
final class QuoteDoc extends \TCPDF
{
    private array $company = [];
    private array $quote = [];
    private string $docLabel = 'COTIZACIÓN';
    private array $accent = [232, 89, 12];
    private array $ink = [28, 31, 34];
    private array $steel = [90, 100, 112];

    public function setDocMeta(array $company, array $quote, string $docLabel, array $accent, array $ink, array $steel): void
    {
        $this->company  = $company;
        $this->quote    = $quote;
        $this->docLabel = $docLabel;
        $this->accent   = $accent;
        $this->ink      = $ink;
        $this->steel    = $steel;
    }

    public function Header(): void
    {
        $w  = 179.4;
        $ft = Pdf::font('title');
        $fs = Pdf::font('sans');
        $fb = Pdf::font('sansb');

        // Filete de acento superior a todo lo ancho de la hoja.
        $this->SetFillColor($this->accent[0], $this->accent[1], $this->accent[2]);
        $this->Rect(0, 0, 215.9, 2.6, 'F');

        // Logo o nombre de la empresa.
        $logo = $this->logoFile();
        $y = 11;
        if ($logo !== null) {
            try {
                $this->Image($logo, 16, $y, 0, 13, '', '', '', true, 300, '', false, false, 0, 'LT');
            } catch (\Throwable) {
                $logo = null;
            }
        }
        if ($logo === null) {
            $this->SetXY(16, $y + 1);
            $this->SetFont($ft, '', 15);
            $this->SetTextColor($this->ink[0], $this->ink[1], $this->ink[2]);
            $this->Cell(100, 6, (string) ($this->company['name'] ?? ''), 0, 0, 'L');
        }

        // Rótulo del documento a la derecha.
        $this->SetXY(16 + $w - 90, $y);
        $this->SetFont($fb, '', 6.4);
        $this->SetTextColor($this->steel[0], $this->steel[1], $this->steel[2]);
        $this->Cell(90, 4, Pdf::track($this->docLabel), 0, 2, 'R');
        $this->SetFont($ft, '', 16);
        $this->SetTextColor($this->ink[0], $this->ink[1], $this->ink[2]);
        $this->Cell(90, 7, (string) ($this->quote['number'] ?? ''), 0, 0, 'R');

        // Cotas: marcas de medición sobre la línea base del encabezado.
        $base = 31;
        $this->SetDrawColor(214, 217, 210);
        $this->SetLineWidth(0.2);
        $this->Line(16, $base, 16 + $w, $base);
        for ($i = 0; $i <= 12; $i++) {
            $x = 16 + ($w / 12) * $i;
            $this->Line($x, $base, $x, $base + ($i % 3 === 0 ? 2.2 : 1.2));
        }

        // Datos fiscales de la empresa en una línea fina.
        $bits = array_filter([
            (string) ($this->company['legal_name'] ?? $this->company['name'] ?? ''),
            $this->company['nit'] ? 'NIT ' . $this->company['nit'] : '',
            (string) ($this->company['address'] ?? ''),
            (string) ($this->company['phone'] ?? ''),
        ]);
        $this->SetXY(16, $base + 3);
        $this->SetFont($fs, '', 7);
        $this->SetTextColor($this->steel[0], $this->steel[1], $this->steel[2]);
        $this->Cell($w, 3.4, mb_substr(implode('  ·  ', $bits), 0, 150), 0, 0, 'L');
    }

    public function Footer(): void
    {
        $w = 179.4;
        $fs = Pdf::font('sans');
        $fb = Pdf::font('sansb');
        $this->SetY(-17);
        $this->SetDrawColor(214, 217, 210);
        $this->SetLineWidth(0.2);
        $this->Line(16, $this->GetY(), 16 + $w, $this->GetY());
        $this->SetY(-14);
        $this->SetFont($fs, '', 6.8);
        $this->SetTextColor($this->steel[0], $this->steel[1], $this->steel[2]);
        $foot = (string) ($this->company['pdf_footer'] ?? '');
        if ($foot === '') {
            $foot = trim(((string) ($this->company['email'] ?? '')) . '   ' . ((string) ($this->company['phone'] ?? '')));
        }
        $this->Cell($w - 30, 4, mb_substr($foot, 0, 120), 0, 0, 'L');
        $this->SetFont($fb, '', 6.8);
        $this->SetTextColor($this->ink[0], $this->ink[1], $this->ink[2]);
        $this->Cell(30, 4, $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
    }

    private function logoFile(): ?string
    {
        $logo = (string) ($this->company['logo'] ?? '');
        if ($logo === '') {
            return null;
        }
        $abs = STORAGE_PATH . '/uploads/' . ltrim($logo, '/');
        if (!is_file($abs)) {
            return null;
        }
        $real = realpath($abs);
        $root = realpath(STORAGE_PATH . '/uploads');
        if (!$real || !$root || !str_starts_with($real, $root)) {
            return null;
        }
        return $real;
    }
}
