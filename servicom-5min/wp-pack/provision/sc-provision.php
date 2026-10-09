<?php
/**
 * Servicom "Tu web en 5 minutos" - runner de aprovisionamiento de WordPress.
 *
 * Se copia a la raíz del sitio (junto a wp-load.php) durante la construcción
 * y se BORRA al terminar (lo hace el portal).
 *
 *   CLI : php sc-provision.php <jobdir> <step> [--arg=valor ...]
 *   HTTP: sc-provision.php?job=<id>&step=<step>&ts=<unix>&sig=<hmac_sha256(secret,"step|job|ts")>
 *         secret = wp-content/sc-jobs/secret.php (return 'hex';). ts +-120 s.
 *         Argumentos extra por GET (email, name, from, to, budget). Opcional: `asig` =
 *         hmac_sha256(secret, "step|job|ts|" . http_build_query(argumentos ordenados, '', '&', PHP_QUERY_RFC3986))
 *         que, si se envía, se verifica y fija los argumentos a la firma.
 *
 * Salida: UNA línea JSON  {"ok":true,"step":..,"data":{..},"more":false}
 *                      o  {"ok":false,"error":"mensaje corto","retry":true|false}
 * "more":true = volver a llamar al mismo paso (presupuesto de tiempo agotado).
 * Pasos: install media pages store finish qa publish replace_domain reset status.
 * Código compatible con PHP 8.0.
 */

@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
error_reporting(E_ALL);

$GLOBALS['SC_PROV'] = array(
	'out' => false,
	'http' => (PHP_SAPI !== 'cli'),
	'step' => '',
	'warnings' => array(),
	'root' => __DIR__,
);

function sc_prov_clean($s, $max = 200)
{
	$s = (string) $s;
	$root = $GLOBALS['SC_PROV']['root'];
	$s = str_replace(array($root . '/', $root, defined('ABSPATH') ? ABSPATH : "\0"), '', $s);
	$s = trim(preg_replace('/\s+/', ' ', strip_tags($s)));
	if (function_exists('mb_substr')) {
		return mb_substr($s, 0, $max);
	}
	return substr($s, 0, $max);
}

function sc_prov_emit(array $j, $status = 200)
{
	$P = &$GLOBALS['SC_PROV'];
	if ($P['out']) {
		return;
	}
	$P['out'] = true;
	while (ob_get_level() > 0) {
		@ob_end_clean();
	}
	if ($P['http'] && !headers_sent()) {
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		header('X-Robots-Tag: noindex');
	}
	if (!$P['http'] && !empty($P['warnings']) && !empty($j['ok'])) {
		$j['data']['_warnings'] = array_slice($P['warnings'], 0, 20);
	}
	$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;
	echo json_encode($j, $flags) . "\n";
}

function sc_prov_fail($msg, $retry = false, $status = 200)
{
	sc_prov_emit(array('ok' => false, 'error' => sc_prov_clean($msg), 'retry' => (bool) $retry), $status);
	if (!$GLOBALS['SC_PROV']['http']) {
		exit(1);
	}
	exit;
}

class SC_Prov_Die extends RuntimeException
{
}

function sc_prov_die_handler($message, $title = '', $args = array())
{
	if (is_wp_error($message)) {
		$message = $message->get_error_message();
	}
	throw new SC_Prov_Die(is_string($message) ? $message : 'wp_die');
}

ob_start();
register_shutdown_function(function () {
	$P = &$GLOBALS['SC_PROV'];
	if ($P['out']) {
		return;
	}
	$e = error_get_last();
	$buf = '';
	while (ob_get_level() > 0) {
		$buf .= (string) @ob_get_clean();
	}
	if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), true)) {
		sc_prov_emit(array('ok' => false, 'error' => 'error fatal: ' . sc_prov_clean($e['message'], 160), 'retry' => true));
		return;
	}
	$hint = sc_prov_clean($buf, 140);
	sc_prov_emit(array('ok' => false, 'error' => 'WordPress no pudo iniciar' . ($hint !== '' ? ': ' . $hint : ''), 'retry' => false));
});

set_error_handler(function ($no, $str, $file, $line) {
	if (!(error_reporting() & $no)) {
		return true;
	}
	$GLOBALS['SC_PROV']['warnings'][] = array(
		'type' => $no, 'msg' => sc_prov_clean($str, 160), 'at' => basename((string) $file) . ':' . (int) $line,
	);
	return true;
});

/* ---------------- entrada ---------------- */

