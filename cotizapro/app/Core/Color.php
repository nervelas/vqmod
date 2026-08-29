<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Utilidades de color para garantizar contraste accesible (WCAG AA)
 * sobre cualquier acento que elija la empresa.
 */
final class Color
{
    /** Luminancia relativa (WCAG). */
    public static function luminance(array $rgb): float
    {
        $c = [];
        foreach ($rgb as $v) {
            $s = $v / 255;
            $c[] = $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        }
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    public static function contrast(array $a, array $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    public static function hex(array $rgb): string
    {
        return sprintf('#%02X%02X%02X', (int) max(0, min(255, $rgb[0])), (int) max(0, min(255, $rgb[1])), (int) max(0, min(255, $rgb[2])));
    }

    public static function scale(array $rgb, float $f): array
    {
        return [$rgb[0] * $f, $rgb[1] * $f, $rgb[2] * $f];
    }

    /** Oscurece un color hasta alcanzar el contraste pedido contra $bg. */
    public static function darkenUntil(array $fg, array $bg, float $min = 4.6): array
    {
        $c = $fg;
        for ($i = 0; $i < 60 && self::contrast($c, $bg) < $min; $i++) {
            $c = self::scale($c, 0.96);
        }
        return $c;
    }

    /**
     * Devuelve [colorDeFondo, colorDeTexto] garantizando al menos $min de
     * contraste. Elige entre tinta y blanco y, si hace falta, oscurece o
     * aclara el fondo hasta cumplir.
     */
    public static function accessiblePair(array $accent, array $ink, float $min = 4.6): array
    {
        $white = [255, 255, 255];
        $cInk   = self::contrast($accent, $ink);
        $cWhite = self::contrast($accent, $white);
        $fg = $cInk >= $cWhite ? $ink : $white;
        $best = max($cInk, $cWhite);
        if ($best >= $min) {
            return [$accent, $fg];
        }
        // Ajusta el fondo en la dirección que aumenta el contraste.
        $darken = $fg === $white;
        $bg = $accent;
        for ($i = 0; $i < 40; $i++) {
            $bg = $darken ? self::scale($bg, 0.96) : array_map(static fn ($v) => $v + (255 - $v) * 0.05, $bg);
            if (self::contrast($bg, $fg) >= $min) {
                break;
            }
        }
        return [$bg, $fg];
    }
}
