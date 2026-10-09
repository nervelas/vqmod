<?php
/**
 * Servicom builder: paso "store" (solo plan tienda).
 * WooCommerce: moneda GTQ, país GT, sin impuestos, páginas, pasarelas BACS/COD,
 * inventario, correos, categorías y productos. Degrada con elegancia si
 * WooCommerce no está (devuelve ok con nota).
 * Idempotente (meta _sc_key en posts/términos) y reanudable por lotes.
 */
if (!defined('ABSPATH')) {
	exit;
}

class SC_Build_Store
{
	private $m;
	private $lang;
	private $st;

	public static function run(array $args)
	{
		$o = new self();
		return $o->go($args);
	}

	private static function woo_present()
	{
		return defined('WC_SIM') || class_exists('WooCommerce', false) || defined('WC_VERSION');
	}

	private function go(array $args)
	{
		$this->m = SC_Builder::manifest();
		$this->lang = SC_Builder::lang();
		$this->st = $this->m['store'];
		if ($this->m['plan'] !== 'tienda') {
			return array('data' => array('skipped' => 'plan info: no hay tienda'), 'more' => false);
		}
		if (!self::woo_present()) {
			SC_Builder::note('woocommerce ausente: paso store omitido');
			$s = SC_Builder::state();
			$s['steps']['store'] = array('done' => true, 'skipped' => true, 'at' => time());
			SC_Builder::save_state($s);
			return array('data' => array('skipped' => 'WooCommerce no está instalado/activo'), 'more' => false);
		}

		$this->options();
		$pages = $this->pages();
		$this->shipping();

		$catMap = $this->categories();
		$assets = SC_Builder::assets_map();
		$prods = array_values(array_filter((array) ($this->st['productos'] ?? array()), function ($p) {
			return is_array($p) && trim((string) ($p['nombre'] ?? '')) !== '';
		}));
		$known = SC_Builder::keymap('prod:');
		$created = 0;
		$skipped = 0;
		$more = false;
		foreach ($prods as $i => $p) {
			$key = 'prod:' . $i;
			$id = $known[$key] ?? 0;
			if ($id && get_post_meta($id, '_sc_done', true) === '1') {
				$skipped++;
				continue;
			}
			if (SC_Builder::out_of_time(3)) {
				$more = true;
				break;
			}
			$known[$key] = $this->product($i, $p, $id, $catMap, $assets);
			$created++;
		}
		if (function_exists('wc_delete_product_transients')) {
			wc_delete_product_transients(0);
		}
		if (!$more) {
			$s = SC_Builder::state();
			$s['steps']['store'] = array('done' => true, 'at' => time());
			SC_Builder::save_state($s);
			flush_rewrite_rules(false);
		}
		return array('data' => array(
			'products_total' => count($prods), 'created' => $created, 'skipped' => $skipped, 'categories' => count($catMap),
			'pages' => $pages,
		), 'more' => $more);
	}

	/* ------------------------------------------------------------------ */

	private function merge_opt($name, array $vals)
	{
		$cur = get_option($name, array());
		if (!is_array($cur)) {
			$cur = array();
		}
		update_option($name, array_merge($cur, $vals));
	}

