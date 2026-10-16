<?php
declare(strict_types=1);
if (!defined('GX')) { http_response_code(403); exit; }

/* =====================================================================
 *  Editor de páginas: solo contenido. Nunca plantillas ni código.
 * ===================================================================== */

const GX_BLOCK_TYPES = [
    'text' => 'Texto',
    'heading' => 'Título destacado',
    'videos' => 'Videos',
    'cards' => 'Tarjetas de contacto',
    'features' => 'Misión / Visión (tarjetas con ícono)',
    'gallery' => 'Galería de imágenes',
    'links' => 'Enlaces internos (municipios / zonas)',
    'form' => 'Formulario → WhatsApp',
];
const GX_ICONS = ['target' => 'Objetivo', 'eye' => 'Visión', 'shield-check' => 'Escudo', 'badge-check' => 'Insignia', 'zap' => 'Rayo', 'wrench' => 'Llave', 'truck' => 'Grúa', 'clock' => 'Reloj', 'users' => 'Personas', 'gauge' => 'Medidor'];

function gx_block_default(string $type): array
{
    switch ($type) {
        case 'text':
            return ['type' => 'text', 'variant' => 'plain', 'html' => '<p>Nuevo texto</p>'];
        case 'heading':
            return ['type' => 'heading', 'html' => 'Nuevo título'];
        case 'videos':
            return ['type' => 'videos', 'items' => []];
        case 'cards':
            return ['type' => 'cards'];
        case 'features':
            return ['type' => 'features', 'items' => [['icon' => 'badge-check', 'title' => 'TÍTULO', 'text' => 'Texto']]];
        case 'gallery':
            return ['type' => 'gallery', 'items' => []];
        case 'links':
            return ['type' => 'links', 'title' => 'Municipios que atendemos', 'items' => []];
        default:
            return ['type' => 'form', 'title' => 'Envíenos un mensaje',
                'labels' => ['name' => 'Nombre *', 'phone' => 'Teléfono *', 'email' => 'Correo electrónico', 'service' => 'Tipo de servicio *', 'service_ph' => 'Seleccione una opción', 'place' => 'Ubicación / destino', 'message' => 'Mensaje *', 'send' => 'Enviar mensaje'],
                'services' => ['Servicio de grúa'], 'wa_intro' => 'Hola, necesito el servicio de grúas.'];
    }
}

/** Videos disponibles (los pone la instalación; el panel no sube videos). */
function gx_video_registry(): array
{
    $r = gx_setting('videos', []);
    return is_array($r) ? $r : [];
}

