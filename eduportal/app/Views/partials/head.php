<?php
/** @var array $cfgColegio */
use App\Controllers\Configuracion;
use App\Core\Auth;
use App\Core\Settings;

$u = Auth::user();
$temaClave = (string)($u['tema'] ?? Settings::get('tema', 'default'));
if (!isset(Configuracion::TEMAS[$temaClave])) {
    $temaClave = (string)Settings::get('tema', 'default');
}
$tema = Configuracion::TEMAS[$temaClave] ?? Configuracion::TEMAS['default'];
$oscuro = (int)($u['modo_oscuro'] ?? 0) === 1 ? '1' : '0';
$colorPersonalizado = (string)Settings::get('color_personalizado', '');
$nombreColegio = (string)Settings::get('colegio_nombre', 'EduPortal');
$logo = (string)Settings::get('colegio_logo', '');
$favicon = (string)Settings::get('colegio_favicon', '') ?: $logo;
$tituloPagina = trim(($titulo ?? '') !== '' ? ($titulo . ' · ' . $nombreColegio) : $nombreColegio);
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($tituloPagina) ?></title>
<meta name="description" content="<?= e($descripcion ?? Settings::get('seo_description', 'Portal escolar')) ?>">
<meta name="theme-color" content="<?= e($tema['primario']) ?>">
<meta name="color-scheme" content="light dark">
<meta name="format-detection" content="telephone=no">
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e(mb_substr($nombreColegio, 0, 14)) ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= e(is_file(BASE_PATH . '/storage/uploads/pwa/icon-180.png') ? url('archivo/pwa/icon-180.png') : url('assets/icons/icon-180.png')) ?>">
<link rel="apple-touch-icon" sizes="152x152" href="<?= e(is_file(BASE_PATH . '/storage/uploads/pwa/icon-152.png') ? url('archivo/pwa/icon-152.png') : url('assets/icons/icon-152.png')) ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?= e(is_file(BASE_PATH . '/storage/uploads/pwa/icon-192.png') ? url('archivo/pwa/icon-192.png') : url('assets/icons/icon-192.png')) ?>">
<?php if ($favicon !== ''): ?>
<link rel="shortcut icon" href="<?= e(archivo_url($favicon)) ?>">
<?php else: ?>
<link rel="shortcut icon" href="<?= e(asset('icons/icon-96.png')) ?>">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="<?= e(asset('fonts/inter-latin-400-normal.woff2')) ?>" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/paginas.css')) ?>">
<!-- Las fuentes locales de /assets/fonts se usan de inmediato; la copia de Google Fonts
     se solicita despues, sin bloquear el render (ver assets/js/app.js). -->
<?php if ($colorPersonalizado !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $colorPersonalizado)): ?>
<style>:root{--acento:<?= e($colorPersonalizado) ?>}</style>
<?php endif; ?>
