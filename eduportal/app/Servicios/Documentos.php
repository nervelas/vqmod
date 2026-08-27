<?php
declare(strict_types=1);

namespace App\Servicios;

use App\Core\Imagen;
use App\Core\Settings;
use App\Models\Academico;
use App\Models\Alumno;
use App\Models\Cobranza;
use App\Models\Evaluacion;
use Vendor\Pdf\Pdf;

final class Documentos
{
    /** Recibo de pago numerado, con logo y firma. */
    public static function recibo(array $pago, array $detalle, array $alumno): DocumentoBase
    {
        $pdf = new DocumentoBase(Pdf::A4);
        $pdf->tituloDoc = 'RECIBO DE PAGO';
        $pdf->subtitulo = 'No. ' . ($pago['recibo_no'] ?? 's/n');
        $pdf->setMeta('Recibo ' . ($pago['recibo_no'] ?? ''), (string)Settings::get('colegio_nombre', 'EduPortal'));
        $pdf->agregarPagina();

        $pdf->setFuente('Helvetica', '', 9.5);
        $ancho = $pdf->anchoUtil();
        $col = $ancho / 2;

        $pdf->setFuente('Helvetica', 'B', 9);
        $pdf->celda($col, 6, 'DATOS DEL ALUMNO', 0, 0, 'L');
        $pdf->celda($col, 6, 'DATOS DEL PAGO', 0, 1, 'L');
        $pdf->setFuente('Helvetica', '', 9.5);

        $y0 = $pdf->getY();
        $pdf->celda($col, 5.5, 'Nombre: ' . Alumno::nombre($alumno), 0, 2, 'L');
        $pdf->celda($col, 5.5, 'Codigo: ' . (string)$alumno['codigo'], 0, 2, 'L');
        $pdf->celda($col, 5.5, 'Grado: ' . (string)($alumno['grupo'] ?? '-'), 0, 2, 'L');
        $yA = $pdf->getY();

        $pdf->setXY(14 + $col, $y0);
        $pdf->celda($col, 5.5, 'Fecha: ' . fecha((string)$pago['fecha']), 0, 2, 'L');
        $pdf->setX(14 + $col);
        $pdf->celda($col, 5.5, 'Metodo: ' . ucfirst((string)$pago['metodo']), 0, 2, 'L');
        $pdf->setX(14 + $col);
        $pdf->celda($col, 5.5, 'Referencia: ' . ((string)($pago['referencia'] ?? '') ?: '-'), 0, 2, 'L');
        $pdf->setXY(14, max($yA, $pdf->getY()) + 4);

        $pdf->tablaEncabezado([
            [$ancho - 40, 'Concepto', 'L'],
            [40, 'Monto', 'R'],
        ]);
        $i = 0;
        $total = 0.0;
        foreach ($detalle as $d) {
            $pdf->tablaFila([
                [$ancho - 40, (string)$d['descripcion'], 'L'],
                [40, moneda((float)$d['monto']), 'R'],
            ], $i % 2 === 1);
            $total += (float)$d['monto'];
            $i++;
        }
        $pdf->ln(2);
        $pdf->setFuente('Helvetica', 'B', 11);
        $pdf->setColorHex('#0B1F3A', 'texto');
        $pdf->celda($ancho - 40, 9, 'TOTAL RECIBIDO', 0, 0, 'R');
        $pdf->celda(40, 9, moneda($total), 0, 1, 'R');
        $pdf->setColorTexto(0);

        $pdf->ln(4);
        $pdf->setFuente('Helvetica', '', 9);
        $letras = self::montoEnLetras($total);
        $pdf->multiCelda($ancho, 5, 'Son: ' . $letras . ' ' . (string)Settings::get('moneda', 'Q') . '.');
        $nota = (string)Settings::get('recibo_texto', '');
        if ($nota !== '') {
            $pdf->ln(2);
            $pdf->setColorTexto(110, 110, 110);
            $pdf->multiCelda($ancho, 5, $nota);
            $pdf->setColorTexto(0);
        }

        // Firma
        $pdf->ln(18);
        $firma = (string)Settings::get('director_firma', '');
        $rutaFirma = $firma !== '' ? BASE_PATH . '/storage/uploads/' . $firma : '';
        $xFirma = 14 + $ancho - 70;
        if ($rutaFirma !== '' && is_file($rutaFirma)) {
            $pdf->imagen($rutaFirma, $xFirma + 10, $pdf->getY() - 14, 50, 0);
        }
        $pdf->setColorTrazo(120, 120, 120);
        $pdf->linea($xFirma, $pdf->getY(), $xFirma + 70, $pdf->getY());
        $pdf->setXY($xFirma, $pdf->getY() + 1);
        $pdf->setFuente('Helvetica', '', 8.5);
        $pdf->celda(70, 5, (string)Settings::get('director_nombre', 'Direccion'), 0, 2, 'C');
        $pdf->setX($xFirma);
        $pdf->setColorTexto(120, 120, 120);
        $pdf->celda(70, 5, 'Direccion General', 0, 0, 'C');
        return $pdf;
    }

