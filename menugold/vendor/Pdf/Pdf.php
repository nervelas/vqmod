<?php
declare(strict_types=1);

namespace MenuGold\Vendor\Pdf;

/**
 * Generador de documentos PDF en PHP puro (sin extensiones externas).
 *
 * Soporta paginas de cualquier tamano, las 14 fuentes base de PDF con
 * codificacion WinAnsi (acentos del espanol incluidos), colores, rectangulos
 * redondeados, lineas, circulos, tablas, imagenes JPEG/PNG y compresion zlib.
 *
 * Es la base de los QR imprimibles, tickets y reportes de MenuGold.
 */
class Pdf
{
    /** Tamanos en puntos (1 pt = 1/72"). */
    public const TAMANOS = [
        'A4'     => [595.28, 841.89],
        'A5'     => [419.53, 595.28],
        'A6'     => [297.64, 419.53],
        'LETTER' => [612.00, 792.00],
        'LEGAL'  => [612.00, 1008.00],
        'CM10'   => [283.46, 283.46],   // 10 x 10 cm
        'TICKET80' => [226.77, 0],      // 80 mm de ancho, alto variable
        'TICKET58' => [164.41, 0],      // 58 mm de ancho, alto variable
    ];

    protected array $paginas = [];
    protected int $paginaActual = -1;
    protected float $ancho = 595.28;
    protected float $alto = 841.89;
    protected float $margen = 36.0;
    protected string $buffer = '';
    protected array $objetos = [];
    protected array $imagenes = [];
    protected array $fuentesUsadas = [];
    protected bool $comprimir = true;

    protected string $fuente = 'helvetica';
    protected float $tamFuente = 11.0;
    protected array $colorTexto = [0, 0, 0];
    protected array $colorRelleno = [0, 0, 0];
    protected array $colorTrazo = [0, 0, 0];
    protected float $grosorTrazo = 0.6;
    public float $y = 0.0;
    public float $x = 0.0;

    protected array $meta = ['titulo' => '', 'autor' => 'MenuGold', 'asunto' => '', 'creador' => 'MenuGold'];

    protected const FUENTES = [
        'helvetica'    => 'Helvetica',
        'helvetica-b'  => 'Helvetica-Bold',
        'helvetica-i'  => 'Helvetica-Oblique',
        'helvetica-bi' => 'Helvetica-BoldOblique',
        'times'        => 'Times-Roman',
        'times-b'      => 'Times-Bold',
        'times-i'      => 'Times-Italic',
        'times-bi'     => 'Times-BoldItalic',
        'courier'      => 'Courier',
        'courier-b'    => 'Courier-Bold',
    ];

    public function __construct(string $tamano = 'A4', string $orientacion = 'P', float $margen = 36.0)
    {
        $this->setTamano($tamano, $orientacion);
        $this->margen = $margen;
        $this->comprimir = function_exists('gzcompress');
    }

    public function setTamano(string $tamano, string $orientacion = 'P', float $altoPersonalizado = 0): void
    {
        $t = self::TAMANOS[strtoupper($tamano)] ?? self::TAMANOS['A4'];
        $w = $t[0];
        $h = $t[1] > 0 ? $t[1] : ($altoPersonalizado > 0 ? $altoPersonalizado : 800.0);
        if (strtoupper($orientacion) === 'L') { [$w, $h] = [$h, $w]; }
        $this->ancho = $w;
        $this->alto  = $h;
    }

    public function meta(string $clave, string $valor): void
    {
        if (array_key_exists($clave, $this->meta)) $this->meta[$clave] = $valor;
    }

    public function anchoUtil(): float { return $this->ancho - 2 * $this->margen; }
    public function ancho(): float { return $this->ancho; }
    public function alto(): float { return $this->alto; }
    public function margen(): float { return $this->margen; }
    public function setMargen(float $m): void { $this->margen = $m; }

