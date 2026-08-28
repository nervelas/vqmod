<?php
declare(strict_types=1);

namespace Vendor\Pdf;

/**
 * Motor PDF en PHP puro (PDF 1.4), unidades en milímetros, origen arriba-izquierda.
 * Fuentes base Type 1 (Helvetica / Times) con codificación WinAnsi.
 * Soporta texto, párrafos, tablas, rectángulos, líneas, colores e imágenes JPEG/PNG.
 *
 * ResidencialPro — librería local, sin composer ni dependencias externas.
 */
class Pdf
{
    protected const K = 2.834645669; // mm -> pt

    protected float $anchoPagina;
    protected float $altoPagina;
    protected float $margenIzq = 15.0;
    protected float $margenDer = 15.0;
    protected float $margenSup = 18.0;
    protected float $margenInf = 18.0;

    protected array $paginas = [];
    protected int $paginaActual = -1;
    protected string $buffer = '';

    protected float $x = 0.0;
    protected float $y = 0.0;

    protected string $familia = 'helvetica';
    protected string $estilo  = '';
    protected float  $tam     = 10.0;
    protected array  $fuentesUsadas = [];

    protected string $colorTexto  = '0 g';
    protected string $colorRelleno = '1 1 1 rg';
    protected string $colorTrazo  = '0 G';
    protected float  $grosorLinea = 0.2;

    protected array $imagenes = [];
    protected string $aliasPaginas = '{{paginas}}';
    protected array $meta = [];

    /** @var callable|null */
    protected $encabezado = null;
    /** @var callable|null */
    protected $pie = null;

    public function __construct(string $formato = 'A4', string $orientacion = 'V')
    {
        [$an, $al] = match (strtoupper($formato)) {
            'CARTA', 'LETTER' => [215.9, 279.4],
            'OFICIO', 'LEGAL' => [215.9, 355.6],
            default           => [210.0, 297.0],
        };
        if (strtoupper($orientacion) === 'H') {
            [$an, $al] = [$al, $an];
        }
        $this->anchoPagina = $an;
        $this->altoPagina  = $al;
    }

    // ------------------------------------------------------------- Ajustes

    public function margenes(float $izq, float $sup, float $der, float $inf): void
    {
        $this->margenIzq = $izq;
        $this->margenSup = $sup;
        $this->margenDer = $der;
        $this->margenInf = $inf;
    }

    public function info(string $titulo, string $autor = '', string $asunto = ''): void
    {
        $this->meta = ['titulo' => $titulo, 'autor' => $autor, 'asunto' => $asunto];
    }

    public function alEncabezado(callable $fn): void { $this->encabezado = $fn; }
    public function alPie(callable $fn): void        { $this->pie = $fn; }

    public function ancho(): float      { return $this->anchoPagina; }
    public function alto(): float       { return $this->altoPagina; }
    public function anchoUtil(): float  { return $this->anchoPagina - $this->margenIzq - $this->margenDer; }
    public function limiteInferior(): float { return $this->altoPagina - $this->margenInf; }
    public function getY(): float       { return $this->y; }
    public function getX(): float       { return $this->x; }
    public function setY(float $y): void { $this->y = $y; }
    public function setX(float $x): void { $this->x = $x; }
    public function setXY(float $x, float $y): void { $this->x = $x; $this->y = $y; }
    public function saltar(float $mm): void { $this->y += $mm; }
    public function paginas(): int { return count($this->paginas); }

    // ------------------------------------------------------------- Páginas

    public function agregarPagina(): void
    {
        if ($this->paginaActual >= 0 && $this->pie !== null) {
            ($this->pie)($this);
        }
        $this->paginas[] = '';
        $this->paginaActual = count($this->paginas) - 1;
        $this->x = $this->margenIzq;
        $this->y = $this->margenSup;
        $this->escribir(sprintf('%.2f w', $this->grosorLinea * self::K));
        if ($this->encabezado !== null) {
            ($this->encabezado)($this);
        }
    }

    /** Salta de página si no caben $alturaNecesaria mm. */
    public function asegurarEspacio(float $alturaNecesaria): void
    {
        if ($this->paginaActual < 0) {
            $this->agregarPagina();
            return;
        }
        if ($this->y + $alturaNecesaria > $this->limiteInferior()) {
            $this->agregarPagina();
        }
    }

    // -------------------------------------------------------------- Estilo

