<?php
namespace MenuGold\Core;

/**
 * Temas de color.
 *
 * Toda la hoja de estilo está escrita contra un puñado de variables RGB
 * (`--ink-rgb`, `--cream-rgb`, `--gold-rgb`…). Un tema no es más que otro
 * juego de esos valores, así que cambiarlo repinta carta y panel enteros
 * sin tocar una sola regla de CSS.
 *
 * `ink` es siempre el fondo y `cream` siempre el texto: en los temas claros
 * simplemente se invierten. Lo que NUNCA se invierte es el velo que va sobre
 * las fotos (`scrim`), porque ahí el texto va en blanco pase lo que pase.
 */
final class Theme
{
    const PREDETERMINADO = 'brasa';

    /**
     * Cada tema define: modo, etiqueta, y los colores base.
     *
     * ink        fondo de la página
     * carbon     superficie (tarjetas, barras)
     * carbon2    superficie elevada (menús, campos)
     * cream      color del texto
     * gold       color primario (precios, botones, filos)
     * goldSoft   variante clara del primario
     * ember      color de acento (avisos, botón de pedido)
     * green      confirmaciones
     * onAccent   texto encima de un relleno de acento
     * shadow     color de las sombras
     */
    public static function todos()
    {
        return array(

            /* ---------------------- 4 temas oscuros ---------------------- */

            'brasa' => array(
                'modo' => 'oscuro', 'label' => 'Brasa', 'nota' => 'Negro humo y dorado champagne',
                'ink' => '#0C0B09', 'carbon' => '#161412', 'carbon2' => '#1E1B18',
                'cream' => '#F4EDE1', 'gold' => '#D8B26E', 'goldSoft' => '#E8CE9C',
                'ember' => '#C4502B', 'green' => '#6FBF8B',
                'onAccent' => '#FFF6F1', 'shadow' => '#000000',
            ),
            'medianoche' => array(
                'modo' => 'oscuro', 'label' => 'Medianoche', 'nota' => 'Azul noche y platino',
                'ink' => '#080B12', 'carbon' => '#111726', 'carbon2' => '#182034',
                'cream' => '#EAF0FA', 'gold' => '#9FB6E0', 'goldSoft' => '#C6D6F2',
                'ember' => '#4E7BD6', 'green' => '#5FC7A8',
                'onAccent' => '#F2F7FF', 'shadow' => '#000308',
            ),
            'esmeralda' => array(
                'modo' => 'oscuro', 'label' => 'Esmeralda', 'nota' => 'Verde bosque y oro viejo',
                'ink' => '#080D0A', 'carbon' => '#101A14', 'carbon2' => '#17241C',
                'cream' => '#EDF4EE', 'gold' => '#C9AE72', 'goldSoft' => '#E3CE9E',
                'ember' => '#2F8F63', 'green' => '#79D3A0',
                'onAccent' => '#F1FBF5', 'shadow' => '#000502',
            ),
            'borgona' => array(
                'modo' => 'oscuro', 'label' => 'Borgoña', 'nota' => 'Vino profundo y rosa seco',
                'ink' => '#0E0709', 'carbon' => '#1A1012', 'carbon2' => '#241619',
                'cream' => '#F6EBEC', 'gold' => '#D9A8A8', 'goldSoft' => '#EFCFCF',
                'ember' => '#9E2B3E', 'green' => '#6FBF8B',
                'onAccent' => '#FFF2F4', 'shadow' => '#050001',
            ),

            /* ----------------------- 6 temas claros ---------------------- */

            'marfil' => array(
                'modo' => 'claro', 'label' => 'Marfil', 'nota' => 'Papel hueso y bronce',
                'ink' => '#F7F3EA', 'carbon' => '#FFFFFF', 'carbon2' => '#F1EADC',
                'cream' => '#1A1712', 'gold' => '#7A5A22', 'goldSoft' => '#B99C63',
                'ember' => '#B4491F', 'green' => '#2E7D53',
                'onAccent' => '#FFF7F3', 'shadow' => '#6B5A3C',
            ),
            'lino' => array(
                'modo' => 'claro', 'label' => 'Lino', 'nota' => 'Gris cálido y grafito',
                'ink' => '#F4F2EF', 'carbon' => '#FFFFFF', 'carbon2' => '#EAE7E2',
                'cream' => '#17171A', 'gold' => '#4A463F', 'goldSoft' => '#8B857A',
                'ember' => '#9B4A2C', 'green' => '#2C7A55',
                'onAccent' => '#FFF8F5', 'shadow' => '#57544E',
            ),
            'olivo' => array(
                'modo' => 'claro', 'label' => 'Olivo', 'nota' => 'Verde salvia y tinta oliva',
                'ink' => '#F3F5EE', 'carbon' => '#FFFFFF', 'carbon2' => '#E8ECDF',
                'cream' => '#171A12', 'gold' => '#455426', 'goldSoft' => '#7E9153',
                'ember' => '#8A6B1F', 'green' => '#2E7D4F',
                'onAccent' => '#FBFFF4', 'shadow' => '#4E5940',
            ),
            'porcelana' => array(
                'modo' => 'claro', 'label' => 'Porcelana', 'nota' => 'Blanco frío y azul acero',
                'ink' => '#F2F5F9', 'carbon' => '#FFFFFF', 'carbon2' => '#E6ECF4',
                'cream' => '#111721', 'gold' => '#2B4867', 'goldSoft' => '#6D8CB2',
                'ember' => '#1F5FA8', 'green' => '#1E7A63',
                'onAccent' => '#F4F9FF', 'shadow' => '#44506080',
            ),
            'arena' => array(
                'modo' => 'claro', 'label' => 'Arena', 'nota' => 'Terracota y arcilla',
                'ink' => '#FAF4EC', 'carbon' => '#FFFFFF', 'carbon2' => '#F2E7D8',
                'cream' => '#1E1610', 'gold' => '#7E4818', 'goldSoft' => '#C08A55',
                'ember' => '#B7411F', 'green' => '#37734F',
                'onAccent' => '#FFF6F1', 'shadow' => '#7A5B3E',
            ),
            'rosa' => array(
                'modo' => 'claro', 'label' => 'Rosa', 'nota' => 'Nácar y ciruela',
                'ink' => '#FBF3F4', 'carbon' => '#FFFFFF', 'carbon2' => '#F4E6E8',
                'cream' => '#1D1316', 'gold' => '#783346', 'goldSoft' => '#BA808F',
                'ember' => '#B03050', 'green' => '#2F7A5C',
                'onAccent' => '#FFF4F7', 'shadow' => '#7A5560',
            ),
        );
    }

