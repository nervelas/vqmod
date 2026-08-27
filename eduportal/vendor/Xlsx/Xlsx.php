<?php
declare(strict_types=1);

namespace Vendor\Xlsx;

/**
 * Escritor XLSX local (SpreadsheetML minimo) con encabezados, anchos,
 * formatos de numero/moneda/fecha y filas alternas.
 */
final class Xlsx
{
    /** @var array<int,array{nombre:string,encabezados:array,filas:array,anchos:array}> */
    private array $hojas = [];

    public function agregarHoja(string $nombre, array $encabezados, array $filas, array $anchos = []): void
    {
        $this->hojas[] = [
            'nombre'      => $this->nombreValido($nombre),
            'encabezados' => array_values($encabezados),
            'filas'       => $filas,
            'anchos'      => $anchos,
        ];
    }

    private function nombreValido(string $n): string
    {
        $n = preg_replace('/[\\\\\/\?\*\[\]:]/', '', $n) ?? 'Hoja';
        $n = trim($n) === '' ? 'Hoja' : $n;
        return mb_substr($n, 0, 31);
    }

    public function salida(): string
    {
        if ($this->hojas === []) {
            $this->agregarHoja('Hoja1', [], []);
        }
        $zip = new Zip();
        $zip->agregar('[Content_Types].xml', $this->contentTypes());
        $zip->agregar('_rels/.rels', $this->rels());
        $zip->agregar('xl/workbook.xml', $this->workbook());
        $zip->agregar('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->agregar('xl/styles.xml', $this->styles());
        foreach ($this->hojas as $i => $hoja) {
            $zip->agregar('xl/worksheets/sheet' . ($i + 1) . '.xml', $this->hoja($hoja));
        }
        return $zip->salida();
    }

    public function descargar(string $nombre): string
    {
        $datos = $this->salida();
        if (!headers_sent()) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre) . '"');
            header('Content-Length: ' . strlen($datos));
        }
        return $datos;
    }

    private function contentTypes(): string
    {
        $s = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
           . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
           . '<Default Extension="xml" ContentType="application/xml"/>'
           . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
           . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        foreach ($this->hojas as $i => $h) {
            $s .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return $s . '</Types>';
    }

    private function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
             . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
             . '</Relationships>';
    }

    private function workbook(): string
    {
        $s = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
           . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        foreach ($this->hojas as $i => $h) {
            $s .= '<sheet name="' . $this->esc($h['nombre']) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        return $s . '</sheets></workbook>';
    }

    private function workbookRels(): string
    {
        $s = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $n = count($this->hojas);
        foreach ($this->hojas as $i => $h) {
            $s .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $s .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return $s . '</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
             . '<numFmts count="2">'
             . '<numFmt numFmtId="164" formatCode="&quot;Q&quot;#,##0.00"/>'
             . '<numFmt numFmtId="165" formatCode="dd/mm/yyyy"/>'
             . '</numFmts>'
             . '<fonts count="2">'
             . '<font><sz val="11"/><color rgb="FF1B2430"/><name val="Calibri"/></font>'
             . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
             . '</fonts>'
             . '<fills count="3">'
             . '<fill><patternFill patternType="none"/></fill>'
             . '<fill><patternFill patternType="gray125"/></fill>'
             . '<fill><patternFill patternType="solid"><fgColor rgb="FF0B1F3A"/><bgColor indexed="64"/></patternFill></fill>'
             . '</fills>'
             . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
             . '<border><left/><right/><top/><bottom style="thin"><color rgb="FFDDDDDD"/></bottom><diagonal/></border></borders>'
             . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
             . '<cellXfs count="5">'
             . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
             . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center"/></xf>'
             . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1"/>'
             . '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1"/>'
             . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>'
             . '</cellXfs>'
             . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
             . '</styleSheet>';
    }

    private function hoja(array $hoja): string
    {
        $s = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if ($hoja['anchos'] !== []) {
            $s .= '<cols>';
            foreach (array_values($hoja['anchos']) as $i => $w) {
                $s .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float)$w . '" customWidth="1"/>';
            }
            $s .= '</cols>';
        }
        $s .= '<sheetData>';
        $fila = 1;
        if ($hoja['encabezados'] !== []) {
            $s .= '<row r="1" ht="22" customHeight="1">';
            foreach ($hoja['encabezados'] as $c => $v) {
                $s .= $this->celda($this->columna($c) . '1', $v, 1);
            }
            $s .= '</row>';
            $fila = 2;
        }
        foreach ($hoja['filas'] as $r) {
            $s .= '<row r="' . $fila . '">';
            $c = 0;
            foreach ($r as $v) {
                $s .= $this->celda($this->columna($c) . $fila, $v, null);
                $c++;
            }
            $s .= '</row>';
            $fila++;
        }
        return $s . '</sheetData></worksheet>';
    }

    private function celda(string $ref, mixed $valor, ?int $estilo): string
    {
        $st = $estilo;
        if (is_array($valor)) {
            $tipo = $valor['tipo'] ?? 'texto';
            $v = $valor['valor'] ?? '';
            if ($tipo === 'moneda') {
                return '<c r="' . $ref . '" s="2"><v>' . (float)$v . '</v></c>';
            }
            if ($tipo === 'fecha' && $v !== '' && $v !== null) {
                return '<c r="' . $ref . '" s="3"><v>' . $this->serieFecha((string)$v) . '</v></c>';
            }
            $valor = $v;
        }
        if ($st === null) {
            $st = 4;
        }
        if (is_int($valor) || is_float($valor)) {
            return '<c r="' . $ref . '" s="' . $st . '"><v>' . $valor . '</v></c>';
        }
        $texto = (string)($valor ?? '');
        if ($texto === '') {
            return '<c r="' . $ref . '" s="' . $st . '"/>';
        }
        return '<c r="' . $ref . '" s="' . $st . '" t="inlineStr"><is><t xml:space="preserve">' . $this->esc($texto) . '</t></is></c>';
    }

    private function serieFecha(string $fecha): float
    {
        $ts = strtotime($fecha);
        if ($ts === false) {
            return 0;
        }
        return floor($ts / 86400) + 25569;
    }

    private function columna(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = intdiv($i - $m - 1, 26);
        }
        return $s;
    }

    private function esc(string $s): string
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? $s;
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