    /** Boleta de calificaciones de un alumno. */
    public static function boleta(array $alumno, ?int $cicloId = null, bool $nuevaPagina = true, ?DocumentoBase $pdf = null): DocumentoBase
    {
        $cicloId = $cicloId ?: Academico::cicloActivoId();
        $ciclo = Academico::cicloActivo();
        $datos = Evaluacion::boleta((int)$alumno['id'], $cicloId);

        if ($pdf === null) {
            $pdf = new DocumentoBase(Pdf::A4);
            $pdf->setMeta('Boleta de calificaciones', (string)Settings::get('colegio_nombre', 'EduPortal'));
        }
        $pdf->tituloDoc = 'BOLETA DE CALIFICACIONES';
        $pdf->subtitulo = 'Ciclo ' . (string)($ciclo['nombre'] ?? date('Y'));
        if ($nuevaPagina) {
            $pdf->agregarPagina();
        }
        $ancho = $pdf->anchoUtil();

        $pdf->setColorRelleno(247, 245, 240);
        $pdf->rectRedondeado(14, $pdf->getY(), $ancho, 20, 3, 'F');
        $pdf->setXY(18, $pdf->getY() + 3);
        $pdf->setFuente('Times', 'B', 13);
        $pdf->setColorHex('#0B1F3A', 'texto');
        $pdf->celda($ancho - 8, 7, Alumno::nombre($alumno), 0, 2, 'L');
        $pdf->setX(18);
        $pdf->setFuente('Helvetica', '', 9);
        $pdf->setColorTexto(90, 90, 90);
        $pdf->celda($ancho - 8, 6, 'Codigo: ' . (string)$alumno['codigo'] . '    Grado: ' . (string)($alumno['grupo'] ?? '-'), 0, 0, 'L');
        $pdf->setXY(14, $pdf->getY() + 12);
        $pdf->setColorTexto(0);

        $periodos = $datos['periodos'];
        $nPer = max(1, count($periodos));
        $anchoMateria = $ancho - ($nPer * 22) - 24;
        $cols = [[$anchoMateria, 'Materia', 'L']];
        foreach ($periodos as $p) {
            $cols[] = [22, mb_substr((string)$p['nombre'], 0, 12), 'C'];
        }
        $cols[] = [24, 'Promedio', 'C'];
        $pdf->tablaEncabezado($cols);

        $min = Evaluacion::notaMinima();
        $i = 0;
        foreach ($datos['materias'] as $m) {
            $fila = [[$anchoMateria, (string)$m['materia'], 'L']];
            foreach ($periodos as $p) {
                $n = $m['periodos'][(int)$p['id']] ?? null;
                $fila[] = [22, $n && $n['total'] !== null ? number_format((float)$n['total'], 0) : '—', 'C'];
            }
            $fila[] = [24, $m['promedio'] !== null ? number_format((float)$m['promedio'], 2) : '—', 'C'];
            $pdf->tablaFila($fila, $i % 2 === 1);
            $i++;
        }

        $pdf->ln(3);
        $pdf->setFuente('Helvetica', 'B', 11);
        $pdf->setColorHex('#0B1F3A', 'texto');
        $pdf->celda($ancho - 40, 9, 'PROMEDIO GENERAL', 0, 0, 'R');
        $pdf->celda(40, 9, number_format((float)$datos['promedio'], 2), 0, 1, 'R');
        $pdf->setColorTexto(0);

        if (Settings::bool('ranking_boleta', false) && !empty($alumno['seccion_id'])) {
            $rank = Evaluacion::promediosSeccion((int)$alumno['seccion_id'], $cicloId);
            $pos = 0;
            foreach ($rank as $idx => $r) {
                if ((int)$r['id'] === (int)$alumno['id']) {
                    $pos = $idx + 1;
                    break;
                }
            }
            if ($pos > 0) {
                $pdf->setFuente('Helvetica', '', 9.5);
                $pdf->celda($ancho, 6, 'Posicion en el grupo: ' . $pos . ' de ' . count($rank), 0, 1, 'R');
            }
        }

        $pdf->ln(3);
        $pdf->setFuente('Helvetica', '', 8.5);
        $pdf->setColorTexto(110, 110, 110);
        $pdf->multiCelda($ancho, 5,
            'Nota minima de promocion: ' . number_format($min, 0)
            . '. Ponderacion: zona ' . number_format(Evaluacion::pondZona(), 0)
            . ' puntos y evaluacion ' . number_format(Evaluacion::pondExamen(), 0) . ' puntos.');
        $pdf->setColorTexto(0);

        $pdf->ln(14);
        $y = $pdf->getY();
        $pdf->setColorTrazo(120, 120, 120);
        $pdf->linea(20, $y, 20 + 60, $y);
        $pdf->linea($pdf->ancho() - 80, $y, $pdf->ancho() - 20, $y);
        $pdf->setXY(20, $y + 1);
        $pdf->setFuente('Helvetica', '', 8.5);
        $pdf->celda(60, 5, (string)Settings::get('director_nombre', 'Direccion'), 0, 0, 'C');
        $pdf->setXY($pdf->ancho() - 80, $y + 1);
        $pdf->celda(60, 5, 'Padre o encargado', 0, 1, 'C');
        return $pdf;
    }

