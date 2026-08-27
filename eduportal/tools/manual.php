<?php
/**
 * Genera el manual de usuario en PDF (edicion premium).
 * Uso: php tools/manual.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/Autoloader.php';
$loader = new App\Core\Autoloader();
$loader->addNamespace('Vendor', BASE_PATH . '/vendor');
$loader->register();

use Vendor\Pdf\Pdf;

// ---------------------------------------------------------------- identidad
const PORTAL  = 'Gestión Colegio';
const DOMINIO = 'https://gestioncolegio.paginasweb.gt';
const NAVY    = '#0B1F3A';
const NAVY2   = '#16324F';
const GOLD    = '#C9A961';
const GOLD_OS = '#8A6D2F';
const TINTA   = '#20262E';
const GRIS    = '#6B7280';
const HAIR    = '#E3E1DA';
const MARFIL  = '#FBF8F1';

const ML = 26.0;   // margen izquierdo
const MR = 26.0;   // margen derecho
const MT = 32.0;
const MB = 26.0;

final class Manual extends Pdf
{
    public string $seccion = '';
    public bool $cromo = true;
    /** @var int[] paginas sin encabezado ni pie (portada y contraportada) */
    public array $sinCromo = [];

    protected function encabezado(): void
    {
        if (!$this->cromo || in_array($this->paginaActual(), $this->sinCromo, true)) { return; }
        $this->setFuente('Times', 'I', 8.6);
        $this->setColorHex(GRIS, 'texto');
        $this->setXY(ML, 15.5);
        $this->celda(80, 5, PORTAL, 0, 0, 'L');
        if ($this->seccion !== '') {
            $this->setFuente('Helvetica', '', 7.6);
            $this->setColorHex(GOLD_OS, 'texto');
            $this->setXY($this->w - MR - 90, 15.7);
            $this->celda(90, 5, mb_strtoupper($this->seccion), 0, 0, 'R');
        }
        $this->setColorHex(HAIR, 'trazo');
        $this->setGrosor(0.2);
        $this->linea(ML, 21.5, $this->w - MR, 21.5);
        $this->setColorHex(GOLD, 'trazo');
        $this->setGrosor(0.7);
        $this->linea(ML, 21.5, ML + 16, 21.5);
        $this->setGrosor(0.2);
        $this->setColorTexto(0);
        $this->setXY(ML, MT);
    }

    protected function pie(): void
    {
        if (!$this->cromo || in_array($this->paginaActual(), $this->sinCromo, true)) { return; }
        $cx = $this->w / 2;
        $y  = $this->h - 17;
        $this->setColorHex(HAIR, 'trazo');
        $this->setGrosor(0.2);
        $this->linea(ML, $y, $cx - 9, $y);
        $this->linea($cx + 9, $y, $this->w - MR, $y);
        $this->setFuente('Times', '', 9);
        $this->setColorHex(NAVY, 'texto');
        $this->setXY($cx - 9, $y - 2.6);
        $this->celda(18, 5.2, (string)$this->paginaActual(), 0, 0, 'C');
        $this->setColorTexto(0);
    }

    /** Espaciado entre caracteres (Tc) para titulillos. */
    public function espaciado(float $mm): void
    {
        $this->out(sprintf('%.2F Tc', $mm * $this->k));
    }

    public function circulo(float $cx, float $cy, float $r, string $estilo = 'D'): void
    {
        $this->rectRedondeado($cx - $r, $cy - $r, $r * 2, $r * 2, $r, $estilo);
    }
}

$p = new Manual();
$p->setMargenes(ML, MT, MR, MB);
$p->setMeta(PORTAL . ' · Manual de usuario', PORTAL);
$W = $p->anchoUtil();

// ---------------------------------------------------------------- utilidades
/** Corta el texto en lineas; devuelve [texto, esFinDeParrafo]. */
function envolver(Manual $p, string $t, float $w): array
{
    $out = [];
    foreach (explode("\n", str_replace("\r", '', $t)) as $parr) {
        $palabras = preg_split('/\s+/', trim($parr)) ?: [];
        $linea = '';
        foreach ($palabras as $pal) {
            if ($pal === '') { continue; }
            $prueba = $linea === '' ? $pal : $linea . ' ' . $pal;
            if ($p->anchoTexto($prueba) > $w && $linea !== '') {
                $out[] = [$linea, false];
                $linea = $pal;
            } else {
                $linea = $prueba;
            }
        }
        $out[] = [$linea, true];
    }
    return $out;
}

/** Texto justificado a ambos margenes. */
function texto(Manual $p, string $t, float $x, float $w, float $tam = 9.8, float $lh = 5.6, string $color = TINTA, string $estilo = ''): void
{
    $p->setFuente('Helvetica', $estilo, $tam);
    $p->setColorHex($color, 'texto');
    foreach (envolver($p, $t, $w) as [$linea, $fin]) {
        $p->saltoSiNecesario($lh);
        $y = $p->getY() + $lh * 0.72;
        $palabras = explode(' ', $linea);
        $n = count($palabras);
        $anchoP = 0.0;
        foreach ($palabras as $pal) { $anchoP += $p->anchoTexto($pal); }
        $sobra = $w - $anchoP;
        if (!$fin && $n > 1 && $sobra > 0 && $sobra < $w * 0.35) {
            $hueco = $sobra / ($n - 1);
            $cx = $x;
            foreach ($palabras as $pal) {
                $p->texto($cx, $y, $pal);
                $cx += $p->anchoTexto($pal) + $hueco;
            }
        } else {
            $p->texto($x, $y, $linea);
        }
        $p->setY($p->getY() + $lh);
        $p->setX(ML);
    }
    $p->setColorTexto(0);
}

function parrafo(Manual $p, string $t, float $tam = 9.8): void
{
    global $W;
    soltarSub($p, min(count(envolver($p, $t, $W)) * 5.6, 30));
    texto($p, $t, ML, $W, $tam);
    $p->setY($p->getY() + 2.4);
    $p->setX(ML);
}

/** Titulillo con espaciado entre letras. */
function titulillo(Manual $p, string $t, float $x, float $y, string $color = GOLD_OS, float $tam = 7.4): void
{
    $p->setFuente('Helvetica', 'B', $tam);
    $p->setColorHex($color, 'texto');
    $p->espaciado(0.5);
    $p->setXY($x, $y);
    $p->celda(120, 4.6, mb_strtoupper($t), 0, 0, 'L');
    $p->espaciado(0);
    $p->setColorTexto(0);
}