    /** Solo los oscuros o solo los claros, para agrupar en el panel. */
    public static function porModo($modo)
    {
        $out = array();
        foreach (self::todos() as $k => $t) {
            if ($t['modo'] === $modo) { $out[$k] = $t; }
        }
        return $out;
    }

    public static function existe($clave)
    {
        $t = self::todos();
        return isset($t[$clave]);
    }

    /** Nombres retirados de versiones anteriores, para no dejar a nadie sin tema. */
    private static $alias = array(
        'vino' => 'borgona', 'cobre' => 'arena', 'indigo' => 'medianoche',
        'obsidiana' => 'lino', 'custom' => 'brasa',
    );

    public static function uno($clave)
    {
        $t = self::todos();
        if (isset($t[$clave])) { return $t[$clave]; }
        if (isset(self::$alias[$clave], $t[self::$alias[$clave]])) { return $t[self::$alias[$clave]]; }
        return $t[self::PREDETERMINADO];
    }

    /** Traduce una clave antigua a la vigente. */
    public static function normaliza($clave)
    {
        $t = self::todos();
        if (isset($t[$clave])) { return $clave; }
        if (isset(self::$alias[$clave])) { return self::$alias[$clave]; }
        return self::PREDETERMINADO;
    }

    /** "#D8B26E" -> "216, 178, 110" */
    public static function rgb($hex)
    {
        $c = Image::hexToRgb($hex);
        return $c[0] . ', ' . $c[1] . ', ' . $c[2];
    }

