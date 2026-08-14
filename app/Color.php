<?php
/**
 * Color math for deriving accessible palettes from a theme's base colors.
 */
class Color
{
    public static function toRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) < 6 || !ctype_xdigit(substr($hex, 0, 6))) {
            return [0, 0, 0];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    public static function toHex(array $rgb): string
    {
        return sprintf('#%02X%02X%02X',
            (int)max(0, min(255, round($rgb[0]))),
            (int)max(0, min(255, round($rgb[1]))),
            (int)max(0, min(255, round($rgb[2])))
        );
    }

    /** Convert to an "rgba()"-ready component string. */
    public static function rgbString(string $hex): string
    {
        [$r, $g, $b] = self::toRgb($hex);
        return "$r, $g, $b";
    }

    /** WCAG relative luminance (0 dark .. 1 light). */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::toRgb($hex);
        $ch = array_map(function ($c) {
            $c /= 255;
            return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        }, [$r, $g, $b]);
        return 0.2126 * $ch[0] + 0.7152 * $ch[1] + 0.0722 * $ch[2];
    }

    /** WCAG contrast ratio between two colors. */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        $hi = max($la, $lb);
        $lo = min($la, $lb);
        return ($hi + 0.05) / ($lo + 0.05);
    }

    public static function isLight(string $hex): bool
    {
        return self::luminance($hex) > 0.42;
    }

    /** Mix two colors. $weight is the amount of $b (0..1). */
    public static function mix(string $a, string $b, float $weight): string
    {
        $ra = self::toRgb($a);
        $rb = self::toRgb($b);
        $w  = max(0, min(1, $weight));
        return self::toHex([
            $ra[0] + ($rb[0] - $ra[0]) * $w,
            $ra[1] + ($rb[1] - $ra[1]) * $w,
            $ra[2] + ($rb[2] - $ra[2]) * $w,
        ]);
    }

    public static function lighten(string $hex, float $amount): string
    {
        return self::mix($hex, '#FFFFFF', $amount);
    }

    public static function darken(string $hex, float $amount): string
    {
        return self::mix($hex, '#000000', $amount);
    }

    /**
     * Pick the most readable text color for a given background, favouring the
     * provided secondary color when it already contrasts well.
     */
    public static function readableText(string $bg, ?string $preferred = null): string
    {
        $candidates = [];
        if ($preferred) {
            $candidates[] = $preferred;
        }
        $candidates[] = self::isLight($bg) ? '#0B0F14' : '#FFFFFF';
        $candidates[] = self::isLight($bg) ? '#FFFFFF' : '#0B0F14';
        $best = $candidates[0];
        $bestC = 0.0;
        foreach ($candidates as $c) {
            $ratio = self::contrast($bg, $c);
            if ($ratio >= 7.0) {
                return $c; // AAA — good enough, keep preference order
            }
            if ($ratio > $bestC) {
                $bestC = $ratio;
                $best  = $c;
            }
        }
        // If even the best is weak, fall back to pure black/white.
        if ($bestC < 4.5) {
            return self::isLight($bg) ? '#000000' : '#FFFFFF';
        }
        return $best;
    }

    /**
     * Ensure a foreground reaches a minimum contrast against a background by
     * pushing it lighter or darker until it does (or returns black/white).
     */
    public static function ensureContrast(string $fg, string $bg, float $min = 4.5): string
    {
        if (self::contrast($fg, $bg) >= $min) {
            return $fg;
        }
        $towardsLight = !self::isLight($bg);
        $target = $towardsLight ? '#FFFFFF' : '#000000';
        for ($i = 1; $i <= 20; $i++) {
            $candidate = self::mix($fg, $target, $i / 20);
            if (self::contrast($candidate, $bg) >= $min) {
                return $candidate;
            }
        }
        return self::readableText($bg);
    }
}
