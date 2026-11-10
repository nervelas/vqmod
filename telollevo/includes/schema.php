<?php
/**
 * Esquema del panel de administración: define qué se puede editar y cómo se valida.
 * El panel dibuja los formularios automáticamente a partir de este esquema.
 */
if (!defined('TLL_BOOT')) { http_response_code(403); exit; }

function F(string $k, string $t, string $l, array $x = []): array { return array_merge(['k' => $k, 't' => $t, 'l' => $l], $x); }

/** Secciones editables (grupo => lista). */
function admin_schema(): array {
    $anchors = ['#inicio', '#servicios', '#como-funciona', '#tarifas', '#porque', '#planes', '#cobertura', '#opiniones', '#preguntas', '#contacto'];
    $cta = function (string $k, string $l) { return [F($k . '_label', 'text', $l . ' - texto', ['max' => 40]), F($k . '_href', 'url', $l . ' - enlace', ['max' => 200, 'ph' => '#contacto'])]; };

    return [
        'site' => ['group' => 'General', 'title' => 'Información general', 'icon' => 'gear-six', 'desc' => 'Nombre, logo y datos básicos del sitio.',
            'fields' => [
                F('brand', 'text', 'Nombre de la marca', ['max' => 60]),
                F('tagline', 'text', 'Eslogan corto (junto al logo)', ['max' => 60]),
                F('logo', 'image', 'Logo', ['help' => 'Recomendado: imagen cuadrada o circular con fondo transparente (PNG).']),
                F('meta_title', 'text', 'Título de la pestaña del navegador', ['max' => 120]),
                F('meta_description', 'textarea', 'Descripción corta del sitio', ['max' => 300, 'rows' => 3, 'help' => 'Se muestra al compartir el enlace en WhatsApp y redes.']),
                F('share_image', 'image', 'Imagen al compartir el enlace', ['help' => 'Recomendado: 1200 x 630 px.']),
                F('floating', 'bool', 'Mostrar botones flotantes de llamada y WhatsApp'),
            ]],
        'contact' => ['group' => 'General', 'title' => 'Contacto y redes', 'icon' => 'address-book', 'desc' => 'Teléfono, WhatsApp, correo, horario y redes sociales.',
            'fields' => [
                F('phone_display', 'text', 'Teléfono (como se muestra)', ['max' => 30, 'ph' => '3789 9709']),
                F('phone_digits', 'digits', 'Teléfono para llamadas (solo números con código de país)', ['max' => 15, 'help' => 'Guatemala = 502 + número. Ej.: 50237899709']),
                F('whatsapp', 'digits', 'WhatsApp (solo números con código de país)', ['max' => 15, 'help' => 'Ej.: 50237899709']),
                F('whatsapp_msg', 'textarea', 'Mensaje inicial de WhatsApp', ['max' => 300, 'rows' => 2]),
                F('email', 'email', 'Correo que se muestra al público', ['max' => 120]),
                F('address', 'text', 'Ubicación / zona', ['max' => 160]),
                F('hours', 'textarea', 'Horario de atención (una línea por horario)', ['max' => 400, 'rows' => 3]),
                F('facebook', 'url', 'Facebook (enlace)', ['max' => 200, 'ph' => 'https://facebook.com/tu-pagina']),
                F('instagram', 'url', 'Instagram (enlace)', ['max' => 200, 'ph' => 'https://instagram.com/tu-cuenta']),
                F('tiktok', 'url', 'TikTok (enlace)', ['max' => 200, 'ph' => 'https://tiktok.com/@tu-cuenta']),
                F('youtube', 'url', 'YouTube (enlace)', ['max' => 200]),
            ]],
        'nav' => ['group' => 'General', 'title' => 'Menú de navegación', 'icon' => 'list', 'desc' => 'Enlaces del menú superior y botón de acción.',
            'fields' => [
                F('items', 'list', 'Enlaces del menú', ['il' => 'label', 'max_items' => 10, 'f' => [
                    F('label', 'text', 'Texto', ['max' => 30]),
                    F('href', 'url', 'Enlace', ['max' => 200, 'suggest' => $anchors, 'help' => 'Usa #servicios, #contacto, etc. o una dirección completa.']),
                ]]),
                F('cta_label', 'text', 'Botón destacado - texto', ['max' => 30]),
                F('cta_href', 'url', 'Botón destacado - enlace', ['max' => 200, 'suggest' => $anchors]),
            ]],
        'hero' => ['group' => 'Secciones de la página', 'title' => 'Portada', 'icon' => 'rocket-launch', 'desc' => 'Lo primero que ve el visitante.',
            'fields' => array_merge([
                F('badge', 'text', 'Etiqueta superior', ['max' => 60]),
                F('title', 'textarea', 'Título principal', ['max' => 140, 'rows' => 2, 'help' => 'Pon *asteriscos* alrededor de una palabra para resaltarla en amarillo.']),
                F('subtitle', 'textarea', 'Subtítulo', ['max' => 300, 'rows' => 3]),
            ], $cta('cta1', 'Botón 1'), $cta('cta2', 'Botón 2'), [
                F('image', 'image', 'Imagen principal (de preferencia con fondo transparente)'),
                F('image_alt', 'text', 'Descripción de la imagen', ['max' => 120]),
                F('card1_icon', 'icon', 'Tarjeta flotante 1 - icono'),
                F('card1_title', 'text', 'Tarjeta flotante 1 - título', ['max' => 40]),
                F('card1_text', 'text', 'Tarjeta flotante 1 - texto', ['max' => 60]),
                F('card2_icon', 'icon', 'Tarjeta flotante 2 - icono'),
                F('card2_title', 'text', 'Tarjeta flotante 2 - título', ['max' => 40]),
                F('card2_text', 'text', 'Tarjeta flotante 2 - texto', ['max' => 60]),
                F('trust', 'list', 'Puntos de confianza (bajo los botones)', ['il' => 'text', 'max_items' => 4, 'f' => [
                    F('icon', 'icon', 'Icono'), F('text', 'text', 'Texto', ['max' => 40]),
                ]]),
            ])],
        'stats' => ['group' => 'Secciones de la página', 'title' => 'Cifras destacadas', 'icon' => 'chart-line-up', 'desc' => 'Franja de datos con números animados.',
            'fields' => [F('items', 'list', 'Cifras', ['il' => 'label', 'max_items' => 6, 'f' => [
                F('icon', 'icon', 'Icono'),
                F('prefix', 'text', 'Antes del número (ej. Q)', ['max' => 6]),
                F('number', 'text', 'Valor (un número se anima; también puede ser texto)', ['max' => 24]),
                F('suffix', 'text', 'Después del número (ej. %, min)', ['max' => 12]),
                F('label', 'text', 'Descripción', ['max' => 60]),
            ]])]],
        'services' => ['group' => 'Secciones de la página', 'title' => 'Servicios', 'icon' => 'moped', 'desc' => 'Tarjetas con los servicios que ofreces.',
            'fields' => [
                F('eyebrow', 'text', 'Etiqueta de la sección', ['max' => 40]),
                F('title', 'textarea', 'Título', ['max' => 140, 'rows' => 2, 'help' => 'Usa *asteriscos* para resaltar palabras.']),
                F('subtitle', 'textarea', 'Subtítulo', ['max' => 300, 'rows' => 2]),
                F('cta_label', 'text', 'Texto del enlace en cada tarjeta', ['max' => 40]),
                F('items', 'list', 'Servicios', ['il' => 'title', 'max_items' => 12, 'f' => [
                    F('icon', 'icon', 'Icono'), F('title', 'text', 'Nombre', ['max' => 60]),
                    F('tag', 'text', 'Etiqueta pequeña', ['max' => 30]),
                    F('text', 'textarea', 'Descripción', ['max' => 300, 'rows' => 3]),
                ]]),
            ]],
        'about' => ['group' => 'Secciones de la página', 'title' => 'Misión, Visión y Propósito', 'icon' => 'target', 'desc' => 'Quiénes somos: misión, visión, propósito y valores.',
            'fields' => [
                F('eyebrow', 'text', 'Etiqueta de la sección', ['max' => 40]),
                F('title', 'textarea', 'Título', ['max' => 140, 'rows' => 2]),
                F('image', 'image', 'Foto lateral (vertical)'),
                F('image_alt', 'text', 'Descripción de la foto lateral', ['max' => 120]),
                F('items', 'list', 'Misión, visión y propósito', ['il' => 'title', 'max_items' => 6, 'f' => [
                    F('icon', 'icon', 'Icono'), F('title', 'text', 'Título', ['max' => 40]),
                    F('text', 'textarea', 'Texto', ['max' => 600, 'rows' => 4]),
                ]]),
                F('banner', 'image', 'Foto ancha (equipo)'),
                F('banner_alt', 'text', 'Descripción de la foto ancha', ['max' => 120]),
                F('values', 'list', 'Valores (se muestran sobre la foto ancha)', ['il' => 'name', 'max_items' => 8, 'f' => [F('name', 'text', 'Valor', ['max' => 40])]]),
            ]],
        'how' => ['group' => 'Secciones de la página', 'title' => 'Cómo funciona', 'icon' => 'path', 'desc' => 'Pasos del proceso de envío.',
            'fields' => [
                F('eyebrow', 'text', 'Etiqueta de la sección', ['max' => 40]),
                F('title', 'textarea', 'Título', ['max' => 140, 'rows' => 2]),
                F('subtitle', 'textarea', 'Subtítulo', ['max' => 300, 'rows' => 2]),
                F('items', 'list', 'Pasos', ['il' => 'title', 'max_items' => 6, 'f' => [
                    F('icon', 'icon', 'Icono'), F('title', 'text', 'Título', ['max' => 50]),
                    F('text', 'textarea', 'Descripción', ['max' => 250, 'rows' => 2]),
                ]]),
            ]],
        'rates' => ['group' => 'Secciones de la página', 'title' => 'Tarifas y cotizador', 'icon' => 'calculator', 'desc' => 'Precios que usa el cotizador de la página.',
            'fields' => [
                F('eyebrow', 'text', 'Etiqueta de la sección', ['max' => 40]),
                F('title', 'textarea', 'Título', ['max' => 140, 'rows' => 2]),
                F('subtitle', 'textarea', 'Subtítulo', ['max' => 300, 'rows' => 2]),
                F('currency', 'text', 'Símbolo de moneda', ['max' => 5]),
                F('distances', 'list', 'Precio base por distancia', ['il' => 'label', 'max_items' => 12, 'f' => [
                    F('label', 'text', 'Rango de distancia', ['max' => 40]), F('price', 'number', 'Precio', ['min' => 0, 'max' => 100000, 'step' => '0.01']),
                ]]),
                F('sizes', 'list', 'Tamaño del paquete (cargo adicional)', ['il' => 'label', 'max_items' => 10, 'f' => [
                    F('label', 'text', 'Tamaño', ['max' => 60]), F('extra', 'number', 'Cargo extra', ['min' => 0, 'max' => 100000, 'step' => '0.01']),
                ]]),
                F('speeds', 'list', 'Urgencia (cargo adicional)', ['il' => 'label', 'max_items' => 8, 'f' => [
                    F('label', 'text', 'Tipo de servicio', ['max' => 60]), F('extra', 'number', 'Cargo extra', ['min' => 0, 'max' => 100000, 'step' => '0.01']),
                ]]),
                F('note', 'textarea', 'Nota bajo el cotizador', ['max' => 300, 'rows' => 3]),
                F('cta_label', 'text', 'Texto del botón de pedido', ['max' => 40]),
            ]],
        'why' => ['group' => 'Secciones de la página', 'title' => '¿Por qué elegirnos?', 'icon' => 'seal-check', 'desc' => 'Ventajas y diferenciales de tu servicio.',
            'fields' => [
                F('eyebrow', 'text', 'Etiqueta de la sección', ['max' => 40]),
                F('title', 'textarea', 'Título', ['max' => 140, 'rows' => 2]),
                F('subtitle', 'textarea', 'Subtítulo', ['max' => 300, 'rows' => 2]),
                F('image', 'image', 'Imagen'),
                F('image_alt', 'text', 'Descripción de la imagen', ['max' => 120]),
                F('badge_title', 'text', 'Insignia sobre la imagen - título', ['max' => 40]),
                F('badge_text', 'text', 'Insignia sobre la imagen - texto', ['max' => 60]),
                F('items', 'list', 'Ventajas', ['il' => 'title', 'max_items' => 12, 'f' => [
                    F('icon', 'icon', 'Icono'), F('title', 'text', 'Título', ['max' => 50]),
                    F('text', 'textarea', 'Descripción', ['max' => 250, 'rows' => 2]),
                ]]),
            ]],
        'plans' => ['group' => 'Secciones de la página', 'title' => 'Planes', 'icon' => 'stack', 'desc' => 'Planes o paquetes de servicio.',
            'fields' => [
                F('eyebrow', 'text', 'Etiqueta de la sección', ['max' => 40]),
                F('title', 'textarea', 'Título', ['max' => 140, 'rows' => 2]),
                F('subtitle', 'textarea', 'Subtítulo', ['max' => 300, 'rows' => 2]),
                F('items', 'list', 'Planes', ['il' => 'name', 'max_items' => 4, 'f' => [
                    F('icon', 'icon', 'Icono'), F('name', 'text', 'Nombre del plan', ['max' => 40]),
                    F('price', 'text', 'Precio o texto de precio', ['max' => 30]), F('period', 'text', 'Detalle del precio', ['max' => 40]),
                    F('desc', 'textarea', 'Descripción', ['max' => 200, 'rows' => 2]),
                    F('features', 'textarea', 'Beneficios (uno por línea)', ['max' => 600, 'rows' => 5]),
                    F('featured', 'bool', 'Plan destacado (recomendado)'),
                    F('cta_label', 'text', 'Texto del botón', ['max' => 30]), F('cta_href', 'url', 'Enlace del botón', ['max' => 200]),
                ]]),
            ]],
        'coverage' => ['group' => 'Secciones de la página', 'title' => 'Cobertura', 'icon' => 'map-trifold', 'desc' => 'Zonas y municipios donde trabajas.',
            'fields' => [
                F('eyebrow', 'text', 'Etiqueta de la sección', ['max' => 40]),
                F('title', 'textarea', 'Título', ['max' => 140, 'rows' => 2]),
                F('subtitle', 'textarea', 'Subtítulo', ['max' => 700, 'rows' => 5]),
                F('zones', 'list', 'Zonas / municipios', ['il' => 'name', 'max_items' => 40, 'f' => [F('name', 'text', 'Nombre', ['max' => 60])]]),
                F('note', 'textarea', 'Nota (cada línea es un párrafo)', ['max' => 600, 'rows' => 4]),
                F('map_badge', 'text', 'Etiqueta sobre el mapa', ['max' => 40]),
                F('cta_label', 'text', 'Texto del botón', ['max' => 40]),
            ]],
        'testimonials' => ['group' => 'Secciones de la página', 'title' => 'Opiniones de clientes', 'icon' => 'chat-circle-text', 'desc' => 'Reemplaza los ejemplos por comentarios reales y actívala en "Secciones y orden".',
            'fields' => [
                F('eyebrow', 'text', 'Etiqueta de la sección', ['max' => 40]),
                F('title', 'textarea', 'Título', ['max' => 140, 'rows' => 2]),
                F('subtitle', 'textarea', 'Subtítulo', ['max' => 300, 'rows' => 2]),
                F('items', 'list', 'Opiniones', ['il' => 'name', 'max_items' => 12, 'f' => [
                    F('name', 'text', 'Nombre', ['max' => 60]), F('role', 'text', 'Cargo o negocio', ['max' => 60]),
                    F('rating', 'number', 'Estrellas (1 a 5)', ['min' => 1, 'max' => 5]),
                    F('text', 'textarea', 'Comentario', ['max' => 400, 'rows' => 3]),
                ]]),
            ]],
        'faq' => ['group' => 'Secciones de la página', 'title' => 'Preguntas frecuentes', 'icon' => 'question', 'desc' => 'Preguntas y respuestas.',
            'fields' => [
                F('eyebrow', 'text', 'Etiqueta de la sección', ['max' => 40]),
                F('title', 'textarea', 'Título', ['max' => 140, 'rows' => 2]),
                F('subtitle', 'textarea', 'Subtítulo', ['max' => 300, 'rows' => 2]),
                F('items', 'list', 'Preguntas', ['il' => 'q', 'max_items' => 30, 'f' => [
                    F('q', 'text', 'Pregunta', ['max' => 160]), F('a', 'textarea', 'Respuesta', ['max' => 700, 'rows' => 3]),
                ]]),
            ]],
        'cta' => ['group' => 'Secciones de la página', 'title' => 'Banda de llamado a la acción', 'icon' => 'megaphone', 'desc' => 'Banda amarilla antes del formulario.',
            'fields' => [
                F('title', 'textarea', 'Título', ['max' => 140, 'rows' => 2]),
                F('text', 'textarea', 'Texto', ['max' => 250, 'rows' => 2]),
                F('whatsapp_label', 'text', 'Botón de WhatsApp', ['max' => 40]),
                F('call_label', 'text', 'Botón de llamada', ['max' => 40]),
            ]],
        'form' => ['group' => 'Secciones de la página', 'title' => 'Formulario de solicitud', 'icon' => 'paper-plane-tilt', 'desc' => 'Textos y opciones del formulario. Los mensajes llegan a tu correo y al buzón del panel.',
            'fields' => [
                F('eyebrow', 'text', 'Etiqueta de la sección', ['max' => 40]),
                F('title', 'textarea', 'Título', ['max' => 140, 'rows' => 2]),
                F('subtitle', 'textarea', 'Subtítulo', ['max' => 300, 'rows' => 3]),
                F('services', 'textarea', 'Opciones de "Tipo de servicio" (una por línea)', ['max' => 800, 'rows' => 7]),
                F('sends_label', 'text', 'Título del campo "qué se envía"', ['max' => 60]),
                F('sends', 'textarea', 'Sugerencias del campo "qué se envía" (una por línea)', ['max' => 600, 'rows' => 4]),
                F('size_label', 'text', 'Título del campo de volumen/tamaño', ['max' => 60]),
                F('sizes', 'textarea', 'Opciones del campo de volumen/tamaño (una por línea)', ['max' => 800, 'rows' => 6]),
                F('button', 'text', 'Texto del botón de envío', ['max' => 40]),
                F('success', 'textarea', 'Mensaje de éxito', ['max' => 300, 'rows' => 2]),
                F('privacy', 'textarea', 'Aviso de privacidad bajo el botón', ['max' => 250, 'rows' => 2]),
            ]],
        'footer' => ['group' => 'Secciones de la página', 'title' => 'Pie de página', 'icon' => 'align-bottom', 'desc' => 'Texto final del sitio.',
            'fields' => [
                F('about', 'textarea', 'Descripción breve', ['max' => 250, 'rows' => 2]),
                F('copyright', 'text', 'Texto de derechos reservados', ['max' => 120]),
            ]],
        'legal' => ['group' => 'Secciones de la página', 'title' => 'Páginas legales', 'icon' => 'scales', 'desc' => 'Política de privacidad y términos. Son textos de ejemplo: revísalos con tu asesor legal.',
            'fields' => [
                F('privacy_title', 'text', 'Título de privacidad', ['max' => 80]),
                F('privacy', 'textarea', 'Política de privacidad', ['max' => 8000, 'rows' => 14, 'help' => 'Una línea corta seguida de salto de línea se muestra como subtítulo.']),
                F('terms_title', 'text', 'Título de términos', ['max' => 80]),
                F('terms', 'textarea', 'Términos del servicio', ['max' => 8000, 'rows' => 14]),
            ]],
        'theme' => ['group' => 'Apariencia', 'title' => 'Colores', 'icon' => 'palette', 'desc' => 'Cambia los colores de toda la página. Por defecto usa los colores del logo.',
            'fields' => [
                F('blue', 'color', 'Azul principal'),
                F('navy', 'color', 'Azul oscuro (fondos y títulos)'),
                F('yellow', 'color', 'Amarillo de acento'),
            ]],
    ];
}