	private function options()
	{
		$es = ($this->lang === 'es');
		$st = $this->st;
		$nombre = SC_Builder::biz('nombre', '');
		$o = array(
			'woocommerce_currency' => 'GTQ',
			'woocommerce_currency_pos' => 'left',
			'woocommerce_price_thousand_sep' => ',',
			'woocommerce_price_decimal_sep' => '.',
			'woocommerce_price_num_decimals' => '2',
			'woocommerce_default_country' => 'GT',
			'woocommerce_allowed_countries' => 'specific',
			'woocommerce_ship_to_countries' => '',
			'woocommerce_ship_to_destination' => 'shipping',
			'woocommerce_calc_taxes' => 'no',
			'woocommerce_prices_include_tax' => 'no',
			'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
			'woocommerce_enable_myaccount_registration' => 'yes',
			'woocommerce_enable_guest_checkout' => 'yes',
			'woocommerce_registration_generate_password' => 'yes',
			'woocommerce_manage_stock' => 'yes',
			'woocommerce_hold_stock_minutes' => '60',
			'woocommerce_notify_low_stock' => 'yes',
			'woocommerce_notify_no_stock' => 'yes',
			'woocommerce_notify_low_stock_amount' => (string) max(0, (int) ($st['umbral_stock'] ?? 2)),
			'woocommerce_notify_no_stock_amount' => '0',
			'woocommerce_enable_reviews' => 'no',
			'woocommerce_enable_coupons' => 'no',
			'woocommerce_cart_redirect_after_add' => 'no',
			'woocommerce_shop_page_display' => 'both',
			'woocommerce_coming_soon' => 'no',
			'woocommerce_store_pages_only' => 'no',
			'woocommerce_show_marketplace_suggestions' => 'no',
			'woocommerce_task_list_hidden' => 'yes',
			'woocommerce_onboarding_opt_in' => 'no',
			'woocommerce_allow_tracking' => 'no',
			'woocommerce_queue_flush_rewrite_rules' => 'yes',
			'sc_delivery_note' => trim((string) ($st['nota_entrega'] ?? '')),
		);
		$alerts = trim((string) ($st['correo_alertas'] ?? ''));
		if (is_email($alerts)) {
			$o['woocommerce_stock_email_recipient'] = $alerts;
		}
		$orders = trim((string) ($st['correo_pedidos'] ?? ''));
		$from = is_email($orders) ? $orders : trim((string) SC_Builder::biz('correo_contacto', ''));
		if (is_email($from)) {
			$o['woocommerce_email_from_address'] = $from;
		}
		if ($nombre !== '') {
			$o['woocommerce_email_from_name'] = $nombre;
		}
		$addr = SC_Builder::biz('direccion', '');
		if ($addr !== '') {
			$o['woocommerce_store_address'] = mb_substr($addr, 0, 120);
		}
		foreach ($o as $k => $v) {
			update_option($k, $v);
		}
		update_option('woocommerce_specific_allowed_countries', array('GT'));
		if (is_email($orders)) {
			$this->merge_opt('woocommerce_new_order_settings', array('enabled' => 'yes', 'recipient' => $orders));
		}
		delete_transient('_wc_activation_redirect');

		// Pasarelas: solo transferencia/depósito y (opcional) contra entrega
		$b = is_array($st['banco'] ?? null) ? $st['banco'] : array();
		$hasBank = trim((string) ($b['numero'] ?? '')) !== '' || trim((string) ($b['titular'] ?? '')) !== '';
		$tipo = trim((string) ($b['tipo'] ?? ''));
		$bacsDesc = $es ? 'Realice su pago por transferencia o depósito en la cuenta indicada.' : 'Pay by bank transfer or deposit to the account below.';
		$bacsIns = ($es ? 'Realice el pago en la cuenta indicada y envíenos el comprobante. Su pedido se procesará al confirmar el pago.' : 'Make the payment to the account below and send us the receipt. Your order will be processed once payment is confirmed.');
		if ($tipo !== '') {
			$bacsIns = ($es ? 'Tipo de cuenta: ' : 'Account type: ') . $tipo . '. ' . $bacsIns;
		}
		$this->merge_opt('woocommerce_bacs_settings', array(
			'enabled' => $hasBank ? 'yes' : 'no',
			'title' => $es ? 'Transferencia o depósito bancario' : 'Bank transfer or deposit',
			'description' => $bacsDesc,
			'instructions' => $bacsIns,
		));
		update_option('woocommerce_bacs_accounts', $hasBank ? array(array(
			'account_name' => (string) ($b['titular'] ?? ''),
			'account_number' => (string) ($b['numero'] ?? ''),
			'bank_name' => (string) ($b['banco'] ?? ''),
			'sort_code' => '', 'iban' => '', 'bic' => '',
		)) : array());
		$cod = !empty($st['contra_entrega']);
		$this->merge_opt('woocommerce_cod_settings', array(
			'enabled' => $cod ? 'yes' : 'no',
			'title' => $es ? 'Pago contra entrega' : 'Cash on delivery',
			'description' => $es ? 'Pague en efectivo al recibir su pedido.' : 'Pay in cash when you receive your order.',
			'instructions' => $es ? 'Pague en efectivo al recibir su pedido.' : 'Pay in cash when you receive your order.',
			'enable_for_methods' => array(),
			'enable_for_virtual' => 'yes',
		));
		$this->merge_opt('woocommerce_cheque_settings', array('enabled' => 'no'));
		$this->merge_opt('woocommerce_paypal_settings', array('enabled' => 'no'));
		update_option('woocommerce_gateway_order', array('bacs' => 0, 'cod' => 1));
		if (!$hasBank && !$cod) {
			SC_Builder::note('tienda sin método de pago configurado (faltan datos bancarios)');
		}
	}

