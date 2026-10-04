<div class="wrap booking">
  <div class="panel">
    <span class="eyebrow"><?= e(__('Reprogramar')) ?></span>
    <h2><?= e($a['service_name']) ?> · <?= e($a['prof_name']) ?></h2>
    <p class="sub"><?= e(__('Actual')) ?>: <?= e(\Aurea\Core\Util::dateLong($a['start_at'])) ?>, <?= e(ftime($a['start_at'])) ?>. <?= e(__('Elige tu nuevo horario.')) ?></p>
    <?php if (!empty($_GET['e'])): ?><div class="alert alert-err" role="alert"><?= e((string)$_GET['e']) ?></div><?php endif; ?>
    <div id="resched-root"><div class="skeleton"></div></div>
    <form method="post" action="<?= e(url('/cita/' . $a['token'] . '/reprogramar')) ?>" id="resched-form" class="hidden">
      <?= csrf_field('public') ?>
      <input type="hidden" name="start" id="resched-start" value="">
      <div class="nav-row"><a class="btn btn-ghost" href="<?= e(url('/cita/' . $a['token'])) ?>">← <?= e(__('Volver')) ?></a><button class="btn btn-gold" type="submit"><?= e(__('Confirmar nuevo horario')) ?></button></div>
    </form>
  </div>
</div>
<script type="application/json" id="boot"><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