    // =====================================================================
    //  Paginas
    // =====================================================================
    public function addPage(?string $tamano = null, string $orientacion = 'P'): void
    {
        if ($tamano !== null) $this->setTamano($tamano, $orientacion);
        $this->paginas[] = ['w' => $this->ancho, 'h' => $this->alto, 'c' => ''];
        $this->paginaActual = count($this->paginas) - 1;
        $this->y = $this->margen;
        $this->x = $this->margen;
        $this->aplicarColorTexto();
    }

    public function paginas(): int { return count($this->paginas); }

    protected function w(string $s): void
    {
        if ($this->paginaActual < 0) $this->addPage();
        $this->paginas[$this->paginaActual]['c'] .= $s . "\n";
    }

    /** Salta a nueva pagina si no queda espacio para $alto puntos. */
    public function espacio(float $alto): bool
    {
        if ($this->y + $alto > $this->alto - $this->margen) {
            $this->addPage();
            return true;
        }
        return false;
    }

    // =====================================================================
    //  Colores y trazos
    // =====================================================================
    public static function hex2rgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) return [0, 0, 0];
        return [(int)hexdec(substr($hex,0,2)), (int)hexdec(substr($hex,2,2)), (int)hexdec(substr($hex,4,2))];
    }

    public function setColorTexto($c): void
    {
        $this->colorTexto = is_array($c) ? $c : self::hex2rgb((string)$c);
        $this->aplicarColorTexto();
    }

    protected function aplicarColorTexto(): void
    {
        [$r, $g, $b] = $this->colorTexto;
        $this->w(sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255));
    }

    public function setRelleno($c): void
    {
        $this->colorRelleno = is_array($c) ? $c : self::hex2rgb((string)$c);
    }

    public function setTrazo($c, float $grosor = 0.6): void
    {
        $this->colorTrazo = is_array($c) ? $c : self::hex2rgb((string)$c);
        $this->grosorTrazo = $grosor;
    }

    protected function cRelleno(): string
    {
        [$r, $g, $b] = $this->colorRelleno;
        return sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
    }

    protected function cTrazo(): string
    {
        [$r, $g, $b] = $this->colorTrazo;
        return sprintf('%.3F %.3F %.3F RG %.2F w', $r / 255, $g / 255, $b / 255, $this->grosorTrazo);
    }

    /** Convierte coordenada Y "desde arriba" al sistema PDF. */
    protected function ty(float $y): float { return $this->alto - $y; }

    // =====================================================================
    //  Formas
    // =====================================================================
    public function rect(float $x, float $y, float $w, float $h, string $modo = 'F'): void
    {
        $op = $this->op($modo);
        $this->w($this->estilo($modo) . sprintf(' %.2F %.2F %.2F %.2F re %s', $x, $this->ty($y + $h), $w, $h, $op));
        $this->aplicarColorTexto();
    }

    public function roundRect(float $x, float $y, float $w, float $h, float $r, string $modo = 'F'): void
    {
        $r = min($r, $w / 2, $h / 2);
        $k = 0.5523 * $r;
        $y2 = $this->ty($y + $h);
        $s = $this->estilo($modo) . ' ';
        $s .= sprintf('%.2F %.2F m', $x + $r, $y2);
        $s .= sprintf(' %.2F %.2F l', $x + $w - $r, $y2);
        $s .= sprintf(' %.2F %.2F %.2F %.2F %.2F %.2F c', $x + $w - $r + $k, $y2, $x + $w, $y2 + $r - $k, $x + $w, $y2 + $r);
        $s .= sprintf(' %.2F %.2F l', $x + $w, $y2 + $h - $r);
        $s .= sprintf(' %.2F %.2F %.2F %.2F %.2F %.2F c', $x + $w, $y2 + $h - $r + $k, $x + $w - $r + $k, $y2 + $h, $x + $w - $r, $y2 + $h);
        $s .= sprintf(' %.2F %.2F l', $x + $r, $y2 + $h);
        $s .= sprintf(' %.2F %.2F %.2F %.2F %.2F %.2F c', $x + $r - $k, $y2 + $h, $x, $y2 + $h - $r + $k, $x, $y2 + $h - $r);
        $s .= sprintf(' %.2F %.2F l', $x, $y2 + $r);
        $s .= sprintf(' %.2F %.2F %.2F %.2F %.2F %.2F c', $x, $y2 + $r - $k, $x + $r - $k, $y2, $x + $r, $y2);
        $s .= ' ' . $this->op($modo);
        $this->w($s);
        $this->aplicarColorTexto();
    }

    public function circle(float $cx, float $cy, float $r, string $modo = 'F'): void
    {
        $k = 0.5523 * $r;
        $y = $this->ty($cy);
        $s = $this->estilo($modo) . ' ';
        $s .= sprintf('%.2F %.2F m', $cx - $r, $y);
        $s .= sprintf(' %.2F %.2F %.2F %.2F %.2F %.2F c', $cx - $r, $y + $k, $cx - $k, $y + $r, $cx, $y + $r);
        $s .= sprintf(' %.2F %.2F %.2F %.2F %.2F %.2F c', $cx + $k, $y + $r, $cx + $r, $y + $k, $cx + $r, $y);
        $s .= sprintf(' %.2F %.2F %.2F %.2F %.2F %.2F c', $cx + $r, $y - $k, $cx + $k, $y - $r, $cx, $y - $r);
        $s .= sprintf(' %.2F %.2F %.2F %.2F %.2F %.2F c', $cx - $k, $y - $r, $cx - $r, $y - $k, $cx - $r, $y);
        $s .= ' ' . $this->op($modo);
        $this->w($s);
        $this->aplicarColorTexto();
    }

    public function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->w(sprintf('%s %.2F %.2F m %.2F %.2F l S', $this->cTrazo(), $x1, $this->ty($y1), $x2, $this->ty($y2)));
        $this->aplicarColorTexto();
    }

    /** Linea punteada (para lineas de corte). */
    public function dashed(float $x1, float $y1, float $x2, float $y2, float $on = 3, float $off = 3): void
    {
        $this->w(sprintf('%s [%.1F %.1F] 0 d %.2F %.2F m %.2F %.2F l S [] 0 d',
            $this->cTrazo(), $on, $off, $x1, $this->ty($y1), $x2, $this->ty($y2)));
        $this->aplicarColorTexto();
    }

    protected function estilo(string $modo): string
    {
        $modo = strtoupper($modo);
        if ($modo === 'F')  return $this->cRelleno();
        if ($modo === 'D')  return $this->cTrazo();
        return $this->cRelleno() . ' ' . $this->cTrazo();
    }

    protected function op(string $modo): string
    {
        $modo = strtoupper($modo);
        if ($modo === 'F') return 'f';
        if ($modo === 'D') return 'S';
        return 'B';
    }

    // =====================================================================
    //  Texto
    // =====================================================================
    public function setFuente(string $familia = 'helvetica', float $tam = 11.0): void
    {
        $familia = strtolower($familia);
        if (!isset(self::FUENTES[$familia])) $familia = 'helvetica';
        $this->fuente = $familia;
        $this->tamFuente = $tam;
        $this->fuentesUsadas[$familia] = true;
    }

    public function fuenteActual(): string { return $this->fuente; }
    public function tamActual(): float { return $this->tamFuente; }

    /** Escribe texto en (x, y) donde y es la linea base desde arriba. */
    public function text(float $x, float $y, string $txt): void
    {
        $this->fuentesUsadas[$this->fuente] = true;
        $this->w(sprintf('BT /F%s %.2F Tf %.2F %.2F Td (%s) Tj ET',
            $this->idFuente($this->fuente), $this->tamFuente, $x, $this->ty($y), $this->escapar($txt)));
    }

    /**
     * Celda de texto con alineacion. $align: L, C, R.
     * Devuelve el alto consumido.
     */
    public function cell(float $x, float $y, float $w, string $txt, string $align = 'L', float $alto = 0): float
    {
        $alto = $alto > 0 ? $alto : $this->tamFuente * 1.35;
        $tw = $this->anchoTexto($txt);
        $tx = $x;
        if ($align === 'C') $tx = $x + ($w - $tw) / 2;
        elseif ($align === 'R') $tx = $x + $w - $tw;
        $baseline = $y + $alto - ($alto - $this->tamFuente * 0.72) / 2 - $this->tamFuente * 0.18;
        $this->text($tx, $baseline, $txt);
        return $alto;
    }

    /** Texto multilinea ajustado al ancho. Devuelve el alto total usado. */
    public function multiCell(float $x, float $y, float $w, string $txt, float $interlineado = 1.35, string $align = 'L'): float
    {
        $lineas = $this->ajustar($txt, $w);
        $lh = $this->tamFuente * $interlineado;
        foreach ($lineas as $i => $l) {
            $this->cell($x, $y + $i * $lh, $w, $l, $align, $lh);
        }
        return count($lineas) * $lh;
    }

    /** Divide un texto en lineas que caben en $w. @return array<int,string> */
    public function ajustar(string $txt, float $w): array
    {
        $txt = str_replace(["\r\n", "\r"], "\n", $txt);
        $salida = [];
        foreach (explode("\n", $txt) as $parrafo) {
            $palabras = preg_split('/\s+/u', trim($parrafo)) ?: [];
            $linea = '';
            foreach ($palabras as $p) {
                if ($p === '') continue;
                $prueba = $linea === '' ? $p : $linea . ' ' . $p;
                if ($this->anchoTexto($prueba) <= $w || $linea === '') {
                    $linea = $prueba;
                    // Palabra sola mas ancha que la celda: cortar
                    while ($this->anchoTexto($linea) > $w && mb_strlen($linea) > 1) {
                        $corte = mb_strlen($linea);
                        while ($corte > 1 && $this->anchoTexto(mb_substr($linea, 0, $corte)) > $w) $corte--;
                        $salida[] = mb_substr($linea, 0, $corte);
                        $linea = mb_substr($linea, $corte);
                    }
                } else {
                    $salida[] = $linea;
                    $linea = $p;
                }
            }
            $salida[] = $linea;
        }
        return $salida ?: [''];
    }

    /** Ancho aproximado del texto en puntos (metricas de las fuentes base). */
    public function anchoTexto(string $txt): float
    {
        $m = FontMetrics::widths($this->fuente);
        $s = $this->winAnsi($txt);
        $total = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $total += $m[ord($s[$i])] ?? 500;
        }
        return $total * $this->tamFuente / 1000;
    }

    /** Recorta un texto con puntos suspensivos si excede el ancho. */
    public function truncar(string $txt, float $w): string
    {
        if ($this->anchoTexto($txt) <= $w) return $txt;
        $n = mb_strlen($txt);
        while ($n > 1 && $this->anchoTexto(mb_substr($txt, 0, $n) . '...') > $w) $n--;
        return mb_substr($txt, 0, $n) . '...';
    }

    // =====================================================================
    //  QR vectorial
    // =====================================================================
    /**
     * Dibuja un QR (matriz de rectangulos de QrCode::rects) como vectores.
     * Nitidez perfecta al imprimir a cualquier tamano.
     */
    public function qr(array $rects, int $modulos, float $x, float $y, float $lado, $color = '#000000'): void
    {
        $u = $lado / max(1, $modulos);
        $this->setRelleno($color);
        $s = $this->cRelleno();
        foreach ($rects as $r) {
            [$rx, $ry, $rw] = $r;
            $s .= sprintf(' %.3F %.3F %.3F %.3F re', $x + $rx * $u, $this->ty($y + ($ry + 1) * $u), $rw * $u, $u);
        }
        $this->w($s . ' f');
        $this->aplicarColorTexto();
    }

    // =====================================================================
    //  Imagenes
    // =====================================================================
    /** Inserta una imagen JPEG o PNG. Devuelve true si se pudo. */
    public function image(string $ruta, float $x, float $y, float $w, float $h = 0): bool
    {
        if (!is_file($ruta)) return false;
        $key = md5($ruta);
        if (!isset($this->imagenes[$key])) {
            $info = $this->parseImagen($ruta);
            if (!$info) return false;
            $info['i'] = count($this->imagenes) + 1;
            $this->imagenes[$key] = $info;
        }
        $img = $this->imagenes[$key];
        if ($h <= 0) $h = $w * $img['h'] / max(1, $img['w']);
        $this->w(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q', $w, $h, $x, $this->ty($y + $h), $img['i']));
        return true;
    }

    /** Alto que tendria la imagen con un ancho dado. */
    public function imageHeight(string $ruta, float $w): float
    {
        $s = @getimagesize($ruta);
        if (!$s || empty($s[0])) return 0;
        return $w * $s[1] / $s[0];
    }

    protected function parseImagen(string $ruta): ?array
    {
        $tam = @getimagesize($ruta);
        if (!$tam) return null;
        $mime = (string)($tam['mime'] ?? '');
        $datos = (string)file_get_contents($ruta);

        if ($mime === 'image/jpeg') {
            $canales = (int)($tam['channels'] ?? 3);
            return [
                'w' => (int)$tam[0], 'h' => (int)$tam[1],
                'cs' => $canales === 4 ? 'DeviceCMYK' : ($canales === 1 ? 'DeviceGray' : 'DeviceRGB'),
                'bpc' => (int)($tam['bits'] ?? 8),
                'f' => 'DCTDecode', 'data' => $datos, 'smask' => null, 'pal' => '',
            ];
        }
        // PNG y otros: reconvertimos con GD a JPEG plano (simple y seguro)
        if (!function_exists('imagecreatefromstring')) return null;
        $img = @imagecreatefromstring($datos);
        if (!$img) return null;
        $w = imagesx($img); $h = imagesy($img);
        $fondo = imagecreatetruecolor($w, $h);
        imagefill($fondo, 0, 0, imagecolorallocate($fondo, 255, 255, 255));
        imagealphablending($fondo, true);
        imagecopy($fondo, $img, 0, 0, 0, 0, $w, $h);
        imagedestroy($img);
        ob_start();
        imagejpeg($fondo, null, 88);
        $jpg = (string)ob_get_clean();
        imagedestroy($fondo);
        return ['w' => $w, 'h' => $h, 'cs' => 'DeviceRGB', 'bpc' => 8, 'f' => 'DCTDecode', 'data' => $jpg, 'smask' => null, 'pal' => ''];
    }

    // =====================================================================
    //  Salida
    // =====================================================================
    public function output(): string
    {
        if (!$this->paginas) $this->addPage();
        $this->objetos = [];
        $this->buffer = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";

        $nPaginas = count($this->paginas);
        // Reserva: 1=Catalog, 2=Pages, 3..=paginas y contenidos
        $idxFuentes = [];
        $primeraFuente = 3 + $nPaginas * 2;
        $i = 0;
        foreach (array_keys($this->fuentesUsadas) as $f) {
            $idxFuentes[$f] = $primeraFuente + $i;
            $i++;
        }
        $primeraImagen = $primeraFuente + count($idxFuentes);
        $j = 0;
        foreach ($this->imagenes as $k => $img) {
            $this->imagenes[$k]['obj'] = $primeraImagen + $j;
            $j++;
        }
        $objInfo = $primeraImagen + count($this->imagenes);

        // 1: Catalogo
        $this->obj(1, "<< /Type /Catalog /Pages 2 0 R >>");
        // 2: Arbol de paginas
        $kids = [];
        for ($p = 0; $p < $nPaginas; $p++) $kids[] = (3 + $p * 2) . ' 0 R';
        $this->obj(2, "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count {$nPaginas} >>");

        // Recursos comunes
        $recFuentes = [];
        foreach ($idxFuentes as $f => $oid) $recFuentes[] = '/F' . $this->idFuente($f) . ' ' . $oid . ' 0 R';
        $recImgs = [];
        foreach ($this->imagenes as $img) $recImgs[] = '/I' . $img['i'] . ' ' . $img['obj'] . ' 0 R';
        $recursos = '<< /ProcSet [/PDF /Text /ImageC /ImageB] /Font << ' . implode(' ', $recFuentes) . ' >>'
            . ($recImgs ? ' /XObject << ' . implode(' ', $recImgs) . ' >>' : '') . ' >>';

        for ($p = 0; $p < $nPaginas; $p++) {
            $pg = $this->paginas[$p];
            $oidPag = 3 + $p * 2;
            $oidCont = $oidPag + 1;
            $this->obj($oidPag, sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources %s /Contents %d 0 R >>",
                $pg['w'], $pg['h'], $recursos, $oidCont
            ));
            $cont = $pg['c'];
            if ($this->comprimir) {
                $z = gzcompress($cont, 6);
                $this->obj($oidCont, "<< /Length " . strlen($z) . " /Filter /FlateDecode >>", $z);
            } else {
                $this->obj($oidCont, "<< /Length " . strlen($cont) . " >>", $cont);
            }
        }

        foreach ($idxFuentes as $f => $oid) {
            $this->obj($oid, "<< /Type /Font /Subtype /Type1 /BaseFont /" . self::FUENTES[$f]
                . " /Encoding /WinAnsiEncoding >>");
        }

        foreach ($this->imagenes as $img) {
            $d = "<< /Type /XObject /Subtype /Image /Width {$img['w']} /Height {$img['h']}"
               . " /ColorSpace /{$img['cs']} /BitsPerComponent {$img['bpc']} /Filter /{$img['f']}"
               . " /Length " . strlen($img['data']) . " >>";
            $this->obj($img['obj'], $d, $img['data']);
        }

        $fecha = 'D:' . date('YmdHis') . '+00\'00\'';
        $this->obj($objInfo, "<< /Title (" . $this->escapar($this->meta['titulo']) . ")"
            . " /Author (" . $this->escapar($this->meta['autor']) . ")"
            . " /Subject (" . $this->escapar($this->meta['asunto']) . ")"
            . " /Creator (" . $this->escapar($this->meta['creador']) . ")"
            . " /Producer (MenuGold PDF) /CreationDate ({$fecha}) >>");

        // Tabla de referencias cruzadas
        ksort($this->objetos);
        $total = max(array_keys($this->objetos));
        $xref = "xref\n0 " . ($total + 1) . "\n0000000000 65535 f \n";
        for ($n = 1; $n <= $total; $n++) {
            $xref .= sprintf("%010d 00000 n \n", $this->objetos[$n]['pos'] ?? 0);
        }
        $inicioXref = strlen($this->buffer);
        $this->buffer .= $xref;
        $this->buffer .= "trailer\n<< /Size " . ($total + 1) . " /Root 1 0 R /Info {$objInfo} 0 R >>\n";
        $this->buffer .= "startxref\n{$inicioXref}\n%%EOF\n";
        return $this->buffer;
    }

    protected function obj(int $id, string $dic, ?string $stream = null): void
    {
        $pos = strlen($this->buffer);
        $s = $id . " 0 obj\n" . $dic . "\n";
        if ($stream !== null) $s .= "stream\n" . $stream . "\nendstream\n";
        $s .= "endobj\n";
        $this->buffer .= $s;
        $this->objetos[$id] = ['pos' => $pos];
    }

    protected function idFuente(string $f): int
    {
        $lista = array_keys(self::FUENTES);
        $i = array_search($f, $lista, true);
        return $i === false ? 1 : ((int)$i + 1);
    }

    /** UTF-8 -> WinAnsi (CP1252), suficiente para el espanol. */
    public function winAnsi(string $s): string
    {
        if (function_exists('iconv')) {
            $r = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
            if ($r !== false) return $r;
        }
        if (function_exists('mb_convert_encoding')) {
            return (string)@mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        }
        return $s;
    }

    protected function escapar(string $s): string
    {
        $s = $this->winAnsi($s);
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $s);
    }
}
