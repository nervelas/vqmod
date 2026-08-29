<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Ayudas de color para que la marca de cada restaurante siga siendo legible.
 *
 * El dueño elige su color desde el panel y puede escoger uno de tono medio
 * (un dorado, un naranja) que no contrasta ni sobre blanco ni con letra
 * blanca encima. En vez de impedirselo, derivamos aqui dos variantes:
 * el color del texto que va sobre él, y una version ajustada para cuando el
 * color mismo se usa como letra. Asi el menu se lee siempre.
 */
final class Color
{
    /** @return array{0:int,1:int,2:int} */
    public static function rgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) return [212, 175, 55];
        return [(int)hexdec(substr($hex, 0, 2)), (int)hexdec(substr($hex, 2, 2)), (int)hexdec(substr($hex, 4, 2))];
    }

    public static function hex(array $rgb): string
    {
        return sprintf('#%02X%02X%02X',
            max(0, min(255, (int)$rgb[0])), max(0, min(255, (int)$rgb[1])), max(0, min(255, (int)$rgb[2])));
    }

    /** Luminancia relativa segun WCAG. */
    public static function luminancia(array $rgb): float
    {
        $c = [];
        foreach ($rgb as $v) {
            $v /= 255;
            $c[] = $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    /** Relacion de contraste entre dos colores (1 a 21). */
    public static function contraste(array $a, array $b): float
    {
        $la = self::luminancia($a);
        $lb = self::luminancia($b);
        if ($la < $lb) [$la, $lb] = [$lb, $la];
        return ($la + 0.05) / ($lb + 0.05);
    }

    /** Blanco o casi negro, el que se lea mejor sobre este color. */
    public static function textoSobre(string $hex): string
    {
        $c = self::rgb($hex);
        return self::contraste($c, [255, 255, 255]) >= self::contraste($c, [26, 24, 20])
            ? '#FFFFFF' : '#1A1814';
    }

    /**
     * El mismo color, aclarado u oscurecido lo justo para que sirva de letra
     * sobre el fondo dado. Conserva el tono: sigue siendo la marca.
     */
    public static function legibleSobre(string $hex, string $fondoHex, float $meta = 4.6): string
    {
        $c = self::rgb($hex);
        $f = self::rgb($fondoHex);
        if (self::contraste($c, $f) >= $meta) return self::hex($c);

        $aclarar = self::luminancia($f) < 0.35;   // fondo oscuro => aclaramos
        $mejor = $c;
        for ($i = 1; $i <= 100; $i++) {
            $t = $i / 100;
            $cand = $aclarar
                ? [$c[0] + (255 - $c[0]) * $t, $c[1] + (255 - $c[1]) * $t, $c[2] + (255 - $c[2]) * $t]
                : [$c[0] * (1 - $t), $c[1] * (1 - $t), $c[2] * (1 - $t)];
            $cand = array_map(static fn($v) => (int)round($v), $cand);
            $mejor = $cand;
            if (self::contraste($cand, $f) >= $meta) break;
        }
        return self::hex($mejor);
    }
}
