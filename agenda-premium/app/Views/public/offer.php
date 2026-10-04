<?php /** @var array $biz @var string $token @var ?array $info @var ?string $error */ ?>
<?php partial('public/_header', ['biz' => $biz]); ?>
<main id="main" class="pub-page-main">
  <div class="container pub-narrow">
  <?php if (!$info) : ?>
    <div class="empty"><?= icon('clock') ?><p class="empty-title">Esta oferta ya no está disponible</p><p class="empty-text">El tiempo para confirmar terminó o el horario ya fue tomado. Puedes elegir otro horario disponible.</p><a class="btn btn-gold" href="<?= e(url('/')) ?>">Ver servicios</a></div>
  <?php else : ?>
    <p class="eyebrow">Se liberó un horario</p>
    <h1 class="serif">Hola, <?= e(explode(' ', trim($info['name']))[0]) ?>: tu horario está listo</h1>
    <div class="card card-gold offer-card">
      <div class="card-body stack">
        <p class="serif offer-event"><?= e($info['event']) ?></p>
        <p class="offer-when"><?= e($info['date']) ?> · <span class="mono"><?= e($info['time']) ?></span></p>
        <p class="muted"><?= e($info['duration']) ?> · <?= e($info['mode']) ?></p>
        <p class="offer-clock" role="timer" aria-live="off">Tienes <strong class="mono" data-offer-countdown="<?= e($info['deadline_iso']) ?>">--:--</strong> para confirmarlo.</p>
      </div>
    </div>
    <?php if ($error) : ?><div class="alert alert-err" role="alert"><?= icon('alert') ?><div><?= e($error) ?></div></div><?php endif; ?>
    <form method="post" action="<?= e(url('/espera/' . $token . '/aceptar')) ?>" class="stack pub-form">
      <?= public_csrf_field('book') ?>
      <label class="check"><input type="checkbox" name="consent" value="1" required> <span>Acepto el <a href="<?= e(url('/privacidad')) ?>" target="_blank" rel="noopener">aviso de privacidad</a> y los <a href="<?= e(url('/terminos')) ?>" target="_blank" rel="noopener">términos del servicio</a>.</span></label>
      <div class="form-actions"><button class="btn btn-gold btn-lg" type="submit">Confirmar mi cita</button><a class="btn btn-ghost" href="<?= e(url('/e/' . $info['slug'])) ?>">Prefiero elegir otro horario</a></div>
    </form>
  <?php endif; ?>
  </div>
</main>
<?php partial('public/_footer', ['biz' => $biz]); ?>
