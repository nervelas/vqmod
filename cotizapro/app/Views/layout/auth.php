<?php $noindex = true; $theme = $theme ?? ['accent' => '#E8590C', 'ink' => '#1C1F22', 'paper' => '#F5F6F4']; ?>
<!doctype html>
<html lang="es">
<head><?= \App\Core\View::partial('partials/head', get_defined_vars()) ?></head>
<body>
<div class="authpage">
  <div class="authpage__art blueprint" aria-hidden="true">
    <div class="authpage__artin">
      <span class="kicker" style="color:rgba(255,255,255,.66)">Sistema de cotización</span>
      <p class="h1" style="color:#fff;margin-top:20px;max-width:14ch">Precisión<br>en cada<br>cotización.</p>
      <div class="cota" style="margin-top:34px;color:rgba(255,255,255,.42)">Catálogo · Cotizador · Seguimiento</div>
    </div>
  </div>
  <div class="authpage__form">
    <div class="authpage__box">
      <a class="brand" href="<?= e(url('/')) ?>" style="margin-bottom:34px">
        <span class="brand__mark" aria-hidden="true">CP</span>
        <span><?= e($platformName ?? 'CotizaPro B2B') ?></span>
      </a>
      <?= \App\Core\View::partial('partials/flash', get_defined_vars()) ?>
      <?= $content ?>
    </div>
  </div>
</div>
<?= \App\Core\View::partial('partials/scripts', get_defined_vars()) ?>
</body>
</html>