$P = &$GLOBALS['SC_PROV'];
$args = array();
if ($P['http']) {
	$job = (string) ($_GET['job'] ?? '');
	$step = (string) ($_GET['step'] ?? '');
	$ts = (string) ($_GET['ts'] ?? '');
	$sig = (string) ($_GET['sig'] ?? '');
	$denied = function () {
		sc_prov_emit(array('ok' => false, 'error' => 'forbidden', 'retry' => false), 403);
		exit;
	};
	if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $job) || !preg_match('/^[a-z_]{2,20}$/', $step) || !ctype_digit($ts) || !preg_match('/^[a-f0-9]{64}$/', $sig)) {
		$denied();
	}
	$secretFile = __DIR__ . '/wp-content/sc-jobs/secret.php';
	$secret = is_file($secretFile) ? (function ($f) {
		return include $f;
	})($secretFile) : '';
	if (!is_string($secret) || strlen($secret) < 16) {
		$denied();
	}
	$expected = hash_hmac('sha256', $step . '|' . $job . '|' . $ts, $secret);
	if (!hash_equals($expected, $sig) || abs(time() - (int) $ts) > 120) {
		$denied();
	}
	foreach (array('email', 'name', 'from', 'to', 'budget') as $k) {
		if (isset($_GET[$k]) && is_string($_GET[$k])) {
			$args[$k] = $_GET[$k];
		}
	}
	if (isset($_GET['asig'])) {
		ksort($args);
		$asig = hash_hmac('sha256', $step . '|' . $job . '|' . $ts . '|' . http_build_query($args, '', '&', PHP_QUERY_RFC3986), $secret);
		if (!is_string($_GET['asig']) || !hash_equals($asig, $_GET['asig'])) {
			$denied();
		}
	}
	$jobdir = __DIR__ . '/wp-content/sc-jobs/' . $job;
} else {
	$argv = $_SERVER['argv'] ?? array();
	if (count($argv) < 3) {
		sc_prov_fail('uso: php sc-provision.php <jobdir> <step> [--arg=valor]');
	}
	$jobdir = $argv[1];
	$step = $argv[2];
	for ($i = 3; $i < count($argv); $i++) {
		if (preg_match('/^--([A-Za-z0-9_]+)=(.*)$/s', $argv[$i], $mm)) {
			$args[$mm[1]] = $mm[2];
		}
	}
}
$P['step'] = $step;
$known = array('install', 'media', 'pages', 'store', 'finish', 'qa', 'publish', 'replace_domain', 'reset', 'status');
if (!in_array($step, $known, true)) {
	sc_prov_fail('paso desconocido');
}
$real = @realpath($jobdir);
if ($real === false || !is_dir($real)) {
	if (!in_array($step, array('status', 'qa', 'publish', 'replace_domain'), true)) {
		sc_prov_fail('carpeta del trabajo no encontrada');
	}
	$real = (string) $jobdir;
}
$jobdir = $real;

$budget = isset($args['budget']) ? max(5, min(900, (int) $args['budget'])) : ($P['http'] ? 20 : 90);
@set_time_limit($budget + 45);
@ignore_user_abort(true);
@ini_set('memory_limit', '384M');

/* ---------------- WordPress ---------------- */

define('SC_PROVISIONING', true);
define('WP_USE_THEMES', false);
// CLI: sin servidor web, WordPress deduciría la URL de la ruta del script. Se toma del manifiesto.
if (!$P['http'] && is_readable($jobdir . '/manifest.json')) {
	$mj = json_decode((string) @file_get_contents($jobdir . '/manifest.json'), true);
	$su = is_array($mj) ? (string) ($mj['site']['url'] ?? '') : '';
	$pu = $su !== '' ? parse_url($su) : false;
	if ($pu && !empty($pu['host']) && in_array($pu['scheme'] ?? '', array('http', 'https'), true)) {
		$port = $pu['port'] ?? (($pu['scheme'] === 'https') ? 443 : 80);
		$_SERVER['HTTP_HOST'] = $pu['host'] . (isset($pu['port']) ? ':' . $pu['port'] : '');
		$_SERVER['SERVER_NAME'] = $pu['host'];
		$_SERVER['SERVER_PORT'] = (string) $port;
		$_SERVER['REQUEST_URI'] = '/';
		if ($pu['scheme'] === 'https') {
			$_SERVER['HTTPS'] = 'on';
		}
	}
}
if ($step === 'install' && !defined('WP_INSTALLING')) {
	define('WP_INSTALLING', true);
}
$wpload = __DIR__ . '/wp-load.php';
if (!is_file($wpload)) {
	sc_prov_fail('wp-load.php no encontrado');
}

try {
	require_once $wpload;
	if (!function_exists('add_filter')) {
		sc_prov_fail('WordPress no se cargó');
	}
	add_filter('wp_die_handler', function () {
		return 'sc_prov_die_handler';
	}, 99);
	add_filter('wp_die_ajax_handler', function () {
		return 'sc_prov_die_handler';
	}, 99);
	add_filter('pre_wp_mail', '__return_true');   // el aprovisionamiento nunca envía correos

	if (!class_exists('SC_Builder', false)) {
		$base = (defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins') . '/servicom-core/includes/builder/';
		if (!is_readable($base . 'class-sc-builder.php')) {
			sc_prov_fail('constructor no encontrado en el mu-plugin');
		}
		require_once $base . 'class-sc-builder.php';
		if (!defined('SC_BUILDER_LOADED') && is_readable($base . 'loader.php')) {
			require_once $base . 'loader.php';
		}
	}
	SC_Builder::boot($jobdir, $budget);
	$r = SC_Builder::run($step, $args);
	sc_prov_emit(array(
		'ok' => true, 'step' => $step, 'data' => $r['data'] ?? array(), 'more' => !empty($r['more']),
	));
	if (!$P['http']) {
		exit(0);
	}
} catch (SC_Builder_Fatal $e) {
	sc_prov_fail($e->getMessage(), $e->retry);
} catch (Throwable $e) {
	sc_prov_fail(get_class($e) === 'SC_Prov_Die' ? $e->getMessage() : $e->getMessage(), true);
}
