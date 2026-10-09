<?php
/** Iconos SVG inline coherentes (trazo 1.8, 24x24). Uso: <?= ico('check') ?> */
if (!function_exists('ico')) {
    function ico(string $n, string $cls = ''): string
    {
        static $m = [
            'check'   => '<path d="M5 12.5l4.2 4.2L19 7"/>',
            'pen'     => '<path d="M12 20h9M16.500 3.500a2.100 2.100 0 013 3L7 19l-4 1 1-4z"/>',
            'eye'     => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
            'card'    => '<rect x="2" y="5" width="20" height="14" rx="2.500"/><path d="M2 10h20M6 15h4"/>',
            'upload'  => '<path d="M12 16V4m0 0L8 8m4-4l4 4M4 20h16"/>',
            'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2.500"/><path d="M3 7.500l9 6 9-6"/>',
            'globe'   => '<circle cx="12" cy="12" r="9.500"/><path d="M2.500 12h19M12 2.500c3 3 3 16 0 19M12 2.500c-3 3-3 16 0 19"/>',
            'shield'  => '<path d="M12 3l8 3v6c0 5-3.500 8-8 9-4.500-1-8-4-8-9V6z"/><path d="M8.500 12l2.500 2.500 4.500-5"/>',
            'bag'     => '<path d="M5 8h14l-1 12H6zM9 8V6.500a3 3 0 016 0V8"/>',
            'chat'    => '<path d="M4 5h16v11H9.500L4 20.500z"/>',
            'pin'     => '<path d="M12 21s7-6 7-11a7 7 0 10-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.500"/>',
            'image'   => '<rect x="3" y="4" width="18" height="16" rx="2.500"/><circle cx="9" cy="10" r="1.600"/><path d="M21 16l-5-5-9 9"/>',
            'layout'  => '<rect x="3" y="4" width="18" height="16" rx="2.500"/><path d="M3 9h18M9 9v11"/>',
            'phone'   => '<rect x="7" y="2.500" width="10" height="19" rx="2.500"/><path d="M11 18.500h2"/>',
            'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'slides'  => '<rect x="2.500" y="5" width="19" height="12" rx="2"/><path d="M8 20.500h8M12 17v3.500"/>',
            'form'    => '<rect x="4" y="3" width="16" height="18" rx="2.500"/><path d="M8 8h8M8 12h8M8 16h4"/>',
            'share'   => '<circle cx="6" cy="12" r="2.800"/><circle cx="18" cy="6" r="2.800"/><circle cx="18" cy="18" r="2.800"/><path d="M8.500 10.700l7-3.400M8.500 13.300l7 3.400"/>',
            'video'   => '<rect x="2" y="6" width="14" height="12" rx="2.500"/><path d="M16 10.500l6-3.500v10l-6-3.500"/>',
            'panel'   => '<rect x="3" y="3" width="7.500" height="10" rx="1.800"/><rect x="13.500" y="3" width="7.500" height="5.500" rx="1.800"/><rect x="13.500" y="11.500" width="7.500" height="9.500" rx="1.800"/><rect x="3" y="16" width="7.500" height="5" rx="1.800"/>',
            'lock'    => '<rect x="5" y="11" width="14" height="10" rx="2.500"/><path d="M8 11V8a4 4 0 018 0v3"/>',
            'map'     => '<path d="M9 4L3 6v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14M15 6v14"/>',
            'box'     => '<path d="M21 8l-9-5-9 5v8l9 5 9-5zM3 8l9 5 9-5M12 13v8"/>',
            'bell'    => '<path d="M6 9a6 6 0 0112 0c0 6 3 7 3 7H3s3-1 3-7M10 20a2 2 0 004 0"/>',
            'search'  => '<circle cx="11" cy="11" r="7"/><path d="M20.500 20.500L16 16"/>',
            'users'   => '<circle cx="9" cy="8" r="3.500"/><path d="M2.500 20c0-4 3-6 6.500-6s6.500 2 6.500 6M16.500 4.800a3.200 3.200 0 010 6.400M19 14.300c1.800.9 2.800 2.800 2.800 5.200"/>',
            'cart'    => '<circle cx="9" cy="20" r="1.500"/><circle cx="18" cy="20" r="1.500"/><path d="M2 3h3l3 12h11l2-8H6"/>',
            'truck'   => '<path d="M2 6h11v10H2zM13 9.500h4.500L21 13v3h-8"/><circle cx="6.500" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
            'bank'    => '<path d="M3 10l9-6 9 6M5.500 10.500v7M9.800 10.500v7M14.200 10.500v7M18.500 10.500v7M3 20.500h18"/>',
            'tag'     => '<path d="M3 12.500V4h8.500L21 13.500 13.500 21z"/><circle cx="7.500" cy="8.500" r="1.200"/>',
            'spark'   => '<path d="M12 3l2.200 6.300L20.500 12l-6.300 2.700L12 21l-2.200-6.300L3.500 12l6.300-2.700z"/>',
            'clock'   => '<circle cx="12" cy="12" r="9.500"/><path d="M12 7v5.500l3.500 2"/>',
            'file'    => '<path d="M6 2.500h8.500L19.500 7.500v14H6z"/><path d="M14 2.500v5.500h5.500M9 13h7M9 17h7"/>',
            'wa'      => '<path d="M12 2.800A9.200 9.200 0 004.100 16.700L2.800 21.200l4.600-1.200A9.200 9.200 0 1012 2.800z"/><path d="M8.800 8.200c-.3.800-.1 1.900.9 3.300 1.300 1.800 2.800 2.900 4.600 3.200.9.100 1.700-.4 1.900-1.100l-1.900-1-1 .9c-1-.5-1.900-1.400-2.500-2.500l.8-.9-1-2z"/>',
            'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'copy'    => '<rect x="9" y="9" width="11.500" height="11.500" rx="2.500"/><path d="M5 15V6.500A2.500 2.500 0 017.500 4H15"/>',
            'plus'    => '<path d="M12 5v14M5 12h14"/>',
            'trash'   => '<path d="M4 7h16M10 3.500h4M6.500 7l1 13h9l1-13M10 11v6M14 11v6"/>',
            'up'      => '<path d="M6 15l6-6 6 6"/>',
            'down'    => '<path d="M6 9l6 6 6-6"/>',
            'x'       => '<path d="M6 6l12 12M18 6L6 18"/>',
            'alert'   => '<path d="M12 3.500L2.500 20h19z"/><path d="M12 10v4.500M12 17.500v.1"/>',
            'refresh' => '<path d="M20 11a8 8 0 10-2.300 6M20 4v7h-7"/>',
            'scale'   => '<path d="M12 3v18M6 21h12M5 7l7-2 7 2M5 7l-2.500 6a3.500 3.500 0 005 0zM19 7l-2.500 6a3.500 3.500 0 005 0z"/>',
            'heart'   => '<path d="M12 20.500s-8-4.800-8-10.500A4.500 4.500 0 0112 7.500 4.500 4.500 0 0120 10c0 5.700-8 10.500-8 10.500z"/>',
            'wrench'  => '<path d="M14.500 6.500a4 4 0 005 5l-9 9a2.100 2.100 0 01-3-3l9-9a4 4 0 00-2-2zM15 3.500a4 4 0 00-1 4"/>',
            'shirt'   => '<path d="M8 3.500L3 6l2 4 2-1v12h10V9l2 1 2-4-5-2.500a4 4 0 01-8 0z"/>',
            'fork'    => '<path d="M7 3v8M4 3v6a3 3 0 006 0V3M7 11v10M17 21V3c-2.500 1.500-3.500 4.500-3.500 8H17"/>',
            'calc'    => '<rect x="5" y="2.500" width="14" height="19" rx="2.500"/><path d="M8.500 7h7M9 12h.1M12 12h.1M15 12h.1M9 16h.1M12 16h.1M15 16h.1"/>',
            'ship'    => '<path d="M3 17l2 3h14l2-3zM6 17V10h12v7M9 10V6h6v4M12 3v3"/>',
            'dots'    => '<path d="M5 12h.1M12 12h.1M19 12h.1"/>',
        ];
        $d = $m[$n] ?? $m['dots'];
        return '<svg class="ic' . ($cls !== '' ? ' ' . htmlspecialchars($cls, ENT_QUOTES) : '') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
    }
}
if (!function_exists('money_q')) {
    function money_q($n): string { return 'Q' . number_format((float)$n, 0, '.', ','); }
}
if (!function_exists('wa_link')) {
    function wa_link(string $num, string $msg = ''): string
    {
        $d = preg_replace('/\D+/', '', $num);
        return 'https://wa.me/' . $d . ($msg !== '' ? '?text=' . rawurlencode($msg) : '');
    }
}
