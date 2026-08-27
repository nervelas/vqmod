<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Iconografía SVG en línea (trazo 1.75, 24x24), estilo Lucide.
 * Sin peticiones externas: cada icono se imprime dentro del HTML.
 */
final class Icono
{
    private const TRAZOS = [
        'inicio'        => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>',
        'panel'         => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'casa'          => '<path d="M4 21V9l8-6 8 6v12"/><path d="M9 21v-6h6v6"/>',
        'edificio'      => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 8h1M14 8h1M9 12h1M14 12h1M10 21v-4h4v4"/>',
        'capas'         => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>',
        'usuarios'      => '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 5.2a3.2 3.2 0 0 1 0 6.1"/><path d="M18 14.4a6.5 6.5 0 0 1 3.5 5.6"/>',
        'usuario'       => '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>',
        'billetera'     => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H18a2 2 0 0 1 2 2v1"/><rect x="3" y="7.5" width="18" height="12" rx="2.5"/><circle cx="16.5" cy="13.5" r="1.2"/>',
        'recibo'        => '<path d="M6 2.5h12v19l-2.5-1.6L13 21.5l-2.5-1.6L8 21.5 6 20.2Z"/><path d="M9.5 8h5M9.5 12h5"/>',
        'tarjeta'       => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19"/><path d="M6.5 15h3"/>',
        'moneda'        => '<circle cx="12" cy="12" r="9"/><path d="M15 9.2A3.2 3.2 0 0 0 12 7.5c-1.7 0-3 .9-3 2.2 0 3 6 1.4 6 4.4 0 1.4-1.4 2.4-3 2.4a3.3 3.3 0 0 1-3-1.8"/><path d="M12 5.8v12.4"/>',
        'grafica'       => '<path d="M3 3v17a1 1 0 0 0 1 1h17"/><path d="m7 15 3.5-4 3 2.5L20 7"/>',
        'barras'        => '<path d="M3 21h18"/><rect x="5" y="11" width="3.5" height="7" rx="1"/><rect x="10.5" y="6" width="3.5" height="12" rx="1"/><rect x="16" y="14" width="3.5" height="4" rx="1"/>',
        'pastel'        => '<path d="M12 3a9 9 0 1 0 9 9h-9Z"/><path d="M15.5 3.6A9 9 0 0 1 20.4 8.5H15.5Z"/>',
        'alerta'        => '<path d="M10.3 3.9 2.6 17.4A2 2 0 0 0 4.3 20.4h15.4a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9.5v4M12 17h.01"/>',
        'escudo'        => '<path d="M12 2.5 4.5 5.7v5.6c0 4.6 3.1 8.8 7.5 10.2 4.4-1.4 7.5-5.6 7.5-10.2V5.7Z"/><path d="m9 12 2.2 2.2L15.2 10"/>',
        'sirena'        => '<path d="M6 17a6 6 0 0 1 12 0"/><rect x="3.5" y="17" width="17" height="4" rx="1.5"/><path d="M12 5V2.5M5.5 8 3.7 6.2M18.5 8l1.8-1.8"/>',
        'puerta'        => '<path d="M4 21h16"/><path d="M6 21V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17"/><circle cx="14.5" cy="12.5" r="1"/>',
        'calendario'    => '<rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'reloj'         => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.2 2"/>',
        'megafono'      => '<path d="M4 10v4a2 2 0 0 0 2 2h1l8 4.5V3.5L7 8H6a2 2 0 0 0-2 2Z"/><path d="M18.5 9a4 4 0 0 1 0 6"/>',
        'campana'       => '<path d="M18 8.5a6 6 0 1 0-12 0c0 5-2 6.5-2 6.5h16s-2-1.5-2-6.5Z"/><path d="M10.3 19a2 2 0 0 0 3.4 0"/>',
        'ajustes'       => '<circle cx="12" cy="12" r="3.2"/><path d="M19.4 14.5a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5v.2a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1h.2a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z"/>',
        'salir'         => '<path d="M9 21H5.5A1.5 1.5 0 0 1 4 19.5v-15A1.5 1.5 0 0 1 5.5 3H9"/><path d="m16 16 4-4-4-4"/><path d="M20 12H9.5"/>',
        'entrar'        => '<path d="M15 3h3.5A1.5 1.5 0 0 1 20 4.5v15a1.5 1.5 0 0 1-1.5 1.5H15"/><path d="m10 16-4-4 4-4"/><path d="M6 12h10"/>',
        'menu'          => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'buscar'        => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>',
        'mas'           => '<path d="M12 5v14M5 12h14"/>',
        'editar'        => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7.5 18.5 3 20l1.5-4.5Z"/>',
        'basura'        => '<path d="M3.5 6h17M9 6V4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5V6"/><path d="M6 6v13.5A1.5 1.5 0 0 0 7.5 21h9a1.5 1.5 0 0 0 1.5-1.5V6"/><path d="M10 11v5M14 11v5"/>',
        'check'         => '<path d="m4.5 12.5 5 5 10-11"/>',
        'checkCirculo'  => '<circle cx="12" cy="12" r="9"/><path d="m8 12.2 2.6 2.6L16 9.4"/>',
        'equis'         => '<path d="M6 6l12 12M18 6 6 18"/>',
        'equisCirculo'  => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
        'descargar'     => '<path d="M12 3v12"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M4 20h16"/>',
        'subir'         => '<path d="M12 20V8"/><path d="m7.5 12.5 4.5-4.5 4.5 4.5"/><path d="M4 4h16"/>',
        'archivo'       => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/>',
        'carpeta'       => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V18a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
        'imagen'        => '<rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="8.5" cy="9.5" r="1.6"/><path d="m4 18 5-5 3.5 3.5L16 13l4 4"/>',
        'camara'        => '<path d="M4 8h3l1.5-2.5h7L17 8h3a1.5 1.5 0 0 1 1.5 1.5v9A1.5 1.5 0 0 1 20 20H4a1.5 1.5 0 0 1-1.5-1.5v-9A1.5 1.5 0 0 1 4 8Z"/><circle cx="12" cy="13.5" r="3.5"/>',
        'qr'            => '<rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><path d="M14 14h3v3h-3zM20 14h1M14 20h3M20 17v4"/>',
        'escanear'      => '<path d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2"/><path d="M4 12h16"/>',
        'correo'        => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m4 7 8 6 8-6"/>',
        'telefono'      => '<path d="M6.5 3.5h3l1.5 4-2 1.5a12 12 0 0 0 6 6l1.5-2 4 1.5v3a1.5 1.5 0 0 1-1.7 1.5C11 18.6 5.4 13 4.9 5.2A1.5 1.5 0 0 1 6.5 3.5Z"/>',
        'chat'          => '<path d="M20 12a7.5 7.5 0 0 1-11 6.6L4 20l1.4-4.6A7.5 7.5 0 1 1 20 12Z"/>',
        'carro'         => '<path d="M4.5 16.5h15"/><path d="m5 16.5-1-5 2-4.5h12l2 4.5-1 5"/><circle cx="7.5" cy="17.5" r="1.8"/><circle cx="16.5" cy="17.5" r="1.8"/><path d="M4 11.5h16"/>',
        'mascota'       => '<circle cx="7" cy="9" r="2"/><circle cx="17" cy="9" r="2"/><circle cx="10" cy="5.5" r="1.8"/><circle cx="14" cy="5.5" r="1.8"/><path d="M12 12c-3 0-5 2.2-5 4.6 0 2 1.7 3.4 3.4 2.8a4.7 4.7 0 0 1 3.2 0c1.7.6 3.4-.8 3.4-2.8 0-2.4-2-4.6-5-4.6Z"/>',
        'maletin'       => '<rect x="3" y="7" width="18" height="13" rx="2.5"/><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7"/><path d="M3 12h18"/>',
        'mapa'          => '<path d="m9 4 6 2.5 5-2v15l-5 2-6-2.5-5 2v-15Z"/><path d="M9 4v15M15 6.5v15"/>',
        'llave'         => '<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8 2 2-1.5 1.5L21 9l-2 2-1.5-1.5L16 11"/>',
        'candado'       => '<rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/><path d="M12 14.5v2.5"/>',
        'ojo'           => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
        'filtro'        => '<path d="M3 5h18l-7 8v6l-4 2v-8Z"/>',
        'lista'         => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'refrescar'     => '<path d="M20 11a8 8 0 1 0-.7 4.3"/><path d="M20 4v7h-7"/>',
        'imprimir'      => '<path d="M6.5 9V3.5h11V9"/><rect x="3.5" y="9" width="17" height="7" rx="2"/><path d="M6.5 14h11v6.5h-11Z"/>',
        'guardar'       => '<path d="M5 3h11l3 3v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M8 3v6h7V3M8 21v-6h8v6"/>',
        'enviar'        => '<path d="m21 3-9.5 9.5"/><path d="M21 3 14.5 21l-3-8-8-3Z"/>',
        'sol'           => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M2.5 12h2M19.5 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/>',
        'luna'          => '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z"/>',
        'chevronI'      => '<path d="m14.5 5-7 7 7 7"/>',
        'chevronD'      => '<path d="m9.5 5 7 7-7 7"/>',
        'chevronAb'     => '<path d="m5 9.5 7 7 7-7"/>',
        'flechaIzq'     => '<path d="M20 12H4"/><path d="m10 6-6 6 6 6"/>',
        'flechaDer'     => '<path d="M4 12h16"/><path d="m14 6 6 6-6 6"/>',
        'subeTendencia' => '<path d="m3 16 6-6 4 4 8-8"/><path d="M15 6h6v6"/>',
        'bajaTendencia' => '<path d="m3 8 6 6 4-4 8 8"/><path d="M15 18h6v-6"/>',
        'gota'          => '<path d="M12 3s6 6.4 6 10.4A6 6 0 0 1 6 13.4C6 9.4 12 3 12 3Z"/>',
        'rayo'          => '<path d="M13 2 4 14h7l-1 8 9-12h-7Z"/>',
        'arbol'         => '<path d="M12 2 6.5 11h3L5 18h14l-4.5-7h3Z"/><path d="M12 18v4"/>',
        'llave_inglesa' => '<path d="M15.5 3.5a5 5 0 0 0-4.4 7.3L3.5 18.4a2 2 0 0 0 2.8 2.8l7.6-7.6a5 5 0 0 0 6.2-6.7L17 10l-3-3 3.1-3.1a5 5 0 0 0-1.6-.4Z"/>',
        'voto'          => '<path d="m9 12.5 2.2 2.2L15.5 10"/><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M2.5 20h19"/>',
        'libro'         => '<path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H19v16H5.5A1.5 1.5 0 0 0 4 20.5Z"/><path d="M19 19v2H5.5"/>',
        'brillo'        => '<path d="m12 3 1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8Z"/><path d="M18.5 15.5 19 17l1.5.5L19 18l-.5 1.5L18 18l-1.5-.5L18 17Z"/>',
        'info'          => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'ayuda'         => '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.3a2.5 2.5 0 0 1 4.8.8c0 1.7-2.4 2.2-2.4 3.9M12 17h.01"/>',
        'cuadricula'    => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/>',
        'estrella'      => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9Z"/>',
        'salvavidas'    => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.6"/><path d="m5.6 5.6 3.8 3.8M14.6 14.6l3.8 3.8M18.4 5.6l-3.8 3.8M9.4 14.6l-3.8 3.8"/>',
        'pin'           => '<path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>',
    ];

    public static function svg(string $nombre, int $tam = 20, string $clase = ''): string
    {
        $trazo = self::TRAZOS[$nombre] ?? self::TRAZOS['info'];
        return '<svg class="ico ' . e($clase) . '" width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" '
             . 'fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" '
             . 'stroke-linejoin="round" aria-hidden="true" focusable="false">' . $trazo . '</svg>';
    }

    public static function existe(string $nombre): bool
    {
        return isset(self::TRAZOS[$nombre]);
    }
}