	private function pages()
	{
		$es = ($this->lang === 'es');
		$def = array(
			'shop' => array($es ? 'Tienda' : 'Shop', $es ? 'tienda' : 'shop', ''),
			'cart' => array($es ? 'Carrito' : 'Cart', $es ? 'carrito' : 'cart', '[woocommerce_cart]'),
			'checkout' => array($es ? 'Finalizar compra' : 'Checkout', $es ? 'finalizar-compra' : 'checkout', '[woocommerce_checkout]'),
			'myaccount' => array($es ? 'Mi cuenta' : 'My account', $es ? 'mi-cuenta' : 'my-account', '[woocommerce_my_account]'),
		);
		$known = SC_Builder::keymap('page:');
		$knownWc = SC_Builder::keymap('wcpage:');
		$out = array();
		foreach ($def as $k => $d) {
			$mine = 0;
			if ($k === 'shop') {
				$mine = $known['page:tienda'] ?? 0;
			} else {
				$mine = $knownWc['wcpage:' . $k] ?? 0;
			}
			// Quitar la página que WooCommerce creó solo al activarse
			$old = (int) get_option('woocommerce_' . $k . '_page_id', 0);
			if ($old > 0 && $old !== $mine) {
				$p = get_post($old);
				if ($p && $p->post_type === 'page' && !get_post_meta($old, '_sc_key', true)
					&& (trim($p->post_content) === '' || stripos($p->post_content, 'woocommerce') !== false)) {
					wp_delete_post($old, true);
				}
			}
			if ($k === 'shop') {
				if (!$mine) {
					$mine = SC_Builder::ensure_post('page:tienda', array(
						'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $d[0], 'post_name' => $d[1],
						'post_content' => '[sc_product_search]', 'comment_status' => 'closed',
					), array(), $known);
				}
			} else {
				$mine = SC_Builder::ensure_post('wcpage:' . $k, array(
					'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $d[0], 'post_name' => $d[1],
					'post_content' => $d[2], 'comment_status' => 'closed',
				), array('_sc_hide_title' => '0'), $knownWc);
			}
			update_option('woocommerce_' . $k . '_page_id', $mine);
			$out[$k] = $mine;
		}
		$ids = (array) get_option('sc_page_ids', array());
		$ids['tienda'] = $out['shop'];
		$ids['carrito'] = $out['cart'];
		$ids['finalizar'] = $out['checkout'];
		$ids['mi-cuenta'] = $out['myaccount'];
		update_option('sc_page_ids', $ids);
		return $out;
	}