    public function fuente(string $familia = '', string $estilo = '', float $tam = 0): void
    {
        if ($familia !== '') {
            $this->familia = strtolower($familia);
        }
        $this->estilo = strtoupper($estilo);
        if ($tam > 0) {
            $this->tam = $tam;
        }
        $this->fuentesUsadas[$this->nombreFuente()] = true;
    }

    public function tamano(float $tam): void
    {
        $this->tam = $tam;
        $this->fuentesUsadas[$this->nombreFuente()] = true;
    }

    public function colorTexto(string $hex): void
    {
        [$r, $g, $b] = self::hexRgb($hex);
        $this->colorTexto = sprintf('%.3f %.3f %.3f rg', $r, $g, $b);
    }

    public function colorRelleno(string $hex): void
    {
        [$r, $g, $b] = self::hexRgb($hex);
        $this->colorRelleno = sprintf('%.3f %.3f %.3f rg', $r, $g, $b);
    }

    public function colorTrazo(string $hex): void
    {
        [$r, $g, $b] = self::hexRgb($hex);
        $this->colorTrazo = sprintf('%.3f %.3f %.3f RG', $r, $g, $b);
    }

    public function grosor(float $mm): void
    {
        $this->grosorLinea = $mm;
        $this->escribir(sprintf('%.2f w', $mm * self::K));
    }

    // --------------------------------------------------------------- Texto

    public function texto(float $x, float $y, string $s): void
    {
        $this->escribir(sprintf(
            'BT /F%d %.2f Tf %s %.2f %.2f Td (%s) Tj ET',
            $this->indiceFuente(),
            $this->tam,
            $this->colorTexto,
            $x * self::K,
            ($this->altoPagina - $y) * self::K,
            self::escapar(self::codificar($s))
        ));
    }

    public function textoDerecha(float $xDerecha, float $y, string $s): void
    {
        $this->texto($xDerecha - $this->anchoTexto($s), $y, $s);
    }

    public function textoCentrado(float $centro, float $y, string $s): void
    {
        $this->texto($centro - $this->anchoTexto($s) / 2, $y, $s);
    }

    /** Escribe una línea en la posición actual y avanza. */
    public function linea1(string $s, float $alturaLinea = 5.0): void
    {
        $this->asegurarEspacio($alturaLinea);
        $this->y += $alturaLinea;
        $this->texto($this->x, $this->y - $alturaLinea * 0.25, $s);
    }

    /** Párrafo con ajuste automático. Devuelve la Y final. */
    public function parrafo(string $texto, float $ancho = 0, float $alturaLinea = 4.8): float
    {
        $ancho = $ancho > 0 ? $ancho : $this->anchoUtil();
        foreach ($this->dividirLineas($texto, $ancho) as $linea) {
            $this->asegurarEspacio($alturaLinea);
            $this->y += $alturaLinea;
            $this->texto($this->x, $this->y - $alturaLinea * 0.25, $linea);
        }
        return $this->y;
    }

    /** Divide un texto en líneas que quepan en $ancho mm. */
    public function dividirLineas(string $texto, float $ancho): array
    {
        $texto  = str_replace(["\r\n", "\r"], "\n", $texto);
        $salida = [];
        foreach (explode("\n", $texto) as $parrafo) {
            $palabras = preg_split('/\s+/u', trim($parrafo)) ?: [];
            if ($palabras === [] || $palabras === ['']) {
                $salida[] = '';
                continue;
            }
            $linea = '';
            foreach ($palabras as $p) {
                $prueba = $linea === '' ? $p : $linea . ' ' . $p;
                if ($this->anchoTexto($prueba) <= $ancho) {
                    $linea = $prueba;
                    continue;
                }
                if ($linea !== '') {
                    $salida[] = $linea;
                }
                // Palabra más ancha que la caja: se parte.
                while ($this->anchoTexto($p) > $ancho && mb_strlen($p) > 1) {
                    $corte = mb_strlen($p);
                    while ($corte > 1 && $this->anchoTexto(mb_substr($p, 0, $corte)) > $ancho) {
                        $corte--;
                    }
                    $salida[] = mb_substr($p, 0, $corte);
                    $p = mb_substr($p, $corte);
                }
                $linea = $p;
            }
            $salida[] = $linea;
        }
        return $salida;
    }