    /** Boletas de todo un grupo en un solo PDF. */
    public static function boletasGrupo(array $alumnos, ?int $cicloId = null): DocumentoBase
    {
        $pdf = new DocumentoBase(Pdf::A4);
        $pdf->setMeta('Boletas de calificaciones', (string)Settings::get('colegio_nombre', 'EduPortal'));
        foreach ($alumnos as $a) {
            self::boleta($a, $cicloId, true, $pdf);
        }
        return $pdf;
    }

    /** Carne del alumno con codigo QR. */
    public static function carne(array $alumno): DocumentoBase
    {
        $pdf = new DocumentoBase([86.0, 54.0], 'L');
        $pdf->conEncabezado = false;
        $pdf->setMargenes(4, 4, 4, 4);
        $pdf->setMeta('Carne ' . (string)$alumno['codigo']);
        $pdf->agregarPagina();
        self::pintarCarne($pdf, $alumno, 0, 0);
        return $pdf;
    }

    /** Hoja A4 con varios carnes (8 por pagina). */
    public static function carnes(array $alumnos): DocumentoBase
    {
        $pdf = new DocumentoBase(Pdf::A4);
        $pdf->conEncabezado = false;
        $pdf->setMargenes(10, 10, 10, 10);
        $pdf->setMeta('Carnes de alumnos');
        $porPagina = 8;
        $i = 0;
        foreach ($alumnos as $a) {
            if ($i % $porPagina === 0) {
                $pdf->agregarPagina();
            }
            $col = $i % 2;
            $fila = intdiv($i % $porPagina, 2);
            $x = 12 + $col * 94;
            $y = 12 + $fila * 62;
            self::pintarCarne($pdf, $a, $x, $y);
            $i++;
        }
        if ($i === 0) {
            $pdf->agregarPagina();
        }
        return $pdf;
    }

