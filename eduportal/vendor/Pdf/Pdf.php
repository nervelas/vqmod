<?php
declare(strict_types=1);

namespace Vendor\Pdf;

/**
 * Generador de PDF local (sin composer). API inspirada en FPDF, escrita para EduPortal.
 * Unidades en milimetros, origen en la esquina superior izquierda.
 * Fuentes base PDF (Helvetica / Times / Courier) con codificacion WinAnsi.
 */
class Pdf
{
    public const A4     = [210.0, 297.0];
    public const LETTER = [215.9, 279.4];

    protected float $k = 72 / 25.4;
    protected float $w;
    protected float $h;
    protected float $mL = 15.0;
    protected float $mR = 15.0;
    protected float $mT = 15.0;
    protected float $mB = 18.0;
    protected float $x = 0.0;
    protected float $y = 0.0;
    protected float $lasth = 0.0;
    protected int $page = 0;
    protected array $paginas = [];
    protected string $buffer = '';
    protected array $offsets = [];
    protected int $n = 0;
    protected ?string $generado = null;
    protected string $fuente = 'Helvetica';
    protected float $tam = 10.0;
    protected array $fuentesUsadas = [];
    protected array $imagenes = [];
    protected string $colorTexto = '0 g';
    protected string $colorRelleno = '0 g';
    protected string $colorTrazo = '0 G';
    protected float $grosor = 0.2;
    protected bool $subrayado = false;
    protected string $alias = '{nb}';
    protected bool $enFooter = false;
    protected array $enlaces = [];
    protected string $titulo = 'Documento';
    protected string $autor = 'EduPortal';

    public function __construct(array $tamano = self::A4, string $orientacion = 'P')
    {
        [$w, $h] = $tamano;
        if (strtoupper($orientacion) === 'L') {
            [$w, $h] = [$h, $w];
        }
        $this->w = $w;
        $this->h = $h;
        $this->lasth = 5.0;
    }

    // ---------------- Configuracion ----------------

    public function setMargenes(float $l, float $t, float $r, float $b): void
    {
        $this->mL = $l; $this->mT = $t; $this->mR = $r; $this->mB = $b;
    }

    public function setMeta(string $titulo, string $autor = 'EduPortal'): void
    {
        $this->titulo = $titulo;
        $this->autor  = $autor;
    }

    public function anchoUtil(): float { return $this->w - $this->mL - $this->mR; }
    public function ancho(): float { return $this->w; }
    public function alto(): float { return $this->h; }
    public function getX(): float { return $this->x; }
    public function getY(): float { return $this->y; }
    public function paginaActual(): int { return $this->page; }

    public function setXY(float $x, float $y): void { $this->x = $x; $this->y = $y; }
    public function setX(float $x): void { $this->x = $x; }
    public function setY(float $y, bool $resetX = true): void
    {
        $this->y = $y;
        if ($resetX) {
            $this->x = $this->mL;
        }
    }

    public function ln(?float $h = null): void
    {
        $this->x = $this->mL;
        $this->y += $h ?? $this->lasth;
    }

    public function setFuente(string $familia = 'Helvetica', string $estilo = '', float $tam = 10.0): void
    {
        $this->fuente = $this->resolverFuente($familia, $estilo);
        $this->subrayado = str_contains(strtoupper($estilo), 'U');
        $this->tam = $tam;
        $this->fuentesUsadas[$this->fuente] = true;
    }

    protected function resolverFuente(string $familia, string $estilo): string
    {
        $e = strtoupper(str_replace('U', '', $estilo));
        $fam = strtolower($familia);
        $b = str_contains($e, 'B');
        $i = str_contains($e, 'I');
        if (str_starts_with($fam, 'times')) {
            if ($b && $i) { return 'Times-BoldItalic'; }
            if ($b) { return 'Times-Bold'; }
            if ($i) { return 'Times-Italic'; }
            return 'Times-Roman';
        }
        if (str_starts_with($fam, 'courier')) {
            return $b ? 'Courier-Bold' : 'Courier';
        }
        if ($b && $i) { return 'Helvetica-BoldOblique'; }
        if ($b) { return 'Helvetica-Bold'; }
        if ($i) { return 'Helvetica-Oblique'; }
        return 'Helvetica';
    }

