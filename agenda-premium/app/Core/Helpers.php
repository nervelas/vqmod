<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Fmt;
use App\Core\Security;
use App\Core\Settings;
use App\Core\View;

/** Escape HTML (contexto: texto y atributos entre comillas). */
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Valor para un atributo data-* o JSON incrustado en un atributo. */
function ej($v): string
{
    return e(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP));
}

/** JSON seguro para <script type="application/json"> (no ejecutable; permitido por la CSP). */
function json_script($v): string
{
    return (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function url(string $path = '', array $query = []): string
{
    $u = (defined('BASE_PATH') ? BASE_PATH : '') . '/' . ltrim($path, '/');
    if ($path === '' || $path === '/') {
        $u = (defined('BASE_PATH') ? BASE_PATH : '') . '/';
    }
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

/** URL absoluta (para correos, ICS, enlaces compartibles). */
function abs_url(string $path = '', array $query = []): string
{
    global $__request;
    $base = $__request instanceof \App\Core\Request ? $__request->baseUrl() : rtrim((string) \App\Core\Config::get('base_url', ''), '/');
    $p = '/' . ltrim($path, '/');
    return $base . ($p === '/' ? '/' : $p) . ($query ? '?' . http_build_query($query) : '');
}

/** URL de un recurso estático con versión (cache busting). */
function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : '0';
    return url('/assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function nonce(): string
{
    return Security::nonce();
}

function csrf_field(): string
{
    return Csrf::field();
}

function csrf_token(): string
{
    return Csrf::token();
}

/** Campo oculto con token público firmado (páginas sin sesión). */
function public_csrf_field(string $scope): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::publicToken($scope)) . '">';
}

function public_csrf_token(string $scope): string
{
    return Csrf::publicToken($scope);
}

function setting(string $key, $default = null)
{
    return Settings::get($key, $default);
}

function money($n, bool $symbol = true): string
{
    return Fmt::money($n, $symbol);
}

function partial(string $tpl, array $data = []): void
{
    echo View::partial($tpl, $data);
}

/** Icono del sprite SVG propio: assets/img/icons.svg (ids "i-nombre"). */
function icon(string $name, string $class = ''): string
{
    $name = preg_replace('/[^a-z0-9\-]/', '', strtolower($name));
    return '<svg class="ic ' . e($class) . '" aria-hidden="true" focusable="false"><use href="' . e(asset('img/icons.svg')) . '#i-' . $name . '"></use></svg>';
}

/** Atributo para aplicar variables CSS por JS (la CSP no permite style="" en línea). Ej: <div <?= vars(['--c' => $color]) ?>> */
function vars(array $vars): string
{
    $parts = [];
    foreach ($vars as $k => $v) {
        if (preg_match('/^--[a-z0-9\-]+$/', (string) $k)) {
            $parts[] = $k . ':' . preg_replace('/[^A-Za-z0-9#%.\-_ ,()\/]/', '', (string) $v);
        }
    }
    return 'data-vars="' . e(implode(';', $parts)) . '"';
}

/** Selected/checked helpers */
function sel($a, $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function chk($cond): string
{
    return $cond ? ' checked' : '';
}

function old(array $data, string $key, $default = '')
{
    return $data[$key] ?? $default;
}