    private static function pintarCarne(DocumentoBase $pdf, array $alumno, float $x, float $y): void
    {
        $w = 86.0;
        $h = 54.0;
        $pdf->setColorHex('#0B1F3A', 'relleno');
        $pdf->rectRedondeado($x + 1, $y + 1, $w - 2, $h - 2, 3, 'F');
        $pdf->setColorHex('#C9A961', 'relleno');
        $pdf->rect($x + 1, $y + 1, $w - 2, 3.2, 'F');

        $logo = (string)Settings::get('colegio_logo', '');
        $rutaLogo = $logo !== '' ? BASE_PATH . '/storage/uploads/' . $logo : '';
        $tx = $x + 5;
        if ($rutaLogo !== '' && is_file($rutaLogo)) {
            $pdf->imagen($rutaLogo, $x + 5, $y + 6, 0, 8);
            $tx += 10;
        }
        $pdf->setColorTexto(255, 255, 255);
        $pdf->setFuente('Times', 'B', 8.5);
        $pdf->setXY($tx, $y + 6);
        $pdf->celda($w - ($tx - $x) - 6, 5, mb_substr((string)Settings::get('colegio_nombre', 'EduPortal'), 0, 34), 0, 0, 'L');

        // Foto
        $foto = (string)($alumno['foto'] ?? '');
        $rutaFoto = $foto !== '' ? BASE_PATH . '/storage/uploads/' . $foto : '';
        $pdf->setColorRelleno(255, 255, 255);
        $pdf->rectRedondeado($x + 5, $y + 16, 22, 26, 2, 'F');
        if ($rutaFoto !== '' && is_file($rutaFoto)) {
            $pdf->imagen($rutaFoto, $x + 5.5, $y + 16.5, 21, 25);
        }

        $pdf->setColorTexto(255, 255, 255);
        $pdf->setFuente('Helvetica', 'B', 9);
        $pdf->setXY($x + 30, $y + 17);
        $pdf->celda(38, 4.5, mb_substr((string)$alumno['nombres'], 0, 22), 0, 2, 'L');
        $pdf->setX($x + 30);
        $pdf->celda(38, 4.5, mb_substr((string)$alumno['apellidos'], 0, 22), 0, 2, 'L');
        $pdf->setFuente('Helvetica', '', 7);
        $pdf->setColorHex('#C9A961', 'texto');
        $pdf->setX($x + 30);
        $pdf->celda(38, 4, 'CODIGO ' . (string)$alumno['codigo'], 0, 2, 'L');
        $pdf->setColorTexto(220, 220, 220);
        $pdf->setX($x + 30);
        $pdf->celda(38, 4, (string)($alumno['grupo'] ?? ''), 0, 2, 'L');
        $pdf->setX($x + 30);
        $ciclo = Academico::cicloActivo();
        $pdf->celda(38, 4, 'Ciclo ' . (string)($ciclo['nombre'] ?? date('Y')), 0, 0, 'L');

        // QR
        $png = Imagen::qrPng(url_absoluta('alumnos/' . (int)$alumno['id']), 4);
        if ($png !== null) {
            $tmp = BASE_PATH . '/storage/cache/qr-' . (int)$alumno['id'] . '.png';
            if (@file_put_contents($tmp, $png) !== false) {
                $pdf->imagen($tmp, $x + $w - 24, $y + 20, 19, 19);
                @unlink($tmp);
            }
        }
        $pdf->setColorTexto(0);
        $pdf->setColorRelleno(255, 255, 255);
    }