/** Apertura de seccion en pagina nueva. */
function seccion(Manual $p, string $num, string $titulo, string $bajada): void
{
    global $W;
    $GLOBALS['SUB_PEND'] = null;
    $p->seccion = $titulo;
    $p->agregarPagina();
    $GLOBALS['INDICE'][$titulo] = $p->paginaActual();
    $y = 34.0;

    $p->setColorHex(GOLD, 'trazo');
    $p->setGrosor(0.7);
    $p->linea(ML, $y, ML + 20, $y);
    $p->setGrosor(0.2);

    titulillo($p, 'Sección ' . $num, ML, $y + 3.4);

    $p->setFuente('Times', 'B', 25);
    $p->setColorHex(NAVY, 'texto');
    $p->setXY(ML, $y + 10);
    $p->celda($W, 13, $titulo, 0, 1, 'L');

    $p->setFuente('Times', 'I', 10.6);
    $p->setColorHex(GRIS, 'texto');
    $p->setXY(ML, $y + 24);
    $p->multiCelda($W, 5.4, $bajada);

    $yl = $p->getY() + 3.5;
    $p->setColorHex(HAIR, 'trazo');
    $p->linea(ML, $yl, ML + $W, $yl);
    $p->setColorTexto(0);
    $p->setXY(ML, $yl + 9);
}

/** El subtitulo se dibuja junto con el bloque que lo sigue, para que nunca quede solo. */
function subtitulo(Manual $p, string $t): void
{
    $GLOBALS['SUB_PEND'] = $t;
}

/** Alto aproximado de un bloque de lineas, para decidir el salto de pagina. */
function medir(Manual $p, array $items, float $sangria, float $lh = 5.4, float $extra = 1.6, float $tam = 9.6): float
{
    global $W;
    $p->setFuente('Helvetica', '', $tam);
    $alto = 0.0;
    foreach ($items as $it) {
        $alto += count(envolver($p, $it, $W - $sangria)) * $lh + $extra;
    }
    return $alto;
}

/** Dibuja el subtitulo pendiente reservando espacio para lo que viene. */
function soltarSub(Manual $p, float $necesita = 0.0): void
{
    global $W;
    $t = $GLOBALS['SUB_PEND'] ?? null;
    if ($t === null) { return; }
    $GLOBALS['SUB_PEND'] = null;
    $p->saltoSiNecesario(15 + min($necesita, 42));
    $p->setY($p->getY() + 4);
    $y = $p->getY();
    $p->setFuente('Times', 'B', 12.6);
    $p->setColorHex(NAVY2, 'texto');
    $p->setXY(ML, $y);
    $p->celda($W, 6.4, $t, 0, 1, 'L');
    $p->setColorHex(GOLD, 'trazo');
    $p->setGrosor(0.6);
    $p->linea(ML, $y + 7.6, ML + 13, $y + 7.6);
    $p->setGrosor(0.2);
    $p->setColorTexto(0);
    $p->setXY(ML, $y + 11);
}

function vinetas(Manual $p, array $items): void
{
    global $W;
    soltarSub($p, medir($p, $items, 6.5));
    foreach ($items as $it) {
        $p->saltoSiNecesario(9);
        $y = $p->getY();
        $p->setColorHex(GOLD, 'relleno');
        $p->rect(ML + 0.6, $y + 2.1, 1.6, 1.6, 'F');
        $p->setXY(ML + 6.5, $y);
        texto($p, $it, ML + 6.5, $W - 6.5, 9.6, 5.4);
        $p->setY($p->getY() + 1.6);
        $p->setX(ML);
    }
    $p->setY($p->getY() + 1.4);
    $p->setX(ML);
}

function pasos(Manual $p, array $items): void
{
    global $W;
    soltarSub($p, medir($p, $items, 9.5, 5.4, 2.2));
    $n = 1;
    foreach ($items as $it) {
        $p->saltoSiNecesario(11);
        $y = $p->getY();
        $p->setColorHex(GOLD, 'trazo');
        $p->setGrosor(0.4);
        $p->circulo(ML + 2.9, $y + 2.9, 2.9, 'D');
        $p->setGrosor(0.2);
        $p->setFuente('Helvetica', 'B', 7.2);
        $p->setColorHex(GOLD_OS, 'texto');
        $p->setXY(ML, $y + 0.5);
        $p->celda(5.8, 4.8, (string)$n, 0, 0, 'C');
        $p->setXY(ML + 9.5, $y);
        texto($p, $it, ML + 9.5, $W - 9.5, 9.6, 5.4);
        $p->setY($p->getY() + 2.2);
        $p->setX(ML);
        $n++;
    }
    $p->setY($p->getY() + 1.2);
    $p->setX(ML);
}

/** Tabla editorial: filete dorado arriba, cabecera en versalitas, hairlines. */
function tabla(Manual $p, array $cols, array $filas, array $anchos, array $alin = [], int $mono = -1, float $altoFila = 7.6): void
{
    global $W;
    $dibujarCabecera = static function (Manual $p) use ($cols, $anchos, $alin): void {
        $y = $p->getY();
        $p->setColorHex(GOLD, 'trazo');
        $p->setGrosor(0.7);
        $p->linea(ML, $y, ML + array_sum($anchos), $y);
        $p->setGrosor(0.2);
        $p->setFuente('Helvetica', 'B', 7.4);
        $p->setColorHex(NAVY, 'texto');
        $p->espaciado(0.42);
        $p->setXY(ML, $y + 2.2);
        foreach ($cols as $i => $c) {
            $p->celda($anchos[$i], 6, mb_strtoupper($c), 0, 0, $alin[$i] ?? 'L');
        }
        $p->espaciado(0);
        $p->setColorHex(HAIR, 'trazo');
        $p->linea(ML, $y + 9.4, ML + array_sum($anchos), $y + 9.4);
        $p->setColorTexto(0);
        $p->setXY(ML, $y + 9.4);
    };

    soltarSub($p, 9.4 + count($filas) * $altoFila);
    $p->saltoSiNecesario(26);
    $p->setX(ML);
    $dibujarCabecera($p);

    foreach ($filas as $f) {
        if ($p->getY() + $altoFila > $p->alto() - MB) {
            $p->agregarPagina();
            $dibujarCabecera($p);
        }
        $y = $p->getY();
        $p->setX(ML);
        foreach ($f as $i => $celda) {
            $bold = str_starts_with((string)$celda, '*');
            $txt  = $bold ? substr((string)$celda, 1) : (string)$celda;
            if ($i === $mono) {
                $p->setFuente('Courier', $bold ? 'B' : '', 8.3);
                $p->setColorHex(NAVY2, 'texto');
            } else {
                $p->setFuente('Helvetica', $bold ? 'B' : '', 8.9);
                $p->setColorHex($bold ? NAVY : TINTA, 'texto');
            }
            $p->celda($anchos[$i], $altoFila, $txt, 0, 0, $alin[$i] ?? 'L');
        }
        $p->setColorHex(HAIR, 'trazo');
        $p->linea(ML, $y + $altoFila, ML + array_sum($anchos), $y + $altoFila);
        $p->setXY(ML, $y + $altoFila);
    }
    $p->setColorTexto(0);
    $p->setY($p->getY() + 4);
    $p->setX(ML);
}

