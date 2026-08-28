<?php
declare(strict_types=1);

namespace MenuGold\Vendor\QrCode;

/**
 * Generador de codigos QR en PHP puro (sin extensiones ni servicios externos).
 *
 * Implementa la norma ISO/IEC 18004 en modo byte para las versiones 1 a 20,
 * con los cuatro niveles de correccion de error (L, M, Q, H).
 *
 * Salidas disponibles: matriz booleana, PNG (GD), SVG y rectangulos vectoriales
 * para incrustar en PDF.
 *
 * Uso:
 *   $m   = QrCode::matrix('https://ejemplo.com', 'M');
 *   $png = QrCode::png('https://ejemplo.com', 8, 4);
 */
final class QrCode
{
    public const ECC_L = 0;
    public const ECC_M = 1;
    public const ECC_Q = 2;
    public const ECC_H = 3;

    /** Indicadores de nivel de correccion en la informacion de formato. */
    private const ECC_BITS = [self::ECC_L => 1, self::ECC_M => 0, self::ECC_Q => 3, self::ECC_H => 2];

    /**
     * Bloques RS por version y nivel:
     * [cw de correccion por bloque, bloques grupo1, datos grupo1, bloques grupo2, datos grupo2]
     */
    private const BLOQUES = [
        1  => [[7,1,19,0,0],   [10,1,16,0,0],  [13,1,13,0,0],  [17,1,9,0,0]],
        2  => [[10,1,34,0,0],  [16,1,28,0,0],  [22,1,22,0,0],  [28,1,16,0,0]],
        3  => [[15,1,55,0,0],  [26,1,44,0,0],  [18,2,17,0,0],  [22,2,13,0,0]],
        4  => [[20,1,80,0,0],  [18,2,32,0,0],  [26,2,24,0,0],  [16,4,9,0,0]],
        5  => [[26,1,108,0,0], [24,2,43,0,0],  [18,2,15,2,16], [22,2,11,2,12]],
        6  => [[18,2,68,0,0],  [16,4,27,0,0],  [24,4,19,0,0],  [28,4,15,0,0]],
        7  => [[20,2,78,0,0],  [18,4,31,0,0],  [18,2,14,4,15], [26,4,13,1,14]],
        8  => [[24,2,97,0,0],  [22,2,38,2,39], [22,4,18,2,19], [26,4,14,2,15]],
        9  => [[30,2,116,0,0], [22,3,36,2,37], [20,4,16,4,17], [24,4,12,4,13]],
        10 => [[18,2,68,2,69], [26,4,43,1,44], [24,6,19,2,20], [28,6,15,2,16]],
        11 => [[20,4,81,0,0],  [30,1,50,4,51], [28,4,22,4,23], [24,3,12,8,13]],
        12 => [[24,2,92,2,93], [22,6,36,2,37], [26,4,20,6,21], [28,7,14,4,15]],
        13 => [[26,4,107,0,0], [22,8,37,1,38], [24,8,20,4,21], [22,12,11,4,12]],
        14 => [[30,3,115,1,116],[24,4,40,5,41],[20,11,16,5,17],[24,11,12,5,13]],
        15 => [[22,5,87,1,88], [24,5,41,5,42], [30,5,24,7,25], [24,11,12,7,13]],
        16 => [[24,5,98,1,99], [28,7,45,3,46], [24,15,19,2,20],[30,3,15,13,16]],
        17 => [[28,1,107,5,108],[28,10,46,1,47],[28,1,22,15,23],[28,2,14,17,15]],
        18 => [[30,5,120,1,121],[26,9,43,4,44],[28,17,22,1,23],[28,2,14,19,15]],
        19 => [[28,3,113,4,114],[26,3,44,11,45],[26,17,21,4,22],[26,9,13,16,14]],
        20 => [[28,3,107,5,108],[26,3,41,13,42],[30,15,24,5,25],[28,15,15,10,16]],
    ];

