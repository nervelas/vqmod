<?php
declare(strict_types=1);

namespace Aurea\Core;

/** Colores de marca editables (se inyectan como variables CSS en un bloque con nonce). */
final class Brand
{
    private static function mix(string $hex, string $with, float $t): string
    {
        [$r1, $g1, $b1] = sscanf($hex, '#%02x%02x%02x');
        [$r2, $g2, $b2] = sscanf($with, '#%02x%02x%02x');
        return sprintf('#%02X%02X%02X', (int)round($r1 + ($r2 - $r1) * $t), (int)round($g1 + ($g2 - $g1) * $t), (int)round($b1 + ($b2 - $b1) * $t));
    }

    public static function css(): string
    {
        $gold = (string)Settings::get('color_gold', '');
        $ink = (string)Settings::get('color_ink', '');
        $ivory = (string)Settings::get('color_ivory', '');
        $o = '';
        if (Util::isColor($gold)) {
            $g1 = self::mix($gold, '#000000', 0.22); $g3 = self::mix($gold, '#FFFFFF', 0.45);
            $o .= '--gold-1:' . $g1 . ';--gold-2:' . $gold . ';--gold-3:' . $g3 . ';--gold-text:' . self::mix($gold, '#000000', 0.38)
                . ';--gold-grad:linear-gradient(115deg,' . $g1 . ' 0%,' . $gold . ' 48%,' . $g3 . ' 100%);';
        }
        if (Util::isColor($ink)) { $o .= '--ink:' . $ink . ';--ink-2:' . self::mix($ink, '#FFFFFF', 0.05) . ';--ink-3:' . self::mix($ink, '#FFFFFF', 0.1) . ';--ink-4:' . self::mix($ink, '#FFFFFF', 0.16) . ';'; }
        if (Util::isColor($ivory)) { $o .= '--ivory:' . $ivory . ';--ivory-2:' . self::mix($ivory, '#000000', 0.05) . ';--paper:' . self::mix($ivory, '#FFFFFF', 0.55) . ';'; }
        return $o === '' ? '' : ':root{' . $o . '}';
    }

    public static function logoUrl(): string
    {
        $l = (string)Settings::get('logo', '');
        return $l !== '' ? url('uploads/' . rawurlencode($l)) : '';
    }

    public static function initial(): string
    {
        return mb_strtoupper(mb_substr(trim((string)Settings::get('business_name', 'A')), 0, 1, 'UTF-8'), 'UTF-8') ?: 'A';
    }

    public static function waUrl(string $text = ''): string
    {
        $n = preg_replace('/\D+/', '', (string)Settings::get('business_whatsapp', '')) ?? '';
        if ($n === '') { return ''; }
        if (strlen($n) === 8) { $n = '502' . $n; }
        return 'https://wa.me/' . $n . ($text !== '' ? '?text=' . rawurlencode($text) : '');
    }
}