function gx_block_form(int $i, array $b): string
{
    $type = (string)($b['type'] ?? 'text');
    $n = 'b[' . $i . ']';
    $o = '<fieldset class="blk" data-blk><legend><span class="blk-n">' . e(GX_BLOCK_TYPES[$type] ?? $type) . '</span></legend>'
        . '<input type="hidden" name="bid[]" value="' . $i . '"><input type="hidden" name="' . $n . '[type]" value="' . e($type) . '">'
        . '<input type="hidden" name="' . $n . '[del]" value="0" data-del>'
        . '<div class="blk-t"><button type="button" class="b b-s b-g" data-up title="Subir">↑</button><button type="button" class="b b-s b-g" data-down title="Bajar">↓</button>'
        . '<button type="button" class="b b-s b-d" data-rm>Eliminar bloque</button></div>';
    switch ($type) {
        case 'text':
            $o .= gx_f_sel('Estilo', $n . '[variant]', ['welcome' => 'Bienvenida (primer párrafo grande)', 'plain' => 'Texto normal', 'closing' => 'Frase final destacada', 'article' => 'Artículo (títulos H2/H3, texto alineado a la izquierda)'], $b['variant'] ?? 'plain')
                . gx_f_ta('Contenido', $n . '[html]', $b['html'] ?? '', 6, true, 'Se permiten negritas, cursivas, listas, saltos de línea y enlaces. Marcadores: {phone} {tel} {wa} {email}.');
            break;
        case 'heading':
            $o .= gx_f_ta('Texto del título', $n . '[html]', $b['html'] ?? '', 3, true, 'Los números como Q250 se animan solos.');
            break;
        case 'videos':
            $reg = gx_video_registry();
            $opts = ['' => '— (vacío)'];
            foreach ($reg as $src => $_m) {
                $opts[$src] = $src;
            }
            $items = array_values((array)($b['items'] ?? []));
            for ($k = 0; $k < 4; $k++) {
                $cur = (string)($items[$k]['src'] ?? '');
                $o .= gx_f_sel('Video ' . ($k + 1), $n . '[v][' . $k . ']', $opts, $cur);
            }
            $o .= '<small>Los videos disponibles son los instalados en la carpeta <code>media/</code>. Por seguridad el panel no sube videos.</small>';
            break;
        case 'cards':
            $o .= '<p class="note">Muestra las 3 tarjetas: Llámenos, Escríbanos y Email. Sus textos, teléfono, WhatsApp y correo se editan en <a href="?p=ajustes">Ajustes</a>.</p>';
            break;
        case 'features':
            $items = array_values((array)($b['items'] ?? []));
            for ($k = 0; $k < 4; $k++) {
                $it = (array)($items[$k] ?? []);
                $o .= '<div class="sub"><strong>Tarjeta ' . ($k + 1) . '</strong>'
                    . gx_f_sel('Ícono', $n . '[f][' . $k . '][icon]', GX_ICONS, $it['icon'] ?? 'badge-check')
                    . gx_f_in('Título', $n . '[f][' . $k . '][title]', $it['title'] ?? '', 'text', '', 'maxlength="60"')
                    . gx_f_ta('Texto', $n . '[f][' . $k . '][text]', $it['text'] ?? '', 4)
                    . '</div>';
            }
            $o .= '<small>Deje el título y el texto vacíos para quitar una tarjeta.</small>';
            break;
        case 'links':
            $lines = [];
            foreach ((array)($b['items'] ?? []) as $it) {
                $lines[] = (string)($it['label'] ?? '') . ' | ' . (string)($it['path'] ?? '');
            }
            $o .= gx_f_in('Título de la sección', $n . '[title]', $b['title'] ?? '', 'text', '', 'maxlength="120"')
                . gx_f_ta('Enlaces (uno por línea: Texto | /ruta/)', $n . '[lines]', implode("\n", $lines), 10, false, 'Ej.: Grúas en Mixco | /gruas-mixco/');
            break;
        case 'gallery':
            $o .= '<div class="gal-ed" data-gal data-name="' . $n . '[items][]"><div class="gal-list" data-gal-list>';
            foreach ((array)($b['items'] ?? []) as $it) {
                $m = gx_media_get((int)($it['media'] ?? 0));
                if ($m) {
                    $o .= '<div class="gi" data-gi><img src="/uploads/' . e($m['thumb']) . '" alt=""><input type="hidden" name="' . $n . '[items][]" value="' . (int)$m['id'] . '">'
                        . '<span class="gi-b"><button type="button" class="b b-s b-g" data-gl>←</button><button type="button" class="b b-s b-g" data-gr>→</button><button type="button" class="b b-s b-d" data-gx>×</button></span></div>';
                }
            }
            $o .= '</div><button type="button" class="b b-s" data-gal-add>+ Agregar imágenes</button></div>';
            break;
        case 'form':
            $L = (array)($b['labels'] ?? []);
            $o .= gx_f_in('Título', $n . '[title]', $b['title'] ?? '', 'text', '', 'maxlength="100"');
            foreach (['name' => 'Etiqueta: nombre', 'phone' => 'Etiqueta: teléfono', 'email' => 'Etiqueta: correo', 'service' => 'Etiqueta: tipo de servicio', 'service_ph' => 'Texto inicial de la lista', 'place' => 'Etiqueta: ubicación', 'message' => 'Etiqueta: mensaje', 'send' => 'Texto del botón'] as $k => $lbl) {
                $o .= gx_f_in($lbl, $n . '[labels][' . $k . ']', $L[$k] ?? '', 'text', '', 'maxlength="60"');
            }
            $o .= gx_f_ta('Tipos de servicio (uno por línea)', $n . '[services]', implode("\n", (array)($b['services'] ?? [])), 5)
                . gx_f_in('Saludo inicial del mensaje de WhatsApp', $n . '[wa_intro]', $b['wa_intro'] ?? '', 'text', 'Al enviar, los datos del formulario se abren en el WhatsApp de la empresa.', 'maxlength="200"');
            break;
    }
    return $o . '</fieldset>';
}

