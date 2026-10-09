<?php
/**
 * Servicom builder: formulario de contacto propio [sc_contact_form].
 * Nonce, honeypot, tiempo mínimo, límite por IP (transients), validación,
 * wp_mail al correo del cliente, mensajes en español/inglés. No guarda datos.
 * Se carga en tiempo de ejecución (loader.php); PHP 8.0.
 */
if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('SC_Contact_Form', false)) {
	class SC_Contact_Form
	{
		const ACTION = 'sc_contact_send';
		const MIN_SECONDS = 3;
		const MAX_PER_HOUR = 5;
		const MIN_GAP = 15;

		public static function init()
		{
			add_shortcode('sc_contact_form', array(__CLASS__, 'render'));
			add_action('admin_post_nopriv_' . self::ACTION, array(__CLASS__, 'handle'));
			add_action('admin_post_' . self::ACTION, array(__CLASS__, 'handle'));
		}

		private static function lang()
		{
			return (strpos((string) get_locale(), 'en') === 0) ? 'en' : 'es';
		}

		private static function L($k)
		{
			static $d = array(
				'es' => array(
					'name' => 'Nombre', 'email' => 'Correo electrónico', 'phone' => 'Teléfono (opcional)', 'message' => 'Mensaje',
					'send' => 'Enviar mensaje', 'hp' => 'Dejar este campo vacío',
					'ok' => 'Gracias, hemos recibido su mensaje. Le responderemos pronto.',
					'e_nonce' => 'La página estuvo abierta demasiado tiempo. Actualícela e intente de nuevo.',
					'e_fast' => 'Por favor espere unos segundos e intente de nuevo.',
					'e_limit' => 'Ha enviado varios mensajes seguidos. Intente de nuevo más tarde.',
					'e_name' => 'Escriba su nombre.', 'e_email' => 'Escriba un correo electrónico válido.',
					'e_phone' => 'El teléfono no es válido.', 'e_msg' => 'Escriba un mensaje de al menos 10 caracteres.',
					'e_send' => 'No pudimos enviar su mensaje. Por favor escríbanos por WhatsApp o intente más tarde.',
					'subject' => 'Nuevo mensaje de %s desde el sitio web', 'from' => 'Mensaje enviado desde el formulario de %s',
				),
				'en' => array(
					'name' => 'Name', 'email' => 'Email', 'phone' => 'Phone (optional)', 'message' => 'Message',
					'send' => 'Send message', 'hp' => 'Leave this field empty',
					'ok' => 'Thank you, we received your message. We will reply soon.',
					'e_nonce' => 'This page was open for too long. Please refresh it and try again.',
					'e_fast' => 'Please wait a few seconds and try again.',
					'e_limit' => 'You have sent several messages in a row. Please try again later.',
					'e_name' => 'Please enter your name.', 'e_email' => 'Please enter a valid email address.',
					'e_phone' => 'The phone number is not valid.', 'e_msg' => 'Please write a message of at least 10 characters.',
					'e_send' => 'We could not send your message. Please contact us by WhatsApp or try again later.',
					'subject' => 'New message from %s via the website', 'from' => 'Message sent from the form at %s',
				),
			);
			$l = self::lang();
			return $d[$l][$k] ?? $k;
		}

		private static function stamp()
		{
			$t = time();
			return $t . '.' . substr(hash_hmac('sha256', 'sc-cf|' . $t, wp_salt('nonce')), 0, 20);
		}

		private static function stamp_age($s)
		{
			$p = explode('.', (string) $s);
			if (count($p) !== 2 || !ctype_digit($p[0])) {
				return -1;
			}
			$ok = hash_equals(substr(hash_hmac('sha256', 'sc-cf|' . $p[0], wp_salt('nonce')), 0, 20), (string) $p[1]);
			return $ok ? (time() - (int) $p[0]) : -1;
		}

		private static function ipkey()
		{
			$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0');
			return 'sc_cf_' . substr(hash('sha256', $ip . wp_salt('auth')), 0, 24);
		}

		public static function render($atts = array())
		{
			if (!defined('DONOTCACHEPAGE')) {
				define('DONOTCACHEPAGE', true);
			}
			$msg = isset($_GET['sc_msg']) ? sanitize_key(wp_unslash($_GET['sc_msg'])) : '';
			$old = array('n' => '', 'e' => '', 't' => '', 'm' => '');
			$errs = array();
			if (isset($_GET['sc_r']) && preg_match('/^[a-f0-9]{16}$/', (string) $_GET['sc_r'])) {
				$d = get_transient('sc_cfv_' . $_GET['sc_r']);
				if (is_array($d)) {
					$old = array_merge($old, $d['v'] ?? array());
					$errs = (array) ($d['e'] ?? array());
				}
			}
			$back = function_exists('get_permalink') && get_the_ID() ? get_permalink() : home_url('/');
			$out = '<div class="sc-form-wrap" id="sc-form">';
			if ($msg === 'ok') {
				$out .= '<div class="sc-form__msg sc-form__msg--ok" role="status">' . esc_html(self::L('ok')) . '</div>';
			} elseif ($msg === 'err' && $errs) {
				$out .= '<div class="sc-form__msg sc-form__msg--err" role="alert"><ul>';
				foreach ($errs as $e) {
					$out .= '<li>' . esc_html(self::L($e)) . '</li>';
				}
				$out .= '</ul></div>';
			}
			$out .= '<form class="sc-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" novalidate>';
			$out .= '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '">';
			$out .= wp_nonce_field('sc_contact', 'sc_nonce', false, false);
			$out .= '<input type="hidden" name="sc_ts" value="' . esc_attr(self::stamp()) . '">';
			$out .= '<input type="hidden" name="sc_back" value="' . esc_url($back) . '">';
			$out .= '<div class="sc-form__hp" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden"><label for="sc_website">' . esc_html(self::L('hp')) . '</label><input type="text" id="sc_website" name="sc_website" value="" tabindex="-1" autocomplete="off"></div>';
			$f = function ($id, $label, $type, $val, $extra = '') {
				return '<p class="sc-form__field"><label for="' . $id . '">' . esc_html($label) . '</label><input type="' . $type . '" id="' . $id . '" name="' . $id . '" value="' . esc_attr($val) . '" ' . $extra . '></p>';
			};
			$out .= $f('sc_n', self::L('name'), 'text', $old['n'], 'required maxlength="80" autocomplete="name"');
			$out .= $f('sc_e', self::L('email'), 'email', $old['e'], 'required maxlength="120" autocomplete="email"');
			$out .= $f('sc_t', self::L('phone'), 'tel', $old['t'], 'maxlength="25" autocomplete="tel"');
			$out .= '<p class="sc-form__field"><label for="sc_m">' . esc_html(self::L('message')) . '</label><textarea id="sc_m" name="sc_m" rows="5" required maxlength="3000">' . esc_textarea($old['m']) . '</textarea></p>';
			$out .= '<p class="sc-form__submit"><button type="submit" class="sc-btn sc-btn--primary elementor-button">' . esc_html(self::L('send')) . '</button></p>';
			$out .= '</form></div>';
			return $out;
		}

		private static function done($back, $msg, array $vals = array(), array $errs = array())
		{
			$args = array('sc_msg' => $msg);
			if ($errs) {
				$rid = substr(bin2hex(random_bytes(8)), 0, 16);
				set_transient('sc_cfv_' . $rid, array('v' => $vals, 'e' => $errs), 300);
				$args['sc_r'] = $rid;
			}
			$url = add_query_arg($args, remove_query_arg(array('sc_msg', 'sc_r'), $back)) . '#sc-form';
			wp_safe_redirect($url, 303);
			exit;
		}

		public static function handle()
		{
			$back = isset($_POST['sc_back']) ? esc_url_raw(wp_unslash($_POST['sc_back'])) : '';
			$back = wp_validate_redirect($back, home_url('/'));
			if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
				self::done($back, 'err', array(), array('e_nonce'));
			}
			// 1) nonce
			if (!isset($_POST['sc_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sc_nonce'])), 'sc_contact')) {
				self::done($back, 'err', array(), array('e_nonce'));
			}
			// 2) honeypot: se finge éxito
			if (!empty($_POST['sc_website'])) {
				self::done($back, 'ok');
			}
			// 3) tiempo mínimo
			$age = self::stamp_age(isset($_POST['sc_ts']) ? wp_unslash($_POST['sc_ts']) : '');
			if ($age < 0 || $age > 2 * DAY_IN_SECONDS) {
				self::done($back, 'err', array(), array('e_nonce'));
			}
			$vals = array(
				'n' => trim(preg_replace('/\s+/u', ' ', sanitize_text_field(wp_unslash($_POST['sc_n'] ?? '')))),
				'e' => sanitize_email(wp_unslash($_POST['sc_e'] ?? '')),
				't' => preg_replace('/[^0-9+()\-\s]/', '', (string) wp_unslash($_POST['sc_t'] ?? '')),
				'm' => trim(sanitize_textarea_field(wp_unslash($_POST['sc_m'] ?? ''))),
			);
			$vals['n'] = mb_substr($vals['n'], 0, 80);
			$vals['t'] = mb_substr(trim($vals['t']), 0, 25);
			$vals['m'] = mb_substr($vals['m'], 0, 3000);
			if ($age < self::MIN_SECONDS) {
				self::done($back, 'err', $vals, array('e_fast'));
			}
			// 4) validación
			$errs = array();
			if (mb_strlen($vals['n']) < 2) {
				$errs[] = 'e_name';
			}
			if (!is_email($vals['e'])) {
				$errs[] = 'e_email';
			}
			if ($vals['t'] !== '' && strlen(preg_replace('/\D/', '', $vals['t'])) < 6) {
				$errs[] = 'e_phone';
			}
			if (mb_strlen($vals['m']) < 10) {
				$errs[] = 'e_msg';
			}
			if ($errs) {
				self::done($back, 'err', $vals, $errs);
			}
			// 5) enlaces en exceso = spam (se finge éxito)
			if (preg_match_all('~https?://|www\.~i', $vals['m']) > 3) {
				self::done($back, 'ok');
			}
			// 6) límite por IP
			$k = self::ipkey();
			$hist = get_transient($k);
			$hist = is_array($hist) ? array_filter($hist, function ($t) {
				return $t > time() - HOUR_IN_SECONDS;
			}) : array();
			if (count($hist) >= self::MAX_PER_HOUR || ($hist && (time() - max($hist)) < self::MIN_GAP)) {
				self::done($back, 'err', $vals, array('e_limit'));
			}
			// 7) envío
			$to = function_exists('sc_biz') ? (string) sc_biz('correo') : (string) get_theme_mod('sc_correo', '');
			if (!is_email($to)) {
				$to = (string) get_option('admin_email');
			}
			$site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
			$subject = sprintf(self::L('subject'), $vals['n']);
			$subject = str_replace(array("\r", "\n"), ' ', '[' . $site . '] ' . $subject);
			$body = self::L('name') . ': ' . $vals['n'] . "\n" . self::L('email') . ': ' . $vals['e'] . "\n"
				. ($vals['t'] !== '' ? self::L('phone') . ': ' . $vals['t'] . "\n" : '')
				. "\n" . $vals['m'] . "\n\n--\n" . sprintf(self::L('from'), home_url('/'));
			$name_h = str_replace(array("\r", "\n", '<', '>', '"', ','), ' ', $vals['n']);
			$headers = array('Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $name_h . ' <' . $vals['e'] . '>');
			$sent = wp_mail($to, $subject, $body, $headers);
			if (!$sent) {
				self::done($back, 'err', $vals, array('e_send'));
			}
			$hist[] = time();
			set_transient($k, array_values($hist), HOUR_IN_SECONDS);
			self::done($back, 'ok');
		}
	}

	SC_Contact_Form::init();
}
