<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Generador de patrones guilloché (rosetas, curvas de Lissajous y líneas entrelazadas) en SVG.
 * Todo es matemático y determinista: la misma semilla produce siempre el mismo dibujo.
 */
final class Guilloche
{
    private int $state;

    private function __construct(int $seed)
    {
        $this->state = ($seed * 2654435761 + 12345) & 0x7fffffff;
        if ($this->state === 0) {
            $this->state = 1;
        }
    }

    /** Generador pseudoaleatorio propio (no toca mt_srand global). */
    private function rnd(): float
    {
        $this->state = ($this->state * 1103515245 + 12345) & 0x7fffffff;
        return $this->state / 2147483647;
    }

    private function between(float $a, float $b): float
    {
        return $a + ($b - $a) * $this->rnd();
    }

    private static function num(float $n): string
    {
        $s = number_format($n, 1, '.', '');
        return str_ends_with($s, '.0') ? substr($s, 0, -2) : $s;
    }

    private static function op(float $v): string
    {
        return rtrim(rtrim(number_format(max(0.0, min(1.0, $v)), 2, '.', ''), '0'), '.') ?: '0';
    }

    /** Trazo poligonal en coordenadas relativas compactas (sin deriva de redondeo). @param array<int,array{0:float,1:float}> $pts */
    private static function path(array $pts, bool $close, string $stroke, float $sw, float $op): string
    {
        $px = round($pts[0][0], 1);
        $py = round($pts[0][1], 1);
        $d = 'M' . self::num($px) . ' ' . self::num($py) . 'l';
        $first = true;
        for ($i = 1, $c = count($pts); $i < $c; $i++) {
            $x = round($pts[$i][0], 1);
            $y = round($pts[$i][1], 1);
            $dx = self::num($x - $px);
            $dy = self::num($y - $py);
            $seg = $dx . (str_starts_with($dy, '-') ? '' : ' ') . $dy;
            $d .= ($first || str_starts_with($seg, '-') ? '' : ' ') . $seg;
            $first = false;
            $px = $x;
            $py = $y;
        }
        return '<path d="' . $d . ($close ? 'Z' : '') . '" stroke="' . $stroke . '" stroke-width="' . self::num($sw)
            . '" stroke-opacity="' . self::op($op) . '"/>';
    }

    /**
     * Rosetón: familia de curvas con modulación senoidal desfasada que se entrelazan en pétalos.
     *
     * @param array{curves?:int,petals?:int,seed?:int,colors?:array<int,string>,opacity?:float,stroke?:float,points?:int,depth?:float} $o
     */
    public static function rosette(float $cx, float $cy, float $radius, array $o = []): string
    {
        $g = new self((int) ($o['seed'] ?? 1));
        $n = max(2, (int) ($o['curves'] ?? 28));
        $k = max(3, (int) ($o['petals'] ?? 12));
        $colors = $o['colors'] ?? ['#C9A050'];
        $op = (float) ($o['opacity'] ?? 0.6);
        $sw = (float) ($o['stroke'] ?? 0.6);
        $pts = max(40, (int) ($o['points'] ?? 120));
        $depth = (float) ($o['depth'] ?? 0.28);
        $out = '';
        $pts = max($pts, $k * 11);
        for ($j = 0; $j < $n; $j++) {
            $phase = 2 * M_PI * $j / $n;
            $base = $radius * (0.62 + 0.06 * sin($j * 2 * M_PI / $n * 2));
            $amp = $radius * $depth;
            $poly = [];
            for ($i = 0; $i <= $pts; $i++) {
                $t = 2 * M_PI * ($i % $pts) / $pts;
                $r = $base + $amp * sin($k * $t + $phase) + $radius * 0.08 * sin(2 * $t + 3 * $phase);
                $poly[] = [$cx + $r * cos($t), $cy + $r * sin($t)];
            }
            $out .= self::path($poly, false, $colors[$j % count($colors)], $sw, $op * (0.55 + 0.45 * $g->rnd()));
        }
        // aro exterior
        $out .= '<circle cx="' . self::num($cx) . '" cy="' . self::num($cy) . '" r="' . self::num($radius * 0.98)
            . '" fill="none" stroke="' . $colors[0] . '" stroke-width="' . self::num($sw * 1.4) . '" stroke-opacity="' . self::op($op * 0.6) . '"/>';
        return '<g>' . $out . '</g>';
    }

