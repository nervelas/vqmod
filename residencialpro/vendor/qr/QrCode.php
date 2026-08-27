<?php
declare(strict_types=1);

namespace Vendor\Qr;

/**
 * Generador de códigos QR en PHP puro (modo byte, versiones 1–20, EC L/M/Q/H).
 * Sin dependencias externas. Salida PNG (GD) o SVG.
 *
 * ResidencialPro — librería local, sin composer.
 */
final class QrCode
{
    /** [ecCodewordsPorBloque, bloquesG1, datosG1, bloquesG2, datosG2] */
    private const BLOQUES = [
        'L' => [
            1 => [7,1,19,0,0],   2 => [10,1,34,0,0],  3 => [15,1,55,0,0],  4 => [20,1,80,0,0],
            5 => [26,1,108,0,0], 6 => [18,2,68,0,0],  7 => [20,2,78,0,0],  8 => [24,2,97,0,0],
            9 => [30,2,116,0,0], 10 => [18,2,68,2,69], 11 => [20,4,81,0,0], 12 => [24,2,92,2,93],
            13 => [26,4,107,0,0], 14 => [30,3,115,1,116], 15 => [22,5,87,1,88],
            16 => [24,5,98,1,99], 17 => [28,1,107,5,108], 18 => [30,5,120,1,121],
            19 => [28,3,113,4,114], 20 => [28,3,107,5,108],
        ],
        'M' => [
            1 => [10,1,16,0,0],  2 => [16,1,28,0,0],  3 => [26,1,44,0,0],  4 => [18,2,32,0,0],
            5 => [24,2,43,0,0],  6 => [16,4,27,0,0],  7 => [18,4,31,0,0],  8 => [22,2,38,2,39],
            9 => [22,3,36,2,37], 10 => [26,4,43,1,44], 11 => [30,1,50,4,51], 12 => [22,6,36,2,37],
            13 => [22,8,37,1,38], 14 => [24,4,40,5,41], 15 => [24,5,41,5,42],
            16 => [28,7,45,3,46], 17 => [28,10,46,1,47], 18 => [26,9,43,4,44],
            19 => [26,3,44,11,45], 20 => [26,3,41,13,42],
        ],
        'Q' => [
            1 => [13,1,13,0,0],  2 => [22,1,22,0,0],  3 => [18,2,17,0,0],  4 => [26,2,24,0,0],
            5 => [18,2,15,2,16], 6 => [24,4,19,0,0],  7 => [18,2,14,4,15], 8 => [22,4,18,2,19],
            9 => [20,4,16,4,17], 10 => [24,6,19,2,20], 11 => [28,4,22,4,23], 12 => [26,4,20,6,21],
            13 => [24,8,20,4,21], 14 => [20,11,16,5,17], 15 => [30,5,24,7,25],
            16 => [24,15,19,2,20], 17 => [28,1,22,15,23], 18 => [28,17,22,1,23],
            19 => [26,17,21,4,22], 20 => [30,15,24,5,25],
        ],
        'H' => [
            1 => [17,1,9,0,0],   2 => [28,1,16,0,0],  3 => [22,2,13,0,0],  4 => [16,4,9,0,0],
            5 => [22,2,11,2,12], 6 => [28,4,15,0,0],  7 => [26,4,13,1,14], 8 => [26,4,14,2,15],
            9 => [24,4,12,4,13], 10 => [28,6,15,2,16], 11 => [24,3,12,8,13], 12 => [28,7,14,4,15],
            13 => [22,12,11,4,12], 14 => [24,11,12,5,13], 15 => [24,11,12,7,13],
            16 => [30,3,15,13,16], 17 => [28,2,14,17,15], 18 => [28,2,14,19,15],
            19 => [26,9,13,16,14], 20 => [28,15,15,10,16],
        ],
    ];

    /** Centros de los patrones de alineación por versión. */
    private const ALINEACION = [
        1 => [], 2 => [6,18], 3 => [6,22], 4 => [6,26], 5 => [6,30], 6 => [6,34],
        7 => [6,22,38], 8 => [6,24,42], 9 => [6,26,46], 10 => [6,28,50],
        11 => [6,30,54], 12 => [6,32,58], 13 => [6,34,62], 14 => [6,26,46,66], 15 => [6,26,48,70],
        16 => [6,26,50,74], 17 => [6,30,54,78], 18 => [6,30,56,82], 19 => [6,30,58,86], 20 => [6,34,62,90],
    ];

