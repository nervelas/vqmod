<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Lectura de CSV (con detección de separador y BOM) y de XLSX básico.
 */
final class Sheet
{
    /**
     * Lee las primeras $limit filas de un archivo CSV o XLSX.
     * @return array<int,array<int,string>>
     */
    public static function read(string $file, int $limit = 0): array
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return $ext === 'xlsx' ? self::readXlsx($file, $limit) : self::readCsv($file, $limit);
    }

    public static function readCsv(string $file, int $limit = 0): array
    {
        $fh = @fopen($file, 'rb');
        if (!$fh) {
            return [];
        }
        $first = (string) fgets($fh, 8192);
        // Quita el BOM UTF-8 si existe.
        if (str_starts_with($first, "\xEF\xBB\xBF")) {
            $first = substr($first, 3);
        }
        $sep = self::detectSeparator($first);
        rewind($fh);
        if (str_starts_with((string) fread($fh, 3), "\xEF\xBB\xBF") === false) {
            rewind($fh);
        }
        $rows = [];
        $n = 0;
        while (($row = fgetcsv($fh, 0, $sep, '"', '\\')) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }
            $rows[] = array_map(static fn ($c) => self::clean((string) $c), $row);
            $n++;
            if ($limit > 0 && $n >= $limit) {
                break;
            }
        }
        fclose($fh);
        return $rows;
    }

    private static function detectSeparator(string $line): string
    {
        $best = ',';
        $max = 0;
        foreach ([',', ';', "\t", '|'] as $s) {
            $c = substr_count($line, $s);
            if ($c > $max) {
                $max = $c;
                $best = $s;
            }
        }
        return $best;
    }

    /** Lector XLSX sencillo: primera hoja, valores planos. */
    public static function readXlsx(string $file, int $limit = 0): array
    {
        if (!class_exists('ZipArchive')) {
            return [];
        }
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            return [];
        }
        $shared = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $prev = libxml_use_internal_errors(true);
            $ss = simplexml_load_string($ssXml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT);
            libxml_use_internal_errors($prev);
            if ($ss) {
                foreach ($ss->si as $si) {
                    $txt = '';
                    if (isset($si->t)) {
                        $txt = (string) $si->t;
                    } else {
                        foreach ($si->r as $r) {
                            $txt .= (string) $r->t;
                        }
                    }
                    $shared[] = $txt;
                }
            }
        }
        // Localiza la primera hoja real.
        $sheetName = 'xl/worksheets/sheet1.xml';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nm = (string) $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $nm)) {
                $sheetName = $nm;
                break;
            }
        }
        $xml = $zip->getFromName($sheetName);
        $zip->close();
        if ($xml === false) {
            return [];
        }
        $prev = libxml_use_internal_errors(true);
        $sh = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT);
        libxml_use_internal_errors($prev);
        if (!$sh) {
            return [];
        }
        $rows = [];
        $n = 0;
        foreach ($sh->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = self::colIndex((string) preg_replace('/\d+/', '', $ref));
                $t = (string) $c['t'];
                $v = '';
                if ($t === 's') {
                    $v = $shared[(int) $c->v] ?? '';
                } elseif ($t === 'inlineStr') {
                    $v = (string) ($c->is->t ?? '');
                } else {
                    $v = (string) ($c->v ?? '');
                }
                $cells[$col] = self::clean($v);
            }
            if ($cells) {
                $max = max(array_keys($cells));
                $line = [];
                for ($i = 0; $i <= $max; $i++) {
                    $line[] = $cells[$i] ?? '';
                }
                $rows[] = $line;
            } else {
                $rows[] = [];
            }
            $n++;
            if ($limit > 0 && $n >= $limit) {
                break;
            }
        }
        return $rows;
    }

    private static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return max(0, $n - 1);
    }

    private static function clean(string $v): string
    {
        $v = str_replace(["\0", "\r"], '', $v);
        if (!mb_check_encoding($v, 'UTF-8')) {
            $v = mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
        }
        return trim($v);
    }

    /** Escribe un CSV descargable con BOM para Excel. */
    public static function downloadCsv(string $filename, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9\.\-_]/', '', $filename) . '"');
        header('X-Content-Type-Options: nosniff');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $r) {
            fputcsv($out, $r, ',', '"', '\\');
        }
        fclose($out);
        exit;
    }
}
