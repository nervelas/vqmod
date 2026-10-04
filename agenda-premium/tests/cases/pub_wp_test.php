<?php
declare(strict_types=1);

// Plugin de WordPress PROBADO CON SIMULACRO (stubs de las funciones de WordPress), no con WordPress real.
require __DIR__ . '/../lib/T.php';

define('ABSPATH', '/simulacro/');
$GLOBALS['__sc'] = [];
function add_shortcode($tag, $cb) { $GLOBALS['__sc'][$tag] = $cb; }
function shortcode_atts($defaults, $atts, $tag = '') { return array_merge($defaults, array_intersect_key((array) $atts, $defaults)); }
function esc_url($u) { return preg_match('#^https?://#i', (string) $u) ? htmlspecialchars((string) $u, ENT_QUOTES, 'UTF-8') : ''; }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function wp_kses($s, $allowed) { return strip_tags((string) $s); }
function current_user_can($cap) { return $GLOBALS['__can'] ?? false; }

require dirname(__DIR__, 2) . '/extras/wordpress/agenda-premium-embed.php';
$run = static fn(array $a): string => (string) call_user_func($GLOBALS['__sc']['agenda_premium'], $a);

T::section('Shortcode');
T::ok(isset($GLOBALS['__sc']['agenda_premium']), 'registra el shortcode agenda_premium');
$h = $run(['url' => 'https://mi-sitio.com/agenda/', 'evento' => 'consulta-general']);
T::ok(str_contains($h, 'src="https://mi-sitio.com/agenda/assets/js/embed.js"') && str_contains($h, 'data-event="consulta-general"') && str_contains($h, 'data-mode="inline"'), 'modo inline por defecto con src y evento');
T::ok(preg_match('/data-target="#(ap-embed-\d+)"/', $h, $m) && str_contains($h, 'id="' . $m[1] . '"'), 'contenedor inline con identificador que coincide con data-target');
$h = $run(['url' => 'https://mi-sitio.com/agenda', 'evento' => 'x1', 'modo' => 'boton', 'texto' => 'Reservar ahora']);
T::ok(str_contains($h, 'data-mode="popup"') && str_contains($h, 'data-label="Reservar ahora"') && !str_contains($h, 'data-target'), 'modo boton = popup con texto');
T::ok(str_contains($run(['url' => 'https://a.com', 'evento' => 'x1', 'modo' => 'flotante']), 'data-mode="float"'), 'modo flotante');
T::ok(str_contains($run(['url' => 'https://a.com', 'evento' => 'x1', 'modo' => 'popup']), 'data-mode="popup"'), 'modo popup');
T::ok(str_contains($run(['url' => 'https://a.com', 'evento' => 'x1', 'modo' => 'raro']), 'data-mode="inline"'), 'modo desconocido cae en inline');

T::section('Escapado y validación');
$h = $run(['url' => 'https://a.com/"><script>alert(1)</script>', 'evento' => 'x1', 'texto' => '"><img src=x onerror=alert(2)>']);
T::ok(!str_contains($h, '<script>alert') && !str_contains($h, '<img src=x') && substr_count($h, '<script') === 1, 'url y texto con intentos de inyección quedan escapados');
T::eq('', $run(['url' => 'javascript:alert(1)', 'evento' => 'x1']), 'url con esquema peligroso → sin salida para visitantes');
T::eq('', $run(['url' => 'https://a.com', 'evento' => 'MAL EVENTO']), 'slug inválido → sin salida');
T::eq('', $run(['evento' => 'x1']), 'sin url → sin salida');
$GLOBALS['__can'] = true;
T::ok(str_contains($run(['evento' => 'x1']), 'Falta el atributo url'), 'editores ven un mensaje de ayuda en español');
$GLOBALS['__can'] = false;
$h = $run(['url' => 'https://a.com', 'evento' => 'x1', 'color' => 'C9A050', 'anfitrion' => 'dra-lopez']);
T::ok(str_contains($h, 'data-color="#C9A050"') && str_contains($h, 'data-host="dra-lopez"'), 'color y anfitrión opcionales');
$h = $run(['url' => 'https://a.com', 'evento' => 'x1', 'color' => 'rojo"onload="x', 'anfitrion' => 'a b"c']);
T::ok(!str_contains($h, 'data-color') && !str_contains($h, 'data-host'), 'color y anfitrión inválidos se descartan');

T::section('Archivos entregables');
$php = (string) file_get_contents(dirname(__DIR__, 2) . '/extras/wordpress/agenda-premium-embed.php');
T::ok(str_contains($php, 'Plugin Name:') && str_contains($php, 'Description:') && str_contains($php, "exit;"), 'cabecera de plugin y protección contra acceso directo');
T::ok(is_file(dirname(__DIR__, 2) . '/extras/wordpress/LEEME.txt'), 'LEEME.txt presente');
$node = trim((string) shell_exec('command -v node'));
if ($node !== '') {
    exec($node . ' --check ' . escapeshellarg(dirname(__DIR__, 2) . '/assets/js/embed.js') . ' 2>&1', $out, $code);
    T::eq(0, $code, 'embed.js tiene sintaxis válida');
    foreach (['booking.js', 'dial.js', 'public.js'] as $f) {
        exec($node . ' --check ' . escapeshellarg(dirname(__DIR__, 2) . '/assets/js/' . $f) . ' 2>&1', $o2, $c2);
        T::eq(0, $c2, $f . ' tiene sintaxis válida');
    }
}
T::done();
