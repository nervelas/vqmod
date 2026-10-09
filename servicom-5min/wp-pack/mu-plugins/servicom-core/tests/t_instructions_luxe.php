<?php
/**
 * INSTRUCCIONES para el motor Luxe: cada enlace al editor debe apuntar a una ruta que EXISTA en `sc_site`.
 * El sitio de muestra tiene la misma forma que builder/luxe.php (ids de sección, services.N.*).
 */
require __DIR__ . '/lib.php';
$U = $ENV['url'];
wp_set_current_user(1);

function sample_site(array $drop = []): array {
    $img = ['id' => 0, 'seed' => 7];
    $btn = fn($t, $u) => ['text' => $t, 'url' => $u];
    $home = [
        ['id' => 'inicio-hero', 'type' => 'hero', 'on' => true, 'data' => ['eyebrow' => 'Despacho', 'title' => 'Bienvenidos', 'sub' => 'Sub', 'btn1' => $btn('Escríbanos', 'wa'), 'btn2' => $btn('Servicios', '#servicios'), 'img' => $img, 'slides' => [], 'side' => $img, 'badge_icon' => 'crown']],
        ['id' => 'franja', 'type' => 'strip', 'on' => true, 'data' => ['items' => ['A', 'B', 'C']]],
        ['id' => 'nosotros', 'type' => 'about', 'on' => true, 'data' => ['side' => 'left', 'eyebrow' => 'Nosotros', 'title' => 'Quiénes somos', 'lead' => 'L', 'text' => 'T', 'points' => [], 'img' => $img, 'seal_icon' => 'award', 'btn' => $btn('Conózcanos', 'page:nosotros')]],
        ['id' => 'servicios', 'type' => 'services', 'on' => true, 'data' => ['eyebrow' => 'x', 'title' => 'Nuestros servicios', 'lead' => '', 'limit' => 6, 'more' => 'Ver más', 'btn' => $btn('Ver todos', 'page:servicios')]],
        ['id' => 'valores', 'type' => 'values', 'on' => true, 'data' => ['eyebrow' => 'x', 'title' => 'Por qué', 'lead' => '', 'items' => [['icon' => 'shield', 'title' => 'a', 'text' => 'b']]]],
        ['id' => 'cita', 'type' => 'quote', 'on' => true, 'data' => ['text' => 'Frase', 'by' => 'N', 'img' => $img]],
        ['id' => 'proceso', 'type' => 'process', 'on' => true, 'data' => ['eyebrow' => 'x', 'title' => 'Proceso', 'lead' => '', 'items' => [['title' => 'a', 'text' => 'b']]]],
        ['id' => 'galeria', 'type' => 'gallery', 'on' => true, 'data' => ['eyebrow' => '', 'title' => 'Galería', 'lead' => '', 'items' => [$img, $img, $img]]],
        ['id' => 'video', 'type' => 'video', 'on' => true, 'data' => ['eyebrow' => '', 'title' => 'Video', 'lead' => '', 'url' => 'https://youtu.be/dQw4w9WgXcQ']],
        ['id' => 'preguntas', 'type' => 'faq', 'on' => true, 'data' => ['eyebrow' => '', 'title' => 'FAQ', 'lead' => '', 'items' => [['q' => '¿Q?', 'a' => 'R'], ['q' => '¿Q2?', 'a' => 'R2']]]],
        ['id' => 'cta', 'type' => 'cta', 'on' => true, 'data' => ['eyebrow' => '', 'title' => 'Hablemos', 'text' => 'Texto', 'btn1' => $btn('Contáctenos', 'wa'), 'btn2' => $btn('Llámenos', 'tel'), 'img' => $img]],
        ['id' => 'contacto', 'type' => 'contact', 'on' => true, 'data' => ['eyebrow' => '', 'title' => 'Hablemos', 'lead' => 'Lead', 'form_title' => 'Envíenos un mensaje']],
        ['id' => 'productos', 'type' => 'products', 'on' => true, 'data' => ['eyebrow' => '', 'title' => 'Productos', 'lead' => '', 'limit' => 8, 'btn' => $btn('Tienda', 'page:tienda')]],
    ];
    $site = ['v' => 1, 'design' => ['mood' => 'dark'], 'brand' => ['nombre' => 'Bufete'],
        'pages' => [
            'home' => ['sections' => $home],
            'galeria' => ['sections' => [['id' => 'cabecera', 'type' => 'pagehero', 'on' => true, 'data' => ['title' => 'Galería', 'img' => $img]], ['id' => 'galeria', 'type' => 'gallery', 'on' => true, 'data' => ['items' => [$img]]]]],
            'servicios' => ['sections' => [['id' => 'cabecera', 'type' => 'pagehero', 'on' => true, 'data' => ['title' => 'S', 'img' => $img]], ['id' => 'servicios', 'type' => 'services', 'on' => true, 'data' => ['limit' => 0, 'more' => 'Ver más']]]],
            'contacto' => ['sections' => [['id' => 'cabecera', 'type' => 'pagehero', 'on' => true, 'data' => ['title' => 'C', 'img' => $img]], ['id' => 'contacto', 'type' => 'contact', 'on' => true, 'data' => ['form_title' => 'F']]]],
        ],
        'services' => [
            ['id' => 's1', 'nombre' => 'Divorcios', 'resumen' => 'r', 'descripcion' => 'd', 'icono' => 'scales', 'img' => $img, 'post' => 9, 'origen' => 'form', 'on' => true],
            ['id' => 's2', 'nombre' => 'Contratos', 'resumen' => 'r', 'descripcion' => 'd', 'icono' => 'contract', 'img' => $img, 'post' => 10, 'origen' => 'form', 'on' => true],
        ],
        'footer' => ['texto' => '', 'credit' => '']];
    foreach ($drop as $id) { // quita secciones por id (de todas las páginas) para probar ausencias
        foreach ($site['pages'] as $k => $p) { $site['pages'][$k]['sections'] = array_values(array_filter($p['sections'], fn($s) => $s['id'] !== $id)); }
    }
    return $site;
}

