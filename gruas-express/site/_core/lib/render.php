<?php
declare(strict_types=1);
if (!defined('GX')) { http_response_code(403); exit; }

require_once __DIR__ . '/images.php';

/* =====================================================================
 *  Generador de páginas estáticas ("Publicar")
 *  Toda la salida es HTML estático sin PHP: el sitio público no ejecuta código.
 * ===================================================================== */

function gx_icon(string $name, string $cls = 'ic'): string
{
    static $icons = null;
    if ($icons === null) {
        $icons = require __DIR__ . '/icons.php';
    }
    if (isset($icons['l:' . $name])) {
        return '<svg class="' . e($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icons['l:' . $name] . '</svg>';
    }
    if (isset($icons['b:' . $name])) {
        return '<svg class="' . e($cls) . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="' . $icons['b:' . $name] . '"/></svg>';
    }
    return '';
}

function gx_media_url(?array $m, bool $thumb = false): string
{
    if (!$m) {
        return '';
    }
    return '/uploads/' . ($thumb ? $m['thumb'] : $m['file']);
}

/** Sustituye marcadores {phone} {tel} {wa} {email} en textos y enlaces configurables. */
function gx_tokens(string $s, array $S): string
{
    return strtr($s, [
        '{phone}' => (string)($S['phone_display'] ?? ''),
        '{tel}' => (string)($S['phone_tel'] ?? ''),
        '{wa}' => (string)($S['wa_display'] ?? ''),
        '{email}' => (string)($S['email_display'] ?? ''),
    ]);
}

function gx_wa_url(array $S, string $text = ''): string
{
    $n = preg_replace('/\D+/', '', (string)($S['wa_number'] ?? '')) ?? '';
    $u = 'https://api.whatsapp.com/send?phone=' . $n;
    return $text === '' ? $u : $u . '&text=' . rawurlencode($text);
}

function gx_site_url(array $S): string
{
    $u = rtrim((string)($S['site_url'] ?? ''), '/');
    return $u !== '' ? $u : 'https://localhost';
}

/* ---------------------- bloques ---------------------- */

function gx_block_html(array $b, array $S, array $media): string
{
    $type = (string)($b['type'] ?? '');
    switch ($type) {
        case 'text':
            $v = in_array($b['variant'] ?? '', ['welcome', 'plain', 'closing'], true) ? $b['variant'] : 'plain';
            return '<section class="sec sec-text sec-' . $v . '"><div class="wrap"><div class="prose prose-' . $v . ' reveal">'
                . gx_clean_html(gx_tokens((string)($b['html'] ?? ''), $S)) . '</div></div></section>';

        case 'heading':
            return '<section class="sec sec-price"><div class="wrap"><h2 class="price-t reveal">'
                . gx_clean_html(gx_tokens((string)($b['html'] ?? ''), $S)) . '</h2></div></section>';

        case 'videos':
            $o = '<section class="sec sec-videos"><div class="wrap vids">';
            foreach ((array)($b['items'] ?? []) as $v) {
                $src = (string)($v['src'] ?? '');
                if (!preg_match('~^media/[a-z0-9][a-z0-9_-]{0,60}\.mp4$~', $src)) {
                    continue;
                }
                $poster = isset($v['poster']) ? ($media[(int)$v['poster']] ?? null) : null;
                $w = (int)($v['w'] ?? 0);
                $h = (int)($v['h'] ?? 0);
                $dim = ($w > 0 && $h > 0) ? ' width="' . $w . '" height="' . $h . '"' : '';
                $orient = ($h > $w) ? ' vid-p' : '';
                $o .= '<figure class="vid' . $orient . ' reveal"><video controls playsinline preload="none" controlslist="nodownload"'
                    . ($poster ? ' poster="' . e(gx_media_url($poster, true)) . '"' : '') . $dim . '>'
                    . '<source src="/' . e($src) . '" type="video/mp4"></video></figure>';
            }
            return $o . '</div></section>';

        case 'cards':
            return gx_cards_html($S);

        case 'features':
            $o = '<section class="sec sec-feats"><div class="wrap feats">';
            foreach ((array)($b['items'] ?? []) as $f) {
                $ic = preg_match('/^[a-z0-9-]{2,24}$/', (string)($f['icon'] ?? '')) ? (string)$f['icon'] : 'badge-check';
                $o .= '<article class="feat reveal"><span class="feat-ic">' . gx_icon($ic) . '</span><div><h3 class="feat-t">'
                    . e((string)($f['title'] ?? '')) . '</h3><p class="feat-p">' . e((string)($f['text'] ?? '')) . '</p></div></article>';
            }
            return $o . '</div></section>';

        case 'gallery':
            $o = '<section class="sec sec-gallery"><div class="wrap"><ul class="gal">';
            $i = 0;
            foreach ((array)($b['items'] ?? []) as $it) {
                $m = $media[(int)($it['media'] ?? 0)] ?? null;
                if (!$m) {
                    continue;
                }
                $i++;
                $eager = $i <= 2 ? '' : ' loading="lazy"';
                $o .= '<li class="gal-i reveal"><a class="gal-a" href="' . e(gx_media_url($m)) . '" data-lb="1" aria-label="Ampliar imagen">'
                    . '<img src="' . e(gx_media_url($m, true)) . '" srcset="' . e(gx_media_url($m, true)) . ' ' . (int)$m['tw'] . 'w, ' . e(gx_media_url($m)) . ' ' . (int)$m['w'] . 'w"'
                    . ' sizes="(min-width:1000px) 380px, (min-width:600px) 45vw, 92vw" width="' . (int)$m['tw'] . '" height="' . (int)$m['th'] . '"'
                    . ' alt="' . e((string)$m['alt']) . '"' . $eager . ' decoding="async">'
                    . '<span class="gal-z">' . gx_icon('zoom-in', 'ic ic-z') . '</span></a></li>';
            }
            return $o . '</ul></div></section>';

        case 'form':
            return gx_form_html($b, $S);
    }
    return '';
}

function gx_cards_html(array $S): string
{
    $c = (array)($S['cards'] ?? []);
    $telHref = 'tel:' . preg_replace('/[^0-9+]/', '', (string)($S['phone_tel'] ?? ''));
    $mail = 'mailto:' . (string)($S['email_link'] ?? '');
    $items = [
        ['call', $telHref, '', 'phone', (string)($c['call_title'] ?? ''), (string)($c['call_label'] ?? '') . (string)($S['phone_display'] ?? '')],
        ['wa', gx_wa_url($S), ' target="_blank" rel="noopener noreferrer"', 'whatsapp', (string)($c['wa_title'] ?? ''), (string)($c['wa_label'] ?? '') . (string)($S['wa_display'] ?? '')],
        ['mail', $mail, '', 'mail', (string)($c['mail_title'] ?? ''), (string)($S['email_display'] ?? '')],
    ];
    $o = '<section class="sec sec-cards"><div class="wrap cards">';
    foreach ($items as $it) {
        $o .= '<a class="card card-' . $it[0] . ' reveal" href="' . e($it[1]) . '"' . $it[2] . '>'
            . '<span class="card-ic">' . gx_icon($it[3]) . '</span>'
            . '<span class="card-b"><h2 class="card-t">' . e($it[4]) . '</h2><p class="card-p">' . e($it[5]) . '</p></span></a>';
    }
    return $o . '</div></section>';
}

function gx_form_html(array $b, array $S): string
{
    $L = (array)($b['labels'] ?? []);
    $lab = static function (string $k, string $d) use ($L): string {
        return e((string)($L[$k] ?? $d));
    };
    $opts = '<option value="">' . $lab('service_ph', 'Seleccione una opción') . '</option>';
    foreach ((array)($b['services'] ?? []) as $s) {
        $opts .= '<option value="' . e((string)$s) . '">' . e((string)$s) . '</option>';
    }
    $wa = preg_replace('/\D+/', '', (string)($S['wa_number'] ?? '')) ?? '';
    $o = '<section class="sec sec-form"><div class="wrap">'
        . '<div class="formbox reveal">'
        . '<h2 class="form-t">' . e((string)($b['title'] ?? '')) . '</h2>'
        . '<form class="cf" id="cf" method="post" action="/enviar.php" novalidate data-wa="' . e($wa) . '" data-intro="' . e((string)($b['wa_intro'] ?? '')) . '">'
        . '<div class="cf-grid">'
        . '<div class="fld"><label for="cf-name">' . $lab('name', 'Nombre *') . '</label><input id="cf-name" name="name" type="text" autocomplete="name" maxlength="100" required></div>'
        . '<div class="fld"><label for="cf-phone">' . $lab('phone', 'Teléfono *') . '</label><input id="cf-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="25" required></div>'
        . '<div class="fld"><label for="cf-email">' . $lab('email', 'Correo electrónico') . '</label><input id="cf-email" name="email" type="email" autocomplete="email" maxlength="254"></div>'
        . '<div class="fld"><label for="cf-service">' . $lab('service', 'Tipo de servicio *') . '</label><select id="cf-service" name="service" required>' . $opts . '</select></div>'
        . '<div class="fld fld-p"><label for="cf-place">' . $lab('place', 'Ubicación / destino') . '</label><input id="cf-place" name="place" type="text" maxlength="200"></div>'
        . '<div class="fld fld-w"><label for="cf-msg">' . $lab('message', 'Mensaje *') . '</label><textarea id="cf-msg" name="message" rows="4" maxlength="3000" required></textarea></div>'
        . '</div>'
        . '<div class="hp" aria-hidden="true"><label for="cf-web">No llenar</label><input id="cf-web" name="website" type="text" tabindex="-1" autocomplete="off"></div>'
        . '<input type="hidden" name="token" value="">'
        . '<button class="btn btn-red btn-wa" type="submit">' . gx_icon('whatsapp') . '<span>' . $lab('send', 'Enviar mensaje') . '</span></button>'
        . '<p class="cf-msgbox" id="cf-status" role="status" aria-live="polite"></p>'
        . '<noscript><p class="cf-noscript">Active JavaScript para enviar el formulario o escríbanos por <a href="' . e(gx_wa_url($S)) . '">WhatsApp</a> o llámenos al <a href="tel:' . e((string)($S['phone_tel'] ?? '')) . '">' . e((string)($S['phone_display'] ?? '')) . '</a>.</p></noscript>'
        . '</form></div></div></section>';
    return $o;
}

/* ---------------------- estructura común ---------------------- */

function gx_nav_html(array $S, string $current, string $cls): string
{
    $o = '<nav class="' . $cls . '" aria-label="Menú principal"><ul>';
    foreach ((array)($S['menu'] ?? []) as $it) {
        if (empty($it['visible'])) {
            continue;
        }
        $link = gx_safe_url((string)($it['link'] ?? ''));
        if ($link === '') {
            continue;
        }
        $isCur = ($link === $current) ? ' aria-current="page" class="on"' : '';
        $ic = preg_match('/^[a-z0-9-]{2,24}$/', (string)($it['icon'] ?? '')) ? (string)$it['icon'] : '';
        $o .= '<li><a href="' . e($link) . '"' . $isCur . '>' . ($ic !== '' ? '<span class="ni">' . gx_icon($ic) . '</span>' : '') . '<span class="nt">' . e((string)($it['label'] ?? '')) . '</span></a></li>';
    }
    return $o . '</ul></nav>';
}

function gx_header_html(array $S, array $media, string $current): string
{
    $logo = $media[(int)($S['logo'] ?? 0)] ?? null;
    $logoM = $media[(int)($S['logo_mobile'] ?? 0)] ?? $logo;
    $home = '<a class="brand" href="/" aria-label="' . e((string)($S['site_name'] ?? '')) . '">';
    if ($logo) {
        $home .= '<picture><source media="(max-width: 520px)" srcset="' . e(gx_media_url($logoM)) . '">'
            . '<img src="' . e(gx_media_url($logo)) . '" width="' . (int)$logo['w'] . '" height="' . (int)$logo['h'] . '" alt="' . e((string)$logo['alt']) . '" decoding="async"></picture>';
    } else {
        $home .= '<span class="brand-t">' . e((string)($S['site_name'] ?? '')) . '</span>';
    }
    $home .= '</a>';

    $top = '';
    if (!empty($S['topbar_on'])) {
        $top = '<div class="topbar"><div class="wrap topbar-in">'
            . '<a class="tb" href="tel:' . e((string)($S['phone_tel'] ?? '')) . '">' . gx_icon('phone') . '<span>' . e((string)($S['topbar_phone_label'] ?? '')) . e((string)($S['phone_display'] ?? '')) . '</span></a>'
            . '<span class="tb-sep" aria-hidden="true">|</span>'
            . '<a class="tb" href="mailto:' . e((string)($S['email_link'] ?? '')) . '">' . gx_icon('mail') . '<span>' . e((string)($S['topbar_email_label'] ?? '')) . e((string)($S['email_display'] ?? '')) . '</span></a>'
            . '</div></div>';
    }
    return $top . '<header class="hdr" id="hdr"><div class="wrap hdr-in">' . $home
        . gx_nav_html($S, $current, 'nav')
        . '<button class="burger" type="button" id="burger" aria-label="Abrir menú" aria-expanded="false" aria-controls="mnav"><span></span><span></span><span></span></button>'
        . '</div></header>'
        . '<div class="mnav-ov" id="mnav-ov" hidden></div><aside class="mnav" id="mnav" aria-hidden="true">'
        . '<button class="mnav-x" type="button" id="mnav-x" aria-label="Cerrar menú">' . gx_icon('x') . '</button>'
        . gx_nav_html($S, $current, 'mnav-l')
        . '<a class="btn btn-red mnav-cta" href="tel:' . e((string)($S['phone_tel'] ?? '')) . '">' . gx_icon('phone') . '<span>' . e((string)($S['topbar_phone_label'] ?? '')) . e((string)($S['phone_display'] ?? '')) . '</span></a>'
        . '</aside>';
}

function gx_hero_html(array $page, array $S, array $media): string
{
    $h = (array)($page['hero'] ?? []);
    $img = $media[(int)($h['image'] ?? 0)] ?? null;
    $t = gx_tokens((string)($h['title'] ?? ''), $S);
    $st = gx_tokens((string)($h['subtitle'] ?? ''), $S);
    $tx = gx_tokens((string)($h['text'] ?? ''), $S);
    $bt = gx_tokens((string)($h['btn_text'] ?? ''), $S);
    $bl = gx_safe_url(gx_tokens((string)($h['btn_link'] ?? ''), $S));

    $o = '<section class="hero"><div class="wrap hero-in"><div class="hero-tx reveal">';
    if ($t !== '' || $st !== '') {
        $o .= '<h1 class="h1">' . ($t !== '' ? '<span class="h1-a">' . e($t) . '</span> ' : '') . ($st !== '' ? '<span class="h1-b">' . e($st) . '</span>' : '') . '</h1>';
    }
    if ($tx !== '') {
        $o .= '<p class="hero-sub">' . e($tx) . '</p>';
    }
    if ($bt !== '' && $bl !== '') {
        $isTel = str_starts_with($bl, 'tel:');
        $o .= '<a class="btn btn-red btn-hero" href="' . e($bl) . '">' . gx_icon($isTel ? 'phone' : 'arrow-right') . '<span>' . e($bt) . '</span></a>';
    }
    $o .= '</div>';
    if ($img) {
        $o .= '<div class="hero-fig reveal"><div class="frame"><img class="hero-img" src="' . e(gx_media_url($img)) . '" width="' . (int)$img['w'] . '" height="' . (int)$img['h'] . '" alt="' . e((string)$img['alt']) . '" fetchpriority="high" decoding="async"></div></div>';
    }
    return $o . '</div></section>';
}

function gx_footer_html(array $S): string
{
    $extra = '';
    if (trim((string)($S['address'] ?? '')) !== '') {
        $extra .= '<p class="ft-line">' . gx_icon('map-pin') . '<span>' . e((string)$S['address']) . '</span></p>';
    }
    if (trim((string)($S['hours'] ?? '')) !== '') {
        $extra .= '<p class="ft-line">' . gx_icon('clock') . '<span>' . e((string)$S['hours']) . '</span></p>';
    }
    $soc = '';
    foreach ((array)($S['socials'] ?? []) as $s) {
        $net = (string)($s['net'] ?? '');
        $url = gx_safe_url((string)($s['url'] ?? ''));
        if ($url !== '' && preg_match('/^(facebook|instagram|tiktok|youtube|x)$/', $net)) {
            $soc .= '<a class="soc" href="' . e($url) . '" target="_blank" rel="noopener noreferrer" aria-label="' . e(ucfirst($net)) . '">' . gx_icon($net) . '</a>';
        }
    }
    if ($soc !== '') {
        $extra .= '<p class="ft-soc">' . $soc . '</p>';
    }
    return '<footer class="ftr"><div class="wrap">' . $extra . '<p class="copy">' . e((string)($S['footer_text'] ?? '')) . '</p></div></footer>'
        . '<div class="fab">'
        . '<button class="fab-top" type="button" id="totop" aria-label="Subir al inicio" hidden>' . gx_icon('chevron-up') . '</button>'
        . '<a class="fab-b fab-call" href="tel:' . e((string)($S['phone_tel'] ?? '')) . '" aria-label="Llamar">' . gx_icon('phone') . '</a>'
        . '<a class="fab-b fab-wa" href="' . e(gx_wa_url($S)) . '" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp">' . gx_icon('whatsapp') . '</a>'
        . '</div>';
}

function gx_jsonld(array $S, array $media, array $page): string
{
    $base = gx_site_url($S);
    $logo = $media[(int)($S['logo'] ?? 0)] ?? null;
    $heroImg = $media[(int)(($page['hero']['image'] ?? 0))] ?? null;
    $tel = '+' . preg_replace('/\D+/', '', (string)($S['wa_number'] ?? ''));
    $d = [
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        '@id' => $base . '/#negocio',
        'name' => (string)($S['site_name'] ?? ''),
        'url' => $base . '/',
        'telephone' => $tel,
        'email' => (string)($S['email_display'] ?? ''),
        'image' => $heroImg ? $base . gx_media_url($heroImg) : ($logo ? $base . gx_media_url($logo) : null),
        'logo' => $logo ? $base . gx_media_url($logo) : null,
        'description' => (string)($S['ld_description'] ?? ''),
        'areaServed' => ['@type' => 'Country', 'name' => 'Guatemala'],
        'address' => ['@type' => 'PostalAddress', 'addressCountry' => 'GT'],
        'openingHoursSpecification' => [[
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'opens' => '00:00', 'closes' => '23:59',
        ]],
        'priceRange' => (string)($S['ld_price'] ?? ''),
    ];
    if (trim((string)($S['address'] ?? '')) !== '') {
        $d['address']['streetAddress'] = (string)$S['address'];
    }
    $same = [];
    foreach ((array)($S['socials'] ?? []) as $s) {
        $u = gx_safe_url((string)($s['url'] ?? ''));
        if ($u !== '' && str_starts_with($u, 'http')) {
            $same[] = $u;
        }
    }
    if ($same) {
        $d['sameAs'] = $same;
    }
    $d = array_filter($d, static function ($v) {
        return $v !== null && $v !== '';
    });
    return json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?: '{}';
}

function gx_page_html(array $page, array $S, array $media, string $kind = 'page'): string
{
    $base = gx_site_url($S);
    $path = (string)($page['path'] ?? '/');
    $title = (string)($page['seo_title'] ?? $S['site_name'] ?? '');
    $desc = (string)($page['seo_desc'] ?? '');
    $ogm = $media[(int)($page['og_image'] ?? ($page['hero']['image'] ?? 0))] ?? null;
    $robots = ($kind === 'page') ? 'index, follow, max-image-preview:large' : 'noindex, nofollow';

    $h = '<!doctype html><html lang="es-GT"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
        . '<title>' . e($title) . '</title>'
        . ($desc !== '' ? '<meta name="description" content="' . e($desc) . '">' : '')
        . '<meta name="robots" content="' . $robots . '">'
        . '<meta name="theme-color" content="' . e((string)($S['colors']['navy'] ?? '#0a2a40')) . '">'
        . '<link rel="canonical" href="' . e($base . $path) . '">';
    if ($kind === 'page') {
        $h .= '<meta property="og:locale" content="es_GT"><meta property="og:type" content="website">'
            . '<meta property="og:site_name" content="' . e((string)($S['site_name'] ?? '')) . '">'
            . '<meta property="og:title" content="' . e($title) . '">'
            . ($desc !== '' ? '<meta property="og:description" content="' . e($desc) . '">' : '')
            . '<meta property="og:url" content="' . e($base . $path) . '">'
            . ($ogm ? '<meta property="og:image" content="' . e($base . gx_media_url($ogm)) . '"><meta property="og:image:width" content="' . (int)$ogm['w'] . '"><meta property="og:image:height" content="' . (int)$ogm['h'] . '">' : '')
            . '<meta name="twitter:card" content="summary_large_image">'
            . '<meta name="twitter:title" content="' . e($title) . '">'
            . ($desc !== '' ? '<meta name="twitter:description" content="' . e($desc) . '">' : '')
            . ($ogm ? '<meta name="twitter:image" content="' . e($base . gx_media_url($ogm)) . '">' : '');
    }
    $h .= '<link rel="icon" href="/favicon.ico" sizes="any"><link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">'
        . '<link rel="apple-touch-icon" href="/apple-touch-icon.png">'
        . '<link rel="preload" href="/assets/fonts/bsd-800.woff2" as="font" type="font/woff2" crossorigin>'
        . '<link rel="preload" href="/assets/fonts/manrope-500.woff2" as="font" type="font/woff2" crossorigin>'
        . '<link rel="stylesheet" href="/assets/css/site.min.css?v=' . e(GX_VERSION) . '">'
        . '<link rel="stylesheet" href="/assets/css/theme.css?v=' . e((string)($S['theme_v'] ?? '1')) . '">'
        . '<script src="/assets/js/boot.min.js"></script>';
    if ($kind === 'page') {
        $h .= '<script type="application/ld+json">' . gx_jsonld($S, $media, $page) . '</script>';
    }
    $h .= '</head><body class="pg-' . e((string)($page['slug'] ?? 'x')) . '">'
        . '<a class="skip" href="#main">Ir al contenido</a>'
        . gx_header_html($S, $media, $path)
        . '<main id="main">';
    if ($kind === 'page') {
        $h .= gx_hero_html($page, $S, $media);
        foreach ((array)($page['blocks'] ?? []) as $b) {
            $h .= gx_block_html((array)$b, $S, $media);
        }
    } else {
        $h .= (string)($page['error_html'] ?? '');
    }
    $h .= '</main>' . gx_footer_html($S)
        . '<script src="/assets/js/site.min.js" defer></script></body></html>';
    return $h;
}

/* ---------------------- publicación ---------------------- */

function gx_theme_css(array $S): string
{
    $c = (array)($S['colors'] ?? []);
    $v = [
        'navy' => gx_color($c['navy'] ?? '', '#0a2a40'),
        'blue' => gx_color($c['blue'] ?? '', '#11557b'),
        'red' => gx_color($c['red'] ?? '', '#bf0000'),
        'bg' => gx_color($c['bg'] ?? '', '#ffffff'),
        'soft' => gx_color($c['soft'] ?? '', '#eef4f8'),
        'ink' => gx_color($c['ink'] ?? '', '#12303f'),
    ];
    $o = "/* generado por el panel: no editar a mano */\n:root{";
    foreach ($v as $k => $x) {
        $o .= '--' . $k . ':' . $x . ';';
    }
    return $o . "}\n";
}

function gx_error_pages(array $S): array
{
    $btn = '<a class="btn btn-red" href="/">' . gx_icon('arrow-right') . '<span>Volver al inicio</span></a> '
        . '<a class="btn btn-ghost" href="tel:' . e((string)($S['phone_tel'] ?? '')) . '">' . gx_icon('phone') . '<span>' . e((string)($S['phone_display'] ?? '')) . '</span></a>';
    $mk = static function (string $code, string $t, string $p) use ($btn): string {
        return '<section class="sec err"><div class="wrap err-in"><p class="err-code">' . $code . '</p><h1 class="err-t">' . e($t) . '</h1><p class="err-p">' . e($p) . '</p><div class="err-btns">' . $btn . '</div></div></section>';
    };
    return [
        '404.html' => ['Página no encontrada', $mk('404', 'Página no encontrada', 'La dirección que busca no existe o fue movida.')],
        '403.html' => ['Acceso denegado', $mk('403', 'Acceso denegado', 'No tiene permiso para ver este recurso.')],
        '500.html' => ['Error del servidor', $mk('500', 'Error del servidor', 'Ocurrió un problema temporal. Intente de nuevo en unos minutos.')],
    ];
}

/**
 * Regenera todas las páginas estáticas. Escribe SOLO archivos de salida (html, xml, txt, css)
 * en rutas fijas; nunca toca archivos PHP.
 * @return array{files:array<string,string>,errors:array<int,string>}
 */
function gx_publish(): array
{
    $S = gx_settings_all(true);
    $S['theme_v'] = (string)gx_now();
    gx_set('theme_v', $S['theme_v']);
    $S = gx_settings_all(true);
    $media = [];
    foreach (gx_media_all() as $m) {
        $media[(int)$m['id']] = $m;
    }
    $out = [];
    $errors = [];
    $pages = gx_pages();
    $write = static function (string $rel, string $content) use (&$out, &$errors): void {
        $abs = GX_ROOT . '/' . $rel;
        $dir = dirname($abs);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            $errors[] = 'No se pudo crear la carpeta de ' . $rel;
            return;
        }
        if (file_put_contents($abs, $content, LOCK_EX) === false) {
            $errors[] = 'No se pudo escribir ' . $rel . ' (revise permisos).';
            return;
        }
        @chmod($abs, 0644);
        $out[$rel] = hash('sha256', $content);
    };

    $urls = [];
    foreach ($pages as $slug => $p) {
        if (!preg_match('/^[a-z0-9-]{1,60}$/', $slug)) {
            continue;
        }
        $path = (string)($p['path'] ?? '');
        if (!preg_match('~^/([a-z0-9-]+/)?$~', $path)) {
            continue;
        }
        $rel = ($path === '/') ? 'index.html' : trim($path, '/') . '/index.html';
        $write($rel, gx_page_html($p, $S, $media));
        $urls[] = $path;
    }
    foreach (gx_error_pages($S) as $file => $d) {
        $write($file, gx_page_html(['slug' => 'err', 'path' => '/' . $file, 'seo_title' => $d[0] . ' - ' . ($S['site_name'] ?? ''), 'error_html' => $d[1], 'hero' => []], $S, $media, 'error'));
    }
    $base = gx_site_url($S);
    $today = date('Y-m-d');
    $sm = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $u) {
        $sm .= '  <url><loc>' . e($base . $u) . '</loc><lastmod>' . $today . '</lastmod><changefreq>weekly</changefreq><priority>' . ($u === '/' ? '1.0' : '0.8') . '</priority></url>' . "\n";
    }
    $sm .= '</urlset>' . "\n";
    $write('sitemap.xml', $sm);
    $write('robots.txt', "User-agent: *\nAllow: /\nDisallow: /_core/\nDisallow: /enviar.php\n\nSitemap: " . $base . "/sitemap.xml\n");
    $write('assets/css/theme.css', gx_theme_css($S));

    // favicons desde el medio configurado
    $fav = $media[(int)($S['favicon'] ?? 0)] ?? null;
    if ($fav && !empty($S['favicon_dirty'])) {
        gx_make_favicons($fav);
        gx_set('favicon_dirty', false);
    }
    if (!$errors) {
        gx_set('gen_manifest', $out);
        gx_set('last_publish', gx_now());
    }
    return ['files' => $out, 'errors' => $errors];
}
