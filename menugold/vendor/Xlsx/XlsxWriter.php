<?php
declare(strict_types=1);

namespace MenuGold\Vendor\Xlsx;

/**
 * Generador de archivos XLSX (Excel 2007+) en PHP puro.
 * Soporta varias hojas, encabezados con estilo, anchos de columna,
 * numeros, fechas, moneda, porcentajes y congelado de la primera fila.
 */
class XlsxWriter
{
    /** @var array<int,array{nombre:string,filas:array,anchos:array,formatos:array}> */
    private array $hojas = [];
    private array $cadenas = [];
    private array $cadenasIdx = [];
    private string $autor = 'MenuGold';

    public function setAutor(string $a): void { $this->autor = $a; }

    /**
     * Agrega una hoja.
     * @param string $nombre    Nombre visible de la pestana
     * @param array  $filas     Array de filas; cada fila array de celdas
     * @param array  $anchos    Anchos de columna (caracteres)
     * @param array  $formatos  Formato por columna: 'texto'|'numero'|'moneda'|'entero'|'fecha'|'porcentaje'
     */
    public function hoja(string $nombre, array $filas, array $anchos = [], array $formatos = []): void
    {
        $this->hojas[] = [
            'nombre'   => $this->nombreValido($nombre),
            'filas'    => $filas,
            'anchos'   => $anchos,
            'formatos' => $formatos,
        ];
    }

    public function output(): string
    {
        if (!$this->hojas) $this->hoja('Hoja1', [['Sin datos']]);
        $archivos = [];

        $archivos['[Content_Types].xml'] = $this->contentTypes();
        $archivos['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '</Relationships>';
        $archivos['docProps/core.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>' . $this->esc($this->autor) . '</dc:creator>'
            . '<cp:lastModifiedBy>' . $this->esc($this->autor) . '</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
            . '</cp:coreProperties>';

        // Hojas (rellenan la tabla de cadenas compartidas)
        $hojasXml = [];
        foreach ($this->hojas as $i => $h) {
            $hojasXml[$i] = $this->hojaXml($h);
        }

        $archivos['xl/workbook.xml'] = $this->workbook();
        $archivos['xl/_rels/workbook.xml.rels'] = $this->workbookRels();
        $archivos['xl/styles.xml'] = $this->styles();
        $archivos['xl/sharedStrings.xml'] = $this->sharedStrings();
        foreach ($hojasXml as $i => $xml) {
            $archivos['xl/worksheets/sheet' . ($i + 1) . '.xml'] = $xml;
        }
        return Zip::create($archivos);
    }

    // ------------------------------------------------------------------
    private function hojaXml(array $h): string
    {
        $filas = $h['filas'];
        $formatos = $h['formatos'];
        $maxCol = 0;
        foreach ($filas as $f) $maxCol = max($maxCol, count($f));

        $cols = '';
        if ($h['anchos']) {
            $cols = '<cols>';
            foreach ($h['anchos'] as $i => $w) {
                $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float)$w . '" customWidth="1"/>';
            }
            $cols .= '</cols>';
        }

        $xml = '';
        foreach ($filas as $r => $fila) {
            $nFila = $r + 1;
            $celdas = '';
            $c = 0;
            foreach ($fila as $valor) {
                $ref = $this->col($c) . $nFila;
                $fmt = $r === 0 ? 'encabezado' : ($formatos[$c] ?? 'auto');
                $celdas .= $this->celda($ref, $valor, $fmt);
                $c++;
            }
            $xml .= '<row r="' . $nFila . '"' . ($r === 0 ? ' ht="22" customHeight="1"' : '') . '>' . $celdas . '</row>';
        }

        $dim = 'A1:' . $this->col(max(0, $maxCol - 1)) . max(1, count($filas));
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="' . $dim . '"/>'
            . '<sheetViews><sheetView workbookViewId="0">'
            . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . $cols
            . '<sheetData>' . $xml . '</sheetData>'
            . '<autoFilter ref="' . $dim . '"/>'
            . '</worksheet>';
    }

    private function celda(string $ref, $valor, string $formato): string
    {
        $estilo = $this->estiloId($formato, $valor);
        $s = $estilo > 0 ? ' s="' . $estilo . '"' : '';

        if ($valor === null || $valor === '') {
            return '<c r="' . $ref . '"' . $s . '/>';
        }
        if ($formato !== 'texto' && is_numeric($valor) && !is_bool($valor)
            && !preg_match('/^0\d/', (string)$valor)) {
            return '<c r="' . $ref . '"' . $s . '><v>' . (0 + $valor) . '</v></c>';
        }
        $idx = $this->cadena((string)$valor);
        return '<c r="' . $ref . '" t="s"' . $s . '><v>' . $idx . '</v></c>';
    }

    /** Indices de estilo definidos en styles(). */
    private function estiloId(string $formato, $valor): int
    {
        switch ($formato) {
            case 'encabezado': return 1;
            case 'moneda':     return 2;
            case 'entero':     return 3;
            case 'fecha':      return 4;
            case 'porcentaje': return 5;
            case 'numero':     return 6;
        }
        return 0;
    }

    private function cadena(string $s): int
    {
        if (isset($this->cadenasIdx[$s])) return $this->cadenasIdx[$s];
        $i = count($this->cadenas);
        $this->cadenas[] = $s;
        $this->cadenasIdx[$s] = $i;
        return $i;
    }

    private function sharedStrings(): string
    {
        $x = '';
        foreach ($this->cadenas as $s) {
            $x .= '<si><t xml:space="preserve">' . $this->esc($s) . '</t></si>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'
            . count($this->cadenas) . '" uniqueCount="' . count($this->cadenas) . '">' . $x . '</sst>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="3">'
            . '<numFmt numFmtId="164" formatCode="&quot;Q&quot;#,##0.00"/>'
            . '<numFmt numFmtId="165" formatCode="dd/mm/yyyy hh:mm"/>'
            . '<numFmt numFmtId="166" formatCode="#,##0.00"/>'
            . '</numFmts>'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF141414"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFD4AF37"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2"><border/>'
            . '<border><left/><right/><top/><bottom style="thin"><color rgb="FFBFA53A"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="7">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="10" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="166" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function workbook(): string
    {
        $h = '';
        foreach ($this->hojas as $i => $s) {
            $h .= '<sheet name="' . $this->esc($s['nombre']) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $h . '</sheets></workbook>';
    }

    private function workbookRels(): string
    {
        $r = '';
        $n = count($this->hojas);
        foreach ($this->hojas as $i => $s) {
            $r .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $r .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $r .= '<Relationship Id="rId' . ($n + 2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $r . '</Relationships>';
    }

    private function contentTypes(): string
    {
        $o = '';
        foreach ($this->hojas as $i => $s) {
            $o .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $o
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '</Types>';
    }

    public static function col(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = (int)(($i - $m) / 26);
        }
        return $s;
    }

    private function esc(string $s): string
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? $s;
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function nombreValido(string $n): string
    {
        $n = str_replace(['\\', '/', '*', '[', ']', ':', '?'], '-', $n);
        return mb_substr($n, 0, 31) ?: 'Hoja';
    }
}