	private function shipping()
	{
		if (!class_exists('WC_Shipping_Zone', false) || !class_exists('WC_Shipping_Zones', false)) {
			return;
		}
		try {
			$name = ($this->lang === 'es') ? 'Guatemala' : 'Guatemala';
			foreach (WC_Shipping_Zones::get_zones() as $z) {
				if (($z['zone_name'] ?? '') === $name) {
					return; // ya existe
				}
			}
			$zone = new WC_Shipping_Zone();
			$zone->set_zone_name($name);
			$zone->add_location('GT', 'country');
			$zone->save();
			$iid = $zone->add_shipping_method('flat_rate');
			if ($iid) {
				update_option('woocommerce_flat_rate_' . $iid . '_settings', array(
					'title' => ($this->lang === 'es') ? 'Entrega a coordinar' : 'Delivery to be arranged',
					'tax_status' => 'none', 'cost' => '0',
				));
			}
		} catch (Throwable $e) {
			SC_Builder::note('zona de envío: ' . substr($e->getMessage(), 0, 100));
		}
	}

	/* ------------------------------------------------------------------ */

	/** @return array nombre_en_minúsculas => term_id */
	private function categories()
	{
		$list = array();
		foreach ((array) ($this->st['categorias'] ?? array()) as $c) {
			if (is_array($c) && trim((string) ($c['nombre'] ?? '')) !== '') {
				$list[] = array('nombre' => trim((string) $c['nombre']), 'padre' => trim((string) ($c['padre'] ?? '')));
			}
		}
		// categorías usadas por productos que no estén declaradas
		foreach ((array) ($this->st['productos'] ?? array()) as $p) {
			$cn = trim((string) ($p['categoria'] ?? ''));
			if ($cn !== '') {
				$found = false;
				foreach ($list as $c) {
					if (mb_strtolower($c['nombre']) === mb_strtolower($cn)) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					$list[] = array('nombre' => $cn, 'padre' => '');
				}
			}
		}
		$known = SC_Builder::termkeymap('cat:', 'product_cat');
		$map = array();
		$pending = $list;
		$guard = 0;
		while ($pending && $guard++ < 8) {
			$next = array();
			foreach ($pending as $idx => $c) {
				$lname = mb_strtolower($c['nombre']);
				if (isset($map[$lname])) {
					continue;
				}
				$parent = 0;
				if ($c['padre'] !== '') {
					$lp = mb_strtolower($c['padre']);
					if ($lp === $lname) {
						$parent = 0;
					} elseif (isset($map[$lp])) {
						$parent = $map[$lp];
					} else {
						$next[] = $c;
						continue;
					}
				}
				$map[$lname] = $this->term($c['nombre'], $parent, 'cat:' . md5($lname), $known);
			}
			if (count($next) === count($pending)) {
				foreach ($next as $c) { // padre inexistente: pasa a primer nivel
					$lname = mb_strtolower($c['nombre']);
					$map[$lname] = $this->term($c['nombre'], 0, 'cat:' . md5($lname), $known);
				}
				break;
			}
			$pending = $next;
		}
		return $map;
	}

	private function term($name, $parent, $key, array $known)
	{
		if (isset($known[$key]) && term_exists((int) $known[$key], 'product_cat')) {
			wp_update_term((int) $known[$key], 'product_cat', array('name' => $name, 'parent' => $parent));
			return (int) $known[$key];
		}
		$r = wp_insert_term($name, 'product_cat', array('parent' => (int) $parent));
		if (is_wp_error($r)) {
			$id = (int) ($r->get_error_data('term_exists') ?: 0);
			if (!$id) {
				throw new SC_Builder_Fatal('categoría: ' . $r->get_error_message(), true);
			}
		} else {
			$id = (int) $r['term_id'];
		}
		update_term_meta($id, '_sc_key', $key);
		return $id;
	}

	/* ------------------------------------------------------------------ */