function gx_page_editor(array $p): string
{
    $h = (array)($p['hero'] ?? []);
    $o = gx_f_open('page_save', '<input type="hidden" name="slug" value="' . e((string)$p['slug']) . '">') . '<div class="grid2">'
        . '<section class="card-p"><h2>SEO de la página</h2>'
        . gx_f_in('Título (title)', 'seo_title', $p['seo_title'] ?? '', 'text', 'Recomendado: hasta 60 caracteres.', 'maxlength="120"')
        . gx_f_ta('Descripción (meta description)', 'seo_desc', $p['seo_desc'] ?? '', 3, false, 'Recomendado: hasta 160 caracteres.')
        . gx_f_media('Imagen al compartir (Open Graph)', 'og_image', (int)($p['og_image'] ?? 0))
        . '<p class="note">Dirección (URL): <code>' . e((string)($p['path'] ?? '')) . '</code> — no se modifica para conservar el posicionamiento en Google.</p></section>'
        . '<section class="card-p"><h2>Portada de la página</h2>'
        . gx_f_in('Título (línea 1)', 'hero_title', $h['title'] ?? '', 'text', '', 'maxlength="80"')
        . gx_f_in('Subtítulo (línea 2, en dorado)', 'hero_subtitle', $h['subtitle'] ?? '', 'text', '', 'maxlength="80"')
        . gx_f_in('Texto superior', 'hero_text', $h['text'] ?? '', 'text', '', 'maxlength="100"')
        . gx_f_in('Botón: texto', 'hero_btn_text', $h['btn_text'] ?? '', 'text', 'Marcadores: {phone} {tel} {wa} {email}', 'maxlength="80"')
        . gx_f_in('Botón: enlace', 'hero_btn_link', $h['btn_link'] ?? '', 'text', 'Ej.: tel:{tel}, https://… o /contactenos/', 'maxlength="300"')
        . gx_f_media('Imagen de portada', 'hero_image', (int)($h['image'] ?? 0))
        . gx_f_sel('Ajuste de la imagen', 'hero_fit', ['cover' => 'Llenar el ancho (recorta los bordes)', 'contain' => 'Mostrar completa (imágenes con texto o logo)'], $h['fit'] ?? 'cover')
        . '</section></div><h2 class="sec-h">Contenido de la página <small>(en el orden en que aparece)</small></h2><div id="blocks">';
    foreach (array_values((array)($p['blocks'] ?? [])) as $i => $b) {
        $o .= gx_block_form($i, (array)$b);
    }
    $o .= '</div><div class="add-blk"><label>Agregar bloque <select name="add_type"><option value="">— elegir —</option>';
    foreach (GX_BLOCK_TYPES as $k => $v) {
        $o .= '<option value="' . $k . '">' . e($v) . '</option>';
    }
    return $o . '</select></label><span class="note">Se agrega al guardar.</span></div>'
        . '<div class="sticky-save"><button class="b b-p" type="submit">' . gx_icon('save') . ' Guardar cambios</button> <span class="note">Luego pulse «Publicar» para ver los cambios en el sitio.</span></div></form>';
}