function nota(Manual $p, string $etiqueta, string $cuerpo, string $acento = GOLD, string $fondo = MARFIL, string $etiquetaColor = GOLD_OS): void
{
    global $W;
    $p->setFuente('Helvetica', '', 9.4);
    $lineas = envolver($p, $cuerpo, $W - 20);
    $alto = 13.5 + count($lineas) * 5.2;
    soltarSub($p, $alto);
    $p->saltoSiNecesario($alto + 5);
    $y = $p->getY();
    $p->setColorHex($fondo, 'relleno');
    $p->rectRedondeado(ML, $y, $W, $alto, 1.6, 'F');
    $p->setColorHex($acento, 'relleno');
    $p->rect(ML, $y, 2.2, $alto, 'F');
    titulillo($p, $etiqueta, ML + 9, $y + 3.6, $etiquetaColor);
    $p->setXY(ML + 9, $y + 8.6);
    texto($p, $cuerpo, ML + 9, $W - 20, 9.4, 5.2);
    $p->setXY(ML, $y + $alto + 5);
}

/** Bloque de codigo / enlace destacado. */
function codigo(Manual $p, string $t, float $tam = 8.6): void
{
    global $W;
    soltarSub($p, 15);
    $p->saltoSiNecesario(15);
    $y = $p->getY();
    $p->setColorHex('#0B1F3A', 'relleno');
    $p->rectRedondeado(ML, $y, $W, 11, 1.6, 'F');
    $p->setFuente('Courier', '', $tam);
    $p->setColorHex('#E9D9A8', 'texto');
    $p->setXY(ML + 6, $y + 2.6);
    $p->celda($W - 12, 6, $t, 0, 0, 'L');
    $p->setColorTexto(0);
    $p->setXY(ML, $y + 15);
}

/** Ficha de rol. */
function ficha(Manual $p, string $nombre, string $color, string $alcance, string $cuerpo): void
{
    global $W;
    $p->setFuente('Helvetica', '', 9.4);
    $lineas = envolver($p, $cuerpo, $W - 16);
    $alto = 20 + count($lineas) * 5.2;
    soltarSub($p, $alto);
    $p->saltoSiNecesario($alto + 5);
    $y = $p->getY();
    $p->setColorHex('#FCFBF8', 'relleno');
    $p->rectRedondeado(ML, $y, $W, $alto, 1.6, 'F');
    $p->setColorHex(HAIR, 'trazo');
    $p->setGrosor(0.2);
    $p->rectRedondeado(ML, $y, $W, $alto, 1.6, 'D');
    $p->setColorHex($color, 'relleno');
    $p->rect(ML, $y, 2.2, $alto, 'F');

    $p->setFuente('Times', 'B', 13);
    $p->setColorHex($color, 'texto');
    $p->setXY(ML + 8, $y + 3.4);
    $p->celda(78, 6.4, $nombre, 0, 0, 'L');
    $p->setFuente('Times', 'I', 9.4);
    $p->setColorHex(GRIS, 'texto');
    $p->celda($W - 94, 6.4, $alcance, 0, 0, 'R');

    $p->setXY(ML + 8, $y + 12.4);
    texto($p, $cuerpo, ML + 8, $W - 16, 9.4, 5.2);
    $p->setXY(ML, $y + $alto + 5);
}

/** Credencial destacada. */
function credencial(Manual $p, string $rol, string $color, string $correo, string $clave): void
{
    global $W;
    $alto = 15.5;
    soltarSub($p, $alto * 2);
    $p->saltoSiNecesario($alto + 3);
    $y = $p->getY();
    $p->setColorHex('#FCFBF8', 'relleno');
    $p->rectRedondeado(ML, $y, $W, $alto, 1.6, 'F');
    $p->setColorHex(HAIR, 'trazo');
    $p->rectRedondeado(ML, $y, $W, $alto, 1.6, 'D');
    $p->setColorHex($color, 'relleno');
    $p->rect(ML, $y, 2.2, $alto, 'F');

    titulillo($p, $rol, ML + 8, $y + 2.6, $color, 7.2);
    $p->setFuente('Courier', '', 9.2);
    $p->setColorHex(NAVY2, 'texto');
    $p->setXY(ML + 8, $y + 7.6);
    $p->celda(78, 5.4, $correo, 0, 0, 'L');
    $p->setFuente('Courier', 'B', 9.2);
    $p->setColorHex(GOLD_OS, 'texto');
    $p->celda($W - 94, 5.4, $clave, 0, 0, 'R');
    $p->setColorTexto(0);
    $p->setXY(ML, $y + $alto + 3);
}

