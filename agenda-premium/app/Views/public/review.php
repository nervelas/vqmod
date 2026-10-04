<?php /** @var array $biz @var array $row @var string $firstName @var string $when @var string $eventName @var string $hostName @var bool $done @var ?string $error @var array $values */ ?>
<?php partial('public/_header', ['biz' => $biz]); ?>
<main id="main" class="pub-page-main">
  <div class="container pub-narrow">
  <?php if ($done) : ?>
    <div class="empty"><?= icon('star') ?><p class="empty-title">¡Gracias por tu opinión, <?= e($firstName) ?>!</p><p class="empty-text">Tu reseña nos ayuda a mejorar. La revisamos antes de publicarla.</p><a class="btn btn-gold" href="<?= e(url('/')) ?>">Volver al inicio</a></div>
  <?php else : ?>
    <p class="eyebrow">Tu opinión cuenta</p>
    <h1 class="serif">Hola, <?= e($firstName) ?>: cuéntanos cómo te fue</h1>
    <p class="muted"><?= $eventName !== '' ? e($eventName) : '' ?><?= $hostName !== '' ? ' con ' . e($hostName) : '' ?><?= $when !== '' ? ' · ' . e($when) : '' ?></p>
    <?php if ($error) : ?><div class="alert alert-err" role="alert"><?= icon('alert') ?><div><?= e($error) ?></div></div><?php endif; ?>
    <form method="post" action="<?= e(url('/resena/' . $row['token'])) ?>" class="stack pub-form" novalidate>
      <?= public_csrf_field('book') ?>
      <div class="hp-wrap" aria-hidden="true"><label>No llenar este campo<input type="text" name="company_site" tabindex="-1" autocomplete="off"></label></div>
      <fieldset class="fieldset"><legend>Tu calificación</legend>
        <div class="rate" role="radiogroup" aria-label="Calificación de 1 a 5 estrellas">
          <?php for ($i = 5; $i >= 1; $i--) : ?>
            <input class="sr-only" type="radio" name="rating" id="r<?= e($i) ?>" value="<?= e($i) ?>"<?= chk((int) ($values['rating'] ?? 0) === $i) ?>>
            <label for="r<?= e($i) ?>" title="<?= e($i) ?> de 5"><svg viewBox="0 0 20 20" width="34" height="34" aria-hidden="true"><path d="M10 1.8l2.5 5.3 5.8.8-4.2 4.1 1 5.8L10 15l-5.1 2.8 1-5.8L1.7 7.9l5.8-.8z"/></svg><span class="sr-only"><?= e($i) ?> de 5</span></label>
          <?php endfor; ?>
        </div>
      </fieldset>
      <div class="field"><label for="rc">Tu comentario (opcional)</label><textarea class="textarea" id="rc" name="comment" rows="5" maxlength="1500"><?= e($values['comment'] ?? '') ?></textarea><p class="hint">Si lo publicamos, solo mostraremos tu nombre y la inicial de tu apellido.</p></div>
      <div class="form-actions"><button class="btn btn-gold btn-lg" type="submit">Enviar mi reseña</button></div>
    </form>
  <?php endif; ?>
  </div>
</main>
<?php partial('public/_footer', ['biz' => $biz]); ?>
