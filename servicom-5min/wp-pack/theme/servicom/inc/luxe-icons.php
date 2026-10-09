<?php
/**
 * Servicom LUXE — set de iconos SVG en línea (trazo fino 1.5, viewBox 24).
 * Dibujados a mano. Clase "a" = detalle de acento (duotono vía CSS):
 *   .sc-ic .a { stroke: var(--lx-accent); }
 * API: sc_icon(), sc_icon_keys(), sc_icon_for(), sc_icon_label().
 * PHP >= 8.0. No requiere WordPress (usa esc_attr si existe).
 */
/** Escape seguro con o sin WordPress. */
function sc_icon_esc(string $s): string
{
	if (function_exists('esc_attr')) {
		return (string) esc_attr($s);
	}
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * Definiciones: clave => [trazo principal, acento, relleno(bool)].
 * Si el 3.º elemento es true, el principal va relleno (redes).
 */
function sc_icon_defs(): array
{
	static $d = null;
	if ($d !== null) {
		return $d;
	}
	$d = array(
		'star' => array('<path d="M12 3.5l2.6 5.3 5.9.85-4.25 4.15 1 5.85L12 16.9l-5.25 2.75 1-5.85L3.5 9.65l5.9-.85z"/>', '<circle class="a" cx="12" cy="12.3" r=".5"/>'),
		'shield' => array('<path d="M12 3l7 2.8v5.7c0 4.3-3 7.6-7 9.5-4-1.9-7-5.2-7-9.5V5.8z"/>', '<path class="a" d="M9 12l2.2 2.2L15.200 10"/>'),
		'check' => array('<circle cx="12" cy="12" r="8.5"/>', '<path class="a" d="M8.500 12.300l2.500 2.500 4.500-5.100"/>'),
		'heart' => array('<path d="M12 20s-7.500-4.600-7.500-10.200A4.300 4.300 0 0 1 12 7.300a4.300 4.300 0 0 1 7.500 2.500C19.500 15.400 12 20 12 20z"/>', '<path class="a" d="M7.500 10a2.500 2.500 0 0 1 1.800-2"/>'),
		'clock' => array('<circle cx="12" cy="12" r="8.500"/>', '<path class="a" d="M12 7.500V12l3 1.800"/>'),
		'phone' => array('<path d="M6 3.500h3l1.500 4-2 1.300a10 10 0 0 0 5.700 5.700l1.300-2 4 1.500v3a2 2 0 0 1-2 2A15 15 0 0 1 4 5.500a2 2 0 0 1 2-2z"/>', '<path class="a" d="M14 4.500a5.500 5.500 0 0 1 5.500 5.500"/>'),
		'mail' => array('<rect x="3" y="5.500" width="18" height="13" rx="2.500"/>', '<path class="a" d="M3.500 8l8.500 5.500L20.500 8"/>'),
		'pin' => array('<path d="M12 21s-6.500-5.600-6.500-11a6.500 6.500 0 0 1 13 0c0 5.400-6.500 11-6.500 11z"/>', '<circle class="a" cx="12" cy="10" r="2.300"/>'),
		'calendar' => array('<rect x="4" y="5.500" width="16" height="14.500" rx="2.500"/><path d="M8 3.500v4M16 3.500v4"/>', '<path class="a" d="M4 10h16"/><circle class="a" cx="8.500" cy="14.500" r=".4"/><circle class="a" cx="12" cy="14.500" r=".4"/><circle class="a" cx="15.500" cy="14.500" r=".4"/>'),
		'users' => array('<circle cx="9" cy="8.500" r="3"/><path d="M3.500 19.500c0-3.300 2.500-5.500 5.500-5.500s5.500 2.200 5.500 5.500"/>', '<circle class="a" cx="17" cy="9.500" r="2.200"/><path class="a" d="M16.500 14.300c2.400.2 4 2 4 5.200"/>'),
		'user' => array('<circle cx="12" cy="8.500" r="3.500"/><path d="M5 20c0-3.900 3.100-6.500 7-6.500s7 2.600 7 6.500"/>', '<path class="a" d="M10.300 16.300L12 18l1.700-1.700"/>'),
		'handshake' => array('<rect x="2.500" y="8" width="3" height="8.500" rx=".8"/><rect x="18.500" y="8" width="3" height="8.500" rx=".8"/><path d="M5.500 9.500L9.500 8H13l2.500 2.500 3-1M5.500 15l3.500 2.500 2.500 1 2.500-1 4-2.500"/>', '<path class="a" d="M9.500 11.500l3 2.500 2.500-2.500M13 10.500l-1.500 1.500"/>'),
		'award' => array('<circle cx="12" cy="9.500" r="5.500"/><path d="M8.500 14.200L7.500 21 12 18.800l4.500 2.200-1-6.800"/>', '<circle class="a" cx="12" cy="9.500" r="2"/>'),
		'crown' => array('<path d="M3.500 17.500L5 8l4.500 4L12 6l2.500 6L19 8l1.500 9.500z"/>', '<path class="a" d="M5 20.500h14"/>'),
		'gem' => array('<path d="M6.500 4h11l4 5.500L12 20.500 2.500 9.500z"/>', '<path class="a" d="M2.500 9.500h19M9 4l3 5.500L15 4"/>'),
		'sparkles' => array('<path d="M10 4c.6 4.200 2 5.600 6.500 6.500-4.500.9-5.900 2.300-6.500 6.500-.6-4.200-2-5.600-6.500-6.500C8 9.600 9.400 8.200 10 4z"/>', '<path class="a" d="M18.500 15.500c.3 1.700.8 2.200 2.500 2.500-1.700.3-2.200.8-2.500 2.500-.3-1.700-.8-2.200-2.500-2.500 1.700-.3 2.200-.8 2.500-2.500zM18 3.500v3M16.500 5h3"/>'),
		'lightbulb' => array('<path d="M12 3.500a6 6 0 0 0-3.500 10.900c.6.500 1 1.200 1 2v.1h5v-.1c0-.8.4-1.500 1-2A6 6 0 0 0 12 3.500z"/><path d="M9.500 19h5"/>', '<path class="a" d="M10.500 12.500L12 10.500l1.500 2M10.500 21h3"/>'),
		'target' => array('<circle cx="12" cy="12" r="8.500"/><circle cx="12" cy="12" r="4.500"/>', '<path class="a" d="M12 12l7.500-7.500M16.500 4.500H19.500V7.500"/>'),
		'rocket' => array('<path d="M12 3c3.500 2 5 5.500 5 9.500L15 15H9l-2-2.500C7 8.500 8.500 5 12 3z"/><circle cx="12" cy="9.500" r="1.800"/><path d="M7 12.500L4.500 15l.5 2.500L9 15M17 12.500l2.500 2.500-.5 2.500L15 15"/>', '<path class="a" d="M10.500 18c0 1.500.6 2.500 1.500 3.500.9-1 1.500-2 1.500-3.500"/>'),
		'chat' => array('<path d="M5.500 4.500h13a2 2 0 0 1 2 2V14a2 2 0 0 1-2 2H11l-4.500 3.500V16h-1a2 2 0 0 1-2-2V6.500a2 2 0 0 1 2-2z"/>', '<circle class="a" cx="8.500" cy="10.300" r=".4"/><circle class="a" cx="12" cy="10.300" r=".4"/><circle class="a" cx="15.500" cy="10.300" r=".4"/>'),
		'globe' => array('<circle cx="12" cy="12" r="8.500"/><path d="M12 3.500c-2.400 2.300-3.600 5.100-3.600 8.500s1.200 6.200 3.600 8.500c2.400-2.300 3.600-5.100 3.600-8.500S14.400 5.800 12 3.500z"/>', '<path class="a" d="M3.500 12h17"/>'),
		'link' => array('<path d="M10.500 13.500a3.500 3.500 0 0 0 5 0l3-3a3.500 3.500 0 0 0-5-5l-.8.8M13.500 10.500a3.500 3.500 0 0 0-5 0l-3 3a3.500 3.500 0 0 0 5 5l.8-.8"/>', '<path class="a" d="M10 14l4-4"/>'),
		'lock' => array('<rect x="5" y="10.500" width="14" height="10" rx="2.500"/><path d="M8 10.500V8a4 4 0 0 1 8 0v2.500"/>', '<path class="a" d="M12 14.500v2.500"/>'),
		'key' => array('<circle cx="8" cy="15.500" r="4"/><path d="M11 12.500L19.500 4M16.500 7l2.500 2.500"/>', '<circle class="a" cx="8" cy="15.500" r=".6"/>'),
		'home' => array('<path d="M3.500 11L12 4l8.500 7M5.500 9.500V19a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1V9.500"/>', '<path class="a" d="M10 20v-5h4v5"/>'),
		'building' => array('<path d="M5 20.500V5a1.500 1.500 0 0 1 1.500-1.500h7A1.500 1.500 0 0 1 15 5v15.500M15 9.500h3a1.500 1.500 0 0 1 1.500 1.500v9.500M3.500 20.500h17"/>', '<path class="a" d="M8.500 7.500h3M8.500 11h3M8.500 14.500h3"/>'),
		'briefcase' => array('<rect x="3.500" y="7.500" width="17" height="12" rx="2.500"/><path d="M9 7.500V6a1.500 1.500 0 0 1 1.500-1.500h3A1.500 1.500 0 0 1 15 6v1.500"/>', '<path class="a" d="M3.500 13h17M10.500 13v1.500h3V13"/>'),
		'document' => array('<path d="M7 3.500h7l4.500 4.500v11a1.500 1.500 0 0 1-1.500 1.500H7A1.500 1.500 0 0 1 5.500 19V5A1.500 1.500 0 0 1 7 3.500zM14 3.500V8h4.500"/>', '<path class="a" d="M8.500 13h7M8.500 16.500h4"/>'),
		'pen' => array('<path d="M5 19l.8-3.800L16.500 4.500a2.100 2.100 0 0 1 3 3L8.800 18.200z"/>', '<path class="a" d="M14.500 6.500l3 3M12 20.500h8"/>'),
		'book' => array('<path d="M4 5.500c2.500-1 5-1 8 .8v13.700c-3-1.800-5.500-1.800-8-.8zM20 5.500c-2.500-1-5-1-8 .8v13.700c3-1.800 5.500-1.800 8-.8z"/>', '<path class="a" d="M6.500 9c1.200-.3 2.300-.2 3.500.3"/>'),
		'graduation' => array('<path d="M2.500 9.500L12 5l9.500 4.500L12 14zM6.500 11.500V16c1.500 1.800 3.500 2.500 5.500 2.500s4-.7 5.500-2.500v-4.500"/>', '<path class="a" d="M21.500 9.500V15"/>'),
		'chart' => array('<path d="M4 20.500h16"/><rect x="5.500" y="13" width="3" height="5.500" rx=".8"/><rect x="10.500" y="10" width="3" height="8.500" rx=".8"/><rect x="15.500" y="7" width="3" height="11.500" rx=".8"/>', '<path class="a" d="M5 9.500l4.500-3.500 3.500 2 5.500-4"/>'),
		'trend' => array('<path d="M3.500 20.500h17M3.500 16.500l6-6 3.500 3.500 7.500-8"/>', '<path class="a" d="M15.500 6h5v5"/>'),
		'coins' => array('<ellipse cx="12" cy="6.500" rx="7" ry="3"/><path d="M5 6.500v5c0 1.700 3.100 3 7 3s7-1.300 7-3v-5M5 11.500v5c0 1.700 3.100 3 7 3s7-1.300 7-3v-5"/>', '<path class="a" d="M5 11.500c0 1.700 3.100 3 7 3s7-1.300 7-3"/>'),
		'wallet' => array('<path d="M4 7.500a2 2 0 0 1 2-2h11.500v3M4 7.500v10a2 2 0 0 0 2 2h12.500a1.500 1.500 0 0 0 1.500-1.500V10a1.500 1.500 0 0 0-1.500-1.500H6A2 2 0 0 1 4 7.500z"/>', '<circle class="a" cx="16.500" cy="14" r=".8"/>'),
		'card' => array('<rect x="3" y="5.500" width="18" height="13" rx="2.500"/><path d="M3 10h18"/>', '<path class="a" d="M6.500 15h4"/>'),
		'receipt' => array('<path d="M6 3.500h12v17l-3-1.800-3 1.800-3-1.800-3 1.800z"/>', '<path class="a" d="M9 8.500h6M9 12h3.500"/>'),
		'calculator' => array('<rect x="5.500" y="3.500" width="13" height="17" rx="2.500"/><rect x="8.500" y="6.500" width="7" height="3" rx=".6"/>', '<circle class="a" cx="9" cy="13.500" r=".3"/><circle class="a" cx="12" cy="13.500" r=".3"/><circle class="a" cx="15" cy="13.500" r=".3"/><circle class="a" cx="9" cy="17" r=".3"/><circle class="a" cx="12" cy="17" r=".3"/><circle class="a" cx="15" cy="17" r=".3"/>'),
		'percent' => array('<circle cx="7.500" cy="7.500" r="2.300"/><circle cx="16.500" cy="16.500" r="2.300"/>', '<path class="a" d="M18.500 5.500l-13 13"/>'),
		'tag' => array('<path d="M3.500 12.500v-7a2 2 0 0 1 2-2h7l8 8a2 2 0 0 1 0 2.800l-6.700 6.700a2 2 0 0 1-2.800 0z"/>', '<circle class="a" cx="8.500" cy="8.500" r="1.200"/>'),
		'gift' => array('<rect x="3.500" y="9" width="17" height="4" rx="1"/><path d="M5 13v6a1.500 1.500 0 0 0 1.500 1.500h11A1.500 1.500 0 0 0 19 19v-6M12 9c-3 0-5-1-5-2.500S8.500 4 10 5c1 .7 2 2.500 2 4zm0 0c3 0 5-1 5-2.500S15.500 4 14 5c-1 .7-2 2.500-2 4z"/>', '<path class="a" d="M12 9v11.500"/>'),
		'bag' => array('<path d="M5.500 8h13l1 12.500h-15z"/>', '<path class="a" d="M9 10.500V7a3 3 0 0 1 6 0v3.500"/>'),
		'cart' => array('<path d="M3 4.500h2.500l2.200 10.500h10.300l2-7.500H6.300"/><circle cx="9.500" cy="19.500" r="1.300"/><circle cx="17" cy="19.500" r="1.300"/>', '<path class="a" d="M8.500 11.500h10"/>'),
		'truck' => array('<path d="M3 6.500A1.500 1.500 0 0 1 4.500 5H13v11.500H3zM13 9h4l3.500 3.500v4H13"/><circle cx="7" cy="17.500" r="2"/><circle cx="17" cy="17.500" r="2"/>', '<path class="a" d="M5.500 9h4.500"/>'),
		'box' => array('<path d="M12 3.500L20 8v8.500L12 21l-8-4.500V8z"/>', '<path class="a" d="M4 8l8 4.500L20 8M12 12.500V21"/>'),
		'plane' => array('<path d="M20.500 3.500L3.500 10l6.500 2.500 2.500 6.500z"/>', '<path class="a" d="M10 12.500l5.500-5"/>'),
		'ship' => array('<path d="M3 15.500h18l-2.500 4.500h-13zM12 3.500v12"/>', '<path class="a" d="M12 5l5 8H12"/>'),
		'route' => array('<circle cx="6" cy="18" r="2.200"/><circle cx="18" cy="6" r="2.200"/>', '<path class="a" d="M8.200 18H14a3.500 3.500 0 0 0 0-7h-4a3.500 3.500 0 0 1 0-7h5.800"/>'),
		'map' => array('<path d="M3.500 6.500L9 4.500l6 2 5.500-2v13L15 19.500l-6-2-5.500 2z"/>', '<path class="a" d="M9 4.500v13M15 6.500v13"/>'),
		'compass' => array('<circle cx="12" cy="12" r="8.500"/><path d="M12 3.500v1.800M12 18.700v1.800M3.500 12h1.800M18.700 12h1.800"/>', '<path class="a" d="M15.500 8.500l-2 5-5 2 2-5z"/>'),
		'camera' => array('<path d="M4 8h3l1.500-2.500h7L17 8h3a1 1 0 0 1 1 1v9.500a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.500" r="3.500"/>', '<circle class="a" cx="17.500" cy="10.800" r=".4"/>'),
		'image' => array('<rect x="3.500" y="4.500" width="17" height="15" rx="2.500"/><path d="M4 17l4.500-4.500 4 4 3-3 4.500 4"/>', '<circle class="a" cx="15.500" cy="9" r="1.500"/>'),
		'video' => array('<rect x="3" y="6" width="13" height="12" rx="2.500"/><path d="M16 10.500l5-3v9l-5-3"/>', '<circle class="a" cx="6.800" cy="9.500" r=".4"/>'),
		'music' => array('<path d="M9 17.500v-12l10-2v12"/><circle cx="6.500" cy="17.500" r="2.500"/><circle cx="16.500" cy="15.500" r="2.500"/>', '<path class="a" d="M9 9.500l10-2"/>'),
		'play' => array('<circle cx="12" cy="12" r="8.500"/>', '<path class="a" d="M10 8.500v7l5.500-3.500z"/>'),
		'headset' => array('<path d="M4.500 14v-2a7.500 7.500 0 0 1 15 0v2"/><rect x="3.500" y="13.500" width="4" height="6" rx="1.500"/><rect x="16.500" y="13.500" width="4" height="6" rx="1.500"/>', '<path class="a" d="M18.500 19.500a3 3 0 0 1-3 2h-2.500"/>'),
		'wrench' => array('<path d="M15.500 3.500a5 5 0 0 0-4.700 6.700l-6.500 6.500a2 2 0 0 0 3 3l6.500-6.500a5 5 0 0 0 6.700-4.700 5 5 0 0 0-.5-1.500l-3 3-2.800-.7-.7-2.800 3-3a5 5 0 0 0-1.500-.5z"/>', '<circle class="a" cx="5.800" cy="18.200" r=".5"/>'),
		'gear' => array('<circle cx="12" cy="12" r="6.200"/><circle cx="12" cy="12" r="2.600"/>', '<path class="a" d="M12 3.500v2.300M12 18.200v2.300M3.500 12h2.300M18.200 12h2.300M6 6l1.600 1.600M16.400 16.400L18 18M18 6l-1.600 1.600M7.600 16.400L6 18"/>'),
		'hammer' => array('<g transform="rotate(40 12 12)"><rect x="5" y="3.500" width="14" height="4" rx="1"/><rect x="10.500" y="7.500" width="3" height="13" rx="1.200"/></g>', '<path class="a" d="M10.500 12.500l3 3.200"/>'),
		'bolt' => array('<path d="M13.500 3L5.500 13.500H12l-1.500 7.500 8-10.500H12z"/>', '<path class="a" d="M18 4v3M16.500 5.500h3"/>'),
		'car' => array('<path d="M3.500 15.500V13l2-4.500a2 2 0 0 1 1.800-1.200h9.400a2 2 0 0 1 1.800 1.200l2 4.500v2.500a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1z"/><circle cx="7.500" cy="17.500" r="1.800"/><circle cx="16.500" cy="17.500" r="1.800"/>', '<path class="a" d="M6.500 12.500h11"/>'),
		'tools' => array('<rect x="3.500" y="9" width="17" height="10.500" rx="2"/><path d="M9 9V7a1.500 1.500 0 0 1 1.500-1.500h3A1.500 1.500 0 0 1 15 7v2"/>', '<path class="a" d="M3.500 13.500h17M10.500 12.500v2.500h3v-2.500"/>'),
		'oil' => array('<path d="M5 10h9v8a1.500 1.500 0 0 1-1.500 1.500h-6A1.500 1.500 0 0 1 5 18zM7 10V8h5v2M14 11.500l6-4.500"/>', '<path class="a" d="M19.500 12.500c-1 1.500-1.500 2.200-1.500 3a1.500 1.500 0 0 0 3 0c0-.8-.5-1.500-1.500-3z"/>'),
		'tire' => array('<circle cx="12" cy="12" r="8.500"/><circle cx="12" cy="12" r="3.200"/>', '<circle class="a" cx="12" cy="12" r="6" stroke-dasharray="2 2.900"/>'),
		'battery' => array('<rect x="3" y="7.500" width="18" height="10" rx="2.500"/><path d="M7 7.500V6M17 7.500V6"/>', '<path class="a" d="M7.500 12.500h3M9 11v3M14 12.500h3"/>'),
		'shirt' => array('<path d="M8.500 3.500L3 6.500l2 4 2.500-1V20.500h9V9.500l2.500 1 2-4-5.500-3c-.5 1.500-1.800 2.500-3.500 2.500S9 5 8.500 3.500z"/>', '<path class="a" d="M12 8.500v3"/>'),
		'scissors' => array('<circle cx="6" cy="6.500" r="2.500"/><circle cx="6" cy="17.500" r="2.500"/><path d="M8 8.200L20 17M8 15.800L20 7"/>', '<circle class="a" cx="12" cy="12" r=".4"/>'),
		'ruler' => array('<path d="M4 15.500L15.500 4l4.500 4.500L8.500 20z"/>', '<path class="a" d="M7.500 13l2 2M10 10.500l1.500 1.500M12.500 8l2 2"/>'),
		'hanger' => array('<path d="M12 9.500v-1a2.200 2.200 0 1 0-2.200-2.200M12 9.500L3.500 15.500a1.500 1.500 0 0 0 .9 2.700h15.200a1.500 1.500 0 0 0 .9-2.700z"/>', '<path class="a" d="M7.500 15.500h9"/>'),
		'utensils' => array('<path d="M5 3.500v5a2 2 0 0 0 4 0v-5M7 3.500v17M17 20.500v-17c-2.500 1.500-3.500 4-3.500 6.500H17"/>', '<path class="a" d="M7 3.500v5"/>'),
		'coffee' => array('<path d="M5 9h11v6a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4zM16 10.500h1.500a2.500 2.500 0 0 1 0 5H16M5 21.500h11"/>', '<path class="a" d="M9 3.500c-.8 1 .8 1.800 0 3M12.500 3.500c-.8 1 .8 1.800 0 3"/>'),
		'wine' => array('<path d="M7 3.500h10c.3 4.500-.5 8-5 8.500-4.500-.5-5.300-4-5-8.500zM12 12v8.500M8.500 20.500h7"/>', '<path class="a" d="M7.200 7.500h9.600"/>'),
		'cake' => array('<rect x="5" y="12" width="14" height="8.500" rx="1.500"/><path d="M4 20.500h16M12 12V8.500"/>', '<path class="a" d="M5 15.500c2 1.500 3-1.500 5 0s3 1.500 5 0 3 1.500 4 0M12 4c-.9 1-1 1.800 0 2.500 1-.7.9-1.500 0-2.500z"/>'),
		'chef' => array('<path d="M7.500 14.500v5a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1v-5M7.500 14.500A4 4 0 0 1 7 6.800 4.500 4.500 0 0 1 12 4.500a4.500 4.500 0 0 1 5 2.300 4 4 0 0 1-.5 7.700"/>', '<path class="a" d="M7.500 17.500h9"/>'),
		'leaf' => array('<path d="M5 19c-.5-8 3-14 15-14.500C20 14 15 19.500 5 19z"/>', '<path class="a" d="M5 19c3-5.500 6-8 10-10"/>'),
		'flame' => array('<path d="M12 3.500c.5 3.500 5.500 5.500 5.500 10.500a5.500 5.500 0 0 1-11 0c0-2 1-3.500 2.200-4.500.2 1.500.8 2.200 1.500 2.500C10 9 11 6 12 3.500z"/>', '<path class="a" d="M12 20.500a2.500 2.500 0 0 1-2.500-2.500c0-1.800 1.500-2.500 2.500-4 1 1.500 2.500 2.200 2.500 4a2.500 2.500 0 0 1-2.500 2.500z"/>'),
		'droplet' => array('<path d="M12 3.500c3 4 6 6.800 6 10.500a6 6 0 0 1-12 0c0-3.700 3-6.500 6-10.500z"/>', '<path class="a" d="M9 14.500a3 3 0 0 0 2.500 2.800"/>'),
		'sun' => array('<circle cx="12" cy="12" r="4"/>', '<path class="a" d="M12 3v2M12 19v2M3 12h2M19 12h2M5.600 5.600L7 7M17 17l1.400 1.400M5.600 18.400L7 17M17 7l1.400-1.400"/>'),
		'moon' => array('<path d="M19.500 14.500A8 8 0 0 1 9.500 4.500a8 8 0 1 0 10 10z"/>', '<path class="a" d="M17 4.500v3M15.500 6h3"/>'),
		'stethoscope' => array('<path d="M6 3.500v5a4 4 0 0 0 8 0v-5M5 3.500h2M13 3.500h2M10 12.500V14a4.500 4.500 0 0 0 9 0v-2"/>', '<circle class="a" cx="19" cy="10.200" r="1.800"/>'),
		'pulse' => array('<path d="M3 12h4.500l2-5.500 4 11 2-5.500H21"/>', '<path class="a" d="M7 20.500h10"/>'),
		'tooth' => array('<path d="M8 3.500c-2.500 0-4 1.800-4 4.500 0 3 1.500 4.500 2 7.500l.7 4c.2 1 1.500 1.500 2.200.5.8-1.200 1.200-3.500 3.100-3.500s2.300 2.300 3.100 3.500c.7 1 2 .5 2.200-.5l.7-4c.5-3 2-4.500 2-7.500 0-2.700-1.500-4.500-4-4.500-1.700 0-2.500 1-4 1S9.700 3.500 8 3.500z"/>', '<path class="a" d="M7.500 8c.5-.8 1.200-1.200 2-1.200"/>'),
		'pill' => array('<path d="M8.500 20.500a4.500 4.500 0 0 1-3.200-7.700l7.500-7.500a4.500 4.500 0 0 1 6.400 6.400l-7.500 7.500a4.500 4.500 0 0 1-3.200 1.300z"/>', '<path class="a" d="M9.500 9.500l5 5"/>'),
		'syringe' => array('<path d="M7 13.500l6.500-6.500 3.500 3.500-6.500 6.500zM15.500 5l3.500 3.500M17.500 3l3.500 3.500"/>', '<path class="a" d="M8.500 16.500L4 21M10 11.500l2 2"/>'),
		'microscope' => array('<g transform="rotate(25 11 8)"><rect x="8.500" y="3" width="5" height="10" rx="1.200"/></g><path d="M15 9.500c2.500 2.500 2 6.500-1 8.500M6.500 20.500h11"/>', '<path class="a" d="M8 16.500h5"/>'),
		'eye' => array('<path d="M2.500 12S6 5.500 12 5.500 21.500 12 21.500 12 18 18.500 12 18.500 2.500 12 2.500 12z"/><circle cx="12" cy="12" r="3"/>', '<circle class="a" cx="12" cy="12" r=".4"/>'),
		'brain' => array('<path d="M12 5.500a3 3 0 0 0-5.800 1A3.500 3.500 0 0 0 4.500 12a3.500 3.500 0 0 0 1.800 3.200A3 3 0 0 0 12 18.500zM12 5.500a3 3 0 0 1 5.800 1 3.500 3.500 0 0 1 1.700 5.500 3.500 3.500 0 0 1-1.800 3.200A3 3 0 0 1 12 18.500z"/>', '<path class="a" d="M8.500 10.500H12M12 13.500h3.500"/>'),
		'bone' => array('<g transform="rotate(-45 12 12)"><path d="M8 10.800C8 8.500 6.800 7 5 7S2.500 8.200 2.500 9.800c0 1 .5 1.700 1.200 2.200-.7.500-1.200 1.200-1.200 2.200C2.500 15.800 3.500 17 5 17s3-1.500 3-3.800zM16 10.800C16 8.500 17.200 7 19 7s2.500 1.200 2.500 2.800c0 1-.5 1.700-1.200 2.200.7.500 1.200 1.200 1.200 2.200 0 1.600-1 2.800-2.500 2.800s-3-1.500-3-3.800zM8 10.800h8M8 13.200h8"/></g>', '<circle class="a" cx="12" cy="12" r=".3"/>'),
		'baby' => array('<circle cx="12" cy="12" r="8.500"/><path d="M9.500 14.800c1.500 1.200 3.500 1.200 5 0"/>', '<path class="a" d="M12 3.500c1.800 0 2.300 1.800.8 2.700"/><circle class="a" cx="9.500" cy="11.500" r=".3"/><circle class="a" cx="14.500" cy="11.500" r=".3"/>'),
		'paw' => array('<path d="M12 12.500c-3 0-5 2.800-5 5 0 1.700 1.500 2.500 3 2.500.8 0 1.300-.3 2-.3s1.200.3 2 .3c1.500 0 3-.8 3-2.500 0-2.200-2-5-5-5z"/><circle cx="6.500" cy="10" r="1.700"/><circle cx="17.500" cy="10" r="1.700"/>', '<circle class="a" cx="10" cy="6.800" r="1.700"/><circle class="a" cx="14" cy="6.800" r="1.700"/>'),
		'scales' => array('<path d="M12 4v16.500M8 20.500h8M5 7h14M5 7l-2.500 6.500M5 7l2.500 6.500M19 7l-2.500 6.500M19 7l2.500 6.500"/>', '<path class="a" d="M2.500 13.500h5a2.500 2.500 0 0 1-5 0zM16.500 13.500h5a2.500 2.500 0 0 1-5 0z"/>'),
		'gavel' => array('<g transform="rotate(-40 12 11)"><rect x="5" y="3" width="14" height="6.500" rx="2"/><path d="M12 9.500V17"/></g>', '<path class="a" d="M4 21h16M9 5.800l5.200 4.800"/>'),
		'columns' => array('<path d="M3.500 9L12 4l8.500 5zM4 20.500h16"/>', '<path class="a" d="M6.500 12v6M10 12v6M14 12v6M17.500 12v6"/>'),
		'contract' => array('<path d="M7 3.500h7l4.500 4.500v11a1.500 1.500 0 0 1-1.500 1.500H7A1.500 1.500 0 0 1 5.500 19V5A1.500 1.500 0 0 1 7 3.500zM14 3.500V8h4.500M8.500 9H11M8.500 12h7"/>', '<circle class="a" cx="14.500" cy="16.500" r="2"/>'),
		'stamp' => array('<path d="M12 3.500A2.500 2.500 0 0 1 14.500 6c0 1.800-.8 2.500-.8 4h3.800a2.500 2.500 0 0 1 2.500 2.500v1.500h-15v-1.500A2.500 2.500 0 0 1 8 10h3.800c0-1.500-.8-2.200-.8-4A2.500 2.500 0 0 1 12 3.500zM5.500 17.500h13"/>', '<path class="a" d="M4.500 20.500h15"/>'),
		'fingerprint' => array('<path d="M12 3.500a8.500 8.500 0 0 1 8.500 8.500v.5M3.500 12A8.500 8.500 0 0 1 8 4.500M12 7a5 5 0 0 1 5 5c0 2-.5 3.500-1.200 5M12 10.500a1.500 1.500 0 0 1 1.500 1.500c0 2.500-.8 4.500-2 6.500"/>', '<path class="a" d="M7 12a5 5 0 0 1 .5-2M8.500 17c.8-1.500 1.200-3 1.200-5M5.500 15.500c.7-1 1-2.500 1-3.500"/>'),
		'container' => array('<rect x="2.500" y="7" width="19" height="10" rx="1.500"/>', '<path class="a" d="M6.500 7v10M10 7v10M14 7v10M17.500 7v10"/>'),
		'warehouse' => array('<path d="M3.500 20.500v-11L12 4l8.500 5.500v11M8 20.500v-7h8v7"/>', '<path class="a" d="M8 16h8M8 18.300h8"/>'),
		'barcode' => array('<path d="M5 7v10M7.500 7v10M11 7v10M14 7v10M16 7v10M19 7v10"/>', '<path class="a" d="M3 8.500v-4h4M17 4.500h4v4M21 15.500v4h-4M7 19.500H3v-4"/>'),
		'flag' => array('<path d="M5.500 21V3.500M5.500 4.500c3-1.500 5.500 1.500 8.500 0s4-.5 5.500-1V13c-1.500.5-2.500 1.500-5.500 1.500S8.500 12.500 5.500 14"/>', '<circle class="a" cx="5.500" cy="3" r=".5"/>'),
		'medal' => array('<circle cx="12" cy="14.500" r="5.500"/><path d="M8.500 10L5.500 3.500h4l2.500 4M15.500 10l3-6.500h-4L12 7.500"/>', '<circle class="a" cx="12" cy="14.500" r="2"/>'),
		'diamond' => array('<path d="M12 3.500l8.500 8.500-8.500 8.500L3.500 12z"/>', '<path class="a" d="M12 7.500l4.500 4.500-4.500 4.500L7.500 12z"/>'),
		'thumbs' => array('<path d="M3.500 10.500h4v9h-4zM7.500 10.500L11 3.500c1.800 0 2.800 1.500 2.300 3.200l-.6 2.300h5a2 2 0 0 1 2 2.400l-1.100 6a2 2 0 0 1-2 1.600H7.500"/>', '<path class="a" d="M18 3.500v2.500M16.800 4.800h2.400"/>'),
		'trophy' => array('<path d="M7.500 4h9v5a4.500 4.500 0 0 1-9 0zM7.500 6H5a1 1 0 0 0-1 1c0 2 1.500 3.500 3.700 3.500M16.500 6H19a1 1 0 0 1 1 1c0 2-1.500 3.500-3.700 3.500M12 13.500V17M9.500 17h5v3.500h-5zM8.500 20.500h7"/>', '<circle class="a" cx="12" cy="8" r="1.200"/>'),
		'percent-badge' => array('<path d="M12 3l2 1.700 2.600-.3 1 2.400 2.400 1-.3 2.600L21 12l-1.700 2 .3 2.600-2.400 1-1 2.400-2.600-.3L12 21l-2-1.700-2.600.3-1-2.400-2.400-1 .3-2.600L3 12l1.700-2-.3-2.600 2.400-1 1-2.400 2.600.3z"/>', '<path class="a" d="M15 9l-6 6"/><circle class="a" cx="9.200" cy="9.200" r=".8"/><circle class="a" cx="14.800" cy="14.800" r=".8"/>'),
		'support' => array('<circle cx="12" cy="12" r="8.500"/><circle cx="12" cy="12" r="3.500"/>', '<path class="a" d="M6 6l3.500 3.500M18 6l-3.500 3.500M6 18l3.500-3.500M18 18l-3.500-3.500"/>'),
		'wifi' => array('<path d="M3 9.500a13 13 0 0 1 18 0M6 13a8.500 8.500 0 0 1 12 0"/>', '<path class="a" d="M9 16.300a4 4 0 0 1 6 0"/><circle class="a" cx="12" cy="19.500" r=".4"/>'),
		'cloud' => array('<path d="M7 18.500a4.500 4.500 0 0 1-.6-8.900 6 6 0 0 1 11.300 1.400 3.800 3.800 0 0 1-.2 7.500z"/>', '<path class="a" d="M12 16v-4M10 13.500l2-2 2 2"/>'),
		'code' => array('<path d="M8.500 7.500L4 12l4.500 4.500M15.500 7.500L20 12l-4.500 4.500"/>', '<path class="a" d="M13.500 5.500l-3 13"/>'),
		'printer' => array('<path d="M7 8.500v-5h10v5"/><rect x="3.500" y="8.500" width="17" height="8" rx="2"/><path d="M7 14.500h10v6H7z"/>', '<circle class="a" cx="17" cy="11.500" r=".3"/>'),
		'facebook' => array('<path d="M13.500 21v-8h2.700l.4-3.200h-3.100V7.800c0-.9.300-1.500 1.600-1.500h1.700V3.400c-.3 0-1.300-.1-2.400-.1-2.400 0-4 1.400-4 4.100v2.400H7.700V13h2.700v8z"/>', '', true),
		'instagram' => array('<rect x="3.500" y="3.500" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/>', '<circle class="a" cx="17" cy="7" r=".4"/>'),
		'tiktok' => array('<path d="M14 3h2.600c.2 1.900 1.300 3.200 3.400 3.400V9c-1.300 0-2.400-.4-3.400-1.100v5.800a5.300 5.300 0 1 1-5.300-5.300c.3 0 .6 0 .9.1v2.700a2.700 2.700 0 1 0 1.800 2.500z"/>', '', true),
		'youtube' => array('<path fill-rule="evenodd" d="M21.200 7.600a2.400 2.400 0 0 0-1.700-1.700C18 5.500 12 5.500 12 5.500s-6 0-7.500.4A2.400 2.400 0 0 0 2.800 7.600C2.400 9.100 2.400 12 2.400 12s0 2.900.4 4.400a2.400 2.400 0 0 0 1.700 1.700c1.500.4 7.500.4 7.500.4s6 0 7.500-.4a2.400 2.400 0 0 0 1.700-1.700c.4-1.500.4-4.400.4-4.400s0-2.900-.4-4.400zM10.200 14.800V9.200L15 12z"/>', '', true),
		'x' => array('<path d="M4.500 4h4.200l3.800 5.200L17.200 4h2.300l-5.800 6.700L20 20h-4.200l-4.100-5.600L6.800 20H4.500l6.200-7.100z"/>', '', true),
		'linkedin' => array('<path d="M4.500 9.500h3V20h-3zM6 4a1.750 1.750 0 1 1 0 3.500A1.750 1.750 0 0 1 6 4zM10 9.500h2.900v1.400c.5-.9 1.600-1.700 3.200-1.700 3 0 3.900 1.900 3.900 4.700V20h-3v-5.300c0-1.300-.1-2.700-1.800-2.700-1.700 0-1.900 1.300-1.900 2.600V20h-3z"/>', '', true),
		'whatsapp' => array('<path d="M12 3.500a8.500 8.500 0 0 0-7.300 12.800L3.500 20.500l4.300-1.100A8.500 8.500 0 1 0 12 3.500z"/>', '<path class="a" d="M9 8.500c-.4.700-.2 1.800.8 3.200s2.300 2.300 3.400 2.600c.8.200 1.600-.3 1.800-.9l-1.400-1-.8.600c-.8-.4-1.500-1.200-2-2l.6-.8-1-1.400z"/>'),
		'telegram' => array('<path fill-rule="evenodd" d="M20.800 4.200L3.400 10.900c-.9.400-.9.900-.2 1.100l4.400 1.400 1.700 5.200c.2.600.4.800.9.800.4 0 .6-.2.900-.4l2.100-2 4.300 3.200c.8.400 1.400.2 1.600-.7L22.200 5.300c.3-1.100-.4-1.600-1.400-1.100zM8.800 13L18 7.300c.4-.3.800-.1.500.2L10.900 14.300l-.3 3.300z"/>', '', true),
	);
	return $d;
}

/** Claves en orden estable. */
function sc_icon_keys(): array
{
	return array_keys(sc_icon_defs());
}

/**
 * SVG en línea. $opts: string de clases o array ['class'=>'','size'=>24,'title'=>''].
 */
function sc_icon(string $key, $opts = ''): string
{
	$defs = sc_icon_defs();
	$key = strtolower(trim($key));
	if (!isset($defs[$key])) {
		$key = 'star';
	}
	$class = '';
	$size = 24;
	$title = '';
	if (is_array($opts)) {
		$class = isset($opts['class']) ? (string) $opts['class'] : '';
		if (isset($opts['size']) && is_numeric($opts['size'])) {
			$size = (int) $opts['size'];
		}
		$title = isset($opts['title']) ? (string) $opts['title'] : '';
	} elseif (is_string($opts)) {
		$class = $opts;
	}
	if ($size < 8) {
		$size = 8;
	} elseif ($size > 512) {
		$size = 512;
	}
	$class = trim(preg_replace('/[^A-Za-z0-9_\- ]+/', '', $class) ?? '');
	$cls = 'sc-ic sc-ic--' . $key . ($class !== '' ? ' ' . $class : '');
	$def = $defs[$key];
	$filled = !empty($def[2]);
	$body = $filled ? '<g fill="currentColor" stroke="none">' . $def[0] . '</g>' . $def[1] : $def[0] . $def[1];

	$attrs = 'xmlns="http://www.w3.org/2000/svg" class="' . sc_icon_esc($cls) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"';
	if ($title !== '') {
		$attrs .= ' role="img"';
		$inner = '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>' . $body;
	} else {
		$attrs .= ' aria-hidden="true" focusable="false"';
		$inner = $body;
	}
	return '<svg ' . $attrs . '>' . $inner . '</svg>';
}

/** Nombre legible en español. */
function sc_icon_label(string $key): string
{
	static $l = array(
		'star' => 'Estrella', 'shield' => 'Escudo', 'check' => 'Verificado', 'heart' => 'Corazón', 'clock' => 'Reloj', 'phone' => 'Teléfono', 'mail' => 'Correo', 'pin' => 'Ubicación', 'calendar' => 'Calendario', 'users' => 'Equipo', 'user' => 'Persona', 'handshake' => 'Apretón de manos', 'award' => 'Reconocimiento', 'crown' => 'Corona', 'gem' => 'Gema', 'sparkles' => 'Destellos', 'lightbulb' => 'Idea', 'target' => 'Objetivo', 'rocket' => 'Cohete', 'chat' => 'Conversación', 'globe' => 'Mundo', 'link' => 'Enlace', 'lock' => 'Candado', 'key' => 'Llave', 'home' => 'Casa', 'building' => 'Edificio', 'briefcase' => 'Maletín', 'document' => 'Documento', 'pen' => 'Pluma', 'book' => 'Libro', 'graduation' => 'Birrete', 'chart' => 'Gráfico', 'trend' => 'Tendencia', 'coins' => 'Monedas', 'wallet' => 'Billetera', 'card' => 'Tarjeta', 'receipt' => 'Recibo', 'calculator' => 'Calculadora', 'percent' => 'Porcentaje', 'tag' => 'Etiqueta', 'gift' => 'Regalo', 'bag' => 'Bolsa', 'cart' => 'Carrito', 'truck' => 'Camión', 'box' => 'Caja', 'plane' => 'Avión', 'ship' => 'Barco', 'route' => 'Ruta', 'map' => 'Mapa', 'compass' => 'Brújula', 'camera' => 'Cámara', 'image' => 'Imagen', 'video' => 'Video', 'music' => 'Música', 'play' => 'Reproducir', 'headset' => 'Auriculares', 'wrench' => 'Llave inglesa', 'gear' => 'Engranaje', 'hammer' => 'Martillo', 'bolt' => 'Rayo', 'car' => 'Automóvil', 'tools' => 'Caja de herramientas', 'oil' => 'Aceite', 'tire' => 'Llanta', 'battery' => 'Batería', 'shirt' => 'Camisa', 'scissors' => 'Tijeras', 'ruler' => 'Regla', 'hanger' => 'Percha', 'utensils' => 'Cubiertos', 'coffee' => 'Café', 'wine' => 'Copa de vino', 'cake' => 'Pastel', 'chef' => 'Chef', 'leaf' => 'Hoja', 'flame' => 'Llama', 'droplet' => 'Gota', 'sun' => 'Sol', 'moon' => 'Luna', 'stethoscope' => 'Estetoscopio', 'pulse' => 'Pulso', 'tooth' => 'Diente', 'pill' => 'Píldora', 'syringe' => 'Jeringa', 'microscope' => 'Microscopio', 'eye' => 'Ojo', 'brain' => 'Cerebro', 'bone' => 'Hueso', 'baby' => 'Bebé', 'paw' => 'Huella de mascota', 'scales' => 'Balanza', 'gavel' => 'Mazo', 'columns' => 'Columnas', 'contract' => 'Contrato', 'stamp' => 'Sello', 'fingerprint' => 'Huella digital', 'container' => 'Contenedor', 'warehouse' => 'Almacén', 'barcode' => 'Código de barras', 'flag' => 'Bandera', 'medal' => 'Medalla', 'diamond' => 'Diamante', 'thumbs' => 'Me gusta', 'trophy' => 'Trofeo', 'percent-badge' => 'Oferta', 'support' => 'Soporte', 'wifi' => 'Conexión', 'cloud' => 'Nube', 'code' => 'Código', 'printer' => 'Impresora',
		'facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'x' => 'X', 'linkedin' => 'LinkedIn', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram',
	);
	$key = strtolower(trim($key));
	return $l[$key] ?? ($key !== '' ? ucfirst(str_replace(array('-', '_'), ' ', $key)) : '');
}

/** Normaliza texto: minúsculas, sin acentos. */
function sc_icon_norm(string $t): string
{
	$t = function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
	$t = strtr($t, array('á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u', 'ç' => 'c'));
	return $t;
}

/** Elige un icono según palabras del texto; si no hay coincidencia, uno del rubro. */
function sc_icon_for(string $text, string $rubro = ''): string
{
	static $rules = array(
		'scales' => 'divorcio|abogad|legal|juridic|derecho|litigi|demanda|custodia|pension|law|lawyer|attorney|justice|justicia|herencia|sucesion',
		'gavel' => 'juicio|tribunal|juez|sentencia|court|judge|penal|arbitraj',
		'contract' => 'contrato|escritura|notari|convenio|acuerdo|agreement|testament',
		'stamp' => 'sello|apostill|certificad|legalizacion|tramite|registro|trademark|marca registrada',
		'columns' => 'constitucion|derecho publico|institucion|gobierno|municipal|gubernament',
		'stethoscope' => 'consulta|medic|doctor|clinica|salud|health|checkup|chequeo|diagnostic|pediatr|cardiolog|general medicine|medicina',
		'tooth' => 'dental|dentist|diente|odontolog|ortodoncia|muela|sonrisa|smile|blanqueamiento',
		'pill' => 'farmac|medicamento|pastilla|receta|pharmacy|tratamiento|suplemento',
		'syringe' => 'vacuna|inyeccion|inyectable|vaccine|botox|inyect',
		'microscope' => 'laborator|analisis clinic|biopsia|lab test|muestra|patolog|investigacion cientifica',
		'pulse' => 'emergencia|urgencia|cardio|terapia|fisioterap|rehabilit|therapy|monitor|pulso|ecograf',
		'eye' => 'optica|oftalm|vista|ojos|lentes|vision|optometr|mirada',
		'brain' => 'psicolog|psiquiatr|mente|terapia mental|neurolog|mental|cerebro|coaching|bienestar emocional',
		'bone' => 'traumatolog|ortoped|huesos|fractura|quiropract|columna vertebral',
		'baby' => 'bebe|pediatria|maternidad|embarazo|infantil|baby|nino|guarderia|prenatal|parto',
		'paw' => 'veterinar|mascota|perro|gato|pet|animal|canino|felino|peluqueria canina',
		'calculator' => 'contabilidad|contador|impuesto|tribut|sat |declaracion|iva|isr|accounting|tax|auditoria|nomina|planilla|conciliacion|cuentas',
		'receipt' => 'factura|recibo|invoice|facturacion|comprobante|boleta',
		'coins' => 'ahorro|inversion|prestamo|credito|financ|capital|dinero|money|cambio de divisa|remesa|fondos|savings|loan',
		'wallet' => 'pago|cobro|billetera|payment|cartera|tesoreria|cuota',
		'chart' => 'reporte|estadistic|analisis|analytic|metrica|resultados|informe|dashboard|kpi|data|consultoria financiera',
		'trend' => 'crecimiento|growth|rendimiento|ventas|marketing digital|posicionamiento|seo|resultado|escalar|rentabilidad',
		'percent' => 'descuento|porcentaje|discount|promo|rebaja|oferta|liquidacion',
		'wine' => 'vino|wine|cava|sommelier|bodega|vinoteca|champagne|cocktail|coctel|licor|bar ',
		'utensils' => 'restaurante|comida|menu|cocina|almuerzo|cena|food|dinner|lunch|catering|gastronom|buffet|comedor',
		'coffee' => 'cafe|coffee|desayuno|breakfast|cafeteria|barista|te |infusion|brunch',
		'cake' => 'pastel|reposteria|torta|cake|postre|dessert|panaderia|bakery|cumpleanos|boda|pasteleria|dulce',
		'chef' => 'chef|cocinero|culinar|cuisine|gourmet|receta de autor|cooking|parrilla|asador',
		'flame' => 'brasa|grill|fuego|calor|horno|bbq|calefaccion|fogata|chimenea|quemador',
		'leaf' => 'organico|natural|vegetal|vegano|vegan|eco|jardin|garden|planta|ensalada|sostenible|verde|bio ',
		'droplet' => 'agua|water|limpieza|lavado|plomer|fontaner|riego|spa|hidrat|lavanderia|cleaning|aseo',
		'car' => 'frenos|freno|auto|carro|vehicul|mecanic|automotriz|parabrisas|brakes|motor|alineacion|suspension|taller|repuestos|alquiler de|rent a car',
		'wrench' => 'reparacion|repair|mantenimiento|maintenance|ajuste|servicio tecnico|arreglo|instalacion',
		'oil' => 'aceite|lubric|oil change|filtro|cambio de aceite|lubricante',
		'tire' => 'llanta|neumatic|tire|rueda|vulcaniz|balanceo|cauchos',
		'battery' => 'bateria|battery|electric|energia|solar|carga|inversor|panel solar|ups',
		'bolt' => 'rapido|express|urgente|instantane|velocidad|fast|rayo|turbo',
		'gear' => 'configuracion|ingenieria|industrial|maquinaria|proceso|engineering|automatiz|ajustes|mecanizado|torno',
		'hammer' => 'construccion|obra|remodel|carpinter|albanil|construction|constructor|herreria|soldadura|demolicion',
		'tools' => 'herramienta|ferreteria|tools|bricolaje|handyman|multiservicio|taller de',
		'truck' => 'envio|entrega|delivery|transporte|flete|carga pesada|mudanza|shipping|logistica|reparto|acarreo|camion|despacho',
		'route' => 'ruta|trayecto|recorrido|itinerario|route|viaje terrestre|traslado|rastreo|tracking',
		'box' => 'paquete|paqueteria|empaque|embalaje|package|caja|encomienda|courier|mensajeria',
		'container' => 'contenedor|container|maritimo|importacion|exportacion|aduana|puerto|naviera|flete maritimo',
		'warehouse' => 'bodega|almacen|warehouse|inventario|stock|deposito|fulfillment|distribucion',
		'barcode' => 'codigo de barras|barcode|escaner|etiquetado|sku|trazabilidad|punto de venta|pos ',
		'ship' => 'barco|buque|naviera|ship|maritimo|crucero|navegacion|yate|puerto',
		'plane' => 'avion|vuelo|aereo|flight|aerolinea|aeropuerto|air|pasaje|boleto|courier aereo|aerea',
		'globe' => 'internacional|mundial|global|worldwide|exterior|importar|exportar|comercio exterior|idioma|traduccion|translation',
		'shirt' => 'ropa|camisa|vestimenta|prenda|moda|fashion|camiseta|boutique|playera|uniforme|textil|confeccion|clothes',
		'scissors' => 'corte|costura|sastre|tailor|confecciones|peluquer|barber|salon de belleza|estilista|tijera|hair',
		'ruler' => 'medida|a la medida|sastreria|dimension|medicion|topograf|plano|diseno a medida|bespoke',
		'hanger' => 'guardarropa|closet|armario|vestidor|percha|outfit|colecci|wardrobe',
		'bag' => 'bolsa|bolso|accesorio|cartera de mano|handbag|compras|shopping|tienda|retail|mochila',
		'tag' => 'precio|etiqueta|price|tarifa|precios|catalogo de precios|cotizacion|presupuesto',
		'cart' => 'carrito|tienda en linea|ecommerce|e-commerce|comprar|online store|checkout|pedido|orden',
		'gift' => 'regalo|gift|obsequio|detalle|sorpresa|presente|souvenir|promocion especial',
		'gem' => 'joya|joyer|jewel|diamant|anillo|premium|exclusiv|lujo|luxury|alta gama|oro|plata|collar',
		'diamond' => 'brillante|quilate|gema|piedra preciosa|prestigio|elite',
		'crown' => 'vip|realeza|king|queen|corona|distincion|reserva privada',
		'sparkles' => 'belleza|beauty|estetica|glamour|magia|brillo|nuevo|new|facial|maquillaje|makeup|unas|manicure|spa de unas',
		'award' => 'premio|award|certificacion|calidad|quality|acreditado|garantia de calidad|iso|reconocido',
		'medal' => 'medalla|campeon|logro|achievement|mejor|excelencia|excellence|merito',
		'trophy' => 'trofeo|ganador|winner|campeonato|torneo|competencia|liderazgo|lider|champion',
		'shield' => 'seguro|insurance|proteccion|seguridad|security|garantia|warranty|cobertura|poliza|vigilancia|antivirus|blindaje|resguardo',
		'lock' => 'privacidad|confidencial|candado|cerradura|cerrajer|acceso|password|cifrado|encrypt|privado',
		'key' => 'llave|alquiler|renta|inmobiliar|bienes raices|propiedad|arrendamiento|real estate|key|acceso total|llaves',
		'home' => 'casa|hogar|vivienda|apartamento|residencial|house|home|hipoteca|domicilio|inmueble|hotel|hospedaje|alojamiento',
		'building' => 'edificio|oficina|corporativ|empresa|empresarial|company|office|torre|condominio|sede|business center|coworking',
		'briefcase' => 'negocio|trabajo|empleo|job|asesoria|consultoria|profesional|corporate|gestion|servicios profesionales|reclutamiento|talento|rrhh',
		'handshake' => 'alianza|socio|partner|trato|acuerdo comercial|confianza|aliado|negociacion|colaboracion|compromiso',
		'users' => 'equipo|team|grupo|comunidad|clientes|nosotros|familia|personal|staff|talleres grupales|usuarios',
		'user' => 'perfil|cuenta|miembro|cliente|account|profile|usuario|biografia|sobre mi|about me',
		'heart' => 'amor|cuidado|care|favorito|pasion|salud del corazon|cariño|voluntariado|donacion|ong|caridad|fundacion|dedicacion',
		'star' => 'estrella|valoracion|rating|opinion|resena|review|calificacion|testimonio|destacado|favoritos',
		'check' => 'verificado|garantizado|cumplimiento|aprobado|completado|listo|confirmado|control de calidad|requisito|beneficio',
		'clock' => 'horario|hora|tiempo|time|puntual|24 horas|24h|plazo|reloj|rapidez|disponib|urgencia de tiempo',
		'calendar' => 'cita|reserva|agenda|calendario|evento|appointment|booking|reservacion|programar|turno|fecha',
		'phone' => 'llamar|telefono|llamada|call|phone|contacto telefonico|linea directa|hotline',
		'mail' => 'correo|email|e-mail|mail|carta|boletin|newsletter|mensaje|escribenos',
		'pin' => 'ubicacion|direccion|mapa de|donde|sucursal|location|address|zona|local|visitanos|como llegar',
		'map' => 'mapa|cobertura|territorio|region|mapping|geograf|cartograf|turismo|tour',
		'compass' => 'brujula|orientacion|direccion estrategica|guia|explorar|aventura|exploracion|rumbo|navegar|expedicion',
		'camera' => 'foto|fotograf|photo|video grabacion|camara|sesion|retrato|estudio fotografico|cobertura de eventos|cctv|vigilancia por camara',
		'image' => 'imagen|galeria|portafolio|portfolio|gallery|diseno grafico|ilustracion|arte|pintura',
		'video' => 'video|filmacion|pelicula|audiovisual|cine|streaming|produccion|youtube|reel|grabacion',
		'music' => 'musica|music|concierto|banda|dj|sonido|audio|karaoke|show musical|orquesta|cantante|disco',
		'play' => 'reproducir|demo|ver video|presentacion|play|tutorial|curso en video|webinar',
		'headset' => 'soporte tecnico|atencion al cliente|call center|asistencia|customer service|ayuda|help desk|mesa de ayuda|telemarketing',
		'support' => 'soporte|support|auxilio|rescate|socorro|apoyo|asistencia en carretera|grua|emergencias',
		'wifi' => 'internet|wifi|red|conectividad|network|fibra|telecom|banda ancha|router',
		'cloud' => 'nube|cloud|hosting|alojamiento web|saas|respaldo|backup|servidor|server|almacenamiento en',
		'code' => 'desarrollo web|programacion|software|codigo|code|app|aplicacion|sistema|api|desarrollo|sitio web|pagina web|web design|developer',
		'printer' => 'impresion|imprenta|print|fotocopia|rotulacion|copias|escaneo|publicidad impresa|gigantografia|sublimacion|serigrafia|estampado',
		'document' => 'documento|papeleo|archivo|formulario|expediente|document|tramites|informe escrito|redaccion|solicitud|requisitos',
		'pen' => 'firma|escribir|redactar|autor|signature|escritor|editorial|copywriting|caligrafia|blog|ghostwriting',
		'book' => 'libro|curso|capacitacion|biblioteca|lectura|educacion|clase|book|training|tutoria|taller educativo|manual|guia de',
		'graduation' => 'graduacion|universidad|escuela|colegio|academia|diploma|maestria|school|escolar|estudiante|instituto|beca|posgrado',
		'lightbulb' => 'idea|innovacion|creativ|inspiracion|solucion|consejo|tip|innovation|brainstorm|estrategia creativa',
		'target' => 'objetivo|meta|enfoque|precision|target|mision|estrategia|publicidad dirigida|segmentacion|puntería',
		'rocket' => 'lanzamiento|startup|emprendimiento|launch|impulso|acelerar|despegue|boost|arranque|puesta en marcha',
		'sun' => 'sol|verano|summer|playa|dia|bronceado|beach|solar fotovoltaico|vacaciones|bienvenida',
		'moon' => 'noche|night|nocturno|luna|dormir|sueno|descanso|colchon|relax nocturno|hotel nocturno',
		'thumbs' => 'me gusta|like|recomendado|recomendacion|aprobacion|satisfaccion|recommended|feedback',
		'flag' => 'bandera|pais|nacional|patria|flag|hito|bicentenario|identidad|embajada|consulado',
		'percent-badge' => 'cupon|coupon|black friday|2x1|promocional|sale|ofertas|outlet',
		'fingerprint' => 'huella|biometri|identidad digital|identificacion|autenticacion|forense|antecedentes|dactilar',
		'link' => 'enlace|link|url|vinculo|referido|afiliado|integracion|conexion de sistemas|backlink',
		'facebook' => 'facebook',
		'instagram' => 'instagram',
		'tiktok' => 'tiktok',
		'youtube' => 'youtube canal',
		'linkedin' => 'linkedin',
		'whatsapp' => 'whatsapp',
		'telegram' => 'telegram',
	);
	static $rubros = array(
		'abogado' => array('scales', 'gavel', 'columns', 'contract', 'shield', 'handshake', 'book'),
		'clinica' => array('stethoscope', 'pulse', 'tooth', 'pill', 'heart', 'microscope', 'calendar'),
		'taller' => array('wrench', 'gear', 'car', 'oil', 'tire', 'battery', 'bolt'),
		'ropa' => array('shirt', 'scissors', 'bag', 'ruler', 'gem', 'tag', 'sparkles'),
		'restaurante' => array('utensils', 'wine', 'cake', 'coffee', 'flame', 'leaf', 'chef'),
		'transporte' => array('truck', 'route', 'box', 'compass', 'clock', 'shield', 'plane'),
		'contabilidad' => array('calculator', 'chart', 'coins', 'receipt', 'percent', 'document', 'wallet'),
		'importaciones' => array('globe', 'ship', 'container', 'box', 'plane', 'barcode', 'warehouse'),
		'otro' => array('star', 'sparkles', 'award', 'briefcase', 'check', 'handshake', 'gem'),
	);
	$valid = sc_icon_defs();
	$norm = ' ' . sc_icon_norm($text) . ' ';
	$norm = preg_replace('/[^a-z0-9 \-]+/', ' ', $norm) ?? $norm;
	$norm = preg_replace('/\s+/', ' ', $norm) ?? $norm;
	if (trim($norm) !== '') {
		foreach ($rules as $key => $roots) {
			if (!isset($valid[$key])) {
				continue;
			}
			foreach (explode('|', $roots) as $root) {
				$root = sc_icon_norm($root);
				if ($root === '') {
					continue;
				}
				// Coincidencia por inicio de palabra (raíz).
				$needle = ' ' . $root;
				if (strpos($norm, $needle) !== false) {
					return $key;
				}
			}
		}
	}
	$r = sc_icon_norm(trim($rubro));
	$r = preg_replace('/[^a-z]/', '', $r) ?? '';
	$list = $rubros[$r] ?? $rubros['otro'];
	return $list[abs(crc32($text)) % count($list)];
}