    private const NIVEL_BITS = ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2];

    /** Fuerza una máscara concreta (0-7). Solo para pruebas de conformidad. */
    public static ?int $mascaraFija = null;

    private static array $exp = [];
    private static array $log = [];

    // ------------------------------------------------------------------ API

    /** Matriz booleana del QR (true = módulo oscuro). */
    public static function matriz(string $texto, string $nivel = 'M'): array
    {
        $nivel = isset(self::BLOQUES[$nivel]) ? $nivel : 'M';
        $version = self::elegirVersion(strlen($texto), $nivel);
        $codewords = self::codewordsFinales($texto, $version, $nivel);
        return self::construir($codewords, $version, $nivel);
    }

    /** PNG binario. */
    public static function png(string $texto, int $escala = 8, int $margen = 4, string $nivel = 'M'): string
    {
        $m    = self::matriz($texto, $nivel);
        $n    = count($m);
        $lado = ($n + $margen * 2) * $escala;
        if (!function_exists('imagecreatetruecolor')) {
            return '';
        }
        $img = imagecreatetruecolor($lado, $lado);
        $bl  = imagecolorallocate($img, 255, 255, 255);
        $ne  = imagecolorallocate($img, 15, 46, 36);
        imagefilledrectangle($img, 0, 0, $lado, $lado, $bl);
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($m[$y][$x]) {
                    $px = ($x + $margen) * $escala;
                    $py = ($y + $margen) * $escala;
                    imagefilledrectangle($img, $px, $py, $px + $escala - 1, $py + $escala - 1, $ne);
                }
            }
        }
        ob_start();
        imagepng($img, null, 7);
        imagedestroy($img);
        return (string) ob_get_clean();
    }

    /** SVG (útil para PDF e impresión). */
    public static function svg(string $texto, int $escala = 8, int $margen = 4, string $nivel = 'M', string $color = '#0F2E24'): string
    {
        $m    = self::matriz($texto, $nivel);
        $n    = count($m);
        $lado = ($n + $margen * 2) * $escala;
        $d    = '';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($m[$y][$x]) {
                    $d .= 'M' . (($x + $margen) * $escala) . ' ' . (($y + $margen) * $escala)
                        . 'h' . $escala . 'v' . $escala . 'h-' . $escala . 'z';
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $lado . '" height="' . $lado . '" '
             . 'viewBox="0 0 ' . $lado . ' ' . $lado . '" shape-rendering="crispEdges">'
             . '<rect width="100%" height="100%" fill="#ffffff"/>'
             . '<path d="' . $d . '" fill="' . $color . '"/></svg>';
    }

    // -------------------------------------------------------------- INTERNO

    private static function elegirVersion(int $largo, string $nivel): int
    {
        foreach (self::BLOQUES[$nivel] as $v => $b) {
            $datos   = $b[1] * $b[2] + $b[3] * $b[4];
            $ccBits  = $v <= 9 ? 8 : 16;
            $capacidad = intdiv($datos * 8 - 4 - $ccBits, 8);
            if ($largo <= $capacidad) {
                return $v;
            }
        }
        throw new \RuntimeException('El contenido es demasiado extenso para un código QR.');
    }

    /** Bits de datos + relleno, agrupados en bloques con corrección de errores e intercalados. */
    private static function codewordsFinales(string $texto, int $version, string $nivel): array
    {
        [$ecCw, $b1, $d1, $b2, $d2] = self::BLOQUES[$nivel][$version];
        $totalDatos = $b1 * $d1 + $b2 * $d2;

        $bits = [];
        self::agregarBits($bits, 0b0100, 4);                       // modo byte
        self::agregarBits($bits, strlen($texto), $version <= 9 ? 8 : 16);
        foreach (str_split($texto) as $ch) {
            self::agregarBits($bits, ord($ch), 8);
        }
        // Terminador
        $restante = $totalDatos * 8 - count($bits);
        self::agregarBits($bits, 0, min(4, max(0, $restante)));
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        $datos = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | $bits[$i + $j];
            }
            $datos[] = $byte;
        }
        $alterna = [0xEC, 0x11];
        $k = 0;
        while (count($datos) < $totalDatos) {
            $datos[] = $alterna[$k % 2];
            $k++;
        }

        // Bloques
        $bloquesDatos = [];
        $bloquesEc    = [];
        $pos = 0;
        for ($i = 0; $i < $b1; $i++) {
            $bloque = array_slice($datos, $pos, $d1);
            $pos += $d1;
            $bloquesDatos[] = $bloque;
            $bloquesEc[]    = self::reedSolomon($bloque, $ecCw);
        }
        for ($i = 0; $i < $b2; $i++) {
            $bloque = array_slice($datos, $pos, $d2);
            $pos += $d2;
            $bloquesDatos[] = $bloque;
            $bloquesEc[]    = self::reedSolomon($bloque, $ecCw);
        }

        // Intercalado
        $salida = [];
        $maxD = max($d1, $d2);
        for ($i = 0; $i < $maxD; $i++) {
            foreach ($bloquesDatos as $b) {
                if (isset($b[$i])) {
                    $salida[] = $b[$i];
                }
            }
        }
        for ($i = 0; $i < $ecCw; $i++) {
            foreach ($bloquesEc as $b) {
                if (isset($b[$i])) {
                    $salida[] = $b[$i];
                }
            }
        }
        return $salida;
    }

    private static function agregarBits(array &$bits, int $valor, int $largo): void
    {
        for ($i = $largo - 1; $i >= 0; $i--) {
            $bits[] = ($valor >> $i) & 1;
        }
    }

    // ---- Galois Field 256 (x^8 + x^4 + x^3 + x^2 + 1) ----
    private static function iniciarGf(): void
    {
        if (self::$exp !== []) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 256; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 256; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$exp[(self::$log[$a] + self::$log[$b]) % 255];
    }

    private static function polinomioGenerador(int $grado): array
    {
        self::iniciarGf();
        $g = [1];
        for ($i = 0; $i < $grado; $i++) {
            $nuevo = array_fill(0, count($g) + 1, 0);
            foreach ($g as $j => $c) {
                $nuevo[$j]     ^= $c;                                  // término x
                $nuevo[$j + 1] ^= self::gfMul($c, self::$exp[$i]);     // término alfa^i
            }
            $g = $nuevo;
        }
        return $g;
    }

    private static function reedSolomon(array $datos, int $ecCw): array
    {
        self::iniciarGf();
        $gen  = self::polinomioGenerador($ecCw);
        $rest = array_fill(0, $ecCw, 0);
        foreach ($datos as $d) {
            $factor = $d ^ $rest[0];
            array_shift($rest);
            $rest[] = 0;
            if ($factor !== 0) {
                for ($i = 0; $i < $ecCw; $i++) {
                    $rest[$i] ^= self::gfMul($gen[$i + 1], $factor);
                }
            }
        }
        return $rest;
    }

    // ---- Construcción de la matriz ----
    private static function construir(array $codewords, int $version, string $nivel): array
    {
        $n = $version * 4 + 17;
        $m = array_fill(0, $n, array_fill(0, $n, 0));
        $r = array_fill(0, $n, array_fill(0, $n, false)); // reservado

        // Patrones localizadores + separadores
        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$cx, $cy]) {
            for ($y = -1; $y <= 7; $y++) {
                for ($x = -1; $x <= 7; $x++) {
                    $px = $cx + $x;
                    $py = $cy + $y;
                    if ($px < 0 || $py < 0 || $px >= $n || $py >= $n) {
                        continue;
                    }
                    $borde = ($x >= 0 && $x <= 6 && ($y === 0 || $y === 6))
                          || ($y >= 0 && $y <= 6 && ($x === 0 || $x === 6));
                    $centro = $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4;
                    $m[$py][$px] = ($borde || $centro) ? 1 : 0;
                    $r[$py][$px] = true;
                }
            }
        }

        // Patrones de tiempo
        for ($i = 8; $i < $n - 8; $i++) {
            $v = ($i % 2 === 0) ? 1 : 0;
            $m[6][$i] = $v; $r[6][$i] = true;
            $m[$i][6] = $v; $r[$i][6] = true;
        }

        // Patrones de alineación
        $centros = self::ALINEACION[$version] ?? [];
        foreach ($centros as $cy) {
            foreach ($centros as $cx) {
                if (($cx === 6 && $cy === 6) || ($cx === 6 && $cy === $n - 7) || ($cx === $n - 7 && $cy === 6)) {
                    continue;
                }
                for ($y = -2; $y <= 2; $y++) {
                    for ($x = -2; $x <= 2; $x++) {
                        $anillo = max(abs($x), abs($y));
                        $m[$cy + $y][$cx + $x] = ($anillo === 1) ? 0 : 1;
                        $r[$cy + $y][$cx + $x] = true;
                    }
                }
            }
        }

        // Módulo oscuro fijo
        $m[$n - 8][8] = 1;
        $r[$n - 8][8] = true;

        // Reserva de información de formato
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                $r[8][$i] = true;
                $r[$i][8] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $r[8][$n - 1 - $i] = true;
            $r[$n - 1 - $i][8] = true;
        }

        // Reserva de información de versión (v >= 7)
        if ($version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $r[$i][$n - 11 + $j] = true;
                    $r[$n - 11 + $j][$i] = true;
                }
            }
        }

        // Colocación de datos en zigzag
        $bits = [];
        foreach ($codewords as $cw) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = ($cw >> $i) & 1;
            }
        }
        $idx  = 0;
        $subir = true;
        for ($col = $n - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col = 5;
            }
            for ($t = 0; $t < $n; $t++) {
                $fila = $subir ? ($n - 1 - $t) : $t;
                for ($c = 0; $c < 2; $c++) {
                    $x = $col - $c;
                    if ($r[$fila][$x]) {
                        continue;
                    }
                    $m[$fila][$x] = $bits[$idx] ?? 0;
                    $idx++;
                }
            }
            $subir = !$subir;
        }

        // Selección de máscara
        $mejor = null;
        $mejorPen = PHP_INT_MAX;
        $mejorMask = 0;
        $desde = self::$mascaraFija ?? 0;
        $hasta = self::$mascaraFija !== null ? self::$mascaraFija : 7;
        for ($mask = $desde; $mask <= $hasta; $mask++) {
            $cand = self::aplicarMascara($m, $r, $mask, $n);
            self::ponerFormato($cand, $nivel, $mask, $n);
            if ($version >= 7) {
                self::ponerVersion($cand, $version, $n);
            }
            $pen = self::penalizacion($cand, $n);
            if ($pen < $mejorPen) {
                $mejorPen  = $pen;
                $mejor     = $cand;
                $mejorMask = $mask;
            }
        }
        unset($mejorMask);

        $salida = [];
        foreach ($mejor as $fila) {
            $salida[] = array_map(static fn($v) => $v === 1, $fila);
        }
        return $salida;
    }

    private static function aplicarMascara(array $m, array $r, int $mask, int $n): array
    {
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($r[$y][$x]) {
                    continue;
                }
                $inv = match ($mask) {
                    0 => ($y + $x) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($y + $x) % 3 === 0,
                    4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
                    5 => (($y * $x) % 2) + (($y * $x) % 3) === 0,
                    6 => ((($y * $x) % 2) + (($y * $x) % 3)) % 2 === 0,
                    default => ((($y + $x) % 2) + (($y * $x) % 3)) % 2 === 0,
                };
                if ($inv) {
                    $m[$y][$x] ^= 1;
                }
            }
        }
        return $m;
    }

    private static function ponerFormato(array &$m, string $nivel, int $mask, int $n): void
    {
        $datos = (self::NIVEL_BITS[$nivel] << 3) | $mask;
        $resto = $datos << 10;
        for ($i = 14; $i >= 10; $i--) {
            if ((($resto >> $i) & 1) === 1) {
                $resto ^= 0x537 << ($i - 10);
            }
        }
        $formato = (($datos << 10) | $resto) ^ 0x5412;

        // Los 15 bits se colocan del más significativo al menos significativo.
        for ($i = 0; $i < 15; $i++) {
            $bit = ($formato >> (14 - $i)) & 1;
            // Copia 1: banda horizontal de la fila 8 y banda vertical de la columna 8.
            if ($i < 6) {
                $m[8][$i] = $bit;
            } elseif ($i === 6) {
                $m[8][7] = $bit;
            } elseif ($i === 7) {
                $m[8][8] = $bit;
            } elseif ($i === 8) {
                $m[7][8] = $bit;
            } else {
                $m[14 - $i][8] = $bit;
            }
            // Copia 2: bajo el localizador inferior izquierdo y junto al superior derecho.
            if ($i < 8) {
                $m[$n - 1 - $i][8] = $bit;
            } else {
                $m[8][$n - 15 + $i] = $bit;
            }
        }
        $m[$n - 8][8] = 1;
    }

    private static function ponerVersion(array &$m, int $version, int $n): void
    {
        $resto = $version << 12;
        for ($i = 17; $i >= 12; $i--) {
            if ((($resto >> $i) & 1) === 1) {
                $resto ^= 0x1F25 << ($i - 12);
            }
        }
        $info = ($version << 12) | $resto;
        for ($i = 0; $i < 18; $i++) {
            $bit = ($info >> $i) & 1;
            $fila = intdiv($i, 3);
            $col  = $i % 3;
            $m[$fila][$n - 11 + $col] = $bit;
            $m[$n - 11 + $col][$fila] = $bit;
        }
    }

    private static function penalizacion(array $m, int $n): int
    {
        $p = 0;
        // Regla 1: series de 5+ del mismo color
        for ($y = 0; $y < $n; $y++) {
            $run = 1;
            for ($x = 1; $x < $n; $x++) {
                if ($m[$y][$x] === $m[$y][$x - 1]) {
                    $run++;
                } else {
                    if ($run >= 5) { $p += 3 + ($run - 5); }
                    $run = 1;
                }
            }
            if ($run >= 5) { $p += 3 + ($run - 5); }
        }
        for ($x = 0; $x < $n; $x++) {
            $run = 1;
            for ($y = 1; $y < $n; $y++) {
                if ($m[$y][$x] === $m[$y - 1][$x]) {
                    $run++;
                } else {
                    if ($run >= 5) { $p += 3 + ($run - 5); }
                    $run = 1;
                }
            }
            if ($run >= 5) { $p += 3 + ($run - 5); }
        }
        // Regla 2: bloques 2x2
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                $v = $m[$y][$x];
                if ($v === $m[$y][$x + 1] && $v === $m[$y + 1][$x] && $v === $m[$y + 1][$x + 1]) {
                    $p += 3;
                }
            }
        }
        // Regla 3: patrón 1:1:3:1:1
        $patrones = [[1,0,1,1,1,0,1,0,0,0,0], [0,0,0,0,1,0,1,1,1,0,1]];
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x <= $n - 11; $x++) {
                foreach ($patrones as $pat) {
                    $coincide = true;
                    for ($k = 0; $k < 11; $k++) {
                        if ($m[$y][$x + $k] !== $pat[$k]) { $coincide = false; break; }
                    }
                    if ($coincide) { $p += 40; }
                }
            }
        }
        for ($x = 0; $x < $n; $x++) {
            for ($y = 0; $y <= $n - 11; $y++) {
                foreach ($patrones as $pat) {
                    $coincide = true;
                    for ($k = 0; $k < 11; $k++) {
                        if ($m[$y + $k][$x] !== $pat[$k]) { $coincide = false; break; }
                    }
                    if ($coincide) { $p += 40; }
                }
            }
        }
        // Regla 4: proporción de oscuros
        $oscuros = 0;
        foreach ($m as $fila) {
            $oscuros += array_sum($fila);
        }
        $porcentaje = ($oscuros * 100) / ($n * $n);
        $p += ((int) (abs($porcentaje - 50) / 5)) * 10;
        return $p;
    }
}
