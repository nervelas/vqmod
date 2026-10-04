<?php
use App\Core\Fmt;

$tzb = \App\Core\Settings::tz();
?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title">Ofrecer un horario</h1><p class="page-sub">Para <strong><?= e($w['name']) ?></strong> · <?= e($w['event_name']) ?> · <?= e(Fmt::duration((int) $w['duration'])) ?>. Recibirá un enlace y tendrá <?= (int) $minutes ?> minutos para reservar.</p></div>
    <div class="page-actions"><a class="btn btn-ghost" href="<?= e(url('/admin/espera')) ?>"><?= icon('arrow-left') ?>Volver</a></div>
  </div>
  <?php if (!$slots) : ?>
    <div class="empty"><?= icon('calendar') ?><p class="empty-title">No hay horarios libres en las próximas semanas</p><p class="empty-text">Cuando se libere un espacio, el sistema se lo ofrecerá automáticamente a la primera persona de la lista.</p></div>
  <?php else : ?>
    <form method="post" action="<?= e(url('/admin/espera/' . (int) $w['id'] . '/ofrecer')) ?>" class="card">
      <?= csrf_field() ?>
      <div class="card-body">
        <fieldset class="fieldset"><legend>Horarios disponibles (hora de Guatemala)</legend>
          <div class="p3-slot-list">
            <?php foreach ($slots as $i => $s) : $hid = (int) $s['host_ids'][0]; ?>
              <label class="check p3-slot"><input type="radio" name="slot" value="<?= e($s['start'] . '|' . $hid) ?>"<?= $i === 0 ? ' checked' : '' ?> required><span><?= e(Fmt::dateTime((string) $s['start'], $tzb)) ?><?= $s['host_names'] ? ' <span class="muted">· ' . e($s['host_names']) . '</span>' : '' ?></span></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      </div>
      <div class="card-foot form-actions"><button class="btn btn-gold" type="submit"><?= icon('arrow-right') ?>Enviar oferta</button></div>
    </form>
  <?php endif; ?>
</div>
