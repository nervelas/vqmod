<?php
declare(strict_types=1);
/**
 * Generador mínimo de PDF para pruebas (SOLO tests): objetos, flujos con /Length
 * correcto, incorrecto o indirecto, ObjStm + xref stream, páginas con XObjects.
 * Incluye utilidades para fabricar píxeles con GD y filtros PNG.
 */
final class PdfGen
{
    /** @var array<int,array{b:?string,s:?string,stm:bool}> */
    public array $objs = [];
    private int $n = 2;          // 1 = catálogo, 2 = árbol de páginas
    public string $eol = "\n";
    /** @var int[] */
    public array $pages = [];
    public string $lenMode = 'ok';   // ok | bad | indirect

    public function reservar(): int
    {
        return ++$this->n;
    }

    public function poner(int $num, string $body, bool $enObjStm = false): int
    {
        $this->objs[$num] = ['b' => $body, 's' => null, 'stm' => $enObjStm];
        return $num;
    }

    public function obj(string $body, bool $enObjStm = false): int
    {
        return $this->poner($this->reservar(), $body, $enObjStm);
    }

    /** Flujo: $dict sin << >> ni /Length. */
    public function flujo(string $dict, string $data, ?string $lenMode = null): int
    {
        $num = $this->reservar();
        $lm = $lenMode ?? $this->lenMode;
        $len = (string)strlen($data);
        if ($lm === 'bad') {
            $len = '3';
        } elseif ($lm === 'indirect') {
            $len = $this->obj((string)strlen($data)) . ' 0 R';
        }
        $this->objs[$num] = ['b' => '<<' . $dict . ' /Length ' . $len . '>>', 's' => $data, 'stm' => false];
        return $num;
    }

    /**
     * @param array{w:int,h:int,cs?:string,bpc?:int,filter?:string,parms?:string,data:string,smask?:int,extra?:string} $o
     */
    public function imagen(array $o): int
    {
        $d = '/Type /XObject /Subtype /Image /Width ' . $o['w'] . ' /Height ' . $o['h'];
        if (isset($o['cs'])) {
            $d .= ' /ColorSpace ' . $o['cs'];
        }
        if (isset($o['bpc'])) {
            $d .= ' /BitsPerComponent ' . $o['bpc'];
        }
        if (isset($o['filter'])) {
            $d .= ' /Filter ' . $o['filter'];
        }
        if (isset($o['parms'])) {
            $d .= ' /DecodeParms ' . $o['parms'];
        }
        if (isset($o['smask'])) {
            $d .= ' /SMask ' . $o['smask'] . ' 0 R';
        }
        $d .= ' ' . ($o['extra'] ?? '');
        return $this->flujo($d, $o['data']);
    }

    /** Página que dibuja los XObjects dados (números de objeto). */
    public function pagina(array $imgs, bool $recursosIndirectos = false): int
    {
        $xo = '';
        $cont = '';
        foreach (array_values($imgs) as $i => $n) {
            $xo .= '/Im' . $i . ' ' . $n . ' 0 R ';
            $cont .= 'q 100 0 0 100 ' . (10 + $i * 5) . ' 10 cm /Im' . $i . " Do Q\n";
        }
        $c = $this->flujo('', $cont, 'ok');
        $res = '<< /XObject << ' . $xo . '>> >>';
        if ($recursosIndirectos) {
            $res = $this->obj($res, true) . ' 0 R';
        }
        $p = $this->obj('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources ' . $res . ' /Contents ' . $c . ' 0 R >>', true);
        $this->pages[] = $p;
        return $p;
    }

