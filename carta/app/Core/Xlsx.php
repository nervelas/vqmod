<?php
namespace MenuGold\Core;

use ZipArchive;

/**
 * Lectura y escritura de archivos .xlsx sin dependencias externas:
 * un .xlsx es un ZIP de XML, y para importar/exportar un menú
 * basta con la hoja de cálculo y la tabla de cadenas compartidas.
 */
final class Xlsx
{
    /* ------------------------------------------------------------------
       Escritura
       ------------------------------------------------------------------ */

    /**
     * @param array $sheets ['Nombre hoja' => [ [fila], [fila], ... ], ...]
     * @return string contenido binario del .xlsx
     */
    public static function write(array $sheets)
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('La extensión ZIP de PHP no está disponible en este servidor.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'mgx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el archivo Excel.');
        }

        $names = array_keys($sheets);
        $count = count($names);

        $zip->addFromString('[Content_Types].xml', self::contentTypes($count));
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
          . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
          . '</Relationships>');

        $sheetXml = '';
        $relXml = '';
        foreach ($names as $i => $name) {
            $n = $i + 1;
            $sheetXml .= '<sheet name="' . self::attr(mb_substr($name, 0, 31)) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            $relXml .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
        }
        $relXml .= '<Relationship Id="rId' . ($count + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
          . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
          . '<sheets>' . $sheetXml . '</sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relXml . '</Relationships>');

        $zip->addFromString('xl/styles.xml', self::styles());

        $i = 0;
        foreach ($sheets as $rows) {
            $i++;
            $zip->addFromString('xl/worksheets/sheet' . $i . '.xml', self::sheetXml($rows));
        }

        $zip->close();
        $data = (string)file_get_contents($tmp);
        @unlink($tmp);
        return $data;
    }

    public static function response(array $sheets, $filename = 'datos.xlsx')
    {
        return Response::make(self::write($sheets), 200, array(
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '', $filename) . '"',
            'Cache-Control'       => 'private, max-age=0, must-revalidate',
        ));
    }

    private static function sheetXml(array $rows)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $r => $row) {
            $rowNum = $r + 1;
            $xml .= '<row r="' . $rowNum . '">';
            $c = 0;
            foreach ((array)$row as $value) {
                $ref = self::colName($c) . $rowNum;
                $style = ($rowNum === 1) ? ' s="1"' : '';
                if (is_int($value) || is_float($value)) {
                    $xml .= '<c r="' . $ref . '"' . $style . '><v>' . $value . '</v></c>';
                } elseif ($value === null || $value === '') {
                    $xml .= '<c r="' . $ref . '"' . $style . '/>';
                } else {
                    $xml .= '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">'
                          . self::text((string)$value) . '</t></is></c>';
                }
                $c++;
            }
            $xml .= '</row>';
        }
        return $xml . '</sheetData></worksheet>';
    }

    private static function contentTypes($sheetCount)
    {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
           . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
           . '<Default Extension="xml" ContentType="application/xml"/>'
           . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
           . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $x .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return $x . '</Types>';
    }

    private static function styles()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
             . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
             . '<font><b/><sz val="11"/><color rgb="FF3A2E14"/><name val="Calibri"/></font></fonts>'
             . '<fills count="3"><fill><patternFill patternType="none"/></fill>'
             . '<fill><patternFill patternType="gray125"/></fill>'
             . '<fill><patternFill patternType="solid"><fgColor rgb="FFF2E4C6"/><bgColor indexed="64"/></patternFill></fill></fills>'
             . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
             . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
             . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
             . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
             . '</styleSheet>';
    }

    public static function colName($index)
    {
        $name = '';
        $index = (int)$index;
        while (true) {
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26) - 1;
            if ($index < 0) { break; }
        }
        return $name;
    }

    private static function text($s)
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s);
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function attr($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /* ------------------------------------------------------------------
       Lectura
       ------------------------------------------------------------------ */

    /**
     * Lee la primera hoja de un .xlsx y devuelve una matriz de filas.
     * También acepta CSV, que es lo que muchos dueños tienen a mano.
     *
     * @return array<int,array<int,string>>
     */
    public static function read($path, $maxRows = 2000)
    {
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el archivo.');
        }
        $head = (string)file_get_contents($path, false, null, 0, 4);
        if ($head !== "PK\x03\x04") {
            return self::readCsv($path, $maxRows);
        }
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('La extensión ZIP de PHP no está disponible; sube el archivo en formato CSV.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('El archivo no se pudo abrir. ¿Es un Excel válido?');
        }

        $shared = array();
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false && $sharedXml !== '') {
            $sx = @simplexml_load_string($sharedXml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
            if ($sx) {
                foreach ($sx->si as $si) {
                    $text = '';
                    if (isset($si->t)) { $text = (string)$si->t; }
                    elseif (isset($si->r)) { foreach ($si->r as $r) { $text .= (string)$r->t; } }
                    $shared[] = $text;
                }
            }
        }

        // Primera hoja según el libro; si falla, sheet1.xml.
        $sheetPath = 'xl/worksheets/sheet1.xml';
        $sheetXml = $zip->getFromName($sheetPath);
        if ($sheetXml === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (strpos($name, 'xl/worksheets/sheet') === 0) { $sheetXml = $zip->getFromIndex($i); break; }
            }
        }
        $zip->close();
        if ($sheetXml === false || $sheetXml === '') {
            throw new \RuntimeException('El Excel no contiene ninguna hoja legible.');
        }

        $sx = @simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
        if (!$sx || !isset($sx->sheetData)) {
            throw new \RuntimeException('No se pudo leer el contenido de la hoja.');
        }

        $rows = array();
        $count = 0;
        foreach ($sx->sheetData->row as $row) {
            if ($count++ >= $maxRows) { break; }
            $line = array();
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];
                $col = self::colIndex(preg_replace('/\d+/', '', $ref));
                $type = (string)$c['t'];
                if ($type === 's') {
                    $idx = (int)$c->v;
                    $value = isset($shared[$idx]) ? $shared[$idx] : '';
                } elseif ($type === 'inlineStr') {
                    $value = isset($c->is->t) ? (string)$c->is->t : '';
                } else {
                    $value = isset($c->v) ? (string)$c->v : '';
                }
                $line[$col] = $value;
            }
            if ($line) {
                $max = max(array_keys($line));
                $normalized = array();
                for ($i = 0; $i <= $max; $i++) {
                    $normalized[] = isset($line[$i]) ? trim($line[$i]) : '';
                }
                $rows[] = $normalized;
            } else {
                $rows[] = array();
            }
        }
        return $rows;
    }

    private static function readCsv($path, $maxRows)
    {
        $rows = array();
        $fh = fopen($path, 'r');
        if (!$fh) { throw new \RuntimeException('No se pudo abrir el archivo.'); }
        $first = true;
        while (($data = fgetcsv($fh, 0, ',', '"')) !== false && count($rows) < $maxRows) {
            if ($first) {
                $first = false;
                // Quita la marca BOM que agrega Excel al guardar en CSV.
                if (isset($data[0])) { $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]); }
            }
            $rows[] = array_map(function ($v) { return trim((string)$v); }, $data);
        }
        fclose($fh);
        return $rows;
    }

    public static function colIndex($letters)
    {
        $letters = strtoupper((string)$letters);
        $n = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }
        return max(0, $n - 1);
    }
}
