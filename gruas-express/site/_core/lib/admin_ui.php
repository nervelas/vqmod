<?php
declare(strict_types=1);
if (!defined('GX')) { http_response_code(403); exit; }

/* ---------- mensajes flash ---------- */

function gx_flash(string $type, string $msg): void
{
    $_SESSION['gx_flash'][] = [$type, $msg];
}

function gx_flash_html(): string
{
    $o = '';
    foreach ((array)($_SESSION['gx_flash'] ?? []) as $f) {
        $o .= '<div class="al al-' . ($f[0] === 'ok' ? 'ok' : ($f[0] === 'warn' ? 'warn' : 'bad')) . '" role="status">' . e((string)$f[1]) . '</div>';
    }
    unset($_SESSION['gx_flash']);
    return $o;
}

function gx_redirect(string $p = '', array $q = []): void
{
    $qs = $p === '' ? '' : '?p=' . rawurlencode($p);
    foreach ($q as $k => $v) {
        $qs .= ($qs === '' ? '?' : '&') . rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
    }
    header('Location: ' . gx_admin_base() . $qs, true, 303);
    exit;
}

/* ---------- formularios ---------- */

function gx_f_open(string $act, string $extra = '', bool $multipart = false): string
{
    return '<form method="post" action="' . e(gx_admin_base()) . '"' . ($multipart ? ' enctype="multipart/form-data"' : '') . ' autocomplete="off">'
        . '<input type="hidden" name="_t" value="' . e(gx_csrf()) . '"><input type="hidden" name="act" value="' . e($act) . '">' . $extra;
}

function gx_f_in(string $label, string $name, $val, string $type = 'text', string $hint = '', string $attr = ''): string
{
    return '<div class="f"><label>' . e($label) . '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e((string)$val) . '" ' . $attr . '></label>'
        . ($hint !== '' ? '<small>' . e($hint) . '</small>' : '') . '</div>';
}

function gx_f_ta(string $label, string $name, $val, int $rows = 4, bool $rte = false, string $hint = ''): string
{
    return '<div class="f"><label>' . e($label) . '</label>'
        . ($rte ? '<div class="tb" data-tb></div>' : '')
        . '<textarea name="' . e($name) . '" rows="' . $rows . '"' . ($rte ? ' class="rte"' : '') . '>' . e((string)$val) . '</textarea>'
        . ($hint !== '' ? '<small>' . e($hint) . '</small>' : '') . '</div>';
}

function gx_f_sel(string $label, string $name, array $opts, $cur): string
{
    $o = '<div class="f"><label>' . e($label) . '<select name="' . e($name) . '">';
    foreach ($opts as $v => $t) {
        $o .= '<option value="' . e((string)$v) . '"' . ((string)$v === (string)$cur ? ' selected' : '') . '>' . e((string)$t) . '</option>';
    }
    return $o . '</select></label></div>';
}

function gx_f_chk(string $label, string $name, bool $on): string
{
    return '<div class="f f-chk"><label><input type="checkbox" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . '> <span>' . e($label) . '</span></label></div>';
}

/** Selector de imagen (usa el modal de la biblioteca). */
function gx_f_media(string $label, string $name, int $cur): string
{
    $m = $cur > 0 ? gx_media_get($cur) : null;
    return '<div class="f mp" data-mp><label>' . e($label) . '</label>'
        . '<input type="hidden" name="' . e($name) . '" value="' . $cur . '" data-mp-in>'
        . '<div class="mp-row"><span class="mp-th">' . ($m ? '<img src="/uploads/' . e($m['thumb']) . '" alt="" data-mp-img>' : '<img alt="" data-mp-img hidden>') . '</span>'
        . '<button type="button" class="b b-s" data-mp-open>Elegir imagen</button>'
        . '<button type="button" class="b b-s b-g" data-mp-clear>Quitar</button></div></div>';
}

