<?php
/**
 * Servicom builder: orquestador de pasos idempotentes y reanudables.
 * Pasos: install, media, pages, store, finish, reset, status (y delega
 * qa / publish / replace_domain a las funciones del mu-plugin).
 * PHP 8.0 compatible.
 */
if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/tokens.php';
require_once __DIR__ . '/el.php';

class SC_Builder_Fatal extends RuntimeException
{
	public $retry = false;
	public function __construct($msg, $retry = false)
	{
		parent::__construct($msg);
		$this->retry = $retry;
	}
}

class SC_Builder
{
	const STATE_OPT = 'sc_build_state';
	const ELEMENTOR_VER = '3.24.0';
	const MAX_RESETS = 3;

	public static $dir = '';
	public static $deadline = 0.0;
	public static $notes = array();
	private static $manifest = null;
	private static $lockName = '';

	/* ------------------------------------------------------------------
	 * Arranque, tiempo, estado
	 * ------------------------------------------------------------------ */

	public static function boot($jobdir, $budget = 25)
	{
		self::$dir = rtrim((string) $jobdir, '/\\');
		self::$deadline = microtime(true) + max(3, (float) $budget);
		self::$manifest = null;
		self::$notes = array();
	}

	public static function out_of_time($reserve = 2.0)
	{
		return microtime(true) > (self::$deadline - $reserve);
	}

	public static function manifest()
	{
		if (self::$manifest !== null) {
			return self::$manifest;
		}
		$f = self::$dir . '/manifest.json';
		if (!is_readable($f)) {
			throw new SC_Builder_Fatal('manifest.json no encontrado');
		}
		$raw = file_get_contents($f);
		$m = json_decode((string) $raw, true);
		if (!is_array($m) || (int) ($m['version'] ?? 0) !== 1) {
			throw new SC_Builder_Fatal('manifest.json inválido');
		}
		foreach (array('site', 'plan', 'style', 'business', 'assets') as $k) {
			if (!isset($m[$k])) {
				$m[$k] = ($k === 'style') ? 1 : array();
			}
		}
		$m['plan'] = ($m['plan'] === 'tienda') ? 'tienda' : 'info';
		$m['style'] = max(1, min(5, (int) $m['style']));
		$m['business'] = is_array($m['business']) ? $m['business'] : array();
		$m['texts'] = is_array($m['texts'] ?? null) ? $m['texts'] : array();
		$m['content'] = is_array($m['content'] ?? null) ? $m['content'] : array();
		$m['store'] = is_array($m['store'] ?? null) ? $m['store'] : array();
		$m['preview'] = is_array($m['preview'] ?? null) ? $m['preview'] : array();
		$m['assets'] = is_array($m['assets']) ? $m['assets'] : array();
		self::$manifest = $m;
		return $m;
	}

	public static function lang()
	{
		$m = self::manifest();
		$l = $m['site']['lang'] ?? '';
		if ($l === '' && !empty($m['site']['locale'])) {
			$l = substr($m['site']['locale'], 0, 2);
		}
		return ($l === 'en') ? 'en' : 'es';
	}

	public static function t($key, ...$args)
	{
		return sc_dict($key, self::lang(), ...$args);
	}

	public static function state()
	{
		$s = function_exists('get_option') ? get_option(self::STATE_OPT, array()) : array();
		return is_array($s) ? $s : array();
	}

	public static function save_state(array $s)
	{
		update_option(self::STATE_OPT, $s, false);
	}

	/** Modifica una clave de estado de forma atómica (lectura-modificación-guardado). */
	public static function set_state($path, $value)
	{
		$s = self::state();
		$ref = &$s;
		foreach (explode('.', $path) as $p) {
			if (!isset($ref[$p]) || !is_array($ref[$p])) {
				$ref[$p] = array();
			}
			$ref = &$ref[$p];
		}
		$ref = $value;
		unset($ref);
		self::save_state($s);
	}