	private function price($v)
	{
		$n = is_numeric($v) ? (float) $v : (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', (string) $v));
		return number_format(max(0, $n), 2, '.', '');
	}

	private function product($i, array $p, $existing, array $catMap, array $assets)
	{
		$key = 'prod:' . $i;
		$name = trim((string) $p['nombre']);
		$desc = trim((string) ($p['descripcion'] ?? ''));
		$short = SC_El::trim_words($desc, 200);
		$price = $this->price($p['precio'] ?? 0);
		$stock = max(0, (int) ($p['stock'] ?? 0));
		$cat = $catMap[mb_strtolower(trim((string) ($p['categoria'] ?? '')))] ?? 0;
		$img = 0;
		if (!empty($p['foto']) && isset($assets[(string) $p['foto']])) {
			$img = (int) $assets[(string) $p['foto']]['id'];
		}
		$content = SC_El::prose($desc);
		$content = $desc !== '' ? wp_kses_post($content) : '';

		if (!$existing) { // huérfano de una ejecución interrumpida
			$orph = get_posts(array(
				'post_type' => 'product', 'title' => $name, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids',
				'suppress_filters' => true, 'meta_query' => array(array('key' => '_sc_key', 'compare' => 'NOT EXISTS')),
			));
			$existing = $orph ? (int) $orph[0] : 0;
		}

		if (class_exists('WC_Product_Simple', false)) {
			$prod = $existing ? wc_get_product($existing) : null;
			if (!$prod || !($prod instanceof WC_Product_Simple)) {
				$prod = new WC_Product_Simple();
			}
			$prod->set_name($name);
			$prod->set_status('publish');
			$prod->set_catalog_visibility('visible');
			$prod->set_description($content);
			$prod->set_short_description($short !== '' ? esc_html($short) : '');
			$prod->set_regular_price($price);
			$prod->set_manage_stock(true);
			$prod->set_stock_quantity($stock);
			$prod->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
			$prod->set_backorders('no');
			$prod->set_reviews_allowed(false);
			$prod->set_virtual(false);
			if ($cat) {
				$prod->set_category_ids(array($cat));
			}
			$prod->set_image_id($img);
			$prod->update_meta_data('_sc_key', $key);
			$prod->update_meta_data('_sc_done', '1');
			return (int) $prod->save();
		}

		// Respaldo (sin clases CRUD): datos equivalentes en posts/meta de WooCommerce
		$arr = array(
			'post_type' => 'product', 'post_status' => 'publish', 'post_title' => $name, 'post_content' => $content,
			'post_excerpt' => $short !== '' ? esc_html($short) : '', 'comment_status' => 'closed',
		);
		if ($existing) {
			$arr['ID'] = $existing;
			$id = wp_update_post(wp_slash($arr), true);
		} else {
			$arr['meta_input'] = array('_sc_key' => $key);
			$id = wp_insert_post(wp_slash($arr), true);
		}
		if (is_wp_error($id) || !$id) {
			throw new SC_Builder_Fatal('producto ' . $i . ': ' . (is_wp_error($id) ? $id->get_error_message() : '?'), true);
		}
		update_post_meta($id, '_sc_key', $key);
		update_post_meta($id, '_regular_price', $price);
		update_post_meta($id, '_price', $price);
		update_post_meta($id, '_manage_stock', 'yes');
		update_post_meta($id, '_stock', (string) $stock);
		update_post_meta($id, '_stock_status', $stock > 0 ? 'instock' : 'outofstock');
		update_post_meta($id, '_backorders', 'no');
		update_post_meta($id, '_virtual', 'no');
		update_post_meta($id, '_visibility', 'visible');
		if ($img) {
			set_post_thumbnail($id, $img);
		} else {
			delete_post_meta($id, '_thumbnail_id');
		}
		if ($cat) {
			wp_set_object_terms($id, array($cat), 'product_cat');
		}
		update_post_meta($id, '_sc_done', '1');
		return (int) $id;
	}
}