/** Todas las rutas sc_focus de las tarjetas deben existir en $site; todo enlace debe tener forma válida. */
function broken_links(array $cards, array $site): array {
    $errs = [];
    foreach ($cards as $c) {
        foreach ($c['buttons'] ?? [] as $b) {
            $u = $b['url'] ?? '';
            if ($u === '' || trim($b['label'] ?? '') === '') { $errs[] = "{$c['id']}: botón vacío"; continue; }
            if (!preg_match('#^https?://#', $u)) { $errs[] = "{$c['id']}: URL no absoluta $u"; continue; }
            $q = []; parse_str((string) parse_url($u, PHP_URL_QUERY), $q);
            if (isset($q['sc_focus'])) {
                if (($q['sc_edit'] ?? '') !== '1') { $errs[] = "{$c['id']}: sin sc_edit=1 ($u)"; }
                if (!sc_ins_has($site, $q['sc_focus'])) { $errs[] = "{$c['id']}: ruta inexistente {$q['sc_focus']}"; }
                if (($b['focus'] ?? '') !== $q['sc_focus']) { $errs[] = "{$c['id']}: focus != URL"; }
            }
        }
    }
    return $errs;
}

$orig = get_option('sc_site', null); $origPlan = get_option('sc_plan', '');
update_option('sc_plan', 'info');
$site = sample_site();
update_option('sc_site', $site); sc_site_reload_safe();
function sc_site_reload_safe(): void { if (function_exists('sc_site_reload')) { sc_site_reload(); } }

section('Rutas y URL del editor');
eq(sc_ins_editor_url('pages.home.sections.0.data.title', $site), home_url('/') . '?sc_edit=1&sc_focus=pages.home.sections.0.data.title', 'título del hero -> /?sc_edit=1&sc_focus=ruta');
eq(sc_ins_editor_url('', $site), home_url('/') . '?sc_edit=1', 'sin ruta -> /?sc_edit=1');
ok(str_starts_with(sc_ins_editor_url('services.1.nombre', $site), get_permalink((int) get_option('sc_page_ids')['servicios'])), 'services.N.* -> página Servicios (lista completa)');
ok(str_starts_with(sc_ins_editor_url('pages.galeria.sections.1', $site), get_permalink((int) get_option('sc_page_ids')['galeria'])), 'pages.galeria.* -> página Galería');
eq(sc_ins_first($site, 'hero')['base'], 'pages.home.sections.0', 'hero = sección 0 de inicio');
eq(sc_ins_first($site, 'faq')['base'], 'pages.home.sections.9', 'faq = sección 9 (misma forma que luxe.php)');
ok(sc_ins_has($site, 'services.1.icono') && !sc_ins_has($site, 'services.2.icono') && !sc_ins_has($site, 'pages.home.sections.99'), 'sc_ins_has distingue rutas existentes');

