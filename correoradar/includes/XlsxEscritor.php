<?php
/**
 * CorreoRadar - Generador de archivos Excel .xlsx reales.
 *
 * Escribe un libro OOXML válido usando únicamente ZipArchive (incluida en PHP),
 * sin Composer ni PhpSpreadsheet. El archivo resultante se abre sin avisos en
 * Excel, LibreOffice, Numbers y Google Sheets.
 *
 * Admite varias hojas (por ejemplo "Correos" y "WhatsApp"), encabezado con
 * estilo, ancho de columnas, panel inmovilizado y filtro automático.
 */
declare(strict_types=1);

final class XlsxEscritor
{
    /** @var array<int,array{titulo:string,encabezados:string[],filas:array,anchos:array}> */
    private array $hojas = [];

    public function __construct(private string $tituloLibro = 'CorreoRadar') {}

    /**
     * Añade una hoja al libro.
     *
     * @param string[] $encabezados
     * @param array<int,array<int,string|int|float|null>> $filas
     * @param int[]    $anchos Ancho de cada columna en caracteres.
     */
    public function agregarHoja(string $titulo, array $encabezados, array $filas, array $anchos = []): self
    {
        $this->hojas[] = [
            'titulo'      => $this->limpiarTitulo($titulo, count($this->hojas) + 1),
            'encabezados' => array_values(array_map('strval', $encabezados)),
            'filas'       => array_values($filas),
            'anchos'      => $anchos,
        ];
        return $this;
    }

    /**
     * Genera el archivo .xlsx y devuelve su contenido binario.
     *
     * @throws RuntimeException si la extensión zip no está disponible.
     */
    public function generar(): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('La extensión zip de PHP no está disponible en este servidor.');
        }
        if (!$this->hojas) {
            $this->agregarHoja('Hoja 1', [], []);
        }

        $temporal = tempnam(sys_get_temp_dir(), 'crxlsx');
        if ($temporal === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal del Excel.');
        }

        $zip = new ZipArchive();
        if ($zip->open($temporal, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo Excel.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->relsPrincipales());
        $zip->addFromString('docProps/core.xml', $this->propsCore());
        $zip->addFromString('docProps/app.xml', $this->propsApp());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relsWorkbook());
        $zip->addFromString('xl/styles.xml', $this->estilos());

        foreach ($this->hojas as $i => $hoja) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $this->hojaXml($hoja, $i === 0));
        }
        $zip->close();

        $contenido = (string) file_get_contents($temporal);
        @unlink($temporal);
        return $contenido;
    }

    // ------------------------------------------------------------------ partes

    private function contentTypes(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        foreach ($this->hojas as $i => $hoja) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" '
                . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return $xml
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private function relsPrincipales(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function propsCore(): string
    {
        $fecha = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . $this->x($this->tituloLibro) . '</dc:title>'
            . '<dc:creator>CorreoRadar</dc:creator>'
            . '<cp:lastModifiedBy>CorreoRadar</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $fecha . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $fecha . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private function propsApp(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>CorreoRadar</Application><Company></Company>'
            . '</Properties>';
    }

    private function workbook(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        foreach ($this->hojas as $i => $hoja) {
            $xml .= '<sheet name="' . $this->x($hoja['titulo']) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        return $xml . '</sheets></workbook>';
    }

    private function relsWorkbook(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->hojas as $i => $hoja) {
            $xml .= '<Relationship Id="rId' . ($i + 1) . '" '
                . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
                . 'Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $xml .= '<Relationship Id="rId' . (count($this->hojas) + 1) . '" '
            . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return $xml . '</Relationships>';
    }

    /** Dos estilos: 0 = normal, 1 = encabezado (negrita sobre fondo oro). */
    private function estilos(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF1A1206"/><name val="Calibri"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFD8B36A"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left/><right/><top/><bottom style="thin"><color rgb="FFB08C45"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1">'
            . '<alignment vertical="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function hojaXml(array $hoja, bool $seleccionada): string
    {
        $columnas   = max(1, count($hoja['encabezados']));
        $ultima     = self::letraColumna($columnas);
        $totalFilas = count($hoja['filas']) + (count($hoja['encabezados']) ? 1 : 0);
        $totalFilas = max(1, $totalFilas);

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $ultima . $totalFilas . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"' . ($seleccionada ? ' tabSelected="1"' : '') . '>'
            . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>';

        if ($hoja['anchos']) {
            $xml .= '<cols>';
            foreach ($hoja['anchos'] as $i => $ancho) {
                $n = (int) $i + 1;
                $xml .= '<col min="' . $n . '" max="' . $n . '" width="' . (float) $ancho . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';

        if ($hoja['encabezados']) {
            $xml .= '<row r="1" ht="22" customHeight="1" s="1">';
            foreach ($hoja['encabezados'] as $i => $texto) {
                $xml .= $this->celda(self::letraColumna($i + 1) . '1', (string) $texto, 1);
            }
            $xml .= '</row>';
        }

        $n = count($hoja['encabezados']) ? 1 : 0;
        foreach ($hoja['filas'] as $fila) {
            $n++;
            $xml .= '<row r="' . $n . '">';
            foreach (array_values($fila) as $i => $valor) {
                $xml .= $this->celda(self::letraColumna($i + 1) . $n, $valor, 0);
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData>';
        if ($hoja['encabezados'] && $hoja['filas']) {
            $xml .= '<autoFilter ref="A1:' . $ultima . $totalFilas . '"/>';
        }
        return $xml . '</worksheet>';
    }

    /** Genera una celda: numérica si el valor lo es, y de texto en otro caso. */
    private function celda(string $ref, $valor, int $estilo): string
    {
        $s = $estilo > 0 ? ' s="' . $estilo . '"' : '';

        if ($valor === null || $valor === '') {
            return '<c r="' . $ref . '"' . $s . '/>';
        }
        if (is_int($valor) || is_float($valor)) {
            return '<c r="' . $ref . '"' . $s . '><v>' . $valor . '</v></c>';
        }
        // Texto en línea: evita tener que generar sharedStrings.xml
        return '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">'
            . $this->x((string) $valor) . '</t></is></c>';
    }

    /** Número de columna a letra: 1 = A, 27 = AA. */
    public static function letraColumna(int $numero): string
    {
        $letra = '';
        while ($numero > 0) {
            $resto  = ($numero - 1) % 26;
            $letra  = chr(65 + $resto) . $letra;
            $numero = (int) (($numero - $resto - 1) / 26);
        }
        return $letra !== '' ? $letra : 'A';
    }

    /** Excel limita el nombre de hoja a 31 caracteres y prohíbe : \ / ? * [ ] */
    private function limpiarTitulo(string $titulo, int $indice): string
    {
        $titulo = preg_replace('~[:\\\\/?*\[\]]~', '', $titulo) ?: ('Hoja ' . $indice);
        return mb_substr(trim($titulo), 0, 31) ?: ('Hoja ' . $indice);
    }

    /** Escapa texto para XML y quita caracteres de control no permitidos. */
    private function x(string $texto): string
    {
        $texto = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F]~', '', $texto) ?? $texto;
        return htmlspecialchars($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
