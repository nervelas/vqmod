<?php
declare(strict_types=1);

namespace MenuGold\Vendor\Xlsx;

/**
 * Lector de archivos XLSX (y CSV como respaldo) para la importacion masiva
 * de productos. Devuelve un array de filas con valores en texto plano.
 */
class XlsxReader
{
    private array $cadenas = [];
    public string $error = '';

    /**
     * @return array<int,array<int,string>> filas x columnas
     */
    public function leer(string $rutaArchivo, int $maxFilas = 5000): array
    {
        $ext = strtolower((string)pathinfo($rutaArchivo, PATHINFO_EXTENSION));
        if ($ext === 'csv' || $ext === 'txt') return $this->leerCsv($rutaArchivo, $maxFilas);

        $partes = Zip::read($rutaArchivo);
        if (!$partes) { $this->error = 'No se pudo abrir el archivo. Verifica que sea un .xlsx válido.'; return []; }

        // Cadenas compartidas
        if (isset($partes['xl/sharedStrings.xml'])) {
            $this->cadenas = $this->parseSharedStrings($partes['xl/sharedStrings.xml']);
        }
        // Primera hoja segun el workbook
        $hoja = null;
        foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet.xml'] as $c) {
            if (isset($partes[$c])) { $hoja = $partes[$c]; break; }
        }
        if ($hoja === null) {
            foreach ($partes as $ruta => $contenido) {
                if (strncmp($ruta, 'xl/worksheets/', 14) === 0 && substr($ruta, -4) === '.xml') { $hoja = $contenido; break; }
            }
        }
        if ($hoja === null) { $this->error = 'El archivo no contiene hojas de cálculo.'; return []; }

        return $this->parseHoja($hoja, $maxFilas);
    }

    /** @return array<int,string> */
    private function parseSharedStrings(string $xml): array
    {
        $out = [];
        if (!preg_match_all('~<si>(.*?)</si>~su', $xml, $m)) return $out;
        foreach ($m[1] as $si) {
            $txt = '';
            if (preg_match_all('~<t[^>]*>(.*?)</t>~su', $si, $t)) {
                foreach ($t[1] as $frag) $txt .= $frag;
            }
            $out[] = html_entity_decode($txt, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        return $out;
    }

    /** @return array<int,array<int,string>> */
    private function parseHoja(string $xml, int $maxFilas): array
    {
        $filas = [];
        if (!preg_match_all('~<row[^>]*r="(\d+)"[^>]*>(.*?)</row>~su', $xml, $rm, PREG_SET_ORDER)) {
            // Filas sin atributo r
            preg_match_all('~<row[^>]*>(.*?)</row>~su', $xml, $rm2, PREG_SET_ORDER);
            foreach ($rm2 as $i => $r) {
                if (count($filas) >= $maxFilas) break;
                $filas[] = $this->parseFila($r[1]);
            }
            return $filas;
        }
        $maxIdx = 0;
        $tmp = [];
        foreach ($rm as $r) {
            $idx = (int)$r[1] - 1;
            if ($idx >= $maxFilas) continue;
            $tmp[$idx] = $this->parseFila($r[2]);
            $maxIdx = max($maxIdx, $idx);
        }
        for ($i = 0; $i <= $maxIdx; $i++) $filas[$i] = $tmp[$i] ?? [];
        return $filas;
    }

    /** @return array<int,string> */
    private function parseFila(string $xml): array
    {
        $fila = [];
        $max = -1;
        // La alternativa auto-cerrada va primero para no tragarse celdas vacias
        if (preg_match_all('~<c(\s[^>]*?)?/>|<c(\s[^>]*?)?>(.*?)</c>~su', $xml, $cm, PREG_SET_ORDER)) {
            foreach ($cm as $c) {
                $autocerrada = !isset($c[2]);
                $attrs  = $autocerrada ? ($c[1] ?? '') : ($c[2] ?? '');
                $cuerpo = $autocerrada ? '' : ($c[3] ?? '');
                $col = 0;
                if (preg_match('~r="([A-Z]+)\d+"~', $attrs, $rm)) $col = $this->colIndex($rm[1]);
                $tipo = '';
                if (preg_match('~t="([^"]+)"~', $attrs, $tm)) $tipo = $tm[1];

                $valor = '';
                if ($tipo === 'inlineStr') {
                    if (preg_match_all('~<t[^>]*>(.*?)</t>~su', $cuerpo, $im)) $valor = implode('', $im[1]);
                } elseif (preg_match('~<v>(.*?)</v>~su', $cuerpo, $vm)) {
                    $valor = $vm[1];
                }
                if ($tipo === 's') {
                    $valor = $this->cadenas[(int)$valor] ?? '';
                } else {
                    $valor = html_entity_decode($valor, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
                $fila[$col] = trim((string)$valor);
                $max = max($max, $col);
            }
        }
        $out = [];
        for ($i = 0; $i <= $max; $i++) $out[$i] = $fila[$i] ?? '';
        return $out;
    }

    private function colIndex(string $letras): int
    {
        $n = 0;
        $len = strlen($letras);
        for ($i = 0; $i < $len; $i++) {
            $n = $n * 26 + (ord($letras[$i]) - 64);
        }
        return max(0, $n - 1);
    }

    /** @return array<int,array<int,string>> */
    private function leerCsv(string $ruta, int $maxFilas): array
    {
        $filas = [];
        $fh = @fopen($ruta, 'r');
        if (!$fh) { $this->error = 'No se pudo abrir el archivo CSV.'; return []; }
        // Detectar separador con la primera linea
        $primera = (string)fgets($fh);
        $sep = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';
        rewind($fh);
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($fh);
        while (($r = fgetcsv($fh, 0, $sep)) !== false && count($filas) < $maxFilas) {
            if ($r === [null]) continue;
            $filas[] = array_map(static function ($v) {
                $v = (string)$v;
                if (!mb_check_encoding($v, 'UTF-8')) $v = (string)mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
                return trim($v);
            }, $r);
        }
        fclose($fh);
        return $filas;
    }
}
