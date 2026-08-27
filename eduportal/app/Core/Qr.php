<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Codificador de codigo QR (modo byte, nivel de correccion M, versiones 1-10).
 * Implementacion propia y local; no requiere librerias externas.
 */
final class Qr
{
    /** version => [ec_por_bloque, [ [bloques, datos_por_bloque], ... ] ] */
    private const BLOQUES = [
        1  => [10, [[1, 16]]],
        2  => [16, [[1, 28]]],
        3  => [26, [[1, 44]]],
        4  => [18, [[2, 32]]],
        5  => [24, [[2, 43]]],
        6  => [16, [[4, 27]]],
        7  => [18, [[4, 31]]],
        8  => [22, [[2, 38], [2, 39]]],
        9  => [22, [[3, 36], [2, 37]]],
        10 => [26, [[4, 43], [1, 44]]],
    ];

    private const ALINEACION = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    private static array $exp = [];
    private static array $log = [];

    /**
     * Devuelve la matriz booleana del QR o null si el texto no cabe.
     * @return array<int,array<int,bool>>|null
     */
    public static function matriz(string $texto, ?int $maskForzada = null): ?array
    {
        $bytes = array_values(unpack('C*', $texto) ?: []);
        $len   = count($bytes);
        $version = null;
        $datosTotales = 0;
        foreach (self::BLOQUES as $v => [$ec, $grupos]) {
            $total = 0;
            foreach ($grupos as [$n, $d]) {
                $total += $n * $d;
            }
            $bitsCabecera = 4 + ($v < 10 ? 8 : 16);
            if ($len <= intdiv($total * 8 - $bitsCabecera, 8)) {
                $version = $v;
                $datosTotales = $total;
                break;
            }
        }
        if ($version === null) {
            return null;
        }

        // --- Bits de datos ---
        $bits = '0100';
        $bits .= str_pad(decbin($len), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT);
        foreach ($bytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }
        $capacidadBits = $datosTotales * 8;
        $bits .= str_repeat('0', min(4, $capacidadBits - strlen($bits)));
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - strlen($bits) % 8);
        }
        $relleno = ['11101100', '00010001'];
        $i = 0;
        while (strlen($bits) < $capacidadBits) {
            $bits .= $relleno[$i++ % 2];
        }
        $codewords = [];
        for ($p = 0; $p < $capacidadBits; $p += 8) {
            $codewords[] = bindec(substr($bits, $p, 8));
        }

        // --- Bloques y correccion de errores ---
        [$ecPorBloque, $grupos] = self::BLOQUES[$version];
        self::initGf();
        $bloquesDatos = [];
        $bloquesEc = [];
        $pos = 0;
        foreach ($grupos as [$n, $d]) {
            for ($k = 0; $k < $n; $k++) {
                $blk = array_slice($codewords, $pos, $d);
                $pos += $d;
                $bloquesDatos[] = $blk;
                $bloquesEc[]    = self::reedSolomon($blk, $ecPorBloque);
            }
        }
        $final = [];
        $maxD = max(array_map('count', $bloquesDatos));
        for ($c = 0; $c < $maxD; $c++) {
            foreach ($bloquesDatos as $blk) {
                if (isset($blk[$c])) {
                    $final[] = $blk[$c];
                }
            }
        }
        for ($c = 0; $c < $ecPorBloque; $c++) {
            foreach ($bloquesEc as $blk) {
                if (isset($blk[$c])) {
                    $final[] = $blk[$c];
                }
            }
        }
        $flujo = '';
        foreach ($final as $cw) {
            $flujo .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }

        // --- Matriz ---
        $size = 17 + 4 * $version;
        $m = array_fill(0, $size, array_fill(0, $size, false));
        $res = array_fill(0, $size, array_fill(0, $size, false));

        $ponFinder = static function (int $r, int $c) use (&$m, &$res, $size): void {
            for ($dr = -1; $dr <= 7; $dr++) {
                for ($dc = -1; $dc <= 7; $dc++) {
                    $rr = $r + $dr;
                    $cc = $c + $dc;
                    if ($rr < 0 || $cc < 0 || $rr >= $size || $cc >= $size) {
                        continue;
                    }
                    $borde = ($dr >= 0 && $dr <= 6 && ($dc === 0 || $dc === 6))
                        || ($dc >= 0 && $dc <= 6 && ($dr === 0 || $dr === 6));
                    $centro = $dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4;
                    $m[$rr][$cc]   = $borde || $centro;
                    $res[$rr][$cc] = true;
                }
            }
        };
        $ponFinder(0, 0);
        $ponFinder(0, $size - 7);
        $ponFinder($size - 7, 0);

        foreach (self::ALINEACION[$version] as $ar) {
            foreach (self::ALINEACION[$version] as $ac) {
                if (($ar <= 8 && $ac <= 8)
                    || ($ar <= 8 && $ac >= $size - 9)
                    || ($ar >= $size - 9 && $ac <= 8)) {
                    continue;
                }
                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $m[$ar + $dr][$ac + $dc]   = (max(abs($dr), abs($dc)) !== 1);
                        $res[$ar + $dr][$ac + $dc] = true;
                    }
                }
            }
        }

        for ($k = 8; $k < $size - 8; $k++) {
            $v = ($k % 2 === 0);
            $m[6][$k] = $v; $res[6][$k] = true;
            $m[$k][6] = $v; $res[$k][6] = true;
        }
        $m[$size - 8][8] = true;
        $res[$size - 8][8] = true;

        // Reservar zonas de formato
        for ($k = 0; $k <= 8; $k++) {
            if ($k !== 6) {
                $res[8][$k] = true;
                $res[$k][8] = true;
            }
        }
        for ($k = 0; $k < 8; $k++) {
            $res[8][$size - 1 - $k] = true;
            $res[$size - 1 - $k][8] = true;
        }
        if ($version >= 7) {
            for ($k = 0; $k < 18; $k++) {
                $r = intdiv($k, 3);
                $c = $size - 11 + ($k % 3);
                $res[$r][$c] = true;
                $res[$c][$r] = true;
            }
        }

        // --- Colocacion de datos en zigzag ---
        $idx = 0;
        $largo = strlen($flujo);
        $arriba = true;
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }
            for ($n = 0; $n < $size; $n++) {
                $row = $arriba ? ($size - 1 - $n) : $n;
                for ($s = 0; $s < 2; $s++) {
                    $c = $col - $s;
                    if ($res[$row][$c]) {
                        continue;
                    }
                    $m[$row][$c] = $idx < $largo && $flujo[$idx] === '1';
                    $idx++;
                }
            }
            $arriba = !$arriba;
        }


        // --- Mascaras ---
        $mejor = null;
        $mejorPenal = PHP_INT_MAX;
        $mejorMask = 0;
        $rango = $maskForzada === null ? range(0, 7) : [$maskForzada];
        foreach ($rango as $mask) {
            $cand = $m;
            for ($r = 0; $r < $size; $r++) {
                for ($c = 0; $c < $size; $c++) {
                    if ($res[$r][$c]) {
                        continue;
                    }
                    if (self::mascara($mask, $r, $c)) {
                        $cand[$r][$c] = !$cand[$r][$c];
                    }
                }
            }
            self::formato($cand, $size, $mask);
            if ($version >= 7) {
                self::versionInfo($cand, $size, $version);
            }
            $p = self::penalizacion($cand, $size);
            if ($p < $mejorPenal) {
                $mejorPenal = $p;
                $mejor = $cand;
                $mejorMask = $mask;
            }
        }
        unset($mejorMask);
        return $mejor;
    }

    private static function mascara(int $mask, int $r, int $c): bool
    {
        return match ($mask) {
            0 => ($r + $c) % 2 === 0,
            1 => $r % 2 === 0,
            2 => $c % 3 === 0,
            3 => ($r + $c) % 3 === 0,
            4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
            5 => (($r * $c) % 2 + ($r * $c) % 3) === 0,
            6 => ((($r * $c) % 2 + ($r * $c) % 3) % 2) === 0,
            7 => ((($r + $c) % 2 + ($r * $c) % 3) % 2) === 0,
            default => false,
        };
    }

    private static function formato(array &$m, int $size, int $mask): void
    {
        // Nivel M = 00
        $datos = (0b00 << 3) | $mask;
        $resto = $datos << 10;
        for ($i = 14; $i >= 10; $i--) {
            if ((($resto >> $i) & 1) === 1) {
                $resto ^= 0b10100110111 << ($i - 10);
            }
        }
        $bits = (($datos << 10) | $resto) ^ 0b101010000010010;
        for ($i = 0; $i < 15; $i++) {
            // El bit mas significativo se coloca primero (posicion 0).
            $b = ((($bits >> (14 - $i)) & 1) === 1);
            if ($i < 6) {
                $m[8][$i] = $b;
            } elseif ($i < 8) {
                $m[8][$i + 1] = $b;
            } elseif ($i === 8) {
                $m[7][8] = $b;
            } else {
                $m[14 - $i][8] = $b;
            }
            if ($i < 7) {
                $m[$size - 1 - $i][8] = $b;
            } else {
                $m[8][$size - 15 + $i] = $b;
            }
        }
    }

    private static function versionInfo(array &$m, int $size, int $version): void
    {
        $resto = $version << 12;
        for ($i = 17; $i >= 12; $i--) {
            if ((($resto >> $i) & 1) === 1) {
                $resto ^= 0b1111100100101 << ($i - 12);
            }
        }
        $bits = ($version << 12) | $resto;
        for ($i = 0; $i < 18; $i++) {
            $b = (($bits >> $i) & 1) === 1;
            $r = intdiv($i, 3);
            $c = $size - 11 + ($i % 3);
            $m[$r][$c] = $b;
            $m[$c][$r] = $b;
        }
    }

    private static function penalizacion(array $m, int $size): int
    {
        $p = 0;
        // Regla 1: series de 5 o mas
        for ($r = 0; $r < $size; $r++) {
            $run = 1;
            for ($c = 1; $c < $size; $c++) {
                if ($m[$r][$c] === $m[$r][$c - 1]) {
                    $run++;
                } else {
                    if ($run >= 5) { $p += 3 + ($run - 5); }
                    $run = 1;
                }
            }
            if ($run >= 5) { $p += 3 + ($run - 5); }
        }
        for ($c = 0; $c < $size; $c++) {
            $run = 1;
            for ($r = 1; $r < $size; $r++) {
                if ($m[$r][$c] === $m[$r - 1][$c]) {
                    $run++;
                } else {
                    if ($run >= 5) { $p += 3 + ($run - 5); }
                    $run = 1;
                }
            }
            if ($run >= 5) { $p += 3 + ($run - 5); }
        }
        // Regla 2: bloques 2x2
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $v = $m[$r][$c];
                if ($v === $m[$r][$c + 1] && $v === $m[$r + 1][$c] && $v === $m[$r + 1][$c + 1]) {
                    $p += 3;
                }
            }
        }
        // Regla 3: patrones 1011101
        $patron = [true, false, true, true, true, false, true];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c <= $size - 7; $c++) {
                $ok = true;
                for ($k = 0; $k < 7; $k++) {
                    if ($m[$r][$c + $k] !== $patron[$k]) { $ok = false; break; }
                }
                if ($ok) { $p += 40; }
            }
        }
        for ($c = 0; $c < $size; $c++) {
            for ($r = 0; $r <= $size - 7; $r++) {
                $ok = true;
                for ($k = 0; $k < 7; $k++) {
                    if ($m[$r + $k][$c] !== $patron[$k]) { $ok = false; break; }
                }
                if ($ok) { $p += 40; }
            }
        }
        // Regla 4: proporcion de modulos oscuros
        $oscuros = 0;
        foreach ($m as $fila) {
            foreach ($fila as $v) {
                if ($v) { $oscuros++; }
            }
        }
        $pct = (int)(abs($oscuros * 100 / ($size * $size) - 50) / 5);
        $p += $pct * 10;
        return $p;
    }

    private static function initGf(): void
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
                $x ^= 0x11d;
            }
        }
        for ($i = 256; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    private static function mul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$exp[(self::$log[$a] + self::$log[$b]) % 255];
    }

    private static function reedSolomon(array $datos, int $n): array
    {
        $gen = [1];
        for ($i = 0; $i < $n; $i++) {
            $nuevo = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $g) {
                $nuevo[$j] ^= $g;
                $nuevo[$j + 1] ^= self::mul($g, self::$exp[$i]);
            }
            $gen = $nuevo;
        }
        $res = array_merge($datos, array_fill(0, $n, 0));
        $cnt = count($datos);
        for ($i = 0; $i < $cnt; $i++) {
            $coef = $res[$i];
            if ($coef === 0) {
                continue;
            }
            foreach ($gen as $j => $g) {
                $res[$i + $j] ^= self::mul($g, $coef);
            }
        }
        return array_slice($res, $cnt, $n);
    }
}