section('Tarjetas con sitio completo');
$cards = sc_instruction_cards();
$ids = array_column($cards, 'id');
foreach (['primer-paso', 'guia-visual', 'deshacer', 'portada-titulo', 'portada-imagen', 'textos', 'icono-servicio', 'servicio-editar', 'servicio-agregar', 'servicio-eliminar', 'galeria', 'faq', 'video', 'botones', 'secciones', 'menu', 'logo', 'colores', 'contacto', 'redes', 'formulario', 'password', 'renovacion', 'ayuda'] as $need) { ok(in_array($need, $ids, true), "tarjeta $need"); }
ok(!array_intersect(['productos', 'pedidos', 'pagos'], $ids), 'plan info: sin tarjetas de tienda');
$errs = broken_links($cards, $site);
ok(!$errs, 'ningún enlace roto: toda ruta sc_focus existe en sc_site', implode('; ', $errs));
$byId = array_column($cards, null, 'id');
eq($byId['portada-titulo']['buttons'][0]['url'], home_url('/') . '?sc_edit=1&sc_focus=pages.home.sections.0.data.title', 'portada-titulo -> título del hero');
eq($byId['portada-imagen']['buttons'][0]['url'], home_url('/') . '?sc_edit=1&sc_focus=pages.home.sections.0.data.img', 'portada-imagen -> imagen del hero');
eq($byId['video']['buttons'][0]['focus'], 'pages.home.sections.8.data.url', 'video -> enlace del video');
eq($byId['icono-servicio']['buttons'][0]['focus'], 'services.0.icono', 'ícono de servicio -> services.0.icono');
eq($byId['servicio-editar']['buttons'][1]['label'], 'Contratos', 'un botón por servicio real');
$errs = [];
foreach ($cards as $c) { $n = count($c['steps']); if ($n < 2 || $n > 4) { $errs[] = "{$c['id']}: $n pasos"; } if (trim($c['title']) === '' || trim($c['lead'] ?? 'x') === '') { $errs[] = "{$c['id']}: título/lead vacío"; } if (!isset($c['buttons'])) { $errs[] = "{$c['id']}: sin buttons"; } }
ok(!$errs, 'cada tarjeta: 2–4 pasos, título y resumen', implode('; ', $errs));
foreach ($cards as $c) { foreach (array_merge([$c['title'], $c['lead'] ?? ''], $c['steps']) as $t) { if (preg_match('/\b(tú|tu|tus|haz|elige|pulsa|puedes|quieres)\b/u', $t)) { $errs[] = $c['id'] . ': ' . $t; } } }
ok(!$errs, 'texto en español de usted (sin tuteo)', implode('; ', $errs));
$errs = []; $seen = [];
foreach ($ids as $i) { if (isset($seen[$i])) { $errs[] = "id repetido $i"; } $seen[$i] = 1; }
ok(!$errs, 'ids de tarjeta únicos');

section('Botones sólo si el elemento existe');
foreach ([['video', 'video'], ['preguntas', 'faq'], ['galeria', 'galeria']] as [$drop, $card]) {
    $s2 = sample_site([$drop]); update_option('sc_site', $s2); sc_site_reload_safe();
    $c2 = sc_instruction_cards();
    if ($drop === 'galeria') { ok(!in_array('galeria', array_column($c2, 'id'), true), 'sin galería: no hay tarjeta de galería'); }
    else { ok(!in_array($card, array_column($c2, 'id'), true), "sin sección $drop: no hay tarjeta $card"); }
    $e = broken_links($c2, $s2); ok(!$e, "sin $drop: sin enlaces rotos", implode('; ', $e));
}
$s2 = sample_site(['video', 'cta']); $s2['services'] = []; update_option('sc_site', $s2); sc_site_reload_safe();
$c2 = sc_instruction_cards(); $i2 = array_column($c2, 'id');
ok(!array_intersect(['servicio-editar', 'servicio-agregar', 'servicio-eliminar', 'icono-servicio'], $i2), 'sin servicios: sin tarjetas de servicios');
$e = broken_links($c2, $s2); ok(!$e, 'sin servicios/cta/video: sin enlaces rotos', implode('; ', $e));
$bt = array_column($c2, null, 'id')['botones']['buttons']; ok(count($bt) >= 1 && !str_contains(json_encode($bt), 'invitación final'), 'botones: no ofrece el CTA ausente');