    /** Coordenadas de los patrones de alineacion por version. */
    private const ALINEACION = [
        1=>[], 2=>[6,18], 3=>[6,22], 4=>[6,26], 5=>[6,30], 6=>[6,34],
        7=>[6,22,38], 8=>[6,24,42], 9=>[6,26,46], 10=>[6,28,50],
        11=>[6,30,54], 12=>[6,32,58], 13=>[6,34,62], 14=>[6,26,46,66],
        15=>[6,26,48,70], 16=>[6,26,50,74], 17=>[6,30,54,78], 18=>[6,30,56,82],
        19=>[6,30,58,86], 20=>[6,34,62,90],
    ];

    /** Bits sobrantes al final del area de datos. */
    private const REMANENTE = [1=>0,2=>7,3=>7,4=>7,5=>7,6=>7,7=>0,8=>0,9=>0,10=>0,
        11=>0,12=>0,13=>0,14=>3,15=>3,16=>3,17=>3,18=>3,19=>3,20=>3];

    private static array $exp = [];
    private static array $log = [];

    // =====================================================================
    //  API publica
    // =====================================================================

    /**
     * Devuelve la matriz del QR: array de filas, cada una array de bool.
     * @return array<int,array<int,bool>>
     */
    public static function matrix(string $texto, string $nivel = 'M', int $versionMinima = 1): array
    {
        $ecc = self::nivel($nivel);
        $bytes = array_values(unpack('C*', $texto) ?: []);
        $version = self::elegirVersion(count($bytes), $ecc, max(1, $versionMinima));

        $datos = self::codificarDatos($bytes, $version, $ecc);
        $final = self::intercalar($datos, $version, $ecc);

        $tam = 17 + 4 * $version;
        $mapa = self::plantilla($version, $tam);
        $matriz = array_fill(0, $tam, array_fill(0, $tam, false));
        self::dibujarFuncion($matriz, $version, $tam);
        self::colocarDatos($matriz, $mapa, $final, $tam, $version);

        // Elegir la mascara con menor penalizacion
        $mejor = null; $mejorPuntos = PHP_INT_MAX;
        for ($m = 0; $m < 8; $m++) {
            $prueba = self::aplicarMascara($matriz, $mapa, $tam, $m);
            self::formato($prueba, $ecc, $m, $tam);
            $p = self::penalizacion($prueba, $tam);
            if ($p < $mejorPuntos) { $mejorPuntos = $p; $mejor = $prueba; }
        }
        return $mejor ?? $matriz;
    }