    /** Ancho de un texto en mm con la fuente actual. */
    public function anchoTexto(string $s): float
    {
        $anchos = Metricas::anchos($this->nombreFuenteMetricas());
        $total  = 0;
        $bin    = self::codificar($s);
        $largo  = strlen($bin);
        for ($i = 0; $i < $largo; $i++) {
            $c = ord($bin[$i]);
            $total += $anchos[$c] ?? 500;
        }
        return ($total * $this->tam / 1000) / self::K;
    }

    // ------------------------------------------------------------- Gráficos

    public function rectangulo(float $x, float $y, float $an, float $al, string $modo = 'F'): void
    {
        $op = match (strtoupper($modo)) { 'F' => 'f', 'D' => 'S', default => 'B' };
        $this->escribir(sprintf(
            '%s %s %.2f %.2f %.2f %.2f re %s',
            $this->colorRelleno,
            $this->colorTrazo,
            $x * self::K,
            ($this->altoPagina - $y - $al) * self::K,
            $an * self::K,
            $al * self::K,
            $op
        ));
    }

    public function rectRedondo(float $x, float $y, float $an, float $al, float $r, string $modo = 'F'): void
    {
        $op = match (strtoupper($modo)) { 'F' => 'f', 'D' => 'S', default => 'B' };
        $k  = self::K;
        $yy = $this->altoPagina - $y - $al;
        $c  = 0.5523 * $r;
        $s  = sprintf('%s %s ', $this->colorRelleno, $this->colorTrazo);
        $s .= sprintf('%.2f %.2f m ', ($x + $r) * $k, $yy * $k);
        $s .= sprintf('%.2f %.2f l ', ($x + $an - $r) * $k, $yy * $k);
        $s .= sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c ', ($x + $an - $r + $c) * $k, $yy * $k, ($x + $an) * $k, ($yy + $r - $c) * $k, ($x + $an) * $k, ($yy + $r) * $k);
        $s .= sprintf('%.2f %.2f l ', ($x + $an) * $k, ($yy + $al - $r) * $k);
        $s .= sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c ', ($x + $an) * $k, ($yy + $al - $r + $c) * $k, ($x + $an - $r + $c) * $k, ($yy + $al) * $k, ($x + $an - $r) * $k, ($yy + $al) * $k);
        $s .= sprintf('%.2f %.2f l ', ($x + $r) * $k, ($yy + $al) * $k);
        $s .= sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c ', ($x + $r - $c) * $k, ($yy + $al) * $k, $x * $k, ($yy + $al - $r + $c) * $k, $x * $k, ($yy + $al - $r) * $k);
        $s .= sprintf('%.2f %.2f l ', $x * $k, ($yy + $r) * $k);
        $s .= sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c ', $x * $k, ($yy + $r - $c) * $k, ($x + $r - $c) * $k, $yy * $k, ($x + $r) * $k, $yy * $k);
        $s .= $op;
        $this->escribir($s);
    }

    public function linea(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->escribir(sprintf(
            '%s %.2f %.2f m %.2f %.2f l S',
            $this->colorTrazo,
            $x1 * self::K,
            ($this->altoPagina - $y1) * self::K,
            $x2 * self::K,
            ($this->altoPagina - $y2) * self::K
        ));
    }

    /** Línea horizontal completa de margen a margen. */
    public function separador(float $y, string $hex = '#DCD6C8'): void
    {
        $anterior = $this->colorTrazo;
        $this->colorTrazo($hex);
        $this->linea($this->margenIzq, $y, $this->anchoPagina - $this->margenDer, $y);
        $this->colorTrazo = $anterior;
    }

    // ------------------------------------------------------------- Imágenes