    /**
     * Curvas de Lissajous entrelazadas que llenan un rectángulo.
     *
     * @param array{curves?:int,seed?:int,colors?:array<int,string>,opacity?:float,stroke?:float,points?:int} $o
     */
    public static function lissajous(float $w, float $h, array $o = []): string
    {
        $g = new self((int) ($o['seed'] ?? 1));
        $n = max(1, (int) ($o['curves'] ?? 10));
        $colors = $o['colors'] ?? ['#C9A050'];
        $op = (float) ($o['opacity'] ?? 0.5);
        $sw = (float) ($o['stroke'] ?? 0.6);
        $pts = max(60, (int) ($o['points'] ?? 220));
        $out = '';
        for ($j = 0; $j < $n; $j++) {
            $a = (int) round($g->between(2, 7));
            $b = $a + (int) round($g->between(1, 4));
            $ph = $g->between(0, M_PI);
            $ax = $w * $g->between(0.38, 0.5);
            $ay = $h * $g->between(0.38, 0.5);
            $poly = [];
            for ($i = 0; $i <= $pts; $i++) {
                $t = 2 * M_PI * $i / $pts;
                $poly[] = [$w / 2 + $ax * sin($a * $t + $ph), $h / 2 + $ay * sin($b * $t)];
            }
            $out .= self::path($poly, true, $colors[$j % count($colors)], $sw, $op * (0.5 + 0.5 * $g->rnd()));
        }
        return '<g>' . $out . '</g>';
    }

    /**
     * Franja de líneas entrelazadas (ondas desfasadas) para bordes y cabeceras.
     *
     * @param array{curves?:int,seed?:int,colors?:array<int,string>,opacity?:float,stroke?:float,waves?:float,amp?:float} $o
     */
    public static function weave(float $x, float $y, float $w, float $h, array $o = []): string
    {
        $g = new self((int) ($o['seed'] ?? 1));
        $n = max(2, (int) ($o['curves'] ?? 8));
        $colors = $o['colors'] ?? ['#C9A050'];
        $op = (float) ($o['opacity'] ?? 0.5);
        $sw = (float) ($o['stroke'] ?? 0.6);
        $waves = (float) ($o['waves'] ?? 6);
        $amp = (float) ($o['amp'] ?? 0.42);
        $pts = max(60, (int) ($o['points'] ?? round($w / 8)));
        $out = '';
        for ($j = 0; $j < $n; $j++) {
            $ph = $j * M_PI / $n * 2;
            $poly = [];
            for ($i = 0; $i <= $pts; $i++) {
                $u = $i / $pts;
                $env = min(1.0, 4 * sin(M_PI * $u)) ** 0.7;
                $poly[] = [$x + $w * $u, $y + $h / 2 + $h * $amp * $env * sin(2 * M_PI * $waves * $u + $ph)];
            }
            $out .= self::path($poly, false, $colors[$j % count($colors)], $sw, $op * (0.6 + 0.4 * $g->rnd()));
        }
        return '<g>' . $out . '</g>';
    }

    /**
     * Documento SVG completo.
     * mode: mix | rosette | lissajous | weave. Parámetros: width, height, seed, curves, colors, opacity, stroke, background (opcional).
     *
     * @param array<string,mixed> $o
     */
    public static function svg(array $o = []): string
    {
        $w = (float) ($o['width'] ?? 600);
        $h = (float) ($o['height'] ?? 600);
        $mode = (string) ($o['mode'] ?? 'mix');
        $seed = (int) ($o['seed'] ?? 7);
        $curves = (int) ($o['curves'] ?? 24);
        $colors = $o['colors'] ?? ['#7A5420', '#C9A050', '#F0D9A0'];
        $opacity = (float) ($o['opacity'] ?? 0.6);
        $stroke = (float) ($o['stroke'] ?? 0.6);
        $common = ['seed' => $seed, 'colors' => $colors, 'opacity' => $opacity, 'stroke' => $stroke];
        if (!empty($o['points'])) {
            $common['points'] = (int) $o['points'];
        }
        $body = '';
        if ($mode === 'rosette') {
            $body = self::rosette($w / 2, $h / 2, min($w, $h) * 0.46, $common + ['curves' => $curves, 'petals' => (int) ($o['petals'] ?? 12), 'depth' => (float) ($o['depth'] ?? 0.28)]);
        } elseif ($mode === 'lissajous') {
            $body = self::lissajous($w, $h, $common + ['curves' => $curves]);
        } elseif ($mode === 'weave') {
            $body = self::weave(0, 0, $w, $h, $common + ['curves' => $curves]);
        } else {
            $body = self::lissajous($w, $h, array_merge($common, ['curves' => max(3, intdiv($curves, 3))]))
                . self::rosette($w / 2, $h / 2, min($w, $h) * 0.3, array_merge($common, ['curves' => $curves, 'petals' => (int) ($o['petals'] ?? 9), 'seed' => $seed + 3]));
        }
        $bg = isset($o['background']) ? '<rect width="100%" height="100%" fill="' . $o['background'] . '"/>' : '';
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . self::num($w) . ' ' . self::num($h) . '" width="' . self::num($w) . '" height="' . self::num($h) . '" fill="none" stroke-linejoin="round">'
            . $bg . $body . '</svg>';
    }

    /** Solo el contenido interno (sin <svg>) de un patrón, para componer escenas. */
    public static function fragment(string $mode, float $w, float $h, array $o = []): string
    {
        $svg = self::svg(['mode' => $mode, 'width' => $w, 'height' => $h] + $o);
        return (string) preg_replace('/^<svg[^>]*>|<\/svg>$/', '', $svg);
    }
}
