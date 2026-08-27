<section class="garita-panel" style="grid-column:1/-1">
  <div class="fila-entre">
    <div>
      <h2 style="margin-bottom:2px">Control de accesos</h2>
      <p style="color:rgba(233,238,233,.7);margin:0">
        <?= (int) $deHoy ?> ingreso(s) hoy · <?= count($adentro) ?> persona(s) dentro
        <?php if ($turno !== null): ?> · turno desde <?= e(hora((string) $turno['inicio'])) ?><?php endif; ?>
      </p>
    </div>
    <div data-pendientes-caja hidden>
      <span class="chip aviso"><?= ico('subir', 14) ?> <span data-pendientes>0</span> registro(s) por sincronizar</span>
    </div>
  </div>

  <div class="garita-botones mt-3">
    <a class="btn-garita oro" href="<?= e(url('/garita/ingreso')) ?>">
      <?= ico('escanear', 42) ?> Escanear código<br>o registrar ingreso
    </a>
    <a class="btn-garita" href="<?= e(url('/garita/visitas')) ?>">
      <?= ico('usuarios', 42) ?> Registrar salida
    </a>
    <a class="btn-garita" href="<?= e(url('/garita/directorio')) ?>">
      <?= ico('telefono', 42) ?> Directorio de casas
    </a>
    <a class="btn-garita" href="<?= e(url('/garita/bitacora')) ?>">
      <?= ico('libro', 42) ?> Anotar novedad
    </a>
    <button class="btn-garita rojo" type="button" data-panico>
      <?= ico('sirena', 42) ?> EMERGENCIA
    </button>
  </div>
</section>

<section class="garita-panel">
  <h2><?= ico('reloj', 20) ?> Dentro del residencial</h2>
  <?php if ($adentro === []): ?>
    <p style="color:rgba(233,238,233,.6)">No hay visitas dentro en este momento.</p>
  <?php else: ?>
    <div class="garita-lista">
      <?php foreach (array_slice($adentro, 0, 12) as $v): ?>
        <div class="garita-item">
          <span class="avatar"><?= e(iniciales((string) $v['visitante'])) ?></span>
          <div class="crecer">
            <b><?= e($v['visitante']) ?></b>
            <small><?= e($v['casa'] ?? 'Sin destino') ?> · desde <?= e(hora((string) $v['entrada'])) ?><?= !empty($v['placa']) ? ' · ' . e($v['placa']) : '' ?></small>
          </div>
          <form method="post" action="<?= e(url('/garita/salida/' . (int) $v['id'])) ?>">
            <?= csrf() ?>
            <button class="btn btn-sm btn-oro" type="submit"><?= ico('salir', 15) ?> Salida</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (count($adentro) > 12): ?>
      <a class="btn btn-fantasma btn-bloque mt-2" style="color:#D9E0DA;border-color:rgba(255,255,255,.2)"
         href="<?= e(url('/garita/visitas')) ?>">Ver los <?= count($adentro) ?> registros</a>
    <?php endif; ?>
  <?php endif; ?>
</section>

<section class="garita-panel">
  <h2><?= ico('qr', 20) ?> Visitas autorizadas</h2>
  <?php if ($vigentes === []): ?>
    <p style="color:rgba(233,238,233,.6)">No hay visitas pre-registradas vigentes.</p>
  <?php else: ?>
    <div class="garita-lista">
      <?php foreach (array_slice($vigentes, 0, 12) as $p): ?>
        <div class="garita-item">
          <span style="color:var(--acento-2)"><?= ico((int) $p['recurrente'] === 1 ? 'maletin' : 'usuario', 22) ?></span>
          <div class="crecer">
            <b><?= e($p['visitante']) ?></b>
            <small>
              Casa <?= e($p['casa']) ?> · código <b style="color:var(--acento-2)"><?= e($p['codigo']) ?></b>
              <?php if ((int) $p['recurrente'] === 1): ?> · recurrente<?php endif; ?>
            </small>
          </div>
          <a class="btn btn-sm btn-fantasma" style="color:#D9E0DA;border-color:rgba(255,255,255,.24)"
             href="<?= e(url('/garita/ingreso', ['codigo' => $p['codigo']])) ?>"><?= ico('entrar', 15) ?> Ingresar</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<form id="f-panico" method="post" action="<?= e(url('/garita/panico')) ?>" hidden>
  <?= csrf() ?>
  <input type="hidden" name="detalle" value="Botón de emergencia activado desde la garita.">
  <button type="submit" class="solo-lectores">Confirmar la alerta de emergencia</button>
</form>

<script<?= nonce() ?>>
document.querySelectorAll('[data-panico]').forEach(function (b) {
  b.addEventListener('click', function () {
    RP.confirmar(
      '¿Activar la alerta de emergencia?',
      'Se enviará una notificación inmediata a la administración y a la junta directiva. Úselo únicamente ante una situación real.',
      function () { document.getElementById('f-panico').submit(); },
      'Sí, activar la alerta'
    );
  });
});
</script>