    /** Inserta una imagen JPEG o PNG. Devuelve true si se pudo. */
    public function imagen(string $ruta, float $x, float $y, float $an, float $al = 0): bool
    {
        if (!is_file($ruta)) {
            return false;
        }
        $info = @getimagesize($ruta);
        if ($info === false) {
            return false;
        }
        $clave = md5($ruta);
        if (!isset($this->imagenes[$clave])) {
            $datos = null;
            if ($info[2] === IMAGETYPE_JPEG) {
                $datos = [
                    'data' => (string) file_get_contents($ruta),
                    'w'    => $info[0],
                    'h'    => $info[1],
                    'cs'   => (isset($info['channels']) && (int) $info['channels'] === 4) ? 'DeviceCMYK' : 'DeviceRGB',
                    'bpc'  => isset($info['bits']) ? (int) $info['bits'] : 8,
                ];
            } elseif (function_exists('imagecreatefromstring')) {
                $img = @imagecreatefromstring((string) file_get_contents($ruta));
                if ($img === false) {
                    return false;
                }
                $an0 = imagesx($img);
                $al0 = imagesy($img);
                $plano = imagecreatetruecolor($an0, $al0);
                $blanco = imagecolorallocate($plano, 255, 255, 255);
                imagefilledrectangle($plano, 0, 0, $an0, $al0, $blanco);
                imagecopy($plano, $img, 0, 0, 0, 0, $an0, $al0);
                ob_start();
                imagejpeg($plano, null, 92);
                $bin = (string) ob_get_clean();
                imagedestroy($img);
                imagedestroy($plano);
                $datos = ['data' => $bin, 'w' => $an0, 'h' => $al0, 'cs' => 'DeviceRGB', 'bpc' => 8];
            }
            if ($datos === null) {
                return false;
            }
            $datos['i'] = count($this->imagenes) + 1;
            $this->imagenes[$clave] = $datos;
        }
        $im = $this->imagenes[$clave];
        if ($al <= 0) {
            $al = $an * $im['h'] / max(1, $im['w']);
        }
        $this->escribir(sprintf(
            'q %.2f 0 0 %.2f %.2f %.2f cm /I%d Do Q',
            $an * self::K,
            $al * self::K,
            $x * self::K,
            ($this->altoPagina - $y - $al) * self::K,
            $im['i']
        ));
        return true;
    }

    /** Dibuja una matriz booleana (por ejemplo un QR) como rectángulos. */
    public function matriz(array $m, float $x, float $y, float $lado, string $hex = '#0F2E24'): void
    {
        $n = count($m);
        if ($n === 0) {
            return;
        }
        $paso = $lado / $n;
        $anterior = $this->colorRelleno;
        $this->colorRelleno('#FFFFFF');
        $this->rectangulo($x - $paso, $y - $paso, $lado + $paso * 2, $lado + $paso * 2, 'F');
        $this->colorRelleno($hex);
        $partes = '';
        for ($fy = 0; $fy < $n; $fy++) {
            for ($fx = 0; $fx < $n; $fx++) {
                if (!empty($m[$fy][$fx])) {
                    $partes .= sprintf(
                        '%.3f %.3f %.3f %.3f re ',
                        ($x + $fx * $paso) * self::K,
                        ($this->altoPagina - $y - ($fy + 1) * $paso) * self::K,
                        $paso * self::K + 0.15,
                        $paso * self::K + 0.15
                    );
                }
            }
        }
        $this->escribir($this->colorRelleno . ' ' . $partes . 'f');
        $this->colorRelleno = $anterior;
    }

    // --------------------------------------------------------------- Tablas

