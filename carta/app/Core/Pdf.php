<?php
namespace MenuGold\Core;

/**
 * Generador de PDF propio, sin dependencias.
 *
 * Cubre lo que necesita el sistema: texto con las fuentes base de PDF
 * (Helvetica y Times), rectángulos, líneas, imágenes JPEG y códigos QR
 * dibujados como vectores (nítidos a cualquier tamaño de impresión).
 * Sustituye a TCPDF: pesa unos pocos KB en lugar de varios MB, lo que
 * importa en un hosting compartido.
 *
 * Unidades: milímetros. Origen: esquina superior izquierda.
 */
final class Pdf
{
    const MM = 2.834645669;   // 1 mm en puntos PostScript

    /** @var array<int,string> objetos del documento */
    private $objects = array();
    /** @var array<int,string> flujos de contenido, uno por página */
    private $pages = array();
    /** @var string contenido de la página en curso */
    private $buffer = '';
    /** @var float ancho y alto de página en puntos */
    private $w = 595.28;
    private $h = 841.89;
    /** @var array imágenes embebidas */
    private $images = array();
    /** @var string fuente activa */
    private $font = 'Helvetica';
    private $size = 10.0;
    /** @var array<string,array> tablas de anchos */
    private static $widths = null;
    /** @var string */
    private $title = 'MenúGold';

    /** @param string $size 'A4', 'A5', 'letter' o "80x200" en milímetros */
    public function __construct($size = 'A4', $landscape = false)
    {
        $sizes = array(
            'a4'     => array(210.0, 297.0),
            'a5'     => array(148.0, 210.0),
            'letter' => array(215.9, 279.4),
        );
        $key = strtolower((string)$size);
        if (isset($sizes[$key])) {
            list($mmW, $mmH) = $sizes[$key];
        } elseif (preg_match('/^(\d+(?:\.\d+)?)x(\d+(?:\.\d+)?)$/i', $key, $m)) {
            $mmW = (float)$m[1];
            $mmH = (float)$m[2];
        } else {
            list($mmW, $mmH) = $sizes['a4'];
        }
        if ($landscape) { $t = $mmW; $mmW = $mmH; $mmH = $t; }
        $this->w = $mmW * self::MM;
        $this->h = $mmH * self::MM;
        self::loadWidths();
    }

    public function setTitle($t) { $this->title = $t; }

    public function pageWidth()  { return $this->w / self::MM; }
    public function pageHeight() { return $this->h / self::MM; }

    public function addPage()
    {
        if ($this->buffer !== '' || count($this->pages) === 0) {
            $this->pages[] = $this->buffer;
            $this->buffer = '';
        }
        return $this;
    }

    /* ---------------- Dibujo ---------------- */

    public function setFont($family = 'Helvetica', $style = '', $size = 10)
    {
        $family = strtolower($family) === 'times' ? 'Times' : 'Helvetica';
        if ($family === 'Times') {
            $this->font = $style === 'B' ? 'Times-Bold' : ($style === 'I' ? 'Times-Italic' : 'Times-Roman');
        } else {
            $this->font = $style === 'B' ? 'Helvetica-Bold' : ($style === 'I' ? 'Helvetica-Oblique' : 'Helvetica');
        }
        $this->size = (float)$size;
        return $this;
    }

    public function setFillColor($hex)
    {
        list($r, $g, $b) = Image::hexToRgb($hex);
        $this->buffer .= sprintf("%.3F %.3F %.3F rg\n", $r / 255, $g / 255, $b / 255);
        return $this;
    }

    public function setDrawColor($hex)
    {
        list($r, $g, $b) = Image::hexToRgb($hex);
        $this->buffer .= sprintf("%.3F %.3F %.3F RG\n", $r / 255, $g / 255, $b / 255);
        return $this;
    }

    public function setLineWidth($mm)
    {
        $this->buffer .= sprintf("%.3F w\n", $mm * self::MM);
        return $this;
    }