/** Lee el formulario del editor y devuelve el contenido saneado. */
function gx_parse_page_post(array $old): array
{
    $media_ok = static function ($id): int {
        $id = (int)$id;
        return ($id > 0 && gx_media_get($id)) ? $id : 0;
    };
    $p = $old;
    $p['seo_title'] = gx_line($_POST['seo_title'] ?? '', 120);
    $p['seo_desc'] = gx_line($_POST['seo_desc'] ?? '', 300);
    $p['og_image'] = $media_ok($_POST['og_image'] ?? 0);
    $p['hero'] = [
        'title' => gx_line($_POST['hero_title'] ?? '', 80),
        'subtitle' => gx_line($_POST['hero_subtitle'] ?? '', 80),
        'text' => gx_line($_POST['hero_text'] ?? '', 100),
        'btn_text' => gx_line($_POST['hero_btn_text'] ?? '', 80),
        'btn_link' => gx_safe_url($_POST['hero_btn_link'] ?? '', 300),
        'image' => $media_ok($_POST['hero_image'] ?? 0),
        'fit' => (($_POST['hero_fit'] ?? '') === 'contain') ? 'contain' : 'cover',
        'alt' => (string)($old['hero']['alt'] ?? ''),
    ];
    $blocks = [];
    $ids = is_array($_POST['bid'] ?? null) ? array_slice($_POST['bid'], 0, 60) : [];
    $B = is_array($_POST['b'] ?? null) ? $_POST['b'] : [];
    $reg = gx_video_registry();
    foreach ($ids as $i) {
        $i = (int)$i;
        $b = $B[$i] ?? null;
        if (!is_array($b) || (string)($b['del'] ?? '0') === '1') {
            continue;
        }
        $type = (string)($b['type'] ?? '');
        if (!isset(GX_BLOCK_TYPES[$type])) {
            continue;
        }
        switch ($type) {
            case 'text':
                $v = in_array($b['variant'] ?? '', ['welcome', 'plain', 'closing', 'article'], true) ? $b['variant'] : 'plain';
                $blocks[] = ['type' => 'text', 'variant' => $v, 'html' => gx_clean_html($b['html'] ?? '')];
                break;
            case 'heading':
                $blocks[] = ['type' => 'heading', 'html' => gx_clean_html($b['html'] ?? '', 500)];
                break;
            case 'videos':
                $items = [];
                foreach ((array)($b['v'] ?? []) as $src) {
                    if (is_string($src) && isset($reg[$src])) {
                        $items[] = ['src' => $src, 'poster' => (int)($reg[$src]['poster'] ?? 0), 'w' => (int)($reg[$src]['w'] ?? 0), 'h' => (int)($reg[$src]['h'] ?? 0)];
                    }
                }
                $blocks[] = ['type' => 'videos', 'items' => $items];
                break;
            case 'cards':
                $blocks[] = ['type' => 'cards'];
                break;
            case 'features':
                $items = [];
                foreach ((array)($b['f'] ?? []) as $f) {
                    if (!is_array($f)) {
                        continue;
                    }
                    $t = gx_line($f['title'] ?? '', 60);
                    $x = gx_text($f['text'] ?? '', 1000);
                    if ($t === '' && $x === '') {
                        continue;
                    }
                    $items[] = ['icon' => isset(GX_ICONS[$f['icon'] ?? '']) ? (string)$f['icon'] : 'badge-check', 'title' => $t, 'text' => $x];
                }
                $blocks[] = ['type' => 'features', 'items' => $items];
                break;
            case 'links':
                $items = [];
                foreach (preg_split('/\n/', (string)($b['lines'] ?? '')) as $ln) {
                    $parts = explode('|', (string)$ln, 2);
                    $lab = gx_line($parts[0] ?? '', 90);
                    $path = trim((string)($parts[1] ?? ''));
                    if ($lab !== '' && preg_match('~^/[a-z0-9-]*/?$~', $path) && count($items) < 80) {
                        $items[] = ['label' => $lab, 'path' => $path];
                    }
                }
                $blocks[] = ['type' => 'links', 'title' => gx_line($b['title'] ?? '', 120), 'items' => $items];
                break;
            case 'gallery':
                $items = [];
                foreach (array_slice((array)($b['items'] ?? []), 0, 120) as $id) {
                    $id = $media_ok($id);
                    if ($id > 0) {
                        $items[] = ['media' => $id];
                    }
                }
                $blocks[] = ['type' => 'gallery', 'items' => $items];
                break;
            case 'form':
                $L = [];
                foreach (['name', 'phone', 'email', 'service', 'service_ph', 'place', 'message', 'send'] as $k) {
                    $L[$k] = gx_line($b['labels'][$k] ?? '', 60);
                }
                $svc = [];
                foreach (preg_split('/\R/u', (string)($b['services'] ?? '')) ?: [] as $s) {
                    $s = gx_line($s, 100);
                    if ($s !== '') {
                        $svc[] = $s;
                    }
                }
                $blocks[] = ['type' => 'form', 'title' => gx_line($b['title'] ?? '', 100), 'labels' => $L,
                    'services' => array_slice($svc, 0, 12), 'wa_intro' => gx_line($b['wa_intro'] ?? '', 200)];
                break;
        }
    }
    $add = (string)($_POST['add_type'] ?? '');
    if (isset(GX_BLOCK_TYPES[$add])) {
        $blocks[] = gx_block_default($add);
    }
    $p['blocks'] = $blocks;
    return $p;
}
