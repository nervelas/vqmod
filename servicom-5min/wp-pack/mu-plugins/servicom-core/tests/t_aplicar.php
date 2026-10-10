<?php
/**
 * Kit de cambios (kit-cambios/plantillas/aplicar-cambios.php): el archivo se ejecuta UNA vez al cargar la web,
 * aplica las operaciones del editor, se borra solo y no se repite. Un lote inválido no rompe nada.
 */
require __DIR__ . '/lib.php';
$tpl = dirname(__DIR__, 4) . '/kit-cambios/plantillas/aplicar-cambios.php';
ok(is_file($tpl), 'existe la plantilla del kit');
$mu = WPMU_PLUGIN_DIR;
function site2(): array { wp_cache_delete('sc_site', 'options'); wp_cache_delete('alloptions', 'options'); wp_cache_delete('notoptions', 'options'); return get_option('sc_site', array()); }
/** Carga WordPress en OTRO proceso (como cuando alguien abre la web) para que el mu-plugin de cambios se ejecute. */
function abrir_web(): void { global $ENV; $h = parse_url($ENV['url'], PHP_URL_HOST) . ':' . parse_url($ENV['url'], PHP_URL_PORT);
    shell_exec('HTTP_HOST=' . escapeshellarg($h) . ' php -r ' . escapeshellarg('$_SERVER["HTTP_HOST"]=getenv("HTTP_HOST");$_SERVER["REQUEST_URI"]="/";$_SERVER["REMOTE_ADDR"]="127.0.0.1";require "' . $GLOBALS['ENV']['site'] . '/wp-load.php";') . ' 2>&1'); }
function hacer(string $id, string $lotes, string $extra = ''): string {
    global $tpl, $mu;
    $c = file_get_contents($tpl);
    $c = str_replace("'sc_cambio_AAAAMMDD_HHMM'", "'sc_cambio_$id'", $c);
    $c = preg_replace('/\$lotes = array\(.*?\n\t\);/s', "\$lotes = array(\n$lotes\n\t);", $c, 1);
    $c = str_replace("\t// set_theme_mod( 'nombre', 'valor' );", $extra, $c);
    $f = "$mu/sc-cambios-$id.php"; file_put_contents($f, $c); return $f;
}
$orig = get_option('sc_site', null);
$img = array('id' => 0, 'seed' => 7); $btn = fn($t, $u) => array('text' => $t, 'url' => $u);
update_option('sc_site', array('v' => 1, 'design' => array('mood' => 'dark'), 'brand' => array('nombre' => 'Prueba Kit'),
    'pages' => array(
        'home' => array('sections' => array(
            array('id' => 'inicio-hero', 'type' => 'hero', 'on' => true, 'data' => array('eyebrow' => 'x', 'title' => 'Título original', 'sub' => 'Sub', 'btn1' => $btn('Escríbanos', 'wa'), 'btn2' => $btn('Servicios', '#servicios'), 'img' => $img, 'slides' => array(), 'side' => $img, 'badge_icon' => 'crown')),
            array('id' => 'servicios', 'type' => 'services', 'on' => true, 'data' => array('eyebrow' => 'x', 'title' => 'Servicios', 'lead' => '', 'limit' => 6, 'more' => 'Ver más', 'btn' => $btn('Ver todos', 'page:servicios'))),
        )),
        'servicios' => array('sections' => array(array('id' => 'servicios', 'type' => 'services', 'on' => true, 'data' => array('limit' => 0, 'more' => 'Ver más')))),
    ),
    'services' => array(), 'footer' => array('texto' => '', 'credit' => '')), false);
$site = site2(); $secs = $site['pages']['home']['sections'] ?? array();
$hi = null; foreach ($secs as $i => $s) { if (($s['type'] ?? '') === 'hero') { $hi = $i; break; } }
ok($hi !== null, 'el sitio de pruebas tiene portada (hero)');
$tel0 = (string) get_theme_mod('sc_telefono', '');

section('Aplica los cambios y se borra sola');
$f = hacer('t1', "\t\tarray(\n\t\t\tarray( 'op' => 'text', 'path' => 'pages.home.sections.$hi.data.title', 'value' => 'Título cambiado por el kit' ),\n\t\t\tarray( 'op' => 'biz', 'values' => array( 'telefono' => '2299-1100', 'horario' => 'Lunes a viernes 8:00 a 17:00' ) ),\n\t\t\tarray( 'op' => 'svc_add', 'nombre' => 'Servicio agregado por el kit' ),\n\t\t),", "\tset_theme_mod( 'sc_kit_prueba', 'ok' );");
abrir_web();
$s2 = site2(); wp_cache_delete('theme_mods_' . get_option('stylesheet'), 'options');
ok(($s2['pages']['home']['sections'][$hi]['data']['title'] ?? '') === 'Título cambiado por el kit', 'el texto se cambió');
ok((string) get_theme_mod('sc_telefono') === '2299-1100' || sc_biz('telefono') === '2299-1100', 'el teléfono se cambió');
$nombres = array_column($s2['services'] ?? array(), 'nombre');
ok(in_array('Servicio agregado por el kit', $nombres, true), 'el servicio nuevo existe');
ok(!is_file($f), 'el archivo se borró solo');
ok((int) get_option('sc_cambio_t1') > 0, 'quedó registrado que ya se aplicó');
ok(get_theme_mod('sc_kit_prueba') === 'ok', 'las acciones extra también corren');

section('No se repite');
$f2 = hacer('t1', "\t\tarray( array( 'op' => 'svc_add', 'nombre' => 'No debe duplicarse' ) ),");
abrir_web();
$s3 = site2();
ok(!in_array('No debe duplicarse', array_column($s3['services'] ?? array(), 'nombre'), true), 'un ID ya aplicado no vuelve a ejecutarse');
ok(!is_file($f2), 'y el archivo sobrante también se borra');

section('Un lote inválido no rompe la web y se puede reintentar');
$f3 = hacer('t2', "\t\tarray( array( 'op' => 'text', 'path' => 'pages.home.sections.999.data.title', 'value' => 'x' ) ),");
abrir_web();
ok(is_file($f3), 'si falla, el archivo se conserva');
ok((string) get_option('sc_cambio_t2_error') !== '', 'queda el motivo: ' . get_option('sc_cambio_t2_error'));
ok(!get_option('sc_cambio_t2'), 'no se marca como aplicado');
ok(site2() === $s3, 'el sitio no cambió');
$h = http($ENV['url'] . '/'); ok($h['code'] === 200, 'la web sigue respondiendo 200');
@unlink($f3);

// limpieza: el servicio que creó el kit también crea su página; se elimina para no dejar nada en el sitio de pruebas
foreach (array_merge((array) ($s2['services'] ?? array()), (array) ($s3['services'] ?? array())) as $sv) { if (($sv['nombre'] ?? '') === 'Servicio agregado por el kit' && !empty($sv['post'])) { wp_delete_post((int) $sv['post'], true); } }
// restaurar
if ($orig === null) { delete_option('sc_site'); } else { update_option('sc_site', $orig); }
delete_option('sc_cambio_t1'); delete_option('sc_cambio_t2_error'); delete_option('sc_cambio_t1_lote'); remove_theme_mod('sc_kit_prueba');
done();
