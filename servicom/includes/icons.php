<?php
declare(strict_types=1);

/**
 * Biblioteca de iconos SVG en linea (sin dependencias externas).
 * Todos heredan currentColor y son seleccionables desde el panel.
 */
function icon_library(): array
{
    return [
        'inicio'      => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.5V21h13V9.5"/><path d="M9.5 21v-6h5v6"/>',
        'servicios'   => '<rect x="3" y="3" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="2"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="2"/>',
        'portafolio'  => '<rect x="2.5" y="7" width="19" height="13.5" rx="2.5"/><path d="M8.5 7V5.5A2 2 0 0 1 10.5 3.5h3a2 2 0 0 1 2 2V7"/><path d="M2.5 12.5h19"/>',
        'nosotros'    => '<circle cx="9" cy="8" r="3.4"/><path d="M2.8 20.5c0-3.4 2.8-6 6.2-6s6.2 2.6 6.2 6"/><path d="M16.5 5.2a3.4 3.4 0 0 1 0 6.6"/><path d="M18 14.9c2.1.7 3.5 2.6 3.5 5.1"/>',
        'blog'        => '<path d="M4 4.5h13a3 3 0 0 1 3 3v12H7a3 3 0 0 1-3-3z"/><path d="M8 9h8"/><path d="M8 13h8"/><path d="M8 17h5"/>',
        'contacto'    => '<rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>',
        'telefono'    => '<path d="M6.2 3.5h3l1.6 4-2 1.4a12.5 12.5 0 0 0 6.3 6.3l1.4-2 4 1.6v3a2 2 0 0 1-2.2 2A17.5 17.5 0 0 1 4.2 5.7a2 2 0 0 1 2-2.2z"/>',
        'whatsapp'    => '<path d="M3.5 20.5 5 16.4A8.2 8.2 0 1 1 8.2 19.6z"/><path d="M9 9.2c.4 2.4 3.4 5.4 5.8 5.8l1.2-1.6 2 .9v1.5c0 .7-.6 1.2-1.3 1.1A9.6 9.6 0 0 1 8 9.3c-.1-.7.4-1.3 1.1-1.3h1.5l.9 2z"/>',
        'planes'      => '<path d="M3.5 12.5 12 4l8.5 8.5-8.5 8-8.5-8z"/><circle cx="12" cy="10.5" r="1.6"/>',
        'cotizar'     => '<path d="M6 3.5h12v17l-6-3-6 3z"/><path d="M9 8h6"/><path d="M9 11.5h6"/>',
        'web'         => '<circle cx="12" cy="12" r="9"/><path d="M3.2 12h17.6"/><path d="M12 3a15 15 0 0 1 0 18"/><path d="M12 3a15 15 0 0 0 0 18"/>',
        'tienda'      => '<path d="M3.5 8h17l-1.2 11a2 2 0 0 1-2 1.8H6.7a2 2 0 0 1-2-1.8z"/><path d="M8.5 8V6a3.5 3.5 0 0 1 7 0v2"/>',
        'correo'      => '<rect x="2.5" y="5" width="19" height="14" rx="3"/><path d="M6 9.5h5"/><path d="M6 13h8"/><circle cx="17.5" cy="10" r="1.6"/>',
        'hosting'     => '<rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01"/><path d="M7 17h.01"/>',
        'seo'         => '<path d="M3.5 19.5 9 13l3.5 3.5L20 8"/><path d="M15.5 8H20v4.5"/><path d="M3.5 21h17"/>',
        'redes'       => '<circle cx="6" cy="12" r="2.6"/><circle cx="17.5" cy="6" r="2.6"/><circle cx="17.5" cy="18" r="2.6"/><path d="m8.3 10.8 6.9-3.6"/><path d="m8.3 13.2 6.9 3.6"/>',
        'codigo'      => '<path d="m8.5 8-4.5 4 4.5 4"/><path d="m15.5 8 4.5 4-4.5 4"/><path d="m13.5 4-3 16"/>',
        'diseno'      => '<path d="M12 3.2a8.8 8.8 0 1 0 0 17.6c1.4 0 2-.9 2-1.8s-.8-1.6-.8-2.4c0-.9.7-1.5 1.6-1.5h1.6a4.4 4.4 0 0 0 4.4-4.4C20.8 6.3 16.9 3.2 12 3.2z"/><circle cx="7.5" cy="11" r="1.1"/><circle cx="11" cy="7.5" r="1.1"/><circle cx="15.5" cy="8.5" r="1.1"/>',
        'movil'       => '<rect x="6.5" y="2.5" width="11" height="19" rx="3"/><path d="M10.5 18.5h3"/>',
        'velocidad'   => '<path d="M4 18a9 9 0 1 1 16 0"/><path d="m12 14 4-4"/><circle cx="12" cy="14" r="1.6"/>',
        'escudo'      => '<path d="M12 3 5 6v6c0 4.3 2.9 7.7 7 9 4.1-1.3 7-4.7 7-9V6z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
        'cohete'      => '<path d="M13.5 3.5c3.6 0 7 3.4 7 7 0 4.8-6 10-6 10l-3-3 3-3-2-2-3 3-3-3s5.2-6 10-6z" transform="translate(-1 0)"/><circle cx="14" cy="9" r="1.6"/><path d="M6.5 17.5c-1.2 1.2-1.3 3.5-1.3 3.5s2.3-.1 3.5-1.3"/>',
        'estrella'    => '<path d="m12 3.5 2.7 5.6 6.1.9-4.4 4.3 1 6.2-5.4-2.9-5.4 2.9 1-6.2L3.2 10l6.1-.9z"/>',
        'chispa'      => '<path d="M12 3v4"/><path d="M12 17v4"/><path d="M3 12h4"/><path d="M17 12h4"/><path d="m5.6 5.6 2.8 2.8"/><path d="m15.6 15.6 2.8 2.8"/><path d="m18.4 5.6-2.8 2.8"/><path d="m8.4 15.6-2.8 2.8"/>',
        'reloj'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.3l3.4 2"/>',
        'ubicacion'   => '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
        'check'       => '<path d="m4.5 12.5 5 5 10-11"/>',
        'flecha'      => '<path d="M4 12h15.5"/><path d="m13.5 6 6 6-6 6"/>',
        'flecha-arriba' => '<path d="M12 20V4.5"/><path d="m6 10.5 6-6 6 6"/>',
        'mas'         => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'cerrar'      => '<path d="m6 6 12 12"/><path d="m18 6-12 12"/>',
        'menu'        => '<path d="M3.5 7h17"/><path d="M3.5 12h17"/><path d="M3.5 17h17"/>',
        'comilla'     => '<path d="M9.5 6.5c-3 1.2-4.5 3.6-4.5 7v4h6v-6H7c0-2 .8-3.4 2.5-4.2z"/><path d="M19.5 6.5c-3 1.2-4.5 3.6-4.5 7v4h6v-6H17c0-2 .8-3.4 2.5-4.2z"/>',
        'usuarios'    => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20.5c0-3.9 3.1-7 7-7s7 3.1 7 7"/>',
        'grafica'     => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>',
        'engranaje'   => '<circle cx="12" cy="12" r="3.2"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5.2 5.2l2.1 2.1M16.7 16.7l2.1 2.1M18.8 5.2l-2.1 2.1M7.3 16.7l-2.1 2.1"/>',
        'facebook'    => '<path d="M14.5 8.5V6.8c0-.8.4-1.3 1.4-1.3h1.6V2.6h-2.6c-2.7 0-4 1.6-4 4v1.9H8.5v3h2.4V21h3.6v-9.5h2.6l.4-3z"/>',
        'instagram'   => '<rect x="3" y="3" width="18" height="18" rx="5.5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1.1" fill="currentColor" stroke="none"/>',
        'linkedin'    => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 10.5V17"/><path d="M8 7.3v.01"/><path d="M12 17v-3.6a2.4 2.4 0 0 1 4.8 0V17"/><path d="M12 10.5V17"/>',
        'youtube'     => '<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="m10.5 9.5 5 2.5-5 2.5z"/>',
        'tiktok'      => '<path d="M14 3.5v10.4a3.6 3.6 0 1 1-3-3.5"/><path d="M14 6.5a5 5 0 0 0 5 4"/>',
        'x-social'    => '<path d="m4 4 16 16"/><path d="m20 4-16 16"/>',
        'twitter'     => '<path d="M21 5.5c-.7.3-1.4.5-2.2.6a3.7 3.7 0 0 0 1.7-2 7.6 7.6 0 0 1-2.4.9 3.8 3.8 0 0 0-6.5 3.4A10.8 10.8 0 0 1 3.7 4.5a3.8 3.8 0 0 0 1.2 5 3.7 3.7 0 0 1-1.7-.5 3.8 3.8 0 0 0 3 3.7 3.8 3.8 0 0 1-1.7.1 3.8 3.8 0 0 0 3.5 2.6A7.6 7.6 0 0 1 3 17a10.7 10.7 0 0 0 5.8 1.7c7 0 10.8-5.8 10.8-10.8v-.5c.8-.5 1.4-1.2 1.9-2z"/>',
        'reproducir'  => '<circle cx="12" cy="12" r="9"/><path d="m10 8.5 6 3.5-6 3.5z"/>',
        'documento'   => '<path d="M6 3.5h7l5 5V20a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1z"/><path d="M13 3.5v5h5"/>',
        'imagen'      => '<rect x="3" y="4.5" width="18" height="15" rx="3"/><circle cx="8.5" cy="10" r="1.8"/><path d="m4 17 5-4.5 4 3.5 3-2.5 4 3.5"/>',
        'candado'     => '<rect x="4.5" y="10" width="15" height="10.5" rx="2.5"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/>',
        'infinito'    => '<path d="M7 8.5a3.5 3.5 0 1 0 0 7c3.5 0 6-7 9.5-7a3.5 3.5 0 1 1 0 7c-3.5 0-6-7-9.5-7z"/>',
    ];
}

/**
 * Devuelve un icono. Se dibuja una sola vez por página como <symbol> y aquí
 * solo se referencia, lo que reduce el peso del HTML y los nodos del DOM.
 */
function icon(string $name, int $size = 24, string $class = '', float $stroke = 1.6): string
{
    $lib  = icon_library();
    $name = isset($lib[$name]) ? $name : 'chispa';

    return sprintf(
        '<svg class="ico %s" width="%d" height="%d" aria-hidden="true" focusable="false"><use href="#i-%s"></use></svg>',
        e($class),
        $size,
        $size,
        e($name)
    );
}

/** Imprime una sola vez todos los iconos como <symbol> reutilizables. */
function icon_sprite(): string
{
    $out = '<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true"><defs>';
    foreach (icon_library() as $name => $path) {
        $out .= '<symbol id="i-' . $name . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
              . ' stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $path . '</symbol>';
    }
    return $out . '</defs></svg>';
}

/** @return list<string> Nombres disponibles para los selectores del panel. */
function icon_names(): array
{
    return array_keys(icon_library());
}