section('Sitio sin motor Luxe -> tarjetas clásicas');
update_option('sc_site', []); sc_site_reload_safe();
$c3 = array_column(sc_instruction_cards(), 'id');
ok(in_array('textos', $c3, true) && in_array('banner', $c3, true) && !in_array('primer-paso', $c3, true), 'sin sc_site: tarjetas heredadas');

section('Tienda y filtro');
update_option('sc_site', $site); sc_site_reload_safe(); update_option('sc_plan', 'tienda');
$c4 = sc_instruction_cards(); $i4 = array_column($c4, 'id');
foreach (['productos', 'pedidos', 'pagos', 'banco', 'precios', 'stock', 'categorias', 'cuentas'] as $need) { ok(in_array($need, $i4, true), "plan tienda: tarjeta $need"); }
$e = broken_links($c4, $site); ok(!$e, 'tienda: sin enlaces rotos', implode('; ', $e));
update_option('sc_plan', 'info');

section('SVG y pantalla (HTTP, cliente)');
$svg = sc_ins_toolbar_svg(false) . sc_ins_toolbar_svg(true) . sc_ins_page_svg();
foreach (['Editar mi web', 'Deshacer', 'Datos del negocio', 'Diseño', 'Secciones', 'Panel'] as $t) { ok(substr_count(sc_ins_toolbar_svg(false), '>' . $t . '<') === 1, "barra: herramienta $t"); }
$x = new DOMDocument(); ok(@$x->loadXML('<r>' . $svg . '</r>'), 'SVG bien formados (XML válido)');
ok(!str_contains($svg, '<script') && !str_contains($svg, 'http://www.w3.org/2000/svg" xmlns'), 'SVG sin scripts');
$jc = login_jar('cliente@bufete.test', 'Cliente-Test-123!', 'cli');
$r = http($U . '/wp-admin/admin.php?page=sc-instrucciones', ['jar' => $jc]);
eq($r['code'], 200, 'INSTRUCCIONES 200 con sitio Luxe');
$b = $r['body'];
foreach (['Primer paso: pulse «Editar mi web»', '¿Qué desea cambiar?', 'sc-ins__svg--wide', 'sc-ins__svg--narrow', 'sc_edit=1&#038;sc_focus=pages.home.sections.0.data.title', 'Cómo deshacer un cambio', 'Cómo pedir ayuda', 'wa.me/50212345678', 'sc-ins__btn--gold'] as $needle) {
    ok(str_contains($b, $needle) || str_contains(str_replace('&amp;', '&#038;', $b), $needle), "contiene: $needle");
}
ok(!preg_match('/\b(Elige|Pulsa|Haz clic|Entra a|tu sitio)\b/', strip_tags($b)), 'pantalla sin tuteo');
preg_match_all('/href="([^"]*sc_focus=[^"]*)"/', $b, $m); $hrefs = array_unique(array_map('html_entity_decode', $m[1]));
ok(count($hrefs) >= 20, 'enlaces al editor en la página: ' . count($hrefs));
$bad = []; foreach ($hrefs as $h) { $q = []; parse_str((string) parse_url($h, PHP_URL_QUERY), $q); if (!sc_ins_has($site, $q['sc_focus'] ?? '')) { $bad[] = $h; } }
ok(!$bad, 'todos los enlaces del HTML apuntan a rutas existentes', implode('; ', $bad));
ok(substr_count($b, '<article class="sc-ins__card') >= 24, 'tarjetas renderizadas: ' . substr_count($b, '<article class="sc-ins__card'));
// Menú: primera posición y redirección del cliente al entrar (comportamiento existente).
ok(has_action('admin_menu', 'sc_ins_menu') === 1, 'menú INSTRUCCIONES registrado con prioridad 1');
$cli = get_user_by('email', 'cliente@bufete.test');
eq(sc_roles_login_redirect(admin_url(), '', $cli), sc_instructions_url(), 'el cliente entra a INSTRUCCIONES');

section('sc_editor_url del editor, si existe');
if (!function_exists('sc_editor_url')) { function sc_editor_url($focus = '') { return home_url('/') . '?sc_edit=1' . ($focus !== '' ? '&sc_focus=' . rawurlencode($focus) . '&x=1' : ''); } }
ok(str_contains(sc_ins_editor_url('pages.home.sections.0.data.title', $site), '&x=1'), 'usa sc_editor_url cuando existe');
update_option('sc_site', $site);

// Restaurar
if ($orig === null) { delete_option('sc_site'); } else { update_option('sc_site', $orig); }
update_option('sc_plan', $origPlan); sc_site_reload_safe();
done();