    public function setColorTexto(int $r, ?int $g = null, ?int $b = null): void
    {
        $this->colorTexto = $this->color($r, $g, $b, false);
    }

    public function setColorRelleno(int $r, ?int $g = null, ?int $b = null): void
    {
        $this->colorRelleno = $this->color($r, $g, $b, false);
        if ($this->page > 0) {
            $this->out($this->colorRelleno);
        }
    }

    public function setColorTrazo(int $r, ?int $g = null, ?int $b = null): void
    {
        $this->colorTrazo = $this->color($r, $g, $b, true);
        if ($this->page > 0) {
            $this->out($this->colorTrazo);
        }
    }

    public function setColorHex(string $hex, string $tipo = 'relleno'): void
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '000000';
        }
        $r = (int)hexdec(substr($hex, 0, 2));
        $g = (int)hexdec(substr($hex, 2, 2));
        $b = (int)hexdec(substr($hex, 4, 2));
        match ($tipo) {
            'texto' => $this->setColorTexto($r, $g, $b),
            'trazo' => $this->setColorTrazo($r, $g, $b),
            default => $this->setColorRelleno($r, $g, $b),
        };
    }

    protected function color(int $r, ?int $g, ?int $b, bool $trazo): string
    {
        $suf = $trazo ? 'G' : 'g';
        if ($g === null || $b === null) {
            return sprintf('%.3F %s', $r / 255, $suf);
        }
        return sprintf('%.3F %.3F %.3F %s', $r / 255, $g / 255, $b / 255, $trazo ? 'RG' : 'rg');
    }

    public function setGrosor(float $mm): void
    {
        $this->grosor = $mm;
        if ($this->page > 0) {
            $this->out(sprintf('%.2F w', $mm * $this->k));
        }
    }

    // ---------------- Paginas ----------------

    public function agregarPagina(): void
    {
        if ($this->page > 0) {
            $this->pieDePagina();
        }
        $this->page++;
        $this->paginas[$this->page] = '';
        $this->x = $this->mL;
        $this->y = $this->mT;
        $this->out($this->colorRelleno);
        $this->out($this->colorTrazo);
        $this->out(sprintf('%.2F w', $this->grosor * $this->k));
        $this->encabezado();
    }

    /** Punto de extension para el encabezado de cada pagina. */
    protected function encabezado(): void {}

    /** Punto de extension para el pie de cada pagina. */
    protected function pie(): void {}

    private function pieDePagina(): void
    {
        $this->enFooter = true;
        $yx = $this->y;
        $this->pie();
        $this->y = $yx;
        $this->enFooter = false;
    }

    public function saltoSiNecesario(float $altoNecesario): void
    {
        if ($this->y + $altoNecesario > $this->h - $this->mB && !$this->enFooter) {
            $this->agregarPagina();
        }
    }

    // ---------------- Texto ----------------

    public function anchoTexto(string $texto): float
    {
        $anchos = Metricas::anchos($this->fuente);
        $s = $this->cp1252($texto);
        $t = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $t += $anchos[ord($s[$i])] ?? 500;
        }
        return $t * $this->tam / 1000;
    }

    public function celda(
        float $w,
        float $h,
        string $texto = '',
        mixed $borde = 0,
        int $ln = 0,
        string $alinear = 'L',
        bool $relleno = false,
        ?string $enlace = null
    ): void {
        if ($w <= 0) {
            $w = $this->w - $this->mR - $this->x;
        }
        $s = '';
        if ($relleno || $borde === 1) {
            $op = $relleno ? ($borde === 1 ? 'B' : 'f') : 'S';
            $s .= sprintf(
                '%.2F %.2F %.2F %.2F re %s ',
                $this->x * $this->k,
                ($this->h - $this->y) * $this->k,
                $w * $this->k,
                -$h * $this->k,
                $op
            );
        }
        if (is_string($borde)) {
            $x = $this->x; $y = $this->y;
            if (str_contains($borde, 'L')) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $this->k, ($this->h - $y) * $this->k, $x * $this->k, ($this->h - ($y + $h)) * $this->k);
            }
            if (str_contains($borde, 'T')) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $this->k, ($this->h - $y) * $this->k, ($x + $w) * $this->k, ($this->h - $y) * $this->k);
            }
            if (str_contains($borde, 'R')) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', ($x + $w) * $this->k, ($this->h - $y) * $this->k, ($x + $w) * $this->k, ($this->h - ($y + $h)) * $this->k);
            }
            if (str_contains($borde, 'B')) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $this->k, ($this->h - ($y + $h)) * $this->k, ($x + $w) * $this->k, ($this->h - ($y + $h)) * $this->k);
            }
        }
        if ($texto !== '') {
            $ancho = $this->anchoTexto($texto);
            $dx = match ($alinear) {
                'C' => ($w - $ancho) / 2,
                'R' => $w - $ancho - 1.5,
                default => 1.5,
            };
            $fs = $this->tam / $this->k; // tamano de fuente en mm
            $base = $this->y + 0.5 * $h + 0.30 * $fs;
            $s .= 'q ' . $this->colorTexto . ' BT /F' . $this->indiceFuente($this->fuente) . ' '
                . sprintf('%.2F', $this->tam) . ' Tf ';
            $s .= sprintf(
                '%.2F %.2F Td (%s) Tj ET ',
                ($this->x + $dx) * $this->k,
                ($this->h - $base) * $this->k,
                $this->escapar($this->cp1252($texto))
            );
            if ($this->subrayado) {
                $s .= $this->lineaSubrayado($this->x + $dx, $base + 0.22 * $fs, $ancho, $fs);
            }
            $s .= 'Q ';
            if ($enlace !== null && $enlace !== '') {
                $this->enlaces[$this->page][] = [$this->x + $dx, $this->y, $ancho, $h, $enlace];
            }
        }
        if ($s !== '') {
            $this->out(trim($s));
        }
        $this->lasth = $h;
        if ($ln > 0) {
            $this->y += $h;
            $this->x = $ln === 1 ? $this->mL : $this->x;
        } else {
            $this->x += $w;
        }
    }

    protected function lineaSubrayado(float $x, float $y, float $ancho, float $fs): string
    {
        return sprintf(
            '%.2F %.2F %.2F %.2F re f ',
            $x * $this->k,
            ($this->h - $y) * $this->k,
            $ancho * $this->k,
            -max(0.4, 0.06 * $fs * $this->k)
        );
    }

    /** @return string[] lineas resultantes */
    public function dividirTexto(string $texto, float $ancho): array
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $salida = [];
        foreach (explode("\n", $texto) as $parrafo) {
            $palabras = preg_split('/\s+/', trim($parrafo)) ?: [];
            $linea = '';
            foreach ($palabras as $p) {
                if ($p === '') {
                    continue;
                }
                $prueba = $linea === '' ? $p : $linea . ' ' . $p;
                if ($this->anchoTexto($prueba) > $ancho - 3 && $linea !== '') {
                    $salida[] = $linea;
                    $linea = $p;
                } else {
                    $linea = $prueba;
                }
            }
            $salida[] = $linea;
        }
        return $salida;
    }

    public function multiCelda(float $w, float $h, string $texto, mixed $borde = 0, string $alinear = 'L', bool $relleno = false): void
    {
        if ($w <= 0) {
            $w = $this->w - $this->mR - $this->x;
        }
        $xIni = $this->x;
        foreach ($this->dividirTexto($texto, $w) as $linea) {
            $this->saltoSiNecesario($h);
            $this->x = $xIni;
            $this->celda($w, $h, $linea, $borde, 2, $alinear, $relleno);
        }
        $this->x = $this->mL;
    }

    public function texto(float $x, float $y, string $texto): void
    {
        $this->out(
            'q ' . $this->colorTexto . ' BT /F' . $this->indiceFuente($this->fuente) . ' '
            . sprintf('%.2F', $this->tam) . ' Tf '
            . sprintf('%.2F %.2F Td (%s) Tj ET Q', $x * $this->k, ($this->h - $y) * $this->k, $this->escapar($this->cp1252($texto)))
        );
    }

    // ---------------- Dibujo ----------------

    public function linea(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->out(sprintf(
            '%.2F %.2F m %.2F %.2F l S',
            $x1 * $this->k,
            ($this->h - $y1) * $this->k,
            $x2 * $this->k,
            ($this->h - $y2) * $this->k
        ));
    }

    public function rect(float $x, float $y, float $w, float $h, string $estilo = 'D'): void
    {
        $op = match ($estilo) { 'F' => 'f', 'FD', 'DF' => 'B', default => 'S' };
        $this->out(sprintf(
            '%.2F %.2F %.2F %.2F re %s',
            $x * $this->k,
            ($this->h - $y) * $this->k,
            $w * $this->k,
            -$h * $this->k,
            $op
        ));
    }

    public function rectRedondeado(float $x, float $y, float $w, float $h, float $r, string $estilo = 'D'): void
    {
        $op = match ($estilo) { 'F' => 'f', 'FD', 'DF' => 'B', default => 'S' };
        $k = $this->k;
        $hp = $this->h;
        $c = 0.5523;
        $s = sprintf('%.2F %.2F m ', ($x + $r) * $k, ($hp - $y) * $k);
        $s .= sprintf('%.2F %.2F l ', ($x + $w - $r) * $k, ($hp - $y) * $k);
        $s .= sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ',
            ($x + $w - $r + $r * $c) * $k, ($hp - $y) * $k,
            ($x + $w) * $k, ($hp - ($y + $r - $r * $c)) * $k,
            ($x + $w) * $k, ($hp - ($y + $r)) * $k);
        $s .= sprintf('%.2F %.2F l ', ($x + $w) * $k, ($hp - ($y + $h - $r)) * $k);
        $s .= sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ',
            ($x + $w) * $k, ($hp - ($y + $h - $r + $r * $c)) * $k,
            ($x + $w - $r + $r * $c) * $k, ($hp - ($y + $h)) * $k,
            ($x + $w - $r) * $k, ($hp - ($y + $h)) * $k);
        $s .= sprintf('%.2F %.2F l ', ($x + $r) * $k, ($hp - ($y + $h)) * $k);
        $s .= sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ',
            ($x + $r - $r * $c) * $k, ($hp - ($y + $h)) * $k,
            $x * $k, ($hp - ($y + $h - $r + $r * $c)) * $k,
            $x * $k, ($hp - ($y + $h - $r)) * $k);
        $s .= sprintf('%.2F %.2F l ', $x * $k, ($hp - ($y + $r)) * $k);
        $s .= sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ',
            $x * $k, ($hp - ($y + $r - $r * $c)) * $k,
            ($x + $r - $r * $c) * $k, ($hp - $y) * $k,
            ($x + $r) * $k, ($hp - $y) * $k);
        $this->out($s . $op);
    }

    // ---------------- Imagenes ----------------

    public function imagen(string $ruta, float $x, float $y, float $w = 0, float $h = 0): bool
    {
        $info = $this->cargarImagen($ruta);
        if ($info === null) {
            return false;
        }
        if ($w <= 0 && $h <= 0) {
            $w = $info['w'] / $this->k / 2;
            $h = $info['h'] / $this->k / 2;
        } elseif ($w <= 0) {
            $w = $h * $info['w'] / $info['h'];
        } elseif ($h <= 0) {
            $h = $w * $info['h'] / $info['w'];
        }
        $this->out(sprintf(
            'q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q',
            $w * $this->k,
            $h * $this->k,
            $x * $this->k,
            ($this->h - ($y + $h)) * $this->k,
            $info['i']
        ));
        return true;
    }

    protected function cargarImagen(string $ruta): ?array
    {
        if (isset($this->imagenes[$ruta])) {
            return $this->imagenes[$ruta];
        }
        if (!is_file($ruta)) {
            return null;
        }
        $tipo = @getimagesize($ruta);
        if ($tipo === false) {
            return null;
        }
        $i = count($this->imagenes) + 1;
        if ($tipo[2] === IMAGETYPE_JPEG) {
            $datos = (string)file_get_contents($ruta);
            $info = [
                'i' => $i, 'w' => $tipo[0], 'h' => $tipo[1],
                'cs' => ($tipo['channels'] ?? 3) === 4 ? 'DeviceCMYK' : 'DeviceRGB',
                'bpc' => 8, 'f' => 'DCTDecode', 'datos' => $datos, 'smask' => null,
            ];
        } else {
            if (!function_exists('imagecreatefrompng')) {
                return null;
            }
            $img = match ($tipo[2]) {
                IMAGETYPE_PNG  => @imagecreatefrompng($ruta),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($ruta) : null,
                IMAGETYPE_GIF  => @imagecreatefromgif($ruta),
                default        => null,
            };
            if (!$img instanceof \GdImage) {
                return null;
            }
            $w = imagesx($img);
            $h = imagesy($img);
            $rgb = '';
            $alpha = '';
            $tieneAlpha = false;
            for ($yy = 0; $yy < $h; $yy++) {
                for ($xx = 0; $xx < $w; $xx++) {
                    $c = imagecolorat($img, $xx, $yy);
                    $a = ($c >> 24) & 0x7F;
                    $rgb .= chr(($c >> 16) & 0xFF) . chr(($c >> 8) & 0xFF) . chr($c & 0xFF);
                    $av = (int)round((127 - $a) * 255 / 127);
                    $alpha .= chr($av);
                    if ($av !== 255) {
                        $tieneAlpha = true;
                    }
                }
            }
            imagedestroy($img);
            $info = [
                'i' => $i, 'w' => $w, 'h' => $h, 'cs' => 'DeviceRGB', 'bpc' => 8,
                'f' => 'FlateDecode', 'datos' => $this->comprimir($rgb),
                'smask' => $tieneAlpha ? $this->comprimir($alpha) : null,
            ];
        }
        return $this->imagenes[$ruta] = $info;
    }

    // ---------------- Salida ----------------

    protected function out(string $s): void
    {
        if ($this->page === 0) {
            $this->agregarPagina();
        }
        $this->paginas[$this->page] .= $s . "\n";
    }

    protected function escapar(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', '\\r'], $s);
    }

    protected function cp1252(string $s): string
    {
        $r = @iconv('UTF-8', 'CP1252//TRANSLIT', $s);
        if ($r === false) {
            $r = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        }
        return $r === false ? $s : $r;
    }

    protected function comprimir(string $s): string
    {
        return function_exists('gzcompress') ? (string)gzcompress($s, 6) : $s;
    }

    protected function indiceFuente(string $nombre): int
    {
        $lista = array_keys($this->fuentesUsadas);
        $i = array_search($nombre, $lista, true);
        return $i === false ? 1 : (int)$i + 1;
    }

    protected function nuevoObjeto(): int
    {
        $this->n++;
        $this->offsets[$this->n] = strlen($this->buffer);
        $this->buffer .= $this->n . " 0 obj\n";
        return $this->n;
    }

    protected function put(string $s): void
    {
        $this->buffer .= $s . "\n";
    }

    public function salida(): string
    {
        if ($this->generado !== null) {
            return $this->generado;
        }
        if ($this->page === 0) {
            $this->agregarPagina();
        }
        $this->pieDePagina();
        if ($this->fuentesUsadas === []) {
            $this->fuentesUsadas['Helvetica'] = true;
        }

        $totalPaginas = $this->page;
        $this->buffer = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $this->offsets = [];

        // Contenidos
        $contenidoIds = [];
        $comprimir = function_exists('gzcompress');
        for ($p = 1; $p <= $totalPaginas; $p++) {
            $c = str_replace($this->alias, (string)$totalPaginas, $this->paginas[$p]);
            $datos = $comprimir ? $this->comprimir($c) : $c;
            $id = $this->nuevoObjeto();
            $this->put('<< /Length ' . strlen($datos) . ($comprimir ? ' /Filter /FlateDecode' : '') . ' >>');
            $this->put("stream\n" . $datos . "\nendstream");
            $this->put('endobj');
            $contenidoIds[$p] = $id;
        }

        // Imagenes
        $imgIds = [];
        foreach ($this->imagenes as $img) {
            $smaskId = null;
            if ($img['smask'] !== null) {
                $smaskId = $this->nuevoObjeto();
                $this->put('<< /Type /XObject /Subtype /Image /Width ' . $img['w'] . ' /Height ' . $img['h']
                    . ' /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length ' . strlen($img['smask']) . ' >>');
                $this->put("stream\n" . $img['smask'] . "\nendstream");
                $this->put('endobj');
            }
            $id = $this->nuevoObjeto();
            $this->put('<< /Type /XObject /Subtype /Image /Width ' . $img['w'] . ' /Height ' . $img['h']
                . ' /ColorSpace /' . $img['cs'] . ' /BitsPerComponent ' . $img['bpc']
                . ' /Filter /' . $img['f']
                . ($smaskId !== null ? ' /SMask ' . $smaskId . ' 0 R' : '')
                . ' /Length ' . strlen($img['datos']) . ' >>');
            $this->put("stream\n" . $img['datos'] . "\nendstream");
            $this->put('endobj');
            $imgIds[$img['i']] = $id;
        }

        // Fuentes
        $fuenteIds = [];
        $idx = 0;
        foreach (array_keys($this->fuentesUsadas) as $nombre) {
            $idx++;
            $id = $this->nuevoObjeto();
            $this->put('<< /Type /Font /Subtype /Type1 /BaseFont /' . $nombre
                . ' /Encoding /WinAnsiEncoding >>');
            $this->put('endobj');
            $fuenteIds[$idx] = $id;
        }

        // Anotaciones de enlaces
        $anotIds = [];
        foreach ($this->enlaces as $p => $lista) {
            $ids = [];
            foreach ($lista as [$lx, $ly, $lw, $lh, $url]) {
                $id = $this->nuevoObjeto();
                $this->put(sprintf(
                    '<< /Type /Annot /Subtype /Link /Border [0 0 0] /Rect [%.2F %.2F %.2F %.2F] /A << /S /URI /URI (%s) >> >>',
                    $lx * $this->k,
                    ($this->h - ($ly + $lh)) * $this->k,
                    ($lx + $lw) * $this->k,
                    ($this->h - $ly) * $this->k,
                    $this->escapar($url)
                ));
                $this->put('endobj');
                $ids[] = $id;
            }
            $anotIds[$p] = $ids;
        }

        // Paginas
        $paginaIds = [];
        $padreId = $this->n + $totalPaginas + 1;
        for ($p = 1; $p <= $totalPaginas; $p++) {
            $id = $this->nuevoObjeto();
            $recursos = '/Font << ';
            foreach ($fuenteIds as $i => $fid) {
                $recursos .= '/F' . $i . ' ' . $fid . ' 0 R ';
            }
            $recursos .= '>>';
            if ($imgIds !== []) {
                $recursos .= ' /XObject << ';
                foreach ($imgIds as $i => $iid) {
                    $recursos .= '/I' . $i . ' ' . $iid . ' 0 R ';
                }
                $recursos .= '>>';
            }
            $anots = '';
            if (!empty($anotIds[$p])) {
                $anots = ' /Annots [' . implode(' ', array_map(static fn($a) => $a . ' 0 R', $anotIds[$p])) . ']';
            }
            $this->put('<< /Type /Page /Parent ' . $padreId . ' 0 R'
                . sprintf(' /MediaBox [0 0 %.2F %.2F]', $this->w * $this->k, $this->h * $this->k)
                . ' /Resources << ' . $recursos . ' >>'
                . $anots
                . ' /Contents ' . $contenidoIds[$p] . ' 0 R >>');
            $this->put('endobj');
            $paginaIds[$p] = $id;
        }

        $padre = $this->nuevoObjeto();
        $this->put('<< /Type /Pages /Kids [' . implode(' ', array_map(static fn($i) => $i . ' 0 R', $paginaIds)) . ']'
            . ' /Count ' . $totalPaginas . ' >>');
        $this->put('endobj');

        $info = $this->nuevoObjeto();
        $this->put('<< /Producer (EduPortal) /Title (' . $this->escapar($this->cp1252($this->titulo)) . ')'
            . ' /Author (' . $this->escapar($this->cp1252($this->autor)) . ')'
            . ' /CreationDate (D:' . date('YmdHis') . ') >>');
        $this->put('endobj');

        $catalogo = $this->nuevoObjeto();
        $this->put('<< /Type /Catalog /Pages ' . $padre . ' 0 R /PageLayout /OneColumn >>');
        $this->put('endobj');

        $inicioXref = strlen($this->buffer);
        $max = $this->n;
        $this->buffer .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $this->buffer .= sprintf("%010d 00000 n \n", $this->offsets[$i] ?? 0);
        }
        $this->buffer .= "trailer\n<< /Size " . ($max + 1) . " /Root " . $catalogo . " 0 R /Info " . $info . " 0 R >>\n";
        $this->buffer .= "startxref\n" . $inicioXref . "\n%%EOF\n";
        return $this->generado = $this->buffer;
    }

    public function descargar(string $nombre, bool $enLinea = true): string
    {
        $pdf = $this->salida();
        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . ($enLinea ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre) . '"');
            header('Content-Length: ' . strlen($pdf));
            header('Cache-Control: private, max-age=0, must-revalidate');
        }
        return $pdf;
    }
}
