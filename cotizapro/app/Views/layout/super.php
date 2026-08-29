<?php
$withPanelCss = true;
$withPanelJs  = true;
$noindex = true;
$theme = ['accent' => '#E8590C', 'ink' => '#1C1F22', 'paper' => '#F5F6F4'];
$path = \App\Core\Request::path();
$nav = [
    ['/super', 'Resumen', 'M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z'],
    ['/super/empresas', 'Empresas', 'M3 21V8l7-5 7 5v13M9 21v-6h4v6M17 21h4V11l-4-3'],
    ['/super/planes', 'Planes y límites', 'M4 4h16v4H4V4Zm0 6h16v4H4v-4Zm0 6h10v4H4v-4Z'],
    ['/super/landing', 'Landing de venta', 'M4 4h16v6H4V4Zm0 8h7v8H4v-8Zm9 0h7v8h-7v-8Z'],
    ['/super/respaldos', 'Respaldos', 'M12 3v12m0 0 4-4m-4 4-4-4M4 19h16'],
    ['/super/bitacora', 'Bitácora global', 'M6 3h9l4 4v14H6V3Zm3 7h8M9 14h8M9 18h5'],
    ['/super/ajustes', 'Ajustes', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8-3a8 8 0 0 1-.1 1.2l2 1.6-2 3.4-2.4-1a8 8 0 0 1-2 1.2L15 21H9l-.5-2.6a8 8 0 0 1-2-1.2l-2.4 1-2-3.4 2-1.6a8 8 0 0 1 0-2.4l-2-1.6 2-3.4 2.4 1a8 8 0 0 1 2-1.2L9 3h6l.5 2.6a8 8 0 0 1 2 1.2l2.4-1 2 3.4-2 1.6c.07.4.1.8.1 1.2Z'],
];
?>
<!doctype html>
<html lang="es">
<head><?= \App\Core\View::partial('partials/head', get_defined_vars()) ?></head>
<body>
<a class="skip" href="#contenido">Saltar al contenido</a>
<div class="app">
  <aside class="side" id="sidebar">
    <a class="side__brand" href="<?= e(url('/super')) ?>">
      <span class="side__mark" aria-hidden="true">CP</span>
      <span><b><?= e($platformName ?? 'CotizaPro B2B') ?></b><small>Superadministración</small></span>
    </a>
    <nav aria-label="Superadmin">
      <div class="side__label">Plataforma</div>
      <?php foreach ($nav as [$href, $label, $d]): $active = $href === '/super' ? $path === '/super' : str_starts_with($path, $href); ?>
        <a href="<?= e(url($href)) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= e($d) ?>"/></svg>
          <span><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="side__foot">
      <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Ver la landing ↗</a>
      <a href="<?= e(url('/salir')) ?>">Cerrar sesión</a>
    </div>
  </aside>
  <div class="main">
    <div class="pbar">
      <button class="sidetoggle" type="button" aria-expanded="false" aria-controls="sidebar" aria-label="Abrir menú">
        <svg width="18" height="14" viewBox="0 0 18 14" aria-hidden="true"><path d="M0 1h18M0 7h18M0 13h18" stroke="currentColor" stroke-width="1.6"/></svg>
      </button>
      <h1><?= e($title ?? 'Plataforma') ?></h1>
      <div class="pbar__actions"><?= $barActions ?? '' ?><span class="badge badge--dark">Superadmin</span></div>
    </div>
    <div class="pbody" id="contenido">
      <?= \App\Core\View::partial('partials/flash', get_defined_vars()) ?>
      <?= $content ?>
    </div>
  </div>
</div>
<?= \App\Core\View::partial('partials/scripts', get_defined_vars()) ?>
</body>
</html>
