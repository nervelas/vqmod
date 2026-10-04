<?php
$h = $hook ?: ['id' => 0, 'name' => '', 'url' => '', 'secret' => '', 'active' => 1];
?>
<div class="page">
  <div class="page-head">
    <div><h1 class="page-title"><?= $h['id'] ? 'Editar webhook' : 'Nuevo webhook' ?></h1><p class="page-sub">Elige a dónde enviar los avisos y cuáles eventos quieres recibir.</p></div>
    <div class="page-actions"><a class="btn btn-ghost" href="<?= e(url('/admin/webhooks')) ?>"><?= icon('arrow-left') ?>Volver</a></div>
  </div>
  <div class="grid cols-2">
    <form method="post" action="<?= e(url('/admin/webhooks/guardar')) ?>" class="card">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
      <div class="card-body stack">
        <div class="field"><label for="wh-name">Nombre</label><input class="input" id="wh-name" name="name" maxlength="120" required value="<?= e($h['name']) ?>" placeholder="Sistema de facturación"></div>
        <div class="field"><label for="wh-url">Dirección que recibirá los avisos</label><input class="input mono" id="wh-url" type="url" name="url" maxlength="500" required value="<?= e($h['url']) ?>" placeholder="https://ejemplo.com/avisos"><p class="hint">Debe ser pública. Por seguridad no se permiten direcciones internas.</p></div>
        <fieldset class="fieldset"><legend>Eventos</legend>
          <div class="stack">
            <?php foreach ($eventsList as $k => $label) : ?>
              <label class="check"><input type="checkbox" name="events[]" value="<?= e($k) ?>"<?= chk(in_array($k, $selected, true)) ?>><span><?= e($label) ?> <span class="muted mono"><?= e($k) ?></span></span></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <label class="check"><input type="checkbox" name="active" value="1"<?= chk((int) $h['active'] === 1) ?>><span>Webhook activo</span></label>
      </div>
      <div class="card-foot form-actions"><button class="btn btn-gold" type="submit">Guardar webhook</button></div>
    </form>

    <?php if ($h['id']) : ?>
      <section class="card card-gold" aria-labelledby="h-sec">
        <div class="card-head"><h2 id="h-sec" class="serif">Secreto de firma</h2></div>
        <div class="card-body stack">
          <p class="muted">Úsalo en el sistema que recibe los avisos para verificar la firma. Trátalo como una contraseña.</p>
          <div class="input-group"><input class="input mono" id="wh-secret" readonly value="<?= e($h['secret']) ?>" aria-label="Secreto del webhook"><button class="btn btn-outline" type="button" data-copy="#wh-secret"><?= icon('copy') ?>Copiar</button></div>
          <form method="post" action="<?= e(url('/admin/webhooks/' . (int) $h['id'] . '/secreto')) ?>" data-confirm="¿Generar un secreto nuevo? El actual dejará de funcionar de inmediato."><?= csrf_field() ?><button class="btn btn-ghost" type="submit"><?= icon('refresh') ?>Regenerar secreto</button></form>
          <form method="post" action="<?= e(url('/admin/webhooks/' . (int) $h['id'] . '/prueba')) ?>"><?= csrf_field() ?><button class="btn btn-outline" type="submit"><?= icon('play') ?>Enviar evento de prueba</button></form>
        </div>
      </section>
    <?php else : ?>
      <section class="card" aria-labelledby="h-info"><div class="card-head"><h2 id="h-info" class="serif">Secreto de firma</h2></div><div class="card-body"><p class="muted">Al guardar generamos un secreto único para firmar cada aviso. Lo verás en esta pantalla para copiarlo.</p></div></section>
    <?php endif; ?>
  </div>
</div>