    /**
     * Devuelve el bloque `:root` del tema, ya listo para inyectar.
     *
     * @param string $clave    tema elegido
     * @param string $primary  color primario personalizado (opcional)
     * @param string $accent   color de acento personalizado (opcional)
     */
    public static function css($clave, $primary = '', $accent = '')
    {
        $t = self::uno($clave);
        // Solo cuenta como color propio si de verdad difiere del tema.
        if (strcasecmp($primary, $t['gold']) === 0) { $primary = ''; }
        if (strcasecmp($accent, $t['ember']) === 0) { $accent = ''; }
        if ($primary !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $primary)) {
            $t['gold'] = $primary;
            $t['goldSoft'] = self::aclarar($primary, $t['modo'] === 'claro' ? 0.42 : 0.30);
        }
        if ($accent !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $accent)) {
            $t['ember'] = $accent;
        }
        $claro = $t['modo'] === 'claro';

        $css  = '--ink-rgb:' . self::rgb($t['ink']) . ';';
        $css .= '--carbon-rgb:' . self::rgb($t['carbon']) . ';';
        $css .= '--carbon2-rgb:' . self::rgb($t['carbon2']) . ';';
        $css .= '--cream-rgb:' . self::rgb($t['cream']) . ';';
        $css .= '--gold-rgb:' . self::rgb($t['gold']) . ';';
        $css .= '--gold-soft-rgb:' . self::rgb($t['goldSoft']) . ';';
        $css .= '--ember-rgb:' . self::rgb($t['ember']) . ';';
        $css .= '--green-rgb:' . self::rgb($t['green']) . ';';
        $css .= '--shadow-rgb:' . self::rgb($t['shadow']) . ';';
        $css .= '--on-accent:' . $t['onAccent'] . ';';
        // Texto sobre un relleno del color primario: se elige por luminancia,
        // así funciona igual con un dorado claro que con un azul oscuro.
        $css .= '--on-gold:' . (self::luz($t['gold']) > 0.55 ? 'rgb(var(--ink-rgb))' : '#FFFFFF') . ';';
        $css .= '--a-numeral:' . ($claro ? '1' : '.78') . ';';
        // En claro los tintes de un color sobre fondo blanco se ven lavados:
        // se suben las opacidades de filos y rellenos para compensar.
        $css .= '--a-line:' . ($claro ? '.34' : '.20') . ';';
        $css .= '--a-line-soft:' . ($claro ? '.14' : '.10') . ';';
        $css .= '--a-dim:' . ($claro ? '.78' : '.68') . ';';
        $css .= '--a-faint:' . ($claro ? '.62' : '.58') . ';';
        $css .= '--a-fill:' . ($claro ? '.12' : '.07') . ';';
        $css .= '--a-shadow:' . ($claro ? '.20' : '.90') . ';';
        $css .= 'color-scheme:' . ($claro ? 'light' : 'dark') . ';';
        return $css;
    }

    /** Luminancia percibida (0 negro, 1 blanco). */
    public static function luz($hex)
    {
        $c = Image::hexToRgb($hex);
        return (0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2]) / 255.0;
    }

    /** Aclara (+) u oscurece (-) un color en proporción. */
    public static function aclarar($hex, $f)
    {
        $c = Image::hexToRgb($hex);
        $out = '#';
        foreach ($c as $v) {
            $n = $f >= 0 ? $v + (255 - $v) * $f : $v * (1 + $f);
            $out .= str_pad(dechex((int)max(0, min(255, round($n)))), 2, '0', STR_PAD_LEFT);
        }
        return $out;
    }
}
