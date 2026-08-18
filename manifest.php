<?php
/**
 * Dynamic web app manifest.
 *
 * Served with absolute, same-origin URLs so Chrome installs the site as a real
 * app (WebAPK) instead of a home-screen shortcut. It never redirects and never
 * errors: if the app is not installed yet, or the DB is unreachable, it falls
 * back to sane defaults and still returns a valid manifest.
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

define('FL_APP', __DIR__ . '/app');

// Minimal autoloader (same as bootstrap, without the installer redirect).
spl_autoload_register(function (string $class): void {
    $file = FL_APP . '/' . $class . '.php';
    if (is_file($file)) { require $file; }
});
require FL_APP . '/helpers.php';

$name       = 'Liga de Fútbol';
$themeBg    = '#0B0F14';
$themeColor = '#0B0F14';

$configFile = FL_APP . '/config.php';
if (is_file($configFile)) {
    try {
        $config = require $configFile;
        if (!empty($config['app']['base_url'])) { define('FL_BASE_URL', $config['app']['base_url']); }
        Database::connect($config['db']);
        Settings::load();
        $name    = (string)Settings::get('site_name', $name);
        $theme   = Theme::resolve((int)Settings::get('default_theme_id', 1));
        $themeBg    = $theme['color_bg'] ?? $themeBg;
        $themeColor = $theme['color_bg'] ?? $themeColor;
    } catch (Throwable $e) {
        // Keep defaults — a manifest must always be valid.
    }
}

$name  = trim($name) !== '' ? trim($name) : 'Liga de Fútbol';

// Build a short name (~12 chars) without cutting a word in half.
$short = $name;
if (mb_strlen($name) > 12) {
    $short = '';
    foreach (preg_split('/\s+/', $name) as $word) {
        $candidate = $short === '' ? $word : $short . ' ' . $word;
        if (mb_strlen($candidate) > 12) { break; }
        $short = $candidate;
    }
    if ($short === '') { $short = mb_substr($name, 0, 12); } // single very long word
    $short = preg_replace('/\s+(de|del|la|el|los|las|y)$/iu', '', $short) ?: $short; // no trailing connector
}

// Absolute, same-origin URLs (with correct scheme/host) remove any ambiguity
// that would make Chrome fall back to a plain shortcut.
$root = base_url('');

$manifest = [
    'id'               => $root,
    'lang'             => 'es',
    'dir'              => 'ltr',
    'name'             => $name,
    'short_name'       => $short,
    'description'      => 'Resultados, tablas, goleadores y estadísticas de tu liga de fútbol.',
    'start_url'        => $root,
    'scope'            => $root,
    'display'          => 'standalone',
    'display_override' => ['standalone', 'minimal-ui'],
    'orientation'      => 'portrait',
    'background_color' => $themeBg,
    'theme_color'      => $themeColor,
    'prefer_related_applications' => false,
    'icons' => [
        ['src' => base_url('assets/img/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => base_url('assets/img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => base_url('assets/img/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
        ['src' => base_url('assets/img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');
echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
