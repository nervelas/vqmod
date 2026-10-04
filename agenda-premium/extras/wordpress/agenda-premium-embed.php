<?php
/**
 * Plugin Name: Agenda Premium · Reservas en tu sitio
 * Description: Inserta tu página de reservas de Agenda Premium con un shortcode: [agenda_premium url="https://tudominio.com/agenda" evento="mi-evento" modo="inline" texto="Reservar cita"].
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Agenda Premium
 * License: GPL-2.0-or-later
 * Text Domain: agenda-premium-embed
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode [agenda_premium url="" evento="" modo="inline|popup|boton" texto="" color="" anfitrion=""]
 * Emite el script embed.js del sistema con atributos data-* escapados.
 */
function ap_embed_shortcode($atts)
{
    $a = shortcode_atts(
        array(
            'url'       => '',
            'evento'    => '',
            'modo'      => 'inline',
            'texto'     => 'Reservar cita',
            'color'     => '',
            'anfitrion' => '',
        ),
        $atts,
        'agenda_premium'
    );

    $url = trim((string) $a['url']);
    if ($url === '' || stripos($url, 'https://') !== 0 && stripos($url, 'http://') !== 0) {
        return ap_embed_error('Falta el atributo url con la dirección de tu sistema de reservas (por ejemplo, https://tudominio.com/agenda).');
    }
    $slug = strtolower(trim((string) $a['evento']));
    if (!preg_match('/^[a-z0-9-]{1,80}$/', $slug)) {
        return ap_embed_error('Falta el atributo evento con el identificador del evento (por ejemplo, evento="consulta-general").');
    }

    // "boton" se muestra como ventana emergente abierta desde un botón; "flotante" deja el botón fijo en la esquina.
    $map  = array('inline' => 'inline', 'popup' => 'popup', 'boton' => 'popup', 'flotante' => 'float', 'float' => 'float');
    $modo = isset($map[strtolower((string) $a['modo'])]) ? $map[strtolower((string) $a['modo'])] : 'inline';

    $src = rtrim($url, '/') . '/assets/js/embed.js';
    static $n = 0;
    $n++;
    $target = 'ap-embed-' . $n;

    $attrs  = ' src="' . esc_url($src) . '"';
    $attrs .= ' data-event="' . esc_attr($slug) . '"';
    $attrs .= ' data-mode="' . esc_attr($modo) . '"';
    $attrs .= ' data-label="' . esc_attr($a['texto']) . '"';
    if (preg_match('/^#?[0-9A-Fa-f]{6}$/', (string) $a['color'])) {
        $attrs .= ' data-color="#' . esc_attr(ltrim((string) $a['color'], '#')) . '"';
    }
    if (preg_match('/^[A-Za-z0-9-]{1,80}$/', (string) $a['anfitrion'])) {
        $attrs .= ' data-host="' . esc_attr($a['anfitrion']) . '"';
    }
    if ($modo === 'inline') {
        $attrs .= ' data-target="#' . esc_attr($target) . '"';
    }

    $html = '';
    if ($modo === 'inline') {
        $html .= '<div id="' . esc_attr($target) . '" class="agenda-premium-embed"></div>';
    }
    $html .= '<script' . $attrs . ' defer></script>';
    return $html;
}

function ap_embed_error($msg)
{
    if (function_exists('current_user_can') && current_user_can('edit_posts')) {
        return '<p class="agenda-premium-error"><strong>Agenda Premium:</strong> ' . esc_html($msg) . '</p>';
    }
    return '';
}

add_shortcode('agenda_premium', 'ap_embed_shortcode');
