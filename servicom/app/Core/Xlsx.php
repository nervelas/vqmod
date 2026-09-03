<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Escritor XLSX mínimo y sin dependencias (ZipArchive + XML).
 * Suficiente para exportar reportes y plantillas de importación.
 */
final class Xlsx
{
    private array $sheets = [];

    /**
     * @param array<int,array<int,scalar|null>> $rows  primera fila = encabezados
     */
    public function addSheet(string $name, array $rows, array $widths = []): self
    {
        $this->sheets[] = [
            'name'   => mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $name) ?: 'Hoja', 0, 31),
            'rows'   => $rows,
            'widths' => $widths,
        ];
        return $this;
    }

    public function save(string $file): bool
    {
        if (!class_exists('ZipArchive')) {
            return false;
        }
        if (!$this->sheets) {
            $this->addSheet('Hoja1', [['(sin datos)']]);
        }
        @unlink($file);
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $strings = [];
        $sheetXml = [];
        foreach ($this->sheets as $i => $s) {
            $sheetXml[$i] = $this->sheetXml($s, $strings);
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>CotizaPro B2B</Application></Properties>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>CotizaPro B2B</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . date('c') . '</dcterms:created></cp:coreProperties>');
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/sharedStrings.xml', $this->sharedStrings($strings));
        foreach ($sheetXml as $i => $xml) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $xml);
        }
        return $zip->close();
    }

    public function download(string $filename): never
    {
        $tmp = STORAGE_PATH . '/tmp/' . bin2hex(random_bytes(8)) . '.xlsx';
        if (!is_dir(dirname($tmp))) {
            @mkdir(dirname($tmp), 0700, true);
        }
        if (!$this->save($tmp)) {
            ErrorHandler::render(500);
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9\.\-_]/', '', $filename) . '"');
        header('Content-Length: ' . filesize($tmp));
        header('X-Content-Type-Options: nosniff');
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    private function sheetXml(array $s, array &$strings): string
    {
        $cols = '';
        if ($s['widths']) {
            $cols = '<cols>';
            foreach ($s['widths'] as $i => $wd) {
                $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float) $wd . '" customWidth="1"/>';
            }
            $cols .= '</cols>';
        }
        $out = '';
        foreach ($s['rows'] as $r => $row) {
            $cells = '';
            $c = 0;
            foreach ($row as $v) {
                $ref = $this->colName($c) . ($r + 1);
                $style = $r === 0 ? ' s="1"' : '';
                if ($v === null || $v === '') {
                    $c++;
                    continue;
                }
                if (is_int($v) || is_float($v) || (is_string($v) && $v !== '' && preg_match('/^-?\d+(\.\d+)?$/', $v) && strlen($v) < 15)) {
                    $cells .= '<c r="' . $ref . '"' . $style . '><v>' . (0 + $v) . '</v></c>';
                } else {
                    $str = (string) $v;
                    if (!isset($strings[$str])) {
                        $strings[$str] = count($strings);
                    }
                    $cells .= '<c r="' . $ref . '" t="s"' . $style . '><v>' . $strings[$str] . '</v></c>';
                }
                $c++;
            }
            $out .= '<row r="' . ($r + 1) . '">' . $cells . '</row>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $cols . '<sheetData>' . $out . '</sheetData></worksheet>';
    }

    private function colName(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = (int) (($i - $m) / 26);
        }
        return $s;
    }

    private function sharedStrings(array $strings): string
    {
        $xml = '';
        foreach (array_keys($strings) as $s) {
            $xml .= '<si><t xml:space="preserve">' . htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></si>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">'
            . $xml . '</sst>';
    }

    private function workbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $i => $s) {
            $sheets .= '<sheet name="' . htmlspecialchars($s['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheets . '</sheets></workbook>';
    }

    private function workbookRels(): string
    {
        $rels = '';
        $n = count($this->sheets);
        foreach ($this->sheets as $i => $s) {
            $rels .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $rels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $rels .= '<Relationship Id="rId' . ($n + 2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';
    }

    private function contentTypes(): string
    {
        $over = '';
        foreach ($this->sheets as $i => $s) {
            $over .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $over
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1C1F22"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            . '</styleSheet>';
    }
}
