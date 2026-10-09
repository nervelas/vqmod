<?php
/**
 * Servicom builder: formulario de contacto.
 * Si Contact Form 7 está activo se crea un formulario nativo y se verifica que
 * su shortcode renderice un <form>; en cualquier otro caso (o si algo falla)
 * se usa el formulario propio [sc_contact_form] (contact-form.php), que es
 * siempre el respaldo.
 * Fluent Forms: su estructura de datos interna no se puede crear de forma
 * fiable sin el plugin real, por lo que se usa el formulario propio.
 */
if (!defined('ABSPATH')) {
	exit;
}

class SC_Build_Forms
{
	public static function ensure(array $m, $lang)
	{
		$own = '[sc_contact_form]';
		delete_option('sc_contact_form_ref');
		if (!class_exists('WPCF7_ContactForm', false) && !defined('WPCF7_VERSION')) {
			return $own;
		}
		try {
			$id = self::cf7($m, $lang);
			if ($id) {
				$sc = '[contact-form-7 id="' . (int) $id . '" title="Contacto"]';
				$html = (string) do_shortcode($sc);
				if (stripos($html, '<form') !== false) {
					update_option('sc_contact_form_ref', array('provider' => 'cf7', 'id' => $id));
					return $sc;
				}
				SC_Builder::note('CF7 no renderizó el formulario; se usa el formulario propio');
			}
		} catch (Throwable $e) {
			SC_Builder::note('CF7 falló: ' . substr($e->getMessage(), 0, 100));
		}
		return $own;
	}

	private static function cf7(array $m, $lang)
	{
		$es = ($lang !== 'en');
		$to = trim((string) ($m['business']['correo_contacto'] ?? ''));
		if (!is_email($to)) {
			return 0;
		}
		$L = $es
			? array('name' => 'Nombre', 'mail' => 'Correo electrónico', 'tel' => 'Teléfono (opcional)', 'msg' => 'Mensaje', 'send' => 'Enviar mensaje',
				'subj' => 'Mensaje desde el sitio web', 'ok' => 'Gracias, hemos recibido su mensaje. Le responderemos pronto.',
				'ko' => 'No pudimos enviar su mensaje. Por favor intente de nuevo.', 'val' => 'Revise los campos marcados e intente de nuevo.', 'req' => 'Este campo es obligatorio.')
			: array('name' => 'Name', 'mail' => 'Email', 'tel' => 'Phone (optional)', 'msg' => 'Message', 'send' => 'Send message',
				'subj' => 'Message from the website', 'ok' => 'Thank you, we received your message. We will reply soon.',
				'ko' => 'We could not send your message. Please try again.', 'val' => 'Please review the marked fields and try again.', 'req' => 'This field is required.');
		$form = '<label>' . $L['name'] . ' [text* your-name autocomplete:name]</label>' . "\n"
			. '<label>' . $L['mail'] . ' [email* your-email autocomplete:email]</label>' . "\n"
			. '<label>' . $L['tel'] . ' [tel your-tel autocomplete:tel]</label>' . "\n"
			. '<label>' . $L['msg'] . ' [textarea* your-message]</label>' . "\n"
			. '<div style="position:absolute;left:-9999px" aria-hidden="true">[text sc_website tabindex:-1 autocomplete:off]</div>' . "\n"
			. '[submit "' . $L['send'] . '"]';
		$nombre = (string) ($m['business']['nombre'] ?? 'Sitio');
		$mail = array(
			'active' => true,
			'subject' => '[' . $nombre . '] ' . $L['subj'] . ': [your-name]',
			'sender' => $nombre . ' <wordpress@' . self::host() . '>',
			'recipient' => $to,
			'body' => $L['name'] . ": [your-name]\n" . $L['mail'] . ": [your-email]\n" . $L['tel'] . ": [your-tel]\n\n[your-message]",
			'additional_headers' => 'Reply-To: [your-email]',
			'attachments' => '',
			'use_html' => 0,
			'exclude_blank' => 0,
		);
		$mail2 = $mail;
		$mail2['active'] = false;
		$messages = array(
			'mail_sent_ok' => $L['ok'], 'mail_sent_ng' => $L['ko'], 'validation_error' => $L['val'], 'spam' => $L['ko'],
			'invalid_required' => $L['req'], 'invalid_too_long' => $L['val'], 'invalid_too_short' => $L['val'],
		);
		$known = SC_Builder::keymap('form:');
		return SC_Builder::ensure_post('form:cf7', array(
			'post_type' => 'wpcf7_contact_form', 'post_status' => 'publish', 'post_title' => $es ? 'Contacto' : 'Contact', 'post_content' => '',
		), array(
			'_form' => $form, '_mail' => $mail, '_mail_2' => $mail2, '_messages' => $messages,
			'_additional_settings' => '', '_locale' => $es ? 'es_ES' : 'en_US',
		), $known);
	}

	private static function host()
	{
		$h = wp_parse_url(home_url(), PHP_URL_HOST);
		return $h ?: 'localhost';
	}
}
