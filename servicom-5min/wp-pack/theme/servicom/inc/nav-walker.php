<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Walker del menú principal: añade un botón accesible (aria-expanded) tras
 * cada enlace con submenú. En escritorio el submenú se abre con hover, foco
 * y teclado; en móvil funciona como acordeón.
 */
class Servicom_Walker_Nav extends Walker_Nav_Menu
{
	public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
	{
		parent::start_el($output, $item, $depth, $args, $id);
		$classes = empty($item->classes) ? array() : (array) $item->classes;
		if (in_array('menu-item-has-children', $classes, true)) {
			$label = sprintf('Mostrar submenú de %s', wp_strip_all_tags($item->title));
			$output .= '<button type="button" class="sc-sub-toggle" aria-expanded="false" aria-label="' . esc_attr($label) . '">'
				. servicom_icon('chevron', 16) . '</button>';
		}
	}
}