	public static function note($msg)
	{
		if (!in_array($msg, self::$notes, true)) {
			self::$notes[] = $msg;
		}
		$s = self::state();
		$n = $s['notes'] ?? array();
		if (!in_array($msg, $n, true)) {
			$n[] = $msg;
			$s['notes'] = array_slice($n, -40);
			if (function_exists('is_blog_installed') && is_blog_installed()) {
				self::save_state($s);
			}
		}
	}

	private static function lock()
	{
		global $wpdb;
		self::$lockName = 'sc_build_' . md5(DB_NAME . $wpdb->prefix);
		$ok = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', self::$lockName));
		return (string) $ok === '1';
	}

	private static function unlock()
	{
		global $wpdb;
		if (self::$lockName !== '') {
			$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::$lockName));
		}
	}

	/* ------------------------------------------------------------------
	 * Despachador
	 * ------------------------------------------------------------------ */

	/** @return array{data:array,more:bool} */
	public static function run($step, array $args = array())
	{
		$step = (string) $step;
		$known = array('install', 'media', 'pages', 'store', 'finish', 'reset', 'status', 'qa', 'publish', 'replace_domain');
		if (!in_array($step, $known, true)) {
			throw new SC_Builder_Fatal('paso desconocido');
		}
		if (!self::lock()) {
			throw new SC_Builder_Fatal('otro paso en ejecución', true);
		}
		try {
			switch ($step) {
				case 'install':
					return self::step_install($args);
				case 'media':
					require_once __DIR__ . '/media.php';
					return SC_Build_Media::run($args);
				case 'pages':
					require_once __DIR__ . '/pages.php';
					return SC_Build_Pages::run($args);
				case 'store':
					require_once __DIR__ . '/store.php';
					return SC_Build_Store::run($args);
				case 'finish':
					return self::step_finish($args);
				case 'reset':
					return self::step_reset($args);
				case 'status':
					return array('data' => self::step_status(), 'more' => false);
				case 'qa':
					return self::step_qa($args);
				case 'publish':
					return self::step_publish($args);
				case 'replace_domain':
					return self::step_replace_domain($args);
			}
		} finally {
			self::unlock();
		}
		return array('data' => array(), 'more' => false);
	}

	/* ------------------------------------------------------------------
	 * Utilidades de contenido marcado con _sc_key
	 * ------------------------------------------------------------------ */

	/** Mapa clave => post_id de posts con _sc_key que empiezan por $prefix. */
	public static function keymap($prefix)
	{
		global $wpdb;
		$like = $wpdb->esc_like($prefix) . '%';
		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT pm.meta_value k, pm.post_id id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_sc_key' AND pm.meta_value LIKE %s AND p.post_status <> 'trash' ORDER BY pm.post_id ASC",
			$like
		));
		$out = array();
		foreach ((array) $rows as $r) {
			if (!isset($out[$r->k])) {
				$out[$r->k] = (int) $r->id;
			}
		}
		return $out;
	}

	public static function termkeymap($prefix, $taxonomy = '')
	{
		global $wpdb;
		$like = $wpdb->esc_like($prefix) . '%';
		$sql = "SELECT tm.meta_value k, tm.term_id id FROM {$wpdb->termmeta} tm";
		$args = array();
		if ($taxonomy !== '') {
			$sql .= " INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id AND tt.taxonomy = %s";
			$args[] = $taxonomy;
		}
		$sql .= " WHERE tm.meta_key = '_sc_key' AND tm.meta_value LIKE %s";
		$args[] = $like;
		$rows = $wpdb->get_results($wpdb->prepare($sql, $args));
		$out = array();
		foreach ((array) $rows as $r) {
			if (!isset($out[$r->k])) {
				$out[$r->k] = (int) $r->id;
			}
		}
		return $out;
	}

	/**
	 * Busca un post por _sc_key; si no existe lo crea (adoptando un huérfano
	 * con el mismo post_name/tipo si quedó de una ejecución interrumpida).
	 */
	public static function ensure_post($key, array $postarr, array $meta = array(), array $known = null)
	{
		$id = 0;
		if ($known !== null && isset($known[$key])) {
			$id = (int) $known[$key];
		} else {
			$ids = get_posts(array(
				'post_type' => 'any', 'post_status' => 'any', 'meta_key' => '_sc_key', 'meta_value' => $key,
				'numberposts' => 1, 'fields' => 'ids', 'suppress_filters' => true, 'orderby' => 'ID', 'order' => 'ASC',
			));
			$id = $ids ? (int) $ids[0] : 0;
		}
		if (!$id && !empty($postarr['post_name'])) {
			$orph = get_posts(array(
				'post_type' => $postarr['post_type'], 'name' => $postarr['post_name'], 'post_status' => 'any',
				'numberposts' => 1, 'fields' => 'ids', 'suppress_filters' => true,
				'meta_query' => array(array('key' => '_sc_key', 'compare' => 'NOT EXISTS')),
			));
			if ($orph) {
				$id = (int) $orph[0];
				update_post_meta($id, '_sc_key', $key);
			}
		}
		$meta['_sc_key'] = $key;
		if ($id) {
			$upd = $postarr;
			$upd['ID'] = $id;
			unset($upd['post_name']);
			$r = wp_update_post(wp_slash($upd), true);
			if (is_wp_error($r)) {
				throw new SC_Builder_Fatal('no se pudo actualizar ' . $key . ': ' . $r->get_error_message(), true);
			}
			foreach ($meta as $k => $v) {
				update_post_meta($id, $k, $v);
			}
			return $id;
		}
		$postarr['meta_input'] = $meta;
		$r = wp_insert_post(wp_slash($postarr), true);
		if (is_wp_error($r) || !$r) {
			throw new SC_Builder_Fatal('no se pudo crear ' . $key . ': ' . (is_wp_error($r) ? $r->get_error_message() : '?'), true);
		}
		return (int) $r;
	}

	public static function biz($k, $default = '')
	{
		$m = self::manifest();
		$v = $m['business'][$k] ?? $default;
		return is_string($v) ? trim($v) : $v;
	}

	public static function digits($s)
	{
		return preg_replace('/\D+/', '', (string) $s);
	}

	/** Mapa asset_id => [id, url, alt, w, h] de los adjuntos ya importados. */
	public static function assets_map()
	{
		static $cache = null;
		$m = self::manifest();
		$km = self::keymap('asset:');
		$out = array();
		foreach ($m['assets'] as $a) {
			$aid = (string) ($a['id'] ?? '');
			if ($aid === '' || !isset($km['asset:' . $aid])) {
				continue;
			}
			$att = $km['asset:' . $aid];
			$url = wp_get_attachment_url($att);
			if (!$url) {
				continue;
			}
			$out[$aid] = array('id' => $att, 'url' => $url, 'alt' => (string) ($a['alt'] ?? ''), 'w' => (int) ($a['w'] ?? 0), 'h' => (int) ($a['h'] ?? 0));
		}
		return $out;
	}

	public static function elementor_info()
	{
		if (defined('ELEMENTOR_SIM')) {
			return 'simulado (solo pruebas)';
		}
		if (defined('ELEMENTOR_VERSION') || class_exists('\Elementor\Plugin', false)) {
			return 'presente ' . (defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '');
		}
		return 'ausente';
	}

	/* ------------------------------------------------------------------
	 * Negocio -> theme_mods (contrato §9) y logo
	 * ------------------------------------------------------------------ */

	public static function apply_business()
	{
		$m = self::manifest();
		$b = $m['business'];
		$r = is_array($b['redes'] ?? null) ? $b['redes'] : array();
		$wa = self::digits($b['whatsapp'] ?? '');
		$tel = trim((string) ($b['telefono'] ?? ''));
		$mods = array(
			'sc_nombre' => (string) ($b['nombre'] ?? ''),
			'sc_telefono' => $tel,
			'sc_whatsapp' => $wa,
			'sc_whatsapp_msg' => (string) ($b['whatsapp_msg'] ?? ''),
			'sc_correo' => (string) ($b['correo_contacto'] ?? ''),
			'sc_direccion' => (string) ($b['direccion'] ?? ''),
			'sc_mapa_url' => (string) ($b['mapa_url'] ?? ''),
			'sc_horario' => (string) ($b['horario'] ?? ''),
			'sc_facebook' => (string) ($r['facebook'] ?? ''),
			'sc_instagram' => (string) ($r['instagram'] ?? ''),
			'sc_tiktok' => (string) ($r['tiktok'] ?? ''),
			'sc_youtube' => (string) ($r['youtube'] ?? ''),
			'sc_x' => (string) ($r['x'] ?? ''),
			'sc_linkedin' => (string) ($r['linkedin'] ?? ''),
			'sc_footer_credit' => (string) ($b['footer_credit'] ?? 'Sitio creado por Servicom'),
			'sc_float_wa' => $wa !== '' ? '1' : '0',
			'sc_mobile_bar' => ($wa !== '' || $tel !== '') ? '1' : '0',
			'sc_style' => (string) $m['style'],
		);
		foreach ($mods as $k => $v) {
			set_theme_mod($k, $v);
		}
	}

	public static function apply_logo()
	{
		$m = self::manifest();
		$logo = $m['business']['logo'] ?? null;
		if (!$logo) {
			remove_theme_mod('custom_logo');
			return 0;
		}
		$km = self::keymap('asset:' . $logo);
		$id = $km['asset:' . $logo] ?? 0;
		if ($id) {
			set_theme_mod('custom_logo', (int) $id);
		}
		return (int) $id;
	}

	/* ------------------------------------------------------------------
	 * install
	 * ------------------------------------------------------------------ */

	private static function step_install(array $a)
	{
		global $wpdb, $wp_rewrite;
		$m = self::manifest();
		$site = $m['site'];
		$user = (string) ($site['admin_user'] ?? '');
		$pass = (string) ($site['admin_pass'] ?? '');
		$email = (string) ($site['admin_email'] ?? '');
		if ($user === '' || strtolower($user) === 'admin' || !validate_username($user)) {
			throw new SC_Builder_Fatal('usuario administrador inválido');
		}
		if (strlen($pass) < 8) {
			throw new SC_Builder_Fatal('contraseña de administrador demasiado corta');
		}
		if (!is_email($email)) {
			throw new SC_Builder_Fatal('correo de administrador inválido');
		}
		if (!empty($site['table_prefix']) && $site['table_prefix'] !== $wpdb->prefix) {
			throw new SC_Builder_Fatal('prefijo de tablas distinto al de wp-config');
		}
		$locale = (($site['locale'] ?? '') === 'en_US') ? 'en_US' : 'es_ES';
		if (!empty($site['locale']) && in_array($site['locale'], array('es_ES', 'en_US'), true)) {
			$locale = $site['locale'];
		}
		$title = trim((string) ($site['title'] ?? $m['business']['nombre'] ?? 'Sitio'));
		if ($title === '') {
			$title = 'Sitio';
		}

		// Nunca enviar correos de instalación.
		add_filter('pre_wp_mail', '__return_true');

		$fresh = false;
		if (!is_blog_installed()) {
			if (!wp_installing()) {
				wp_installing(true);
			}
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$r = wp_install($title, $user, $email, false, '', $pass, $locale);
			if (is_wp_error($r)) {
				throw new SC_Builder_Fatal('wp_install: ' . $r->get_error_message(), true);
			}
			$fresh = true;
			wp_installing(false);
			wp_cache_flush();
		} else {
			// Reanudación: el usuario ya existe; garantizar rol y contraseña del manifiesto.
			$u = get_user_by('login', $user);
			if (!$u) {
				$uid = wp_create_user($user, $pass, $email);
				if (is_wp_error($uid)) {
					throw new SC_Builder_Fatal('crear admin: ' . $uid->get_error_message(), true);
				}
				(new WP_User($uid))->set_role('administrator');
			}
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		// Ajustes básicos
		self::force_urls();
		update_option('blogname', $title);
		update_option('WPLANG', $locale === 'en_US' ? '' : $locale);
		update_option('timezone_string', (string) ($site['timezone'] ?? 'America/Guatemala'));
		update_option('gmt_offset', '');
		update_option('blog_public', '0');
		update_option('default_comment_status', 'closed');
		update_option('default_ping_status', 'closed');
		update_option('default_pingback_flag', '0');
		update_option('comments_notify', '0');
		update_option('moderation_notify', '0');
		update_option('comment_registration', '1');
		update_option('close_comments_for_old_posts', '1');
		update_option('show_avatars', '0');
		update_option('users_can_register', '0');
		update_option('start_of_week', '1');
		update_option('blogdescription', '');
		if (self::lang() === 'es') {
			update_option('date_format', 'j \d\e F \d\e Y');
			update_option('time_format', 'H:i');
		}
		// Idioma de la sesión actual para el resto del proceso
		$admin = get_user_by('login', $user);
		if ($admin) {
			wp_update_user(array('ID' => $admin->ID, 'display_name' => $user, 'nickname' => $user));
		}

		// Permalinks bonitos
		update_option('permalink_structure', '/%postname%/');
		$wp_rewrite->set_permalink_structure('/%postname%/');
		$wp_rewrite->init();
		self::write_htaccess();

		// Contenido de ejemplo
		self::remove_demo_content();
		self::remove_default_plugins();

		// Tema
		$theme = wp_get_theme('servicom');
		if (!$theme->exists()) {
			throw new SC_Builder_Fatal('tema servicom no encontrado');
		}
		if (get_option('stylesheet') !== 'servicom') {
			switch_theme('servicom');
		}
		delete_option('theme_switched');

		// Plugins permitidos presentes
		$act = self::activate_allowed_plugins($m['plan']);

		// Opciones sc_*
		$pv = $m['preview'];
		update_option('sc_plan', $m['plan']);
		if (!empty($pv['key'])) {
			update_option('sc_preview_key', (string) $pv['key']);
		}
		update_option('sc_portal_url', (string) ($pv['portal_url'] ?? ''));
		update_option('sc_pay_url', (string) ($pv['pay_url'] ?? ''));
		update_option('sc_edit_url', (string) ($pv['edit_url'] ?? ''));
		update_option('sc_wa_servicom', self::digits($pv['wa_servicom'] ?? ($m['support']['wa_servicom'] ?? '')));
		$mode = in_array(($pv['mode'] ?? 'preview'), array('preview', 'demo', 'published'), true) ? $pv['mode'] : 'preview';
		if (function_exists('sc_set_mode')) {
			sc_set_mode($mode);
		} else {
			update_option('sc_mode', $mode);
			self::note('sc_set_mode ausente: se guardó sc_mode directamente');
		}

		self::apply_business();
		self::apply_logo();

		$s = self::state();
		$s['version'] = 1;
		$s['plan'] = $m['plan'];
		$s['started'] = $s['started'] ?? time();
		$s['steps']['install'] = array('done' => true, 'at' => time());
		self::save_state($s);
		if (self::elementor_info() === 'ausente') {
			self::note('elementor ausente');
		}

		return array('data' => array(
			'fresh' => $fresh, 'user' => $user, 'locale' => $locale, 'theme' => get_option('stylesheet'),
			'plugins' => $act, 'elementor' => self::elementor_info(), 'url' => home_url('/'),
		), 'more' => false);
	}

	/** siteurl/home siempre = manifest.site.url (sin barra final). */
	public static function force_urls()
	{
		$m = self::manifest();
		$u = rtrim((string) ($m['site']['url'] ?? ''), '/');
		if ($u !== '' && preg_match('~^https?://[^/\s]+(/[^\s]*)?$~', $u)) {
			update_option('siteurl', $u);
			update_option('home', $u);
		}
	}

	private static function write_htaccess()
	{
		global $wp_rewrite;
		$file = ABSPATH . '.htaccess';
		$rules = (string) $wp_rewrite->mod_rewrite_rules();
		if ($rules === '') {
			return;
		}
		if (!function_exists('insert_with_markers')) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		if (file_exists($file) && is_readable($file) && str_contains((string) file_get_contents($file), '# BEGIN WordPress')) {
			return;
		}
		if ((file_exists($file) && is_writable($file)) || (!file_exists($file) && is_writable(ABSPATH))) {
			insert_with_markers($file, 'WordPress', explode("\n", $rules));
		} else {
			self::note('.htaccess no escribible');
		}
	}

	private static function remove_demo_content()
	{
		foreach (array('hello-world' => 'post', 'sample-page' => 'page', 'politica-de-privacidad' => 'page', 'privacy-policy' => 'page') as $slug => $type) {
			$p = get_page_by_path($slug, OBJECT, $type);
			if ($p && !get_post_meta($p->ID, '_sc_key', true)) {
				wp_delete_post($p->ID, true);
			}
		}
		foreach (get_posts(array('post_type' => 'page', 'post_status' => 'draft', 'numberposts' => 5, 'name' => 'privacy-policy', 'suppress_filters' => true)) as $p) {
			wp_delete_post($p->ID, true);
		}
		foreach (get_comments(array('status' => 'all', 'number' => 20)) as $c) {
			wp_delete_comment($c->comment_ID, true);
		}
		delete_option('wp_page_for_privacy_policy');
	}

	private static function remove_default_plugins()
	{
		$dir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins';
		$hello = $dir . '/hello.php';
		if (is_file($hello)) {
			@unlink($hello);
		}
		$ak = $dir . '/akismet';
		if (is_dir($ak) && !is_link($ak)) {
			self::rrmdir($ak);
		}
		$act = (array) get_option('active_plugins', array());
		$new = array_values(array_diff($act, array('hello.php', 'akismet/akismet.php')));
		if ($new !== $act) {
			update_option('active_plugins', $new);
		}
	}

	public static function rrmdir($d)
	{
		if (!is_dir($d)) {
			return;
		}
		foreach (scandir($d) as $f) {
			if ($f === '.' || $f === '..') {
				continue;
			}
			$p = $d . '/' . $f;
			if (is_dir($p) && !is_link($p)) {
				self::rrmdir($p);
			} else {
				@unlink($p);
			}
		}
		@rmdir($d);
	}

	private static function activate_allowed_plugins($plan)
	{
		$dir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins';
		$want = array('elementor/elementor.php');
		if (file_exists($dir . '/fluentform/fluentform.php')) {
			$want[] = 'fluentform/fluentform.php';
		} elseif (file_exists($dir . '/contact-form-7/wp-contact-form-7.php')) {
			$want[] = 'contact-form-7/wp-contact-form-7.php';
		}
		if ($plan === 'tienda') {
			$want[] = 'woocommerce/woocommerce.php';
		}
		$done = array();
		foreach ($want as $p) {
			if (!file_exists($dir . '/' . $p)) {
				self::note('plugin ausente: ' . dirname($p));
				continue;
			}
			if (is_plugin_active($p)) {
				$done[] = $p;
				continue;
			}
			try {
				ob_start();
				$r = activate_plugin($p, '', false, true);
				ob_end_clean();
				if (is_wp_error($r)) {
					self::note('no se activó ' . $p . ': ' . $r->get_error_message());
				} else {
					$done[] = $p;
				}
			} catch (Throwable $e) {
				if (ob_get_level()) {
					ob_end_clean();
				}
				self::note('fallo al activar ' . $p . ': ' . substr($e->getMessage(), 0, 120));
			}
		}
		delete_transient('elementor_activation_redirect');
		delete_transient('_wc_activation_redirect');
		delete_option('elementor_onboarded_redirect');
		return $done;
	}

	/* ------------------------------------------------------------------
	 * finish / reset / status
	 * ------------------------------------------------------------------ */

	private static function step_finish(array $a)
	{
		global $wp_rewrite;
		$m = self::manifest();
		self::apply_business();
		self::apply_logo();
		$wp_rewrite->init();
		flush_rewrite_rules(true);
		self::write_htaccess();

		$cleared = false;
		if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
			try {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
				$cleared = true;
			} catch (Throwable $e) {
				self::note('clear_cache Elementor falló: ' . substr($e->getMessage(), 0, 100));
			}
		}
		// Regenerar el CSS de cada página y del kit (API pública de Elementor)
		$cssDone = 0;
		if ($cleared && class_exists('\Elementor\Core\Files\CSS\Post')) {
			$all = array_values((array) get_option('sc_page_ids', array())) + array();
			$all = array_merge($all, array_values((array) get_option('sc_service_pages', array())), array((int) get_option('elementor_active_kit', 0)));
			foreach (array_unique(array_filter(array_map('intval', $all))) as $pid) {
				try {
					\Elementor\Core\Files\CSS\Post::create($pid)->update();
					$cssDone++;
				} catch (Throwable $e) {
					self::note('CSS de ' . $pid . ' no se regeneró: ' . substr($e->getMessage(), 0, 80));
				}
			}
		}
		$mode = $m['preview']['mode'] ?? '';
		if ($mode !== '' && function_exists('sc_set_mode') && get_option('sc_mode') !== $mode) {
			sc_set_mode($mode);
		}
		$ids = (array) get_option('sc_page_ids', array());
		$urls = array();
		foreach ($ids as $k => $id) {
			$u = get_permalink($id);
			if ($u) {
				$urls[$k] = $u;
			}
		}
		$svc = (array) get_option('sc_service_pages', array());
		foreach ($svc as $i => $id) {
			$u = get_permalink($id);
			if ($u) {
				$urls['servicio-' . $i] = $u;
			}
		}
		$s = self::state();
		$s['done'] = true;
		$s['finished'] = time();
		$s['steps']['finish'] = array('done' => true, 'at' => time());
		self::save_state($s);
		return array('data' => array(
			'page_ids' => $ids, 'service_pages' => $svc, 'urls' => $urls, 'home' => home_url('/'),
			'elementor_css_cleared' => $cleared, 'elementor_css_regenerated' => $cssDone, 'elementor' => self::elementor_info(), 'notes' => $s['notes'] ?? array(),
		), 'more' => false);
	}

	private static function step_reset(array $a)
	{
		global $wpdb;
		$s = self::state();
		$resets = (int) ($s['resets'] ?? 0);
		if (empty($s['reset_running'])) {
			if ($resets >= self::MAX_RESETS) {
				throw new SC_Builder_Fatal('límite de regeneraciones alcanzado');
			}
			$s['resets'] = $resets + 1;
			$s['reset_running'] = true;
			self::save_state($s);
		}
		// 1) posts marcados (páginas, adjuntos, productos, menús, kit)
		$n = 0;
		while (true) {
			if (self::out_of_time(3)) {
				return array('data' => array('deleted' => $n), 'more' => true);
			}
			$ids = $wpdb->get_col("SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm WHERE pm.meta_key = '_sc_key' ORDER BY pm.post_id DESC LIMIT 25");
			if (!$ids) {
				break;
			}
			foreach ($ids as $id) {
				$pt = get_post_type($id);
				if ($pt === 'attachment') {
					wp_delete_attachment((int) $id, true);
				} else {
					wp_delete_post((int) $id, true);
				}
				// si algo impide borrarlo, quitar la marca para no entrar en bucle
				if (get_post_status($id)) {
					delete_post_meta($id, '_sc_key');
				}
				$n++;
			}
		}
		// 2) términos marcados
		$tids = $wpdb->get_results("SELECT tm.term_id, tt.taxonomy FROM {$wpdb->termmeta} tm INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id WHERE tm.meta_key = '_sc_key'");
		foreach ((array) $tids as $t) {
			wp_delete_term((int) $t->term_id, $t->taxonomy);
			$n++;
		}
		$wpdb->query("DELETE FROM {$wpdb->termmeta} WHERE meta_key = '_sc_key'");
		// 3) opciones/mods derivados
		remove_theme_mod('custom_logo');
		remove_theme_mod('nav_menu_locations');
		delete_option('sc_page_ids');
		delete_option('sc_service_pages');
		delete_option('sc_contact_form_ref');
		delete_option('elementor_active_kit');
		update_option('show_on_front', 'posts');
		update_option('page_on_front', 0);
		// 4) estado: conservar contador y la marca de install
		$s = self::state();
		$keep = array('version' => 1, 'plan' => $s['plan'] ?? '', 'started' => $s['started'] ?? time(), 'resets' => (int) ($s['resets'] ?? 1),
			'steps' => array('install' => $s['steps']['install'] ?? array('done' => true)), 'notes' => array());
		self::save_state($keep);
		return array('data' => array('deleted' => $n, 'resets' => $keep['resets']), 'more' => false);
	}

	public static function step_status()
	{
		global $wpdb;
		$installed = function_exists('is_blog_installed') && is_blog_installed();
		$out = array(
			'installed' => $installed, 'elementor' => self::elementor_info(),
			'woocommerce' => defined('WC_SIM') ? 'simulado (solo pruebas)' : (class_exists('WooCommerce', false) ? 'presente' : 'ausente'),
		);
		if (!$installed) {
			return $out;
		}
		$s = self::state();
		$km = self::keymap('');
		$count = array('page' => 0, 'asset' => 0, 'prod' => 0, 'menu' => 0, 'kit' => 0, 'other' => 0);
		foreach ($km as $k => $id) {
			$p = strstr($k, ':', true);
			if (isset($count[$p])) {
				$count[$p]++;
			} else {
				$count['other']++;
			}
		}
		$out['state'] = $s;
		$out['counts'] = $count;
		$out['mode'] = get_option('sc_mode');
		$out['theme'] = get_option('stylesheet');
		$out['plugins'] = array_values((array) get_option('active_plugins', array()));
		$out['home'] = home_url('/');
		$out['notes'] = array_values(array_unique(array_merge($s['notes'] ?? array(), self::$notes)));
		$out['done'] = !empty($s['done']);
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Delegados al mu-plugin
	 * ------------------------------------------------------------------ */

	private static function step_qa(array $a)
	{
		if (function_exists('sc_selfcheck')) {
			$r = sc_selfcheck();
			return array('data' => is_array($r) ? $r : array('result' => $r), 'more' => false);
		}
		throw new SC_Builder_Fatal('sc_selfcheck() no está disponible (mu-plugin incompleto)');
	}

	private static function step_publish(array $a)
	{
		if (!function_exists('sc_set_mode')) {
			throw new SC_Builder_Fatal('sc_set_mode() no está disponible');
		}
		$email = (string) ($a['email'] ?? '');
		$name = (string) ($a['name'] ?? '');
		if (!is_email($email)) {
			throw new SC_Builder_Fatal('correo del cliente inválido');
		}
		sc_set_mode('published');
		update_option('blog_public', '1');
		$data = array('mode' => 'published');
		if (function_exists('sc_create_client_user')) {
			$r = sc_create_client_user($email, $name);
			if (is_wp_error($r)) {
				throw new SC_Builder_Fatal('usuario cliente: ' . $r->get_error_message(), true);
			}
			$data['client'] = $r;
		} else {
			throw new SC_Builder_Fatal('sc_create_client_user() no está disponible');
		}
		return array('data' => $data, 'more' => false);
	}

	private static function step_replace_domain(array $a)
	{
		$from = (string) ($a['from'] ?? '');
		$to = (string) ($a['to'] ?? '');
		if ($from === '' || $to === '') {
			throw new SC_Builder_Fatal('faltan --from y --to');
		}
		if (!function_exists('sc_replace_domain')) {
			throw new SC_Builder_Fatal('sc_replace_domain() no está disponible');
		}
		$r = sc_replace_domain($from, $to);
		if (is_wp_error($r)) {
			throw new SC_Builder_Fatal('replace_domain: ' . $r->get_error_message(), true);
		}
		return array('data' => is_array($r) ? $r : array('result' => $r), 'more' => false);
	}
}
