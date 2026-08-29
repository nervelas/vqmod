<?php $base = rtrim(url('/'), '/'); ?>
<div class="section blueprint">
  <div class="wrap-narrow">
    <div class="brackets" style="padding:clamp(30px,5vw,56px);background:var(--white);border:1px solid var(--line);border-radius:var(--r)">
      <span class="kicker">Solicitud recibida</span>
      <h1 class="h1" style="margin:18px 0 14px">Gracias, <?= e(explode(' ', (string) $q['contact_name'])[0]) ?>.</h1>
      <p class="lead">Registramos su solicitud <strong><?= e($q['number']) ?></strong>. Nuestro equipo está preparando la cotización formal y le llegará a <strong><?= e($q['contact_email']) ?></strong>.</p>

      <div class="counters" style="margin:30px 0">
        <dl class="counter"><dt>Número</dt><dd style="font-size:1.5rem"><?= e($q['number']) ?></dd></dl>
        <dl class="counter"><dt>Productos</dt><dd><?= e(count($items)) ?></dd></dl>
        <dl class="counter"><dt>Recibida</dt><dd style="font-size:1.3rem"><?= e(fechaCorta((string) $q['created_at'])) ?></dd></dl>
      </div>

      <div class="cota" style="margin-bottom:14px">Su enlace de seguimiento</div>
      <div class="copyfield">
        <label class="sr-only" for="trackurl">Enlace de seguimiento</label>
        <input id="trackurl" value="<?= e(absUrl('/c/' . $q['track_token'])) ?>" readonly>
        <button type="button" data-copy="trackurl">Copiar</button>
      </div>
      <p class="small muted" style="margin-top:10px">Guárdelo: desde ahí verá el estado, descargará el PDF y podrá aprobar la cotización.</p>

      <div class="flex flex-wrap" style="margin-top:28px">
        <a class="btn btn--accent" href="<?= e(url('/c/' . $q['track_token'])) ?>">Ver el estado de mi cotización <span class="arw" aria-hidden="true">&rarr;</span></a>
        <a class="btn btn--ghost" href="<?= e($base . '/catalogo') ?>">Volver al catálogo</a>
      </div>

      <div style="margin-top:34px">
        <div class="cota" style="margin-bottom:14px">Detalle solicitado</div>
        <div class="tablescroll">
          <table class="spectable">
            <caption class="sr-only">Productos solicitados</caption>
            <thead><tr><th scope="col" style="width:auto">Código</th><th scope="col" style="width:auto">Producto</th><th scope="col" style="width:auto">Cantidad</th></tr></thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <tr>
                  <td style="width:110px"><span class="code-chip"><?= e($it['code']) ?></span></td>
                  <td><?= e($it['name']) ?><?php if ($it['notes']): ?><br><span class="small muted"><?= e($it['notes']) ?></span><?php endif; ?></td>
                  <td style="width:100px" class="nowrap"><?= e(qty((float) $it['qty'])) ?> <?= e($it['unit']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