function gx_media_json(): string
{
    $rows = [];
    foreach (gx_media_all() as $m) {
        $rows[] = ['id' => (int)$m['id'], 't' => '/uploads/' . $m['thumb'], 'n' => $m['name'], 'a' => $m['alt']];
    }
    return '<script type="application/json" id="media-data">' . json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
}

/* ---------- plantilla ---------- */

function gx_a_nav(): array
{
    return [
        'panel' => ['Panel', 'layout-dashboard'],
        'paginas' => ['Páginas', 'file-text'],
        'medios' => ['Imágenes', 'image'],
        'ajustes' => ['Ajustes', 'settings'],
        'mensajes' => ['Mensajes', 'mail-open'],
        'seguridad' => ['Seguridad', 'shield-check'],
        'respaldos' => ['Respaldos', 'download'],
        'registro' => ['Registro', 'scan-search'],
        'cuenta' => ['Mi cuenta', 'key-round'],
    ];
}

function gx_a_page(string $title, string $active, string $body): void
{
    $b = e(gx_admin_base());
    $unread = 0;
    try {
        $unread = (int)gx_db()->query('SELECT COUNT(*) FROM messages WHERE is_read = 0')->fetchColumn();
    } catch (Throwable $t) {
        $unread = 0;
    }
    $pending = (bool)gx_setting('pending_publish', false);
    $nav = '';
    foreach (gx_a_nav() as $k => $v) {
        $badge = ($k === 'mensajes' && $unread > 0) ? ' <em class="bd">' . $unread . '</em>' : '';
        $nav .= '<a href="' . $b . '?p=' . $k . '"' . ($k === $active ? ' class="on" aria-current="page"' : '') . '>' . gx_icon($v[1]) . '<span>' . e($v[0]) . $badge . '</span></a>';
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex, nofollow"><title>' . e($title) . ' · Panel</title>'
        . '<link rel="icon" href="/favicon.png"><link rel="stylesheet" href="' . $b . 'assets/admin.css?v=' . e(GX_VERSION) . '"></head><body>'
        . '<div class="shell"><aside class="side" id="side"><div class="side-h"><span class="logo-t">Grúas Express</span><small>Panel de administración</small></div>'
        . '<nav class="side-n">' . $nav . '</nav>'
        . gx_f_open('logout') . '<button class="b b-g b-w" type="submit">' . gx_icon('log-out') . ' Salir</button></form></aside>'
        . '<div class="main"><header class="bar"><button class="b b-s menu-b" type="button" id="side-t" aria-label="Menú">' . gx_icon('menu') . '</button>'
        . '<h1>' . e($title) . '</h1><div class="bar-r">'
        . ($pending ? '<span class="pend">Cambios sin publicar</span>' : '')
        . '<a class="b b-s b-g" href="/" target="_blank" rel="noopener">Ver sitio</a>'
        . gx_f_open('publish') . '<button class="b b-p" type="submit">' . gx_icon('rocket') . ' Publicar</button></form></div></header>'
        . '<main class="content">' . gx_flash_html() . $body . '</main></div></div>'
        . gx_media_json()
        . '<script src="' . $b . 'assets/admin.js?v=' . e(GX_VERSION) . '" defer></script></body></html>';
    exit;
}

/** Página sencilla (login, 2FA, bloqueo) sin menú lateral. */
function gx_a_bare(string $title, string $body, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: text/html; charset=UTF-8');
    $b = e(gx_admin_base());
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex, nofollow"><title>' . e($title) . '</title>'
        . '<link rel="icon" href="/favicon.png"><link rel="stylesheet" href="' . $b . 'assets/admin.css?v=' . e(GX_VERSION) . '"></head><body class="bare">'
        . '<main class="bare-box"><div class="bare-h"><span class="logo-t">Grúas Express</span><small>Panel de administración</small></div>'
        . gx_flash_html() . $body . '</main></body></html>';
    exit;
}