    public function rect($x, $y, $w, $h, $style = 'F', $radius = 0)
    {
        $px = $x * self::MM;
        $py = $this->h - ($y + $h) * self::MM;
        $pw = $w * self::MM;
        $ph = $h * self::MM;
        $op = $style === 'F' ? 'f' : ($style === 'D' ? 'S' : 'B');

        if ($radius > 0) {
            $r = min($radius * self::MM, $pw / 2, $ph / 2);
            $k = $r * 0.5523;
            $this->buffer .= sprintf("%.2F %.2F m\n", $px + $r, $py);
            $this->buffer .= sprintf("%.2F %.2F l\n", $px + $pw - $r, $py);
            $this->buffer .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c\n", $px + $pw - $r + $k, $py, $px + $pw, $py + $r - $k, $px + $pw, $py + $r);
            $this->buffer .= sprintf("%.2F %.2F l\n", $px + $pw, $py + $ph - $r);
            $this->buffer .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c\n", $px + $pw, $py + $ph - $r + $k, $px + $pw - $r + $k, $py + $ph, $px + $pw - $r, $py + $ph);
            $this->buffer .= sprintf("%.2F %.2F l\n", $px + $r, $py + $ph);
            $this->buffer .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c\n", $px + $r - $k, $py + $ph, $px, $py + $ph - $r + $k, $px, $py + $ph - $r);
            $this->buffer .= sprintf("%.2F %.2F l\n", $px, $py + $r);
            $this->buffer .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c\n", $px, $py + $r - $k, $px + $r - $k, $py, $px + $r, $py);
            $this->buffer .= $op . "\n";
        } else {
            $this->buffer .= sprintf("%.2F %.2F %.2F %.2F re %s\n", $px, $py, $pw, $ph, $op);
        }
        return $this;
    }

    public function line($x1, $y1, $x2, $y2)
    {
        $this->buffer .= sprintf("%.2F %.2F m %.2F %.2F l S\n",
            $x1 * self::MM, $this->h - $y1 * self::MM, $x2 * self::MM, $this->h - $y2 * self::MM);
        return $this;
    }

    /** @param string $align L, C o R (respecto a $x, con ancho $w) */
    public function text($x, $y, $text, $align = 'L', $w = 0)
    {
        $encoded = $this->encode($text);
        if ($encoded === '') { return $this; }
        $tw = $this->stringWidth($text);
        $px = $x * self::MM;
        if ($align === 'C')      { $px += (($w * self::MM) - $tw) / 2; }
        elseif ($align === 'R')  { $px += ($w * self::MM) - $tw; }
        $py = $this->h - $y * self::MM;
        $this->buffer .= "BT /F" . $this->fontIndex() . " " . sprintf('%.2F', $this->size) . " Tf "
                       . sprintf('%.2F %.2F', $px, $py) . " Td (" . $this->escape($encoded) . ") Tj ET\n";
        return $this;
    }

    /** Texto ajustado a un ancho, devuelve la Y final. */
    public function textBlock($x, $y, $w, $text, $lineHeight = null)
    {
        $lh = $lineHeight !== null ? $lineHeight : ($this->size * 1.35 / self::MM);
        foreach ($this->wrap($text, $w) as $line) {
            $this->text($x, $y, $line);
            $y += $lh;
        }
        return $y;
    }