/** Secciones de la página que se pueden activar/ordenar. */
function section_labels(): array {
    return [
        'hero' => 'Portada', 'stats' => 'Cifras destacadas', 'services' => 'Servicios', 'about' => 'Misión, Visión y Propósito', 'how' => 'Cómo funciona',
        'rates' => 'Tarifas y cotizador', 'why' => '¿Por qué elegirnos?', 'plans' => 'Planes', 'coverage' => 'Cobertura',
        'testimonials' => 'Opiniones', 'faq' => 'Preguntas frecuentes', 'cta' => 'Llamado a la acción', 'contact' => 'Formulario de contacto',
    ];
}

/* ------------------------------------------------------------------ */
/*  Validación / limpieza                                              */
/* ------------------------------------------------------------------ */
function clean_text($v, int $max = 200, bool $multiline = false): string {
    if (is_array($v)) return '';
    $v = (string)$v;
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
    if ($v === null) $v = '';
    if (!$multiline) $v = str_replace("\n", ' ', $v);
    $v = trim($v);
    if (mb_strlen($v) > $max) $v = mb_substr($v, 0, $max);
    return $v;
}

function clean_image_path($v): string {
    $v = trim((string)$v);
    if ($v === '') return '';
    if (!preg_match('#^(uploads|assets/img)/[A-Za-z0-9_\-./]+$#', $v) || strpos($v, '..') !== false) return '';
    return is_file(TLL_ROOT . '/' . $v) ? $v : '';
}