    /**
     * Tabla sencilla.
     * $cols = [['titulo'=>'Concepto','ancho'=>60,'alinear'=>'I'], ...]
     * $filas = [[c1, c2, ...], ...]
     */
    public function tabla(array $cols, array $filas, array $opciones = []): void
    {
        $altoFila   = (float) ($opciones['alto']       ?? 7.0);
        $colorCab   = (string) ($opciones['cabecera']  ?? '#0F2E24');
        $colorCabTx = (string) ($opciones['cabecera_texto'] ?? '#FFFFFF');
        $cebra      = (string) ($opciones['cebra']     ?? '#F6F3EC');
        $tamCab     = (float) ($opciones['tam_cabecera'] ?? 8.0);
        $tamFila    = (float) ($opciones['tam_fila']   ?? 8.5);
        $x0         = (float) ($opciones['x']          ?? $this->margenIzq);

        $dibujarCabecera = function () use ($cols, $altoFila, $colorCab, $colorCabTx, $tamCab, $x0): void {
            $this->colorRelleno($colorCab);
            $this->rectangulo($x0, $this->y, array_sum(array_column($cols, 'ancho')), $altoFila, 'F');
            $this->fuente('helvetica', 'B', $tamCab);
            $this->colorTexto($colorCabTx);
            $x = $x0;
            foreach ($cols as $c) {
                $tx = (string) $c['titulo'];
                $al = $c['alinear'] ?? 'I';
                if ($al === 'D') {
                    $this->textoDerecha($x + $c['ancho'] - 2, $this->y + $altoFila * 0.68, $tx);
                } elseif ($al === 'C') {
                    $this->textoCentrado($x + $c['ancho'] / 2, $this->y + $altoFila * 0.68, $tx);
                } else {
                    $this->texto($x + 2, $this->y + $altoFila * 0.68, $tx);
                }
                $x += (float) $c['ancho'];
            }
            $this->y += $altoFila;
        };

        $this->asegurarEspacio($altoFila * 3);
        $dibujarCabecera();

        $i = 0;
        foreach ($filas as $fila) {
            if ($this->y + $altoFila > $this->limiteInferior()) {
                $this->agregarPagina();
                $dibujarCabecera();
            }
            if ($cebra !== '' && $i % 2 === 1) {
                $this->colorRelleno($cebra);
                $this->rectangulo($x0, $this->y, array_sum(array_column($cols, 'ancho')), $altoFila, 'F');
            }
            $x = $x0;
            foreach ($cols as $j => $c) {
                $valor = (string) ($fila[$j] ?? '');
                $estilo = $c['negrita'] ?? false ? 'B' : '';
                $this->fuente('helvetica', $estilo, $tamFila);
                $this->colorTexto($c['color'] ?? '#22271F');
                $ancho = (float) $c['ancho'];
                while ($this->anchoTexto($valor) > $ancho - 4 && mb_strlen($valor) > 4) {
                    $valor = mb_substr($valor, 0, mb_strlen($valor) - 2) . '…';
                }
                $al = $c['alinear'] ?? 'I';
                if ($al === 'D') {
                    $this->textoDerecha($x + $ancho - 2, $this->y + $altoFila * 0.68, $valor);
                } elseif ($al === 'C') {
                    $this->textoCentrado($x + $ancho / 2, $this->y + $altoFila * 0.68, $valor);
                } else {
                    $this->texto($x + 2, $this->y + $altoFila * 0.68, $valor);
                }
                $x += $ancho;
            }
            $this->colorTrazo('#E4DFD3');
            $this->linea($x0, $this->y + $altoFila, $x, $this->y + $altoFila);
            $this->y += $altoFila;
            $i++;
        }
        $this->colorTexto('#22271F');
    }

    // -------------------------------------------------------------- Salida

    public function salida(): string
    {
        if ($this->paginaActual < 0) {
            $this->agregarPagina();
        }
        if ($this->pie !== null) {
            ($this->pie)($this);
        }
        $total = count($this->paginas);
        foreach ($this->paginas as $i => $c) {
            $this->paginas[$i] = str_replace($this->aliasPaginas, (string) $total, $c);
        }
        return $this->ensamblar();
    }

    public function totalPaginasAlias(): string
    {
        return $this->aliasPaginas;
    }