    public function wrap($text, $maxWidthMm)
    {
        $maxPt = $maxWidthMm * self::MM;
        $words = preg_split('/\s+/u', trim((string)$text));
        $lines = array();
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($this->stringWidth($candidate) <= $maxPt || $current === '') {
                $current = $candidate;
            } else {
                $lines[] = $current;
                $current = $word;
            }
        }
        if ($current !== '') { $lines[] = $current; }
        return $lines;
    }

    /** Ancho de la cadena en puntos, con la fuente y tamaño activos. */
    public function stringWidth($text)
    {
        $s = $this->encode($text);
        $table = self::$widths[$this->metricKey()];
        $total = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($s[$i]);
            $total += isset($table[$c]) ? $table[$c] : 500;
        }
        return $total * $this->size / 1000;
    }

    public function textWidthMm($text)
    {
        return $this->stringWidth($text) / self::MM;
    }

    /**
     * Dibuja un QR como vectores a partir de su matriz (filas de '1'/'0').
     * Nitidez perfecta al imprimir, sin depender de imágenes.
     */
    public function qr(array $matrix, $x, $y, $size, $color = '#000000', $quietZone = 4)
    {
        $n = count($matrix);
        if ($n === 0) { return $this; }
        $total = $n + $quietZone * 2;
        $cell = $size / $total;
        $this->setFillColor($color);
        for ($row = 0; $row < $n; $row++) {
            $line = $matrix[$row];
            $col = 0;
            while ($col < $n) {
                if ($line[$col] !== '1') { $col++; continue; }
                $run = 1;
                while ($col + $run < $n && $line[$col + $run] === '1') { $run++; }
                $this->rect(
                    $x + ($quietZone + $col) * $cell,
                    $y + ($quietZone + $row) * $cell,
                    $cell * $run + 0.02,
                    $cell + 0.02,
                    'F'
                );
                $col += $run;
            }
        }
        return $this;
    }

    /** Inserta una imagen desde disco (se recodifica a JPEG con GD). */
    public function image($path, $x, $y, $w, $h = null)
    {
        if (!is_file($path) || !function_exists('imagecreatetruecolor')) { return $this; }
        $mime = Image::detectMime($path);
        switch ($mime) {
            case 'image/jpeg': $im = @imagecreatefromjpeg($path); break;
            case 'image/png':  $im = @imagecreatefrompng($path); break;
            case 'image/webp': $im = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false; break;
            case 'image/gif':  $im = @imagecreatefromgif($path); break;
            default: return $this;
        }
        if (!$im) { return $this; }

        $iw = imagesx($im);
        $ih = imagesy($im);
        // Fondo blanco para conservar transparencias sin manchas.
        $flat = imagecreatetruecolor($iw, $ih);
        imagefilledrectangle($flat, 0, 0, $iw, $ih, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $im, 0, 0, 0, 0, $iw, $ih);
        imagedestroy($im);
        ob_start();
        imagejpeg($flat, null, 88);
        $data = ob_get_clean();
        imagedestroy($flat);

        if ($h === null) { $h = $w * ($ih / $iw); }
        $key = 'I' . (count($this->images) + 1);
        $this->images[$key] = array('data' => $data, 'w' => $iw, 'h' => $ih);

        $this->buffer .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n",
            $w * self::MM, $h * self::MM, $x * self::MM, $this->h - ($y + $h) * self::MM, $key);
        return $this;
    }

    /* ---------------- Salida ---------------- */

    public function output()
    {
        $this->addPage();
        $pages = array_values(array_filter($this->pages, function ($p) { return $p !== null; }));
        if (!$pages) { $pages = array(''); }

        $fonts = array('Helvetica', 'Helvetica-Bold', 'Helvetica-Oblique', 'Times-Roman', 'Times-Bold', 'Times-Italic');

        $this->objects = array();
        $pageCount = count($pages);

        // 1 catálogo, 2 páginas, luego 2 objetos por página, fuentes e imágenes.
        $firstPageObj = 3;
        $contentObjs = array();
        $pageObjs = array();
        for ($i = 0; $i < $pageCount; $i++) {
            $pageObjs[]   = $firstPageObj + $i * 2;
            $contentObjs[] = $firstPageObj + $i * 2 + 1;
        }
        $fontFirst = $firstPageObj + $pageCount * 2;
        $imageFirst = $fontFirst + count($fonts);

        $resFonts = array();
        foreach ($fonts as $i => $f) { $resFonts[] = '/F' . ($i + 1) . ' ' . ($fontFirst + $i) . ' 0 R'; }
        $resImages = array();
        $i = 0;
        foreach ($this->images as $key => $img) { $resImages[] = '/' . $key . ' ' . ($imageFirst + $i) . ' 0 R'; $i++; }

        $resources = '<< /ProcSet [/PDF /Text /ImageC] /Font << ' . implode(' ', $resFonts) . ' >>'
                   . ($resImages ? ' /XObject << ' . implode(' ', $resImages) . ' >>' : '') . ' >>';

        $this->objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = array();
        foreach ($pageObjs as $p) { $kids[] = $p . ' 0 R'; }
        $this->objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';

        foreach ($pages as $i => $content) {
            $this->objects[$pageObjs[$i]] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                . sprintf('%.2F %.2F', $this->w, $this->h) . '] /Resources ' . $resources
                . ' /Contents ' . $contentObjs[$i] . ' 0 R >>';
            $stream = $content;
            $this->objects[$contentObjs[$i]] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        }

        foreach ($fonts as $i => $f) {
            $this->objects[$fontFirst + $i] = '<< /Type /Font /Subtype /Type1 /BaseFont /' . $f . ' /Encoding /WinAnsiEncoding >>';
        }

        $i = 0;
        foreach ($this->images as $key => $img) {
            $this->objects[$imageFirst + $i] = '<< /Type /XObject /Subtype /Image /Width ' . $img['w']
                . ' /Height ' . $img['h'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '
                . strlen($img['data']) . " >>\nstream\n" . $img['data'] . "\nendstream";
            $i++;
        }

        // Ensamblado con tabla de referencias cruzadas.
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = array();
        ksort($this->objects);
        foreach ($this->objects as $num => $body) {
            $offsets[$num] = strlen($out);
            $out .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }
        $maxObj = max(array_keys($this->objects));
        $xrefPos = strlen($out);
        $out .= "xref\n0 " . ($maxObj + 1) . "\n0000000000 65535 f \n";
        for ($n = 1; $n <= $maxObj; $n++) {
            $out .= sprintf("%010d 00000 n \n", isset($offsets[$n]) ? $offsets[$n] : 0);
        }
        $out .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R /Info << /Title ("
              . $this->escape($this->encode($this->title)) . ") /Producer (MenuGold) /CreationDate (D:"
              . date('YmdHis') . ") >> >>\nstartxref\n" . $xrefPos . "\n%%EOF";
        return $out;
    }

    public function save($path)
    {
        return (bool)@file_put_contents($path, $this->output());
    }

    public function response($filename = 'documento.pdf', $inline = true)
    {
        return Response::make($this->output(), 200, array(
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '', $filename) . '"',
            'Cache-Control'       => 'private, max-age=0, must-revalidate',
        ));
    }

    /* ---------------- Interno ---------------- */

    private function fontIndex()
    {
        $map = array('Helvetica' => 1, 'Helvetica-Bold' => 2, 'Helvetica-Oblique' => 3,
                     'Times-Roman' => 4, 'Times-Bold' => 5, 'Times-Italic' => 6);
        return isset($map[$this->font]) ? $map[$this->font] : 1;
    }

    private function metricKey()
    {
        if ($this->font === 'Helvetica-Bold') { return 'helvB'; }
        if ($this->font === 'Times-Bold')     { return 'timesB'; }
        if (strpos($this->font, 'Times') === 0) { return 'times'; }
        return 'helv';
    }

    /** UTF-8 → WinAnsi (CP1252), que es la codificación de las fuentes base. */
    private function encode($text)
    {
        $text = (string)$text;
        if ($text === '') { return ''; }
        if (function_exists('iconv')) {
            $out = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $text);
            if ($out !== false) { return $out; }
        }
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        }
        return preg_replace('/[^\x20-\x7E]/', '', $text);
    }

    private function escape($s)
    {
        return str_replace(array('\\', '(', ')', "\r", "\n"), array('\\\\', '\\(', '\\)', '', ' '), $s);
    }

    /** Métricas oficiales de las fuentes base de PDF (unidades por millar). */
    private static function loadWidths()
    {
        if (self::$widths !== null) { return; }

        $helv = '278 278 355 556 556 889 667 191 333 333 389 584 278 333 278 278 556 556 556 556 556 556 556 556 556 556 278 278 584 584 584 556 1015 667 667 722 722 667 611 778 722 278 500 667 556 833 722 778 667 778 722 667 611 722 667 944 667 667 611 278 278 278 469 556 333 556 556 500 556 556 278 556 556 222 222 500 222 833 556 556 556 556 333 500 278 556 500 722 500 500 500 334 260 334 584';
        $helvB = '278 333 474 556 556 889 722 238 333 333 389 584 278 333 278 278 556 556 556 556 556 556 556 556 556 556 333 333 584 584 584 611 975 722 722 722 722 667 611 778 722 278 556 722 611 833 722 778 667 778 722 667 611 722 667 944 667 667 611 333 278 333 584 556 333 556 611 556 611 556 333 611 611 278 278 556 278 889 611 611 611 611 389 556 333 611 556 778 556 556 500 389 280 389 584';
        $times = '250 333 408 500 500 833 778 180 333 333 500 564 250 333 250 278 500 500 500 500 500 500 500 500 500 500 278 278 564 564 564 444 921 722 667 667 722 611 556 722 722 333 389 722 611 889 722 722 556 722 667 556 611 722 722 944 722 722 611 333 278 333 469 500 333 444 500 444 500 444 333 500 500 278 278 500 278 778 500 500 500 500 333 389 278 500 500 722 500 500 444 480 200 480 541';
        $timesB = '250 333 555 500 500 1000 833 278 333 333 500 570 250 333 250 278 500 500 500 500 500 500 500 500 500 500 333 333 570 570 570 500 930 722 667 722 722 667 611 778 778 389 500 778 667 944 722 778 611 778 722 556 667 722 722 1000 722 722 667 333 278 333 581 500 333 500 556 444 556 444 333 500 556 278 333 556 278 833 556 500 556 556 444 389 333 556 500 722 500 500 444 394 220 394 520';

        self::$widths = array(
            'helv'   => self::table($helv,   556),
            'helvB'  => self::table($helvB,  556),
            'times'  => self::table($times,  500),
            'timesB' => self::table($timesB, 500),
        );
    }

    /**
     * Convierte la lista de anchos ASCII (32-126) en una tabla por byte,
     * asignando a las vocales acentuadas y la eñe el ancho de su letra base.
     */
    private static function table($list, $default)
    {
        $values = array_map('intval', preg_split('/\s+/', trim($list)));
        $t = array();
        for ($i = 0; $i < 256; $i++) { $t[$i] = $default; }
        foreach ($values as $i => $w) { $t[32 + $i] = $w; }

        // WinAnsi: acentuadas comparten el avance de su letra base.
        $base = array(
            0xC0 => 'A', 0xC1 => 'A', 0xC2 => 'A', 0xC3 => 'A', 0xC4 => 'A', 0xC5 => 'A',
            0xC7 => 'C', 0xC8 => 'E', 0xC9 => 'E', 0xCA => 'E', 0xCB => 'E',
            0xCC => 'I', 0xCD => 'I', 0xCE => 'I', 0xCF => 'I',
            0xD1 => 'N', 0xD2 => 'O', 0xD3 => 'O', 0xD4 => 'O', 0xD5 => 'O', 0xD6 => 'O',
            0xD9 => 'U', 0xDA => 'U', 0xDB => 'U', 0xDC => 'U', 0xDD => 'Y',
            0xE0 => 'a', 0xE1 => 'a', 0xE2 => 'a', 0xE3 => 'a', 0xE4 => 'a', 0xE5 => 'a',
            0xE7 => 'c', 0xE8 => 'e', 0xE9 => 'e', 0xEA => 'e', 0xEB => 'e',
            0xEC => 'i', 0xED => 'i', 0xEE => 'i', 0xEF => 'i',
            0xF1 => 'n', 0xF2 => 'o', 0xF3 => 'o', 0xF4 => 'o', 0xF5 => 'o', 0xF6 => 'o',
            0xF9 => 'u', 0xFA => 'u', 0xFB => 'u', 0xFC => 'u', 0xFD => 'y', 0xFF => 'y',
            0xBF => '?', 0xA1 => '!', 0xAB => '"', 0xBB => '"', 0xB0 => 'o',
        );
        foreach ($base as $code => $ch) { $t[$code] = $t[ord($ch)]; }
        $t[0x80] = $t[ord('E')];   // €
        $t[0x93] = $t[ord('"')];   // “
        $t[0x94] = $t[ord('"')];   // ”
        $t[0x92] = $t[ord("'")];   // ’
        $t[0x96] = $t[ord('-')];   // –
        $t[0x97] = $t[ord('-')] * 2;
        return $t;
    }
}