    /** PNG mediante GD. $escala = pixeles por modulo, $margen = modulos de silencio. */
    public static function png(string $texto, int $escala = 8, int $margen = 4, string $oscuro = '#000000', string $claro = '#FFFFFF', string $nivel = 'M'): string
    {
        $m = self::matrix($texto, $nivel);
        $n = count($m);
        $escala = max(1, min(40, $escala));
        $margen = max(0, min(16, $margen));
        $lado = ($n + 2 * $margen) * $escala;

        if (!function_exists('imagecreatetruecolor')) {
            return self::svgToPngFallback();
        }
        $img = imagecreatetruecolor($lado, $lado);
        [$r1, $g1, $b1] = self::hex($claro);
        [$r2, $g2, $b2] = self::hex($oscuro);
        $cl = imagecolorallocate($img, $r1, $g1, $b1);
        $os = imagecolorallocate($img, $r2, $g2, $b2);
        imagefilledrectangle($img, 0, 0, $lado, $lado, $cl);
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($m[$y][$x]) {
                    $px = ($x + $margen) * $escala;
                    $py = ($y + $margen) * $escala;
                    imagefilledrectangle($img, $px, $py, $px + $escala - 1, $py + $escala - 1, $os);
                }
            }
        }
        ob_start();
        imagepng($img, null, 6);
        $out = (string)ob_get_clean();
        imagedestroy($img);
        return $out;
    }

    /** SVG escalable, ideal para impresion. */
    public static function svg(string $texto, int $escala = 8, int $margen = 4, string $oscuro = '#000000', string $claro = '#FFFFFF', string $nivel = 'M'): string
    {
        $m = self::matrix($texto, $nivel);
        $n = count($m);
        $lado = ($n + 2 * $margen) * $escala;
        $d = '';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($m[$y][$x]) {
                    $d .= 'M' . (($x + $margen) * $escala) . ' ' . (($y + $margen) * $escala)
                        . 'h' . $escala . 'v' . $escala . 'h-' . $escala . 'z';
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $lado . '" height="' . $lado . '" viewBox="0 0 ' . $lado . ' ' . $lado . '" shape-rendering="crispEdges">'
            . '<rect width="' . $lado . '" height="' . $lado . '" fill="' . htmlspecialchars($claro, ENT_QUOTES) . '"/>'
            . '<path d="' . $d . '" fill="' . htmlspecialchars($oscuro, ENT_QUOTES) . '"/></svg>';
    }

    /**
     * Rectangulos agrupados por filas contiguas: util para dibujar el QR
     * como vectores en un PDF (nitidez perfecta al imprimir).
     * @return array{0:int,1:array<int,array{0:int,1:int,2:int}>} [modulos, [x,y,ancho]]
     */
    public static function rects(string $texto, string $nivel = 'M'): array
    {
        $m = self::matrix($texto, $nivel);
        $n = count($m);
        $out = [];
        for ($y = 0; $y < $n; $y++) {
            $x = 0;
            while ($x < $n) {
                if (!$m[$y][$x]) { $x++; continue; }
                $ini = $x;
                while ($x < $n && $m[$y][$x]) $x++;
                $out[] = [$ini, $y, $x - $ini];
            }
        }
        return [$n, $out];
    }

    // =====================================================================
    //  Codificacion
    // =====================================================================

    private static function nivel(string $n): int
    {
        switch (strtoupper($n)) {
            case 'L': return self::ECC_L;
            case 'Q': return self::ECC_Q;
            case 'H': return self::ECC_H;
        }
        return self::ECC_M;
    }

    private static function capacidadDatos(int $version, int $ecc): int
    {
        [$ecCw, $b1, $d1, $b2, $d2] = self::BLOQUES[$version][$ecc];
        return $b1 * $d1 + $b2 * $d2;
    }

    private static function elegirVersion(int $largo, int $ecc, int $min): int
    {
        for ($v = max(1, $min); $v <= 20; $v++) {
            $cuentaBits = $v < 10 ? 8 : 16;
            $bitsNecesarios = 4 + $cuentaBits + $largo * 8;
            if (self::capacidadDatos($v, $ecc) * 8 >= $bitsNecesarios) return $v;
        }
        throw new \RuntimeException('El texto es demasiado largo para un código QR (máximo versión 20).');
    }

    /** @param array<int,int> $bytes @return array<int,int> codewords de datos */
    private static function codificarDatos(array $bytes, int $version, int $ecc): array
    {
        $bits = '';
        $bits .= '0100';                                   // modo byte
        $cuentaBits = $version < 10 ? 8 : 16;
        $bits .= str_pad(decbin(count($bytes)), $cuentaBits, '0', STR_PAD_LEFT);
        foreach ($bytes as $b) $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);

        $capacidad = self::capacidadDatos($version, $ecc) * 8;
        // Terminador
        $bits .= str_repeat('0', min(4, max(0, $capacidad - strlen($bits))));
        // Relleno a byte completo
        if (strlen($bits) % 8 !== 0) $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        // Bytes de relleno alternos
        $pad = ['11101100', '00010001'];
        $i = 0;
        while (strlen($bits) < $capacidad) { $bits .= $pad[$i % 2]; $i++; }

        $cw = [];
        for ($p = 0; $p < strlen($bits); $p += 8) {
            $cw[] = bindec(substr($bits, $p, 8));
        }
        return $cw;
    }

    /** Divide en bloques, calcula RS e intercala. @return array<int,int> */
    private static function intercalar(array $datos, int $version, int $ecc): array
    {
        [$ecCw, $b1, $d1, $b2, $d2] = self::BLOQUES[$version][$ecc];
        $bloques = [];
        $ecBloques = [];
        $pos = 0;
        for ($i = 0; $i < $b1; $i++) {
            $bloques[] = array_slice($datos, $pos, $d1);
            $pos += $d1;
        }
        for ($i = 0; $i < $b2; $i++) {
            $bloques[] = array_slice($datos, $pos, $d2);
            $pos += $d2;
        }
        foreach ($bloques as $b) $ecBloques[] = self::reedSolomon($b, $ecCw);

        $salida = [];
        $maxDatos = max($d1, $d2);
        for ($i = 0; $i < $maxDatos; $i++) {
            foreach ($bloques as $b) {
                if (isset($b[$i])) $salida[] = $b[$i];
            }
        }
        for ($i = 0; $i < $ecCw; $i++) {
            foreach ($ecBloques as $b) {
                if (isset($b[$i])) $salida[] = $b[$i];
            }
        }
        return $salida;
    }

    // ---------------------------------------------------------------- GF(256)
    private static function initGf(): void
    {
        if (self::$exp) return;
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) $x ^= 0x11D;
        }
        for ($i = 255; $i < 512; $i++) self::$exp[$i] = self::$exp[$i - 255];
    }

    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) return 0;
        return self::$exp[self::$log[$a] + self::$log[$b]];
    }

    /** @return array<int,int> */
    private static function reedSolomon(array $datos, int $grado): array
    {
        self::initGf();
        $gen = self::generador($grado);
        $res = array_merge($datos, array_fill(0, $grado, 0));
        $n = count($datos);
        for ($i = 0; $i < $n; $i++) {
            $factor = $res[$i];
            if ($factor === 0) continue;
            for ($j = 0; $j <= $grado; $j++) {
                $res[$i + $j] ^= self::gfMul($gen[$j], $factor);
            }
        }
        return array_slice($res, $n, $grado);
    }

    /** Polinomio generador de grado $g. @return array<int,int> */
    private static function generador(int $g): array
    {
        self::initGf();
        static $cache = [];
        if (isset($cache[$g])) return $cache[$g];
        $poly = [1];
        for ($i = 0; $i < $g; $i++) {
            $siguiente = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $j => $c) {
                $siguiente[$j] ^= $c;                                   // x * coef
                $siguiente[$j + 1] ^= self::gfMul($c, self::$exp[$i]);  // a^i * coef
            }
            $poly = $siguiente;
        }
        $cache[$g] = $poly;
        return $poly;
    }

    // =====================================================================
    //  Matriz
    // =====================================================================

    /** Mapa de modulos de funcion (true = reservado, no lleva datos). */
    private static function plantilla(int $version, int $tam): array
    {
        $mapa = array_fill(0, $tam, array_fill(0, $tam, false));
        $marcar = static function (array &$mapa, int $x, int $y, int $w, int $h) use ($tam): void {
            for ($j = 0; $j < $h; $j++) {
                for ($i = 0; $i < $w; $i++) {
                    $px = $x + $i; $py = $y + $j;
                    if ($px >= 0 && $py >= 0 && $px < $tam && $py < $tam) $mapa[$py][$px] = true;
                }
            }
        };
        // Buscadores + separadores + formato
        $marcar($mapa, 0, 0, 9, 9);
        $marcar($mapa, $tam - 8, 0, 8, 9);
        $marcar($mapa, 0, $tam - 8, 9, 8);
        // Temporizacion
        $marcar($mapa, 6, 0, 1, $tam);
        $marcar($mapa, 0, 6, $tam, 1);
        // Alineacion
        $al = self::ALINEACION[$version] ?? [];
        foreach ($al as $ax) {
            foreach ($al as $ay) {
                if (($ax <= 8 && $ay <= 8) || ($ax <= 8 && $ay >= $tam - 9) || ($ax >= $tam - 9 && $ay <= 8)) continue;
                $marcar($mapa, $ax - 2, $ay - 2, 5, 5);
            }
        }
        // Informacion de version
        if ($version >= 7) {
            $marcar($mapa, 0, $tam - 11, 6, 3);
            $marcar($mapa, $tam - 11, 0, 3, 6);
        }
        return $mapa;
    }

    private static function dibujarFuncion(array &$m, int $version, int $tam): void
    {
        $buscador = static function (array &$m, int $x0, int $y0) use ($tam): void {
            for ($y = -1; $y <= 7; $y++) {
                for ($x = -1; $x <= 7; $x++) {
                    $px = $x0 + $x; $py = $y0 + $y;
                    if ($px < 0 || $py < 0 || $px >= $tam || $py >= $tam) continue;
                    $borde = ($x >= 0 && $x <= 6 && ($y === 0 || $y === 6))
                          || ($y >= 0 && $y <= 6 && ($x === 0 || $x === 6));
                    $centro = $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4;
                    $m[$py][$px] = $borde || $centro;
                }
            }
        };
        $buscador($m, 0, 0);
        $buscador($m, $tam - 7, 0);
        $buscador($m, 0, $tam - 7);

        // Patrones de temporizacion
        for ($i = 8; $i < $tam - 8; $i++) {
            $v = ($i % 2 === 0);
            $m[6][$i] = $v;
            $m[$i][6] = $v;
        }
        // Modulo oscuro obligatorio
        $m[$tam - 8][8] = true;

        // Patrones de alineacion
        $al = self::ALINEACION[$version] ?? [];
        foreach ($al as $ax) {
            foreach ($al as $ay) {
                if (($ax <= 8 && $ay <= 8) || ($ax <= 8 && $ay >= $tam - 9) || ($ax >= $tam - 9 && $ay <= 8)) continue;
                for ($y = -2; $y <= 2; $y++) {
                    for ($x = -2; $x <= 2; $x++) {
                        $m[$ay + $y][$ax + $x] = (max(abs($x), abs($y)) !== 1);
                    }
                }
            }
        }

        // Informacion de version (>= 7)
        if ($version >= 7) {
            $bits = self::bitsVersion($version);
            for ($i = 0; $i < 18; $i++) {
                $b = (($bits >> $i) & 1) === 1;
                $a = intdiv($i, 3);
                $c = $i % 3;
                $m[$tam - 11 + $c][$a] = $b;
                $m[$a][$tam - 11 + $c] = $b;
            }
        }
    }

    private static function bitsVersion(int $version): int
    {
        $r = $version;
        for ($i = 0; $i < 12; $i++) {
            $r = ($r << 1) ^ ((($r >> 11) & 1) * 0x1F25);
        }
        return ($version << 12) | $r;
    }

    private static function colocarDatos(array &$m, array $mapa, array $cw, int $tam, int $version): void
    {
        $bits = '';
        foreach ($cw as $b) $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        $bits .= str_repeat('0', self::REMANENTE[$version] ?? 0);

        $i = 0;
        $largo = strlen($bits);
        $arriba = true;
        for ($col = $tam - 1; $col > 0; $col -= 2) {
            if ($col === 6) $col--; // se salta la columna de temporizacion
            for ($f = 0; $f < $tam; $f++) {
                $fila = $arriba ? ($tam - 1 - $f) : $f;
                for ($c = 0; $c < 2; $c++) {
                    $x = $col - $c;
                    if ($mapa[$fila][$x]) continue;
                    $m[$fila][$x] = $i < $largo ? ($bits[$i] === '1') : false;
                    $i++;
                }
            }
            $arriba = !$arriba;
        }
    }

    private static function aplicarMascara(array $m, array $mapa, int $tam, int $mask): array
    {
        for ($y = 0; $y < $tam; $y++) {
            for ($x = 0; $x < $tam; $x++) {
                if ($mapa[$y][$x]) continue;
                if (self::mascara($mask, $x, $y)) $m[$y][$x] = !$m[$y][$x];
            }
        }
        return $m;
    }

    private static function mascara(int $n, int $x, int $y): bool
    {
        switch ($n) {
            case 0: return ($x + $y) % 2 === 0;
            case 1: return $y % 2 === 0;
            case 2: return $x % 3 === 0;
            case 3: return ($x + $y) % 3 === 0;
            case 4: return (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0;
            case 5: return (($x * $y) % 2) + (($x * $y) % 3) === 0;
            case 6: return ((($x * $y) % 2) + (($x * $y) % 3)) % 2 === 0;
            case 7: return ((($x + $y) % 2) + (($x * $y) % 3)) % 2 === 0;
        }
        return false;
    }

    private static function formato(array &$m, int $ecc, int $mask, int $tam): void
    {
        $datos = (self::ECC_BITS[$ecc] << 3) | $mask;
        $r = $datos;
        for ($i = 0; $i < 10; $i++) {
            $r = ($r << 1) ^ ((($r >> 9) & 1) * 0x537);
        }
        $bits = (($datos << 10) | $r) ^ 0x5412;

        for ($i = 0; $i <= 5; $i++)  $m[$i][8] = ((($bits >> $i) & 1) === 1);
        $m[7][8] = ((($bits >> 6) & 1) === 1);
        $m[8][8] = ((($bits >> 7) & 1) === 1);
        $m[8][7] = ((($bits >> 8) & 1) === 1);
        for ($i = 9; $i <= 14; $i++) $m[8][14 - $i] = ((($bits >> $i) & 1) === 1);

        for ($i = 0; $i <= 7; $i++)  $m[8][$tam - 1 - $i] = ((($bits >> $i) & 1) === 1);
        for ($i = 8; $i <= 14; $i++) $m[$tam - 15 + $i][8] = ((($bits >> $i) & 1) === 1);
        $m[$tam - 8][8] = true;
    }

    // ------------------------------------------------------- penalizaciones
    private static function penalizacion(array $m, int $tam): int
    {
        $p = 0;
        // N1: series de 5 o mas del mismo color
        for ($y = 0; $y < $tam; $y++) {
            $run = 1;
            for ($x = 1; $x < $tam; $x++) {
                if ($m[$y][$x] === $m[$y][$x - 1]) { $run++; }
                else { if ($run >= 5) $p += 3 + ($run - 5); $run = 1; }
            }
            if ($run >= 5) $p += 3 + ($run - 5);
        }
        for ($x = 0; $x < $tam; $x++) {
            $run = 1;
            for ($y = 1; $y < $tam; $y++) {
                if ($m[$y][$x] === $m[$y - 1][$x]) { $run++; }
                else { if ($run >= 5) $p += 3 + ($run - 5); $run = 1; }
            }
            if ($run >= 5) $p += 3 + ($run - 5);
        }
        // N2: bloques 2x2 del mismo color
        for ($y = 0; $y < $tam - 1; $y++) {
            for ($x = 0; $x < $tam - 1; $x++) {
                $v = $m[$y][$x];
                if ($v === $m[$y][$x + 1] && $v === $m[$y + 1][$x] && $v === $m[$y + 1][$x + 1]) $p += 3;
            }
        }
        // N3: patron 1:1:3:1:1 con zona clara
        $patrones = [
            [true,false,true,true,true,false,true,false,false,false,false],
            [false,false,false,false,true,false,true,true,true,false,true],
        ];
        for ($y = 0; $y < $tam; $y++) {
            for ($x = 0; $x <= $tam - 11; $x++) {
                foreach ($patrones as $pat) {
                    $ok = true;
                    for ($k = 0; $k < 11; $k++) {
                        if ($m[$y][$x + $k] !== $pat[$k]) { $ok = false; break; }
                    }
                    if ($ok) $p += 40;
                }
            }
        }
        for ($x = 0; $x < $tam; $x++) {
            for ($y = 0; $y <= $tam - 11; $y++) {
                foreach ($patrones as $pat) {
                    $ok = true;
                    for ($k = 0; $k < 11; $k++) {
                        if ($m[$y + $k][$x] !== $pat[$k]) { $ok = false; break; }
                    }
                    if ($ok) $p += 40;
                }
            }
        }
        // N4: proporcion de modulos oscuros
        $oscuros = 0;
        for ($y = 0; $y < $tam; $y++) foreach ($m[$y] as $v) if ($v) $oscuros++;
        $porc = ($oscuros * 100) / ($tam * $tam);
        $p += (int)(abs($porc - 50) / 5) * 10;
        return $p;
    }

    private static function hex(string $c): array
    {
        $c = ltrim(trim($c), '#');
        if (strlen($c) === 3) $c = $c[0].$c[0].$c[1].$c[1].$c[2].$c[2];
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $c)) return [0, 0, 0];
        return [(int)hexdec(substr($c,0,2)), (int)hexdec(substr($c,2,2)), (int)hexdec(substr($c,4,2))];
    }

    private static function svgToPngFallback(): string
    {
        throw new \RuntimeException('La extensión GD no está disponible para generar PNG.');
    }
}