    public function construir(bool $xrefStream = false): string
    {
        $kids = implode(' ', array_map(static fn(int $p): string => $p . ' 0 R', $this->pages));
        $this->objs[1] = ['b' => '<< /Type /Catalog /Pages 2 0 R >>', 's' => null, 'stm' => false];
        $this->objs[2] = ['b' => '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($this->pages) . ' >>', 's' => null, 'stm' => $xrefStream];
        $e = $this->eol;
        $out = "%PDF-1.5" . $e . "%\xE2\xE3\xCF\xD3" . $e . "% comentario con 99 0 obj falso" . $e;
        $off = [];
        $stm = [];
        ksort($this->objs);
        foreach ($this->objs as $num => $o) {
            if ($xrefStream && $o['stm'] && $o['s'] === null && $num !== 1) {
                $stm[] = $num;
                continue;
            }
            $off[$num] = strlen($out);
            $out .= $num . ' 0 obj' . $e . $o['b'];
            if ($o['s'] !== null) {
                $out .= $e . 'stream' . ($e === "\r\n" ? "\r\n" : "\n") . $o['s'] . $e . 'endstream';
            }
            $out .= $e . 'endobj' . $e;
        }
        $max = max(array_keys($this->objs));
        if (!$xrefStream) {
            $xr = strlen($out);
            $out .= 'xref' . $e . '0 ' . ($max + 1) . $e . "0000000000 65535 f \n";
            for ($i = 1; $i <= $max; $i++) {
                $out .= isset($off[$i]) ? sprintf("%010d 00000 n \n", $off[$i]) : "0000000000 65535 f \n";
            }
            return $out . 'trailer' . $e . '<< /Size ' . ($max + 1) . ' /Root 1 0 R >>' . $e . 'startxref' . $e . $xr . $e . '%%EOF' . $e;
        }
        // ObjStm con los diccionarios marcados.
        $sn = $max + 1;
        $cab = '';
        $cuerpo = '';
        $idx = [];
        foreach ($stm as $i => $num) {
            $cab .= $num . ' ' . strlen($cuerpo) . ' ';
            $cuerpo .= $this->objs[$num]['b'] . ' ';
            $idx[$num] = $i;
        }
        $data = gzcompress($cab . "\n" . $cuerpo, 6);
        $off[$sn] = strlen($out);
        $out .= $sn . ' 0 obj' . $e . '<< /Type /ObjStm /N ' . count($stm) . ' /First ' . (strlen($cab) + 1) . ' /Filter /FlateDecode /Length ' . strlen($data) . ' >>' . $e . 'stream' . "\n" . $data . $e . 'endstream' . $e . 'endobj' . $e;
        $xn = $sn + 1;
        $xoff = strlen($out);
        $rows = '';
        for ($i = 0; $i <= $xn; $i++) {
            if ($i === 0) {
                $rows .= pack('CNn', 0, 0, 65535);
            } elseif (isset($idx[$i])) {
                $rows .= pack('CNn', 2, $sn, $idx[$i]);
            } elseif ($i === $xn) {
                $rows .= pack('CNn', 1, $xoff, 0);
            } elseif (isset($off[$i])) {
                $rows .= pack('CNn', 1, $off[$i], 0);
            } else {
                $rows .= pack('CNn', 0, 0, 0);
            }
        }
        $out .= $xn . ' 0 obj' . $e . '<< /Type /XRef /Size ' . ($xn + 1) . ' /W [1 4 2] /Root 1 0 R /Length ' . strlen($rows) . ' >>' . $e . 'stream' . "\n" . $rows . $e . 'endstream' . $e . 'endobj' . $e;
        return $out . 'startxref' . $e . $xoff . $e . '%%EOF' . $e;
    }

    // ---------------------------------------------------------------- utilidades de píxeles

    /** Dibuja una "foto" (bandas + elipses aleatorias deterministas). $swap invierte R<->B (para leer BMP como RGB). */
    public static function dibujar(int $w, int $h, int $seed, bool $swap = false)
    {
        $g = imagecreatetruecolor($w, $h);
        $col = static function ($g, int $r, int $gg, int $b) use ($swap): int {
            return $swap ? imagecolorallocate($g, $b, $gg, $r) : imagecolorallocate($g, $r, $gg, $b);
        };
        mt_srand($seed);
        $r0 = mt_rand(0, 120);
        $b0 = mt_rand(0, 120);
        for ($i = 0; $i < 32; $i++) {
            imagefilledrectangle($g, 0, intdiv($h * $i, 32), $w, intdiv($h * ($i + 1), 32), $col($g, $r0 + $i * 4, 40 + $i * 5, $b0 + 100 - $i * 3));
        }
        for ($i = 0; $i < 30; $i++) {
            imagefilledellipse($g, mt_rand(0, $w), mt_rand(0, $h), mt_rand(20, intdiv($w, 2)), mt_rand(20, intdiv($h, 2)), $col($g, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
        for ($i = 0; $i < 2500; $i++) { // textura tipo foto (muchos colores)
            $x = mt_rand(0, $w);
            $y = mt_rand(0, $h);
            imagefilledrectangle($g, $x, $y, $x + mt_rand(1, 6), $y + mt_rand(1, 6), $col($g, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
        return $g;
    }

    /** RGB 8 bits por muestra, fila a fila (sin relleno). */
    public static function rgbCrudo(int $w, int $h, int $seed): string
    {
        $g = self::dibujar($w, $h, $seed, true);
        ob_start();
        imagebmp($g, null, false);
        $bmp = (string)ob_get_clean();
        imagedestroy($g);
        $stride = ($w * 3 + 3) & ~3;
        $filas = [];
        for ($y = $h - 1; $y >= 0; $y--) {
            $filas[] = substr($bmp, 54 + $y * $stride, $w * 3);
        }
        return implode('', $filas);
    }

    public static function jpeg(int $w, int $h, int $seed, int $q = 80): string
    {
        $g = self::dibujar($w, $h, $seed);
        ob_start();
        imagejpeg($g, null, $q);
        imagedestroy($g);
        return (string)ob_get_clean();
    }

    /** Filtros PNG por fila (mezcla 0..4 rotando) para $bpp bytes por píxel. */
    public static function predictorPng(string $raw, int $fila, int $bpp, ?int $tipo = null): string
    {
        $out = '';
        $prev = str_repeat("\0", $fila);
        $rows = str_split($raw, $fila);
        foreach ($rows as $r => $cur) {
            $t = $tipo ?? ($r % 5);
            $c = array_values(unpack('C*', $cur));
            $p = array_values(unpack('C*', $prev));
            $f = [];
            for ($i = 0; $i < $fila; $i++) {
                $a = $i >= $bpp ? $c[$i - $bpp] : 0;
                $b = $p[$i];
                $cc = $i >= $bpp ? $p[$i - $bpp] : 0;
                switch ($t) {
                    case 1: $pr = $a; break;
                    case 2: $pr = $b; break;
                    case 3: $pr = ($a + $b) >> 1; break;
                    case 4:
                        $q = $a + $b - $cc;
                        $pa = abs($q - $a); $pb = abs($q - $b); $pc = abs($q - $cc);
                        $pr = ($pa <= $pb && $pa <= $pc) ? $a : ($pb <= $pc ? $b : $cc);
                        break;
                    default: $pr = 0;
                }
                $f[] = ($c[$i] - $pr) & 255;
            }
            $out .= chr($t) . pack('C*', ...$f);
            $prev = $cur;
        }
        return $out;
    }

    public static function a85(string $d): string
    {
        $o = '';
        $n = strlen($d);
        for ($i = 0; $i < $n; $i += 4) {
            $chunk = substr($d, $i, 4);
            $k = strlen($chunk);
            $v = unpack('N', str_pad($chunk, 4, "\0"))[1];
            if ($v === 0 && $k === 4) {
                $o .= 'z';
                continue;
            }
            $s = '';
            for ($j = 0; $j < 5; $j++) {
                $s = chr(33 + $v % 85) . $s;
                $v = intdiv($v, 85);
            }
            $o .= $k === 4 ? $s : substr($s, 0, $k + 1);
            if (strlen($o) % 70 < 5) {
                $o .= "\n";
            }
        }
        return $o . '~>';
    }

    /** Flate "streaming" que produce $bytes de ceros sin guardarlos en memoria (bomba). */
    public static function bombaCeros(int $bytes): string
    {
        $ctx = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
        $blk = str_repeat("\0", 1048576);
        $o = '';
        for ($i = 0; $i < intdiv($bytes, 1048576); $i++) {
            $o .= deflate_add($ctx, $blk, ZLIB_NO_FLUSH);
        }
        return $o . deflate_add($ctx, '', ZLIB_FINISH);
    }
}
