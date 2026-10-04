<?php /** @var array $biz @var string $docTitle @var string $html @var string $version */ ?>
<?php partial('public/_header', ['biz' => $biz]); ?>
<main id="main" class="pub-page-main">
  <div class="container pub-legal-in">
    <p class="eyebrow">Información legal</p>
    <h1 class="serif"><?= e($docTitle) ?></h1>
    <p class="muted mono">Versión <?= e($version) ?></p>
    <?php if ($html !== '') : ?>
      <article class="prose pub-legal-text"><?= $html ?></article>
    <?php else : ?>
      <div class="empty"><?= icon('file') ?><p class="empty-title">Este documento aún no está publicado</p><p class="empty-text">Si tienes dudas sobre el uso de tus datos, escríbenos y te respondemos.</p></div>
    <?php endif; ?>
    <p><a class="btn btn-outline" href="<?= e(url('/')) ?>"><?= icon('arrow-left') ?> Volver al inicio</a></p>
  </div>
</main>
<?php partial('public/_footer', ['biz' => $biz]); ?>