function sanitize_fields(array $fields, $input, array $existing = []): array {
    $out = [];
    if (!is_array($input)) $input = [];
    foreach ($fields as $f) {
        $k = $f['k'];
        $raw = $input[$k] ?? null;
        $old = $existing[$k] ?? null;
        switch ($f['t']) {
            case 'text':
                $out[$k] = clean_text($raw, $f['max'] ?? 200);
                break;
            case 'textarea':
                $out[$k] = clean_text($raw, $f['max'] ?? 1000, true);
                break;
            case 'url':
                $u = clean_text($raw, $f['max'] ?? 300);
                $out[$k] = safe_url($u);
                break;
            case 'email':
                $u = clean_text($raw, $f['max'] ?? 160);
                $out[$k] = ($u === '' || filter_var($u, FILTER_VALIDATE_EMAIL)) ? $u : (is_string($old) ? $old : '');
                break;
            case 'digits':
                $out[$k] = substr(preg_replace('/\D+/', '', is_array($raw) ? '' : (string)$raw), 0, $f['max'] ?? 15);
                break;
            case 'number':
                $n = is_array($raw) ? 0 : (float)str_replace(',', '.', (string)$raw);
                $min = $f['min'] ?? 0; $max = $f['max'] ?? 1000000;
                $n = max($min, min($max, $n));
                $out[$k] = (floor($n) == $n) ? (int)$n : round($n, 2);
                break;
            case 'color':
                $c = is_array($raw) ? '' : trim((string)$raw);
                $out[$k] = preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtoupper($c) : (is_string($old) ? $old : '#000000');
                break;
            case 'image':
                $out[$k] = clean_image_path($raw);
                break;
            case 'icon':
                $i = is_array($raw) ? '' : strtolower(trim((string)$raw));
                $out[$k] = ($i !== '' && icon_exists($i)) ? $i : '';
                break;
            case 'bool':
                $out[$k] = !empty($raw) && $raw !== '0';
                break;
            case 'select':
                $out[$k] = in_array($raw, $f['opts'] ?? [], true) ? $raw : ($f['opts'][0] ?? '');
                break;
            case 'list':
                $items = [];
                if (is_array($raw)) {
                    $n = 0;
                    foreach ($raw as $row) {
                        if (!is_array($row)) continue;
                        if (++$n > ($f['max_items'] ?? 30)) break;
                        $clean = sanitize_fields($f['f'], $row);
                        // descartar filas totalmente vacías
                        $has = false;
                        foreach ($f['f'] as $sf) {
                            if (in_array($sf['t'], ['text', 'textarea', 'url', 'email', 'digits'], true) && ($clean[$sf['k']] ?? '') !== '') { $has = true; break; }
                        }
                        if ($has) $items[] = $clean;
                    }
                }
                $out[$k] = $items;
                break;
        }
    }
    return $out;
}

function sanitize_layout($input): array {
    $labels = section_labels();
    $order = [];
    foreach ((array)($input['order'] ?? []) as $id) {
        if (is_string($id) && isset($labels[$id]) && !in_array($id, $order, true)) $order[] = $id;
    }
    foreach (array_keys($labels) as $id) { if (!in_array($id, $order, true)) $order[] = $id; }
    $enabled = [];
    foreach (array_keys($labels) as $id) { $enabled[$id] = !empty($input['enabled'][$id]); }
    $enabled['hero'] = true;
    return ['order' => $order, 'enabled' => $enabled];
}
