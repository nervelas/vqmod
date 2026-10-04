<?php
/** Pie público. Variables: $biz */
?>
<footer class="pub-footer">
  <div class="pub-footer-in">
    <div class="pf-brand">
      <p class="pf-name serif"><?= e($biz['name']) ?></p>
      <?php if ($biz['tagline'] !== '') : ?><p class="muted"><?= e($biz['tagline']) ?></p><?php endif; ?>
    </div>
    <nav class="pf-links" aria-label="Información legal">
      <a href="<?= e(url('/privacidad')) ?>">Aviso de privacidad</a>
      <a href="<?= e(url('/terminos')) ?>">Términos del servicio</a>
      <a href="<?= e(url('/equipo')) ?>">Nuestro equipo</a>
    </nav>
    <p class="pf-copy mono">© <?= e(gmdate('Y')) ?> <?= e($biz['name']) ?></p>
  </div>
  <?php if ($biz['cookies_notice'] !== '') : ?>
  <div class="pub-cookies" id="cookie-note" role="region" aria-label="Aviso sobre cookies" hidden>
    <p><?= e($biz['cookies_notice']) ?></p>
    <button type="button" class="btn btn-outline btn-sm" data-dismiss-cookie>Entendido</button>
  </div>
  <?php endif; ?>
</footer>