/** Pregunta y respuesta. */
function pregunta(Manual $p, string $q, string $a): void
{
    global $W;
    $p->setFuente('Helvetica', 'B', 9.8);
    $altoQ = count(envolver($p, $q, $W - 8)) * 5.4;
    $p->setFuente('Helvetica', '', 9.6);
    $altoA = count(envolver($p, $a, $W - 8)) * 5.4;
    $p->saltoSiNecesario($altoQ + $altoA + 8);
    $y = $p->getY();
    $p->setColorHex(GOLD, 'trazo');
    $p->setGrosor(0.5);
    $p->linea(ML, $y, ML + 9, $y);
    $p->setGrosor(0.2);
    $p->setXY(ML, $y + 3);
    texto($p, $q, ML, $W - 8, 9.8, 5.4, NAVY, 'B');
    $p->setY($p->getY() + 0.8);
    texto($p, $a, ML, $W - 8, 9.6, 5.4, TINTA);
    $p->setY($p->getY() + 6);
    $p->setX(ML);
}

// ================================================================= CONTENIDO
/** @param array<string,int> $paginas mapa titulo => pagina (segunda pasada) */
function construir(array $paginas): array
{
    global $p, $W;
    $p = new Manual();
    $p->setMargenes(ML, MT, MR, MB);
    $p->setMeta(PORTAL . ' · Manual de usuario', PORTAL);
    $W = $p->anchoUtil();
    $GLOBALS['INDICE'] = [];

    // --------------------------------------------------------------- PORTADA
    $p->cromo = false;
    $p->agregarPagina();
    $p->sinCromo[] = $p->paginaActual();
    $p->setColorHex(NAVY, 'relleno');
    $p->rect(0, 0, $p->ancho(), $p->alto(), 'F');
    $p->setColorHex(GOLD, 'trazo');
    $p->setGrosor(0.5);
    $p->rect(13, 13, $p->ancho() - 26, $p->alto() - 26, 'D');
    $p->setGrosor(0.2);
    $p->setColorHex('#1D3A5C', 'trazo');
    $p->rect(15.4, 15.4, $p->ancho() - 30.8, $p->alto() - 30.8, 'D');

    // Monograma
    $cx = $p->ancho() / 2;
    $p->setColorHex(GOLD, 'trazo');
    $p->setGrosor(0.5);
    $p->circulo($cx, 66, 13, 'D');
    $p->setGrosor(0.2);
    $p->setFuente('Times', 'B', 17);
    $p->setColorHex(GOLD, 'texto');
    $p->setXY($cx - 20, 61.4);
    $p->celda(40, 9, 'GC', 0, 0, 'C');

    $p->setFuente('Helvetica', 'B', 7.6);
    $p->setColorHex('#9BB0C9', 'texto');
    $p->espaciado(0.9);
    $p->setXY($cx - 70, 96);
    $p->celda(140, 5, 'SISTEMA INTEGRAL DE GESTIÓN ESCOLAR', 0, 0, 'C');
    $p->espaciado(0);

    $p->setFuente('Times', 'B', 33);
    $p->setColorTexto(255, 255, 255);
    $p->setXY($cx - 80, 106);
    $p->celda(160, 18, PORTAL, 0, 0, 'C');

    $p->setColorHex(GOLD, 'trazo');
    $p->setGrosor(0.6);
    $p->linea($cx - 17, 132, $cx + 17, 132);
    $p->setGrosor(0.2);

    $p->setFuente('Times', '', 15.5);
    $p->setColorHex('#C6D3E2', 'texto');
    $p->setXY($cx - 70, 138);
    $p->celda(140, 9, 'Manual de usuario', 0, 0, 'C');

    $p->setFuente('Helvetica', '', 9.2);
    $p->setColorHex('#8296AE', 'texto');
    $p->setXY($cx - 75, 152);
    $p->celda(150, 5.4, 'Accesos, roles, cobros mensuales y operación diaria', 0, 0, 'C');

    // Pie de portada
    $p->setColorHex('#1D3A5C', 'trazo');
    $p->linea(34, 236, $p->ancho() - 34, 236);
    $p->setFuente('Courier', '', 9);
    $p->setColorHex(GOLD, 'texto');
    $p->setXY($cx - 75, 242);
    $p->celda(150, 5.4, DOMINIO, 0, 0, 'C');
    $p->setFuente('Helvetica', '', 7.8);
    $p->setColorHex('#7186A0', 'texto');
    $p->setXY($cx - 75, 250);
    $p->celda(150, 4.6, 'Edición ' . date('Y') . ' · Documento de uso interno', 0, 0, 'C');
    $p->setColorTexto(0);

    // ---------------------------------------------------------------- ÍNDICE
    $p->cromo = true;
    $p->seccion = 'Índice';
    $p->agregarPagina();
    titulillo($p, 'Contenido', ML, 34);
    $p->setFuente('Times', 'B', 22);
    $p->setColorHex(NAVY, 'texto');
    $p->setXY(ML, 40);
    $p->celda($W, 12, 'Índice', 0, 1, 'L');
    $p->setColorHex(GOLD, 'trazo');
    $p->setGrosor(0.7);
    $p->linea(ML, 55, ML + 20, 55);
    $p->setGrosor(0.2);
    $p->setColorTexto(0);

    $entradas = [
        ['01', 'Enlaces de acceso'],
        ['02', 'Usuarios y contraseñas'],
        ['03', 'Qué hace cada rol'],
        ['04', 'Primeros pasos del administrador'],
        ['05', 'Cobros mensuales'],
        ['06', 'Notas y asistencia'],
        ['07', 'El portal de los padres'],
        ['08', 'Avisos, tareas y mensajes'],
        ['09', 'Tareas automáticas y respaldos'],
        ['10', 'Seguridad de las cuentas'],
        ['11', 'Preguntas frecuentes'],
    ];
    $y = 66.0;
    foreach ($entradas as [$num, $tit]) {
        $p->setFuente('Helvetica', 'B', 8.4);
        $p->setColorHex(GOLD, 'texto');
        $p->setXY(ML, $y);
        $p->celda(11, 6, $num, 0, 0, 'L');
        $p->setFuente('Times', '', 11.4);
        $p->setColorHex(TINTA, 'texto');
        $p->celda(110, 6, $tit, 0, 0, 'L');

        $pag = (string)($paginas[$tit] ?? '');
        $xIni = ML + 12 + $p->anchoTexto($tit) + 3;
        $xFin = ML + $W - 8;
        $p->setColorHex('#CFCCC3', 'texto');
        $p->setFuente('Helvetica', '', 8);
        $puntos = '';
        while ($p->anchoTexto($puntos . '.') < $xFin - $xIni) { $puntos .= '.'; }
        $p->texto($xIni, $y + 4.3, $puntos);

        $p->setFuente('Times', '', 11);
        $p->setColorHex(NAVY, 'texto');
        $p->setXY(ML + $W - 10, $y);
        $p->celda(10, 6, $pag, 0, 0, 'R');
        $y += 9.4;
    }

    $p->setXY(ML, $y + 8);
    nota(
        $p,
        'Su portal',
        'Este manual está preparado para ' . PORTAL . ', publicado en ' . DOMINIO
        . '. Todos los enlaces que aparecen aquí funcionan tal como están escritos: puede escribirlos '
        . 'directamente en el navegador o guardarlos como favoritos.'
    );

    // --------------------------------------------------------- 01. ENLACES
    seccion($p, '01', 'Enlaces de acceso', 'Una sola dirección para todos. El sistema reconoce quién entra y lo lleva al lugar que le corresponde.');
    parrafo($p, 'El personal del colegio y los padres de familia usan la misma pantalla de acceso. Según el usuario que ingrese, el sistema abre el panel de administración o el portal de padres, sin que nadie tenga que recordar una dirección distinta.');
    codigo($p, DOMINIO . '/ingresar');
    subtitulo($p, 'Todas las direcciones');
    tabla(
        $p,
        ['Para qué sirve', 'Dirección'],
        [
            ['*Ingresar al sistema', DOMINIO . '/ingresar'],
            ['Sitio público del colegio', DOMINIO . '/'],
            ['Panel de administración', DOMINIO . '/panel'],
            ['Portal de padres y encargados', DOMINIO . '/portal'],
            ['Recuperar contraseña', DOMINIO . '/recuperar'],
            ['Cerrar sesión', DOMINIO . '/salir'],
            ['Pre-inscripción en línea', DOMINIO . '/preinscripcion'],
        ],
        [56, $W - 56],
        ['L', 'L'],
        1
    );
    nota(
        $p,
        'Instálelo como aplicación',
        PORTAL . ' funciona como aplicación en el teléfono. Abra ' . DOMINIO . ' en Chrome (Android) o Safari '
        . '(iPhone) y elija "Agregar a la pantalla de inicio". Queda con su propio ícono, a pantalla completa, '
        . 'y sigue mostrando la información ya cargada aunque se caiga el internet.'
    );

    // -------------------------------------------------------- 02. USUARIOS
    seccion($p, '02', 'Usuarios y contraseñas', 'Quién existe hoy en el sistema, con qué entra cada quien y cómo se crean las cuentas nuevas.');
    nota(
        $p,
        'Léase primero',
        'El primer usuario, el administrador, se crea en el paso 3 del instalador: ahí usted escribe su nombre, '
        . 'su correo y su contraseña. Las cuentas de esta sección son las del juego de datos de demostración y '
        . 'solo existen si usted lo importó durante la instalación.',
        '#0F6E8C',
        '#EEF5F9',
        '#0F6E8C'
    );

    subtitulo($p, 'Cuentas principales');
    credencial($p, 'Administrador', '#7A3E9D', 'admin@colegio.gt', 'Admin2026!');
    credencial($p, 'Secretaría y contabilidad', '#0F6E8C', 'secretaria@colegio.gt', 'Secre2026!');
    credencial($p, 'Docente', '#1B6B4A', 'docente@colegio.gt', 'Docente2026!');
    credencial($p, 'Padre o encargado', '#A8621F', 'padre@colegio.gt', 'Padre2026!');

    subtitulo($p, 'Las demás cuentas de demostración');
    tabla(
        $p,
        ['Rol', 'Correo', 'Contraseña'],
        [
            ['Docente', 'lucia.herrera@colegio.gt', 'Docente2026!'],
            ['Docente', 'marco.solis@colegio.gt', 'Docente2026!'],
            ['Encargados (24)', 'encargado2@correo.gt ... encargado25@correo.gt', 'Padre2026!'],
        ],
        [34, 92, $W - 126],
        ['L', 'L', 'L'],
        1
    );
    parrafo($p, 'En total son treinta usuarios: un administrador, una secretaria, tres docentes y veinticinco encargados. Los primeros cinco encargados tienen dos hijos cada uno, de modo que usted pueda probar el selector de hijos dentro del portal.');
    nota(
        $p,
        'Cambie estas contraseñas',
        'Las contraseñas de demostración son públicas: viajan dentro del paquete de instalación. Antes de entregar '
        . 'el sistema al colegio, cámbielas o desactive esas cuentas.',
        '#B3261E',
        '#FBEFEE',
        '#B3261E'
    );

    subtitulo($p, 'Cómo se crean los usuarios reales');
    vinetas($p, [
        'Personal administrativo: entre como administrador y abra Configuración, Usuarios y accesos, Nuevo. Escriba nombre, correo, rol y una contraseña inicial.',
        'Docentes: cree el usuario con rol Docente y luego asígnele materias en Académico, Asignaciones. Sin asignaciones no verá ningún grupo.',
        'Padres y encargados: se crean solos. Al registrar al encargado en la ficha del alumno, escriba su correo y marque "Crear acceso al portal": el sistema genera la cuenta y le envía sus datos.',
    ]);

    subtitulo($p, 'Cómo entrar la primera vez');
    pasos($p, [
        'Escriba ' . DOMINIO . '/ingresar en el navegador, o toque el enlace que recibió por correo.',
        'Escriba su correo y la contraseña que le entregaron. Si la olvidó, use "¿Olvidó su contraseña?".',
        'Entre a Mi perfil y cambie la contraseña por una suya, de al menos diez caracteres.',
    ]);

    // ------------------------------------------------------------ 03. ROLES
    seccion($p, '03', 'Qué hace cada rol', 'Cuatro tipos de usuario, cada uno con una vista distinta del colegio y límites verificados en el servidor.');
    parrafo($p, 'Ningún usuario puede alcanzar información que no le corresponde cambiando la dirección en el navegador: la restricción se comprueba en cada consulta, no en la pantalla. Un padre ve únicamente a sus hijos y un docente únicamente sus grupos.');

    ficha($p, 'Administrador', '#7A3E9D', 'Acceso total', 'Configura el colegio —nombre, logo, tema, NIT, ciclo y correo saliente—, define la estructura académica y gestiona alumnos, cobros, notas, usuarios, respaldos y bitácora.');
    ficha($p, 'Secretaría y contabilidad', '#0F6E8C', 'El dinero y los expedientes', 'Alumnos y encargados, conceptos de cobro, cargos mensuales, pagos y recibos, estados de cuenta, morosidad, corte de caja, aprobación de comprobantes, avisos y pre-inscripciones. No tiene acceso a las notas.');
    ficha($p, 'Docente', '#1B6B4A', 'Solo sus grupos', 'Captura de notas con guardado automático, actividades con ponderación, zona y examen, conducta, pase de asistencia, tareas, avisos y mensajes con los encargados. No ve información de cobros.');
    ficha($p, 'Padre o encargado', '#A8621F', 'Solo sus hijos', 'Estado de cuenta, envío de comprobantes de pago, descarga de recibos y boletas, asistencia, tareas, avisos y mensajes con los docentes. Si tiene varios hijos, cambia entre ellos con un selector.');

    subtitulo($p, 'Quién entra a qué');
    tabla(
        $p,
        ['Módulo', 'Admin.', 'Secre.', 'Docente', 'Padre'],
        [
            ['Configuración del colegio', 'Sí', '—', '—', '—'],
            ['Estructura académica', 'Sí', '—', '—', '—'],
            ['Alumnos y encargados', 'Sí', 'Sí', 'Ver', '—'],
            ['Cobros, pagos y recibos', 'Sí', 'Sí', '—', 'Sus hijos'],
            ['Aprobar comprobantes', 'Sí', 'Sí', '—', '—'],
            ['Notas y boletas', 'Sí', '—', 'Sus grupos', 'Sus hijos'],
            ['Asistencia', 'Sí', 'Ver', 'Sus grupos', 'Sus hijos'],
            ['Tareas', 'Sí', '—', 'Sus grupos', 'Sus hijos'],
            ['Avisos y calendario', 'Sí', 'Sí', 'Sí', 'Ver'],
            ['Mensajes', 'Sí', 'Sí', 'Sí', 'Sí'],
            ['Reportes y gráficas', 'Sí', 'Sí', '—', '—'],
            ['Usuarios, respaldos, bitácora', 'Sí', '—', '—', '—'],
        ],
        [62, 22, 22, 30, $W - 136],
        ['L', 'C', 'C', 'C', 'C'],
        -1,
        7.2
    );

    // ------------------------------------------------------- 04. ADMIN
    seccion($p, '04', 'Primeros pasos del administrador', 'Diez pasos en orden. Cada uno se apoya en el anterior; hágalos una sola vez, al principio del ciclo.');
    pasos($p, [
        'Configuración, Colegio: nombre, logo, favicon, NIT, dirección, teléfono, tema de color y texto del recibo. El logo genera automáticamente los íconos de la aplicación.',
        'Configuración, Correo (SMTP): servidor, puerto, usuario y contraseña del correo del colegio. Sin esto no salen recibos ni recordatorios. Use el botón de prueba antes de continuar.',
        'Académico: cree el ciclo escolar, los niveles, los grados, las secciones y las materias. Después los periodos —bimestres o trimestres— y revise la escala de notas.',
        'Académico, Asignaciones: relacione a cada docente con las materias y secciones que imparte.',
        'Alumnos: registre a los alumnos uno por uno, o con la importación masiva desde Excel. Descargue primero la plantilla.',
        'En cada ficha de alumno agregue hasta tres encargados y marque "Crear acceso al portal" a quienes deban entrar.',
        'Cobros, Conceptos: cree la colegiatura mensual y los demás conceptos —inscripción, transporte, laboratorio— con su monto y su día de vencimiento.',
        'Cobros, Generar: emita los cargos del mes o de un rango completo de meses.',
        'Configuración, Usuarios y accesos: cree las cuentas de la secretaria y de los docentes.',
        'Configuración, Tareas automáticas: copie el enlace del cron y péguelo en el cron de cPanel, cada quince minutos.',
    ]);

    // ----------------------------------------------------------- 05. COBROS
    seccion($p, '05', 'Cobros mensuales', 'La colegiatura es mensual. El sistema genera el cargo, calcula la mora, recibe el pago y emite el recibo numerado.');

    subtitulo($p, 'Generar los cargos del mes');
    vinetas($p, [
        'Abra Cobros, Generar y elija el mes; o un mes desde y un mes hasta para emitir el ciclo completo de una sola vez.',
        'El sistema nunca duplica: si un alumno ya tiene el cargo de ese mes, lo omite y se lo informa.',
        'Solo se generan cargos a los alumnos activos en el ciclo vigente.',
    ]);

    subtitulo($p, 'Registrar un pago en caja');
    pasos($p, [
        'Cobros, Pagos, Nuevo pago. Busque al alumno por nombre o por código.',
        'Marque los cargos que está cubriendo. Puede pagar varios meses a la vez o abonar una parte.',
        'Elija la forma de pago —efectivo, transferencia, tarjeta o depósito— y anote la referencia.',
        'Guarde. El sistema emite el recibo con su número correlativo y lo envía por correo al encargado.',
    ]);

    subtitulo($p, 'Cuando el padre envía su comprobante');
    pasos($p, [
        'El encargado entra al portal, elige los cargos que paga y sube la foto o el PDF del depósito.',
        'El pago queda "En revisión": todavía no descuenta el saldo.',
        'La secretaría lo ve en Cobros, Por revisar, abre el comprobante y lo aprueba o lo rechaza indicando el motivo.',
        'Al aprobarlo se aplica al saldo y se emite el recibo. Si el saldo cambió desde que el padre lo envió, el sistema aplica solo lo que corresponde y lo advierte en pantalla.',
    ]);

    subtitulo($p, 'Los cuatro estados de un pago');
    tabla(
        $p,
        ['Estado', 'Qué significa'],
        [
            ['*Pendiente', 'El cargo está emitido y todavía no se ha pagado. Después de la fecha de vencimiento empieza a generar mora.'],
            ['*En revisión', 'El encargado envió un comprobante desde el portal. No descuenta saldo hasta que se apruebe.'],
            ['*Aplicado', 'El pago fue recibido o aprobado. Descuenta el saldo y tiene recibo con número correlativo.'],
            ['*Rechazado', 'La secretaría no aceptó el comprobante. El encargado ve el motivo y puede enviar otro.'],
        ],
        [30, $W - 30],
        ['L', 'L'],
        -1,
        11.6
    );
    nota(
        $p,
        'La mora se calcula sola',
        'Usted define el porcentaje o el monto de mora en el concepto de cobro. A partir del día siguiente al '
        . 'vencimiento, la tarea automática la aplica sin que nadie tenga que hacer nada; si el pago entra antes, '
        . 'nunca se cobra.'
    );

    subtitulo($p, 'Reportes de cobranza');
    vinetas($p, [
        'Estado de cuenta por alumno o por familia, con saldo y detalle mes a mes.',
        'Reporte de morosidad: quién debe, cuánto y desde cuándo.',
        'Proyección de ingresos del ciclo y corte de caja del día.',
        'Todo se exporta a PDF y a Excel.',
    ]);

    // ------------------------------------------------------------ 06. NOTAS
    seccion($p, '06', 'Notas y asistencia', 'Lo que el docente hace todos los días, desde la computadora o desde el teléfono.');

    subtitulo($p, 'Cargar notas');
    vinetas($p, [
        'Abra Notas y elija su grupo, su materia y el periodo. Aparece una hoja de captura con sus alumnos.',
        'Escriba y muévase con las flechas o con la tecla Tab. Se guarda solo: no hay botón de guardar.',
        'Las notas fuera del rango permitido se marcan en rojo y no se guardan.',
        'La nota se compone de zona, sobre sesenta, más examen, sobre cuarenta. El sistema calcula el total y advierte quién no alcanza sesenta.',
        'Cuando termine, cierre el periodo: eso bloquea la edición y habilita las boletas.',
    ]);

    subtitulo($p, 'Pasar asistencia');
    vinetas($p, [
        'Abra Asistencia y su grupo. Todos aparecen presentes: marque solo a los ausentes y guarde. Son dos toques desde el teléfono.',
        'Puede marcar la ausencia como justificada y escribir el motivo.',
        'A la tercera ausencia sin justificar, el sistema avisa automáticamente al encargado.',
        'El reporte mensual de asistencia se descarga en PDF o en Excel.',
    ]);

    // ----------------------------------------------------------- 07. PORTAL
    seccion($p, '07', 'El portal de los padres', 'Lo que ve un encargado cuando entra, y por qué no puede ver nada más.');
    parrafo($p, 'El encargado entra en ' . DOMINIO . '/ingresar con su correo y llega directamente al portal. Si tiene más de un hijo inscrito, cambia entre ellos con el selector de la parte superior.');
    vinetas($p, [
        'Estado de cuenta: cuánto debe, de qué meses y desde cuándo, con el detalle de cada cargo.',
        'Pagar: elige los cargos y envía el comprobante. Si el colegio configuró un enlace de pago en línea, aparece el botón; si no, no se muestra nada.',
        'Recibos: descarga en PDF todos los recibos de los pagos ya aplicados.',
        'Calificaciones: boleta del periodo con la nota de cada materia y los comentarios del docente.',
        'Asistencia: días presentes, ausentes y justificados del mes.',
        'Tareas: qué se dejó, para cuándo, y si ya fue entregada.',
        'Avisos y mensajes: circulares del colegio y conversación directa con los docentes.',
    ]);

    subtitulo($p, 'Enviar un comprobante desde el teléfono');
    pasos($p, [
        'Tome la foto del depósito o de la transferencia, o descargue el comprobante del banco en PDF.',
        'En el portal, abra Estado de cuenta y toque Pagar.',
        'Marque los meses que está cubriendo, adjunte el archivo y escriba la referencia del banco.',
        'Envíe. El pago queda en revisión y el encargado recibe el recibo por correo en cuanto la secretaría lo apruebe.',
    ]);
    nota(
        $p,
        'Si un encargado no recibe los correos',
        'Revise que el correo esté bien escrito en la ficha del alumno, en Encargados, y pídale que busque en la '
        . 'carpeta de correo no deseado. Toda la información sigue disponible dentro del portal aunque el correo falle.'
    );

    // ---------------------------------------------------- 08. COMUNICACIÓN
    seccion($p, '08', 'Avisos, tareas y mensajes', 'Una sola circular llega al panel, al teléfono, al correo y a WhatsApp.');
    vinetas($p, [
        'Avisos: escriba el aviso, adjunte archivos y elija a quién va dirigido: todo el colegio, un nivel, un grado, una sección o un alumno. Puede programar la fecha de publicación y la de vencimiento, y ver quién ya lo leyó.',
        'Calendario: las actividades que publique aparecen también en el sitio público del colegio.',
        'Tareas: el docente publica la tarea con su fecha de entrega y el encargado ve si ya fue entregada.',
        'Mensajes: conversación directa entre docente y encargado, dentro del sistema, con historial.',
        'Cada aviso llega además como notificación en el teléfono, por correo, y con un botón para enviarlo por WhatsApp.',
    ]);

    subtitulo($p, 'Buenas prácticas');
    vinetas($p, [
        'Dirija cada aviso al grupo más pequeño posible: una circular que llega a quien no le corresponde deja de leerse.',
        'Use la fecha de vencimiento para que los avisos viejos desaparezcan solos del portal.',
        'Revise los acuses de lectura antes de reenviar: normalmente falta responder un puñado de familias, no todas.',
        'Para lo urgente, combine el aviso con el botón de WhatsApp; para lo formal, deje constancia con el aviso y el correo.',
    ]);

    // ------------------------------------------------------------- 09. CRON
    seccion($p, '09', 'Tareas automáticas y respaldos', 'Quince minutos de diferencia entre un sistema que le recuerda a los padres y uno que no.');
    parrafo($p, 'En cPanel, en Trabajos cron, agregue esta línea. El token se genera solo durante la instalación y lo encuentra en Configuración, Tareas automáticas.');
    codigo($p, '*/15 * * * * curl -s "' . DOMINIO . '/cron/run.php?token=SU_TOKEN"', 7.6);
    parrafo($p, 'Sin el cron el sistema funciona, pero nadie recibe recordatorios y la mora no se calcula. Con él, cada quince minutos:');
    vinetas($p, [
        'Calcula la mora de los cargos vencidos y no pagados.',
        'Envía recordatorios de pago: tres días antes del vencimiento, el día del vencimiento, y cada siete días mientras el cargo siga en mora.',
        'Depura intentos de acceso, enlaces de recuperación vencidos y notificaciones antiguas.',
        'Genera el respaldo automático de la base de datos los domingos.',
    ]);
    nota(
        $p,
        'Respaldo manual',
        'En Configuración, Respaldos puede descargar en cualquier momento un respaldo completo de la base de datos, '
        . 'comprimido, con un solo clic. Guarde una copia fuera del servidor.'
    );

    // -------------------------------------------------------- 10. SEGURIDAD
    seccion($p, '10', 'Seguridad de las cuentas', 'Las reglas que protegen los expedientes y el dinero del colegio.');
    tabla(
        $p,
        ['Regla', 'Cómo funciona'],
        [
            ['*Contraseñas', 'Mínimo diez caracteres. Se guardan cifradas: nadie puede verlas, tampoco usted.'],
            ['*Intentos fallidos', 'Cinco intentos equivocados bloquean el acceso durante quince minutos.'],
            ['*Sesión inactiva', 'Se cierra sola a los treinta minutos sin actividad.'],
            ['*Recuperar contraseña', 'Enlace de un solo uso que vence a los treinta minutos.'],
            ['*Dos pasos', 'Verificación por correo, opcional, para la cuenta de administrador.'],
            ['*Cerrar en todos lados', 'Un botón en su perfil cierra la sesión en cualquier dispositivo olvidado.'],
            ['*Bitácora', 'Cada acción importante queda registrada con usuario, fecha y dirección IP.'],
            ['*Dar de baja', 'Nunca borre un usuario: desactívelo. Así conserva su historial de pagos y notas.'],
        ],
        [44, $W - 44],
        ['L', 'L'],
        -1,
        9.4
    );
    nota(
        $p,
        'Si olvida la contraseña del administrador',
        'Use ' . DOMINIO . '/recuperar con el correo del administrador. Si tampoco tiene acceso a ese correo, '
        . 'la contraseña puede restablecerse desde la base de datos en phpMyAdmin; pida apoyo técnico antes de '
        . 'tocar la tabla de usuarios.'
    );

    // ------------------------------------------------ 11. PREGUNTAS FRECUENTES
    seccion($p, '11', 'Preguntas frecuentes', 'Las dudas que aparecen durante las primeras semanas de uso.');
    pregunta($p, '¿Puede un padre ver la información de otro alumno?',
        'No. El sistema comprueba en cada consulta que el alumno pertenezca a ese encargado. Cambiar el número en la dirección del navegador devuelve un error, no los datos de otra familia.');
    pregunta($p, '¿Qué pasa si genero dos veces los cargos del mismo mes?',
        'Nada. El sistema detecta los cargos que ya existen, los omite y le informa cuántos creó y cuántos dejó fuera.');
    pregunta($p, 'Un docente cambió de grado a mitad de ciclo, ¿pierdo sus notas?',
        'No. Las notas quedan asociadas al grupo y a la materia, no al docente. Retire la asignación anterior y cree la nueva: el historial se conserva.');
    pregunta($p, '¿Puedo cobrar un mes por adelantado o abonar solo una parte?',
        'Sí. En un mismo pago puede cubrir varios meses, y también puede abonar una parte: el saldo restante queda pendiente y sigue apareciendo en el estado de cuenta.');
    pregunta($p, '¿Cómo cambio el logo o los colores del colegio?',
        'En Configuración, Colegio. Al subir el logo el sistema regenera los íconos de la aplicación; el tema de color se aplica al panel, al portal y a los documentos en PDF.');
    pregunta($p, '¿Se puede usar sin internet?',
        'Parcialmente. Instalado como aplicación, el portal sigue mostrando la información ya consultada; para guardar notas, pagos o asistencia sí hace falta conexión.');
    pregunta($p, '¿Dónde quedan los archivos que suben los padres?',
        'En una carpeta protegida del servidor, con el nombre cambiado y sin permiso de ejecución. Solo se alcanzan desde el sistema, por quien tiene derecho a verlos.');

    // --------------------------------------------------------- CONTRAPORTADA
    $p->cromo = false;
    $p->agregarPagina();
    $p->sinCromo[] = $p->paginaActual();
    $p->setColorHex(NAVY, 'relleno');
    $p->rect(0, 0, $p->ancho(), $p->alto(), 'F');
    $p->setColorHex(GOLD, 'trazo');
    $p->setGrosor(0.5);
    $p->rect(13, 13, $p->ancho() - 26, $p->alto() - 26, 'D');
    $p->setGrosor(0.2);
    $cx2 = $p->ancho() / 2;
    $p->setColorHex(GOLD, 'trazo');
    $p->setGrosor(0.5);
    $p->circulo($cx2, 118, 11, 'D');
    $p->setGrosor(0.2);
    $p->setFuente('Times', 'B', 14);
    $p->setColorHex(GOLD, 'texto');
    $p->setXY($cx2 - 20, 114);
    $p->celda(40, 8, 'GC', 0, 0, 'C');
    $p->setFuente('Times', 'B', 20);
    $p->setColorTexto(255, 255, 255);
    $p->setXY($cx2 - 70, 138);
    $p->celda(140, 10, PORTAL, 0, 0, 'C');
    $p->setFuente('Courier', '', 9);
    $p->setColorHex(GOLD, 'texto');
    $p->setXY($cx2 - 70, 152);
    $p->celda(140, 5.4, DOMINIO, 0, 0, 'C');
    $p->setFuente('Helvetica', '', 8);
    $p->setColorHex('#7186A0', 'texto');
    $p->setXY($cx2 - 75, 164);
    $p->celda(150, 4.6, 'Manual de usuario · Edición ' . date('Y'), 0, 0, 'C');
    $p->setColorTexto(0);

    return [$p, $GLOBALS['INDICE']];
}

// Dos pasadas: la primera descubre en que pagina cae cada seccion.
[$tmp, $mapa] = construir([]);
[$pdf, ] = construir($mapa);

$ruta = BASE_PATH . '/Manual-Gestion-Colegio.pdf';
file_put_contents($ruta, $pdf->salida());
echo 'OK ', $ruta, ' · ', number_format(filesize($ruta) / 1024, 1), " KB · {$pdf->paginaActual()} paginas\n";