    protected function ensamblar(): string
    {
        $objetos   = [];
        $numPags   = count($this->paginas);
        $idCatalogo = 1;
        $idPaginas  = 2;
        $idPrimera  = 3;

        $fuentes = array_keys($this->fuentesUsadas);
        if ($fuentes === []) {
            $fuentes = ['helvetica'];
        }
        $idFuentes = [];
        $siguiente = $idPrimera + $numPags * 2;
        foreach ($fuentes as $f) {
            $idFuentes[$f] = $siguiente++;
        }
        $idImagenes = [];
        foreach ($this->imagenes as $clave => $im) {
            $idImagenes[$clave] = $siguiente++;
        }

        $refFuentes = '';
        foreach ($fuentes as $i => $f) {
            $refFuentes .= '/F' . ($i + 1) . ' ' . $idFuentes[$f] . ' 0 R ';
        }
        $refImagenes = '';
        foreach ($this->imagenes as $clave => $im) {
            $refImagenes .= '/I' . $im['i'] . ' ' . $idImagenes[$clave] . ' 0 R ';
        }
        $recursos = '<< /ProcSet [/PDF /Text /ImageC] /Font << ' . $refFuentes . '>>'
                  . ($refImagenes !== '' ? ' /XObject << ' . $refImagenes . '>>' : '') . ' >>';

        $kids = [];
        for ($i = 0; $i < $numPags; $i++) {
            $kids[] = ($idPrimera + $i * 2) . ' 0 R';
        }
        $objetos[$idCatalogo] = '<< /Type /Catalog /Pages ' . $idPaginas . ' 0 R >>';
        $objetos[$idPaginas]  = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $numPags
                              . ' /MediaBox [0 0 ' . sprintf('%.2f %.2f', $this->anchoPagina * self::K, $this->altoPagina * self::K) . '] >>';

        for ($i = 0; $i < $numPags; $i++) {
            $idPag = $idPrimera + $i * 2;
            $idCon = $idPag + 1;
            $objetos[$idPag] = '<< /Type /Page /Parent ' . $idPaginas . ' 0 R /Resources ' . $recursos
                             . ' /Contents ' . $idCon . ' 0 R >>';
            $flujo = $this->paginas[$i];
            $comprimido = function_exists('gzcompress') ? gzcompress($flujo, 6) : false;
            if ($comprimido !== false) {
                $objetos[$idCon] = '<< /Length ' . strlen($comprimido) . ' /Filter /FlateDecode >>' . "\n"
                                 . "stream\n" . $comprimido . "\nendstream";
            } else {
                $objetos[$idCon] = '<< /Length ' . strlen($flujo) . " >>\nstream\n" . $flujo . "\nendstream";
            }
        }

        foreach ($fuentes as $f) {
            $objetos[$idFuentes[$f]] = '<< /Type /Font /Subtype /Type1 /BaseFont /' . Metricas::nombrePdf($f)
                                     . ' /Encoding /WinAnsiEncoding >>';
        }
        foreach ($this->imagenes as $clave => $im) {
            $objetos[$idImagenes[$clave]] = '<< /Type /XObject /Subtype /Image /Width ' . $im['w']
                . ' /Height ' . $im['h'] . ' /ColorSpace /' . $im['cs'] . ' /BitsPerComponent ' . $im['bpc']
                . ' /Filter /DCTDecode /Length ' . strlen($im['data']) . " >>\nstream\n" . $im['data'] . "\nendstream";
        }

        // Info
        $idInfo = $siguiente++;
        $objetos[$idInfo] = '<< /Producer (ResidencialPro) /Creator (ResidencialPro)'
            . ' /Title (' . self::escapar(self::codificar((string) ($this->meta['titulo'] ?? 'Documento'))) . ')'
            . ' /Author (' . self::escapar(self::codificar((string) ($this->meta['autor'] ?? ''))) . ')'
            . ' /Subject (' . self::escapar(self::codificar((string) ($this->meta['asunto'] ?? ''))) . ')'
            . ' /CreationDate (D:' . date('YmdHis') . ') >>';

        ksort($objetos);
        $pdf  = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $desplazamientos = [];
        foreach ($objetos as $id => $cuerpo) {
            $desplazamientos[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $cuerpo . "\nendobj\n";
        }
        $maximo = max(array_keys($objetos)) + 1;
        $inicioXref = strlen($pdf);
        $pdf .= "xref\n0 " . $maximo . "\n0000000000 65535 f \n";
        for ($i = 1; $i < $maximo; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $desplazamientos[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . $maximo . " /Root " . $idCatalogo . " 0 R /Info " . $idInfo . " 0 R >>\n"
              . "startxref\n" . $inicioXref . "\n%%EOF";
        return $pdf;
    }

    // -------------------------------------------------------------- Interno

    protected function escribir(string $s): void
    {
        if ($this->paginaActual < 0) {
            $this->agregarPagina();
        }
        $this->paginas[$this->paginaActual] .= $s . "\n";
    }

    protected function nombreFuente(): string
    {
        return $this->familia . strtolower($this->estilo);
    }

    protected function nombreFuenteMetricas(): string
    {
        return $this->nombreFuente();
    }

    protected function indiceFuente(): int
    {
        $n = $this->nombreFuente();
        $this->fuentesUsadas[$n] = true;
        $i = array_search($n, array_keys($this->fuentesUsadas), true);
        return ($i === false ? 0 : (int) $i) + 1;
    }

    protected static function hexRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return [0.0, 0.0, 0.0];
        }
        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    /** UTF-8 -> CP1252 (WinAnsi). */
    public static function codificar(string $s): string
    {
        if ($s === '') {
            return '';
        }
        $r = @iconv('UTF-8', 'CP1252//TRANSLIT', $s);
        if ($r === false) {
            $r = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
        }
        return $r === false ? preg_replace('/[^\x20-\x7E]/', '?', $s) ?? '' : $r;
    }

    protected static function escapar(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $s);
    }
}