    /** Reporte tabular generico exportable a PDF. */
    public static function tabla(string $titulo, array $columnas, array $filas, string $orientacion = 'P', string $subtitulo = ''): DocumentoBase
    {
        $pdf = new DocumentoBase(Pdf::A4, $orientacion);
        $pdf->tituloDoc = mb_strtoupper($titulo);
        $pdf->subtitulo = $subtitulo;
        $pdf->setMeta($titulo, (string)Settings::get('colegio_nombre', 'EduPortal'));
        $pdf->agregarPagina();
        $ancho = $pdf->anchoUtil();
        $totalPeso = array_sum(array_map(static fn($c) => (float)$c['peso'], $columnas));
        $cols = [];
        foreach ($columnas as $c) {
            $cols[] = [$ancho * ((float)$c['peso'] / $totalPeso), (string)$c['titulo'], (string)($c['alinear'] ?? 'L')];
        }
        $pdf->tablaEncabezado($cols);
        $i = 0;
        foreach ($filas as $fila) {
            $celdas = [];
            foreach (array_values($fila) as $k => $valor) {
                $celdas[] = [$cols[$k][0] ?? 20, (string)$valor, $cols[$k][2] ?? 'L'];
            }
            $pdf->tablaFila($celdas, $i % 2 === 1);
            $i++;
        }
        if ($i === 0) {
            $pdf->ln(4);
            $pdf->setColorTexto(130, 130, 130);
            $pdf->celda($ancho, 8, 'No hay registros para los filtros seleccionados.', 0, 1, 'C');
        }
        return $pdf;
    }

    /** Convierte un monto a letras (español). */
    public static function montoEnLetras(float $monto): string
    {
        $entero = (int)floor($monto);
        $centavos = (int)round(($monto - $entero) * 100);
        $texto = self::numeroEnLetras($entero);
        return mb_strtoupper($texto . ' con ' . str_pad((string)$centavos, 2, '0', STR_PAD_LEFT) . '/100');
    }

    private static function numeroEnLetras(int $n): string
    {
        if ($n === 0) {
            return 'cero';
        }
        if ($n < 0) {
            return 'menos ' . self::numeroEnLetras(-$n);
        }
        $unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
                     'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciseis', 'diecisiete',
                     'dieciocho', 'diecinueve', 'veinte'];
        $decenas = [3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta', 6 => 'sesenta',
                    7 => 'setenta', 8 => 'ochenta', 9 => 'noventa'];
        $centenas = [1 => 'ciento', 2 => 'doscientos', 3 => 'trescientos', 4 => 'cuatrocientos',
                     5 => 'quinientos', 6 => 'seiscientos', 7 => 'setecientos', 8 => 'ochocientos', 9 => 'novecientos'];

        if ($n <= 20) {
            return $unidades[$n];
        }
        if ($n < 30) {
            return 'veinti' . $unidades[$n - 20];
        }
        if ($n < 100) {
            $d = intdiv($n, 10);
            $u = $n % 10;
            return $decenas[$d] . ($u ? ' y ' . $unidades[$u] : '');
        }
        if ($n === 100) {
            return 'cien';
        }
        if ($n < 1000) {
            $c = intdiv($n, 100);
            $r = $n % 100;
            return $centenas[$c] . ($r ? ' ' . self::numeroEnLetras($r) : '');
        }
        if ($n < 1000000) {
            $m = intdiv($n, 1000);
            $r = $n % 1000;
            $pre = $m === 1 ? 'mil' : self::numeroEnLetras($m) . ' mil';
            return $pre . ($r ? ' ' . self::numeroEnLetras($r) : '');
        }
        $mm = intdiv($n, 1000000);
        $r = $n % 1000000;
        $pre = $mm === 1 ? 'un millon' : self::numeroEnLetras($mm) . ' millones';
        return $pre . ($r ? ' ' . self::numeroEnLetras($r) : '');
    }
}
