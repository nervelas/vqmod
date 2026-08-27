<?php
declare(strict_types=1);

namespace App\Servicios;

use App\Core\Settings;
use Vendor\Pdf\Pdf;

/** Documento PDF con la identidad visual del colegio. */
class DocumentoBase extends Pdf
{
    public string $tituloDoc = '';
    public string $subtitulo = '';
    public bool $conEncabezado = true;
    protected array $tema = ['primario' => '#0B1F3A', 'acento' => '#C9A961'];

    public function __construct(array $tamano = self::A4, string $orientacion = 'P')
    {
        parent::__construct($tamano, $orientacion);
        $t = \App\Controllers\Configuracion::TEMAS[(string)Settings::get('tema', 'default')] ?? null;
        if ($t) {
            $this->tema = ['primario' => $t['primario'], 'acento' => $t['acento']];
        }
        $this->setMargenes(14, 34, 14, 20);
    }

    protected function encabezado(): void
    {
        if (!$this->conEncabezado) {
            return;
        }
        $y = 10;
        $logo = (string)Settings::get('colegio_logo', '');
        $rutaLogo = $logo !== '' ? BASE_PATH . '/storage/uploads/' . $logo : '';
        $x = 14;
        if ($rutaLogo !== '' && is_file($rutaLogo)) {
            $this->imagen($rutaLogo, $x, $y - 2, 0, 16);
            $x += 20;
        }
        $this->setColorHex($this->tema['primario'], 'texto');
        $this->setFuente('Times', 'B', 16);
        $this->setXY($x, $y - 2);
        $this->celda(120, 7, (string)Settings::get('colegio_nombre', 'EduPortal'), 0, 2, 'L');
        $this->setFuente('Helvetica', '', 8.5);
        $this->setColorTexto(110, 110, 110);
        $this->setX($x);
        $sub = trim((string)Settings::get('colegio_direccion', '') . '   ' . (string)Settings::get('colegio_telefono', ''));
        $this->celda(120, 4.5, $sub, 0, 2, 'L');
        $this->setX($x);
        $this->celda(120, 4.5, 'NIT: ' . (string)Settings::get('colegio_nit', 'C/F'), 0, 0, 'L');

        if ($this->tituloDoc !== '') {
            $this->setFuente('Helvetica', 'B', 11);
            $this->setColorHex($this->tema['primario'], 'texto');
            $this->setXY($this->w - 90, $y);
            $this->celda(76, 6, $this->tituloDoc, 0, 2, 'R');
            if ($this->subtitulo !== '') {
                $this->setFuente('Helvetica', '', 8.5);
                $this->setColorTexto(110, 110, 110);
                $this->setX($this->w - 90);
                $this->celda(76, 5, $this->subtitulo, 0, 0, 'R');
            }
        }
        $this->setColorHex($this->tema['acento'], 'trazo');
        $this->setGrosor(0.8);
        $this->linea(14, 28, $this->w - 14, 28);
        $this->setGrosor(0.2);
        $this->setColorTexto(0);
        $this->setXY(14, 34);
    }

    protected function pie(): void
    {
        $this->setY($this->h - 14, true);
        $this->setColorTrazo(225, 225, 225);
        $this->linea(14, $this->h - 15, $this->w - 14, $this->h - 15);
        $this->setFuente('Helvetica', '', 7.5);
        $this->setColorTexto(130, 130, 130);
        $this->celda(($this->w - 28) / 2, 6, (string)Settings::get('colegio_nombre', 'EduPortal'), 0, 0, 'L');
        $this->celda(($this->w - 28) / 2, 6, 'Pagina ' . $this->paginaActual() . ' de {nb}   ·   ' . date('d/m/Y H:i'), 0, 0, 'R');
        $this->setColorTexto(0);
    }

    /** Encabezado de tabla con el color institucional. */
    public function tablaEncabezado(array $columnas, float $alto = 8): void
    {
        $this->setFuente('Helvetica', 'B', 8.5);
        $this->setColorHex($this->tema['primario'], 'relleno');
        $this->setColorTexto(255, 255, 255);
        foreach ($columnas as [$ancho, $texto, $alinear]) {
            $this->celda($ancho, $alto, $texto, 0, 0, $alinear, true);
        }
        $this->ln($alto);
        $this->setColorTexto(0);
        $this->setColorRelleno(255, 255, 255);
        $this->setFuente('Helvetica', '', 9);
    }

    public function tablaFila(array $celdas, bool $alterna = false, float $alto = 7): void
    {
        $this->saltoSiNecesario($alto + 2);
        if ($alterna) {
            $this->setColorRelleno(246, 247, 250);
        }
        foreach ($celdas as [$ancho, $texto, $alinear]) {
            $this->celda($ancho, $alto, (string)$texto, 0, 0, $alinear, $alterna);
        }
        $this->ln($alto);
        $this->setColorRelleno(255, 255, 255);
        $this->setColorTrazo(232, 232, 232);
        $this->linea(14, $this->getY(), $this->w - 14, $this->getY());
    }
}
