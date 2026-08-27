<?php
declare(strict_types=1);

namespace Vendor\Xlsx;

/**
 * Generador de libros XLSX (Office Open XML) en PHP puro.
 * Usa la extensión zip de PHP; sin dependencias externas.
 *
 * ResidencialPro — librería local.
 */
final class Xlsx
{
    /** @var array<int,array{nombre:string,filas:array,anchos:array,congelar:int}> */
    private array $hojas = [];
    private string $titulo = 'ResidencialPro';

    public const TXT   = 0;
    public const NUM   = 1;
    public const MONEY = 2;
    public const FECHA = 3;
    public const PCT   = 4;

    public function titulo(string $t): void
    {
        $this->titulo = $t;
    }

    /**
     * Agrega una hoja.
     * $filas: cada fila es un arreglo de celdas. Una celda es un escalar o
     *         ['v' => valor, 't' => Xlsx::MONEY, 'estilo' => 'cabecera'|'total'|'']
     */
    public function hoja(string $nombre, array $filas, array $anchos = [], int $congelarFilas = 1): void
    {
        $this->hojas[] = [
            'nombre'   => self::nombreHoja($nombre),
            'filas'    => $filas,
            'anchos'   => $anchos,
            'congelar' => $congelarFilas,
        ];
    }

    /** Devuelve el binario del archivo .xlsx */
    public function salida(): string
    {
        if ($this->hojas === []) {
            $this->hoja('Hoja 1', [['Sin datos']]);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'rpx');
        if ($tmp === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal del Excel.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo generar el archivo Excel.');
        }
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->relsRaiz());
        $zip->addFromString('docProps/core.xml', $this->core());
        $zip->addFromString('docProps/app.xml', $this->app());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relsWorkbook());
        $zip->addFromString('xl/styles.xml', $this->styles());
        foreach ($this->hojas as $i => $h) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $this->sheet($h));
        }
        $zip->close();
        $bin = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $bin;
    }

    // ------------------------------------------------------------- Interno

    private static function nombreHoja(string $n): string
    {
        $n = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $n) ?? 'Hoja';
        $n = trim(mb_substr($n, 0, 31));
        return $n === '' ? 'Hoja' : $n;
    }

    private static function columna(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $r = ($i - 1) % 26;
            $s = chr(65 + $r) . $s;
            $i = intdiv($i - 1, 26);
        }
        return $s;
    }

    private static function esc(string $s): string
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '';
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function sheet(array $h): string
    {
        $cols = '';
        if ($h['anchos'] !== []) {
            $cols .= '<cols>';
            foreach ($h['anchos'] as $i => $an) {
                $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float) $an . '" customWidth="1"/>';
            }
            $cols .= '</cols>';
        }
        $filasXml = '';
        foreach ($h['filas'] as $nf => $fila) {
            $celdas = '';
            foreach (array_values($fila) as $nc => $celda) {
                $valor  = is_array($celda) ? ($celda['v'] ?? '') : $celda;
                $tipo   = is_array($celda) ? (int) ($celda['t'] ?? self::TXT) : self::TXT;
                $estilo = is_array($celda) ? (string) ($celda['estilo'] ?? '') : '';
                $ref    = self::columna($nc) . ($nf + 1);
                $s      = $this->indiceEstilo($tipo, $estilo);
                if ($valor === null || $valor === '') {
                    $celdas .= '<c r="' . $ref . '" s="' . $s . '"/>';
                    continue;
                }
                if (in_array($tipo, [self::NUM, self::MONEY, self::PCT], true) && is_numeric($valor)) {
                    $celdas .= '<c r="' . $ref . '" s="' . $s . '"><v>' . (0 + $valor) . '</v></c>';
                } else {
                    $celdas .= '<c r="' . $ref . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">'
                             . self::esc((string) $valor) . '</t></is></c>';
                }
            }
            $filasXml .= '<row r="' . ($nf + 1) . '">' . $celdas . '</row>';
        }
        $panes = '';
        if ($h['congelar'] > 0) {
            $panes = '<sheetViews><sheetView workbookViewId="0" showGridLines="0">'
                   . '<pane ySplit="' . $h['congelar'] . '" topLeftCell="A' . ($h['congelar'] + 1) . '" activePane="bottomLeft" state="frozen"/>'
                   . '</sheetView></sheetViews>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
             . $panes . $cols . '<sheetData>' . $filasXml . '</sheetData></worksheet>';
    }

    private function indiceEstilo(int $tipo, string $estilo): int
    {
        if ($estilo === 'cabecera') { return 1; }
        if ($estilo === 'total')    { return match ($tipo) { self::MONEY => 6, default => 5 }; }
        return match ($tipo) {
            self::MONEY => 2,
            self::FECHA => 3,
            self::PCT   => 4,
            default     => 0,
        };
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2">'
            . '<numFmt numFmtId="164" formatCode="&quot;Q&quot;#,##0.00"/>'
            . '<numFmt numFmtId="165" formatCode="dd/mm/yyyy"/>'
            . '</numFmts>'
            . '<fonts count="3">'
            . '<font><sz val="11"/><name val="Calibri"/><color rgb="FF22271F"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/><color rgb="FF0F2E24"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0F2E24"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF1EADA"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2"><border/><border><top style="thin"><color rgb="FFC9A961"/></top></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="7">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="10" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            . '<xf numFmtId="164" fontId="2" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function workbook(): string
    {
        $hojas = '';
        foreach ($this->hojas as $i => $h) {
            $hojas .= '<sheet name="' . self::esc($h['nombre']) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
             . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
             . '<sheets>' . $hojas . '</sheets></workbook>';
    }

    private function relsWorkbook(): string
    {
        $rels = '';
        foreach ($this->hojas as $i => $h) {
            $rels .= '<Relationship Id="rId' . ($i + 1) . '" '
                   . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
                   . 'Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $n = count($this->hojas) + 1;
        $rels .= '<Relationship Id="rId' . $n . '" '
               . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';
    }

    private function relsRaiz(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
             . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
             . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
             . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
             . '</Relationships>';
    }

    private function contentTypes(): string
    {
        $ov = '';
        foreach ($this->hojas as $i => $h) {
            $ov .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" '
                 . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
             . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
             . '<Default Extension="xml" ContentType="application/xml"/>'
             . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
             . $ov
             . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
             . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
             . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
             . '</Types>';
    }

    private function core(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
             . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
             . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
             . '<dc:title>' . self::esc($this->titulo) . '</dc:title>'
             . '<dc:creator>ResidencialPro</dc:creator>'
             . '<cp:lastModifiedBy>ResidencialPro</cp:lastModifiedBy>'
             . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
             . '</cp:coreProperties>';
    }

    private function app(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
             . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
             . '<Application>ResidencialPro</Application><Company>ResidencialPro</Company></Properties>';
    }
}
