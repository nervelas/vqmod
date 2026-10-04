<?php /** @var array $biz @var array $hosts */ ?>
<?php partial('public/_header', ['biz' => $biz, 'nav' => 'team']); ?>
<main id="main" class="pub-page-main">
  <div class="container">
    <div class="pub-head">
      <p class="eyebrow">Equipo</p>
      <h1 class="serif">Nuestro equipo</h1>
      <p class="muted">Conoce a las personas que te atienden y reserva directamente con quien prefieras.</p>
    </div>
    <?php if ($hosts) : partial('public/_hosts', ['hosts' => $hosts]); else : ?>
      <div class="empty"><?= icon('users') ?><p class="empty-title">Pronto presentaremos a nuestro equipo</p><a class="btn btn-gold" href="<?= e(url('/')) ?>">Ver servicios</a></div>
    <?php endif; ?>
  </div>
</main>
<?php partial('public/_footer', ['biz' => $biz]); ?>
