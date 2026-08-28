<?php
/** @var array $archivos, $espacio */
use MenuGold\Core\Backup;
use MenuGold\Core\Security;
use MenuGold\Core\View;
View::set('titulo', 'Respaldos');
View::set('subtitulo', $espacio['archivos'] . ' respaldo(s) · ' . $espacio['peso'] . ' en total');

View::start('acciones');
?>
<button class="bt bt--oro" type="button" id="btnRespaldo"><?= icon('database') ?><span>Crear respaldo</span></button>
<?php View::stop(); ?>

<div class="aviso aviso--info">
  <?= icon('info') ?>
  <span>
    El respaldo incluye <strong>toda la base de datos</strong>: menú, pedidos, clientes y configuración.
    Se conservan los 12 más recientes. Guarda una copia fuera del servidor de vez en cuando.
    <br>El respaldo automático semanal se ejecuta con el cron (ver LÉEME).
  </span>
</div>

<div class="tarjeta-p tarjeta-p--plana">
  <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('database') ?> Respaldos disponibles</h2></div>
  <?php if (!$archivos): ?>
    <div class="vacio-p">
      <?= icon('database', 'ico-lg') ?>
      <h3>Aún no hay respaldos</h3>
      <p>Crea el primero con un clic. Tarda unos segundos.</p>
      <button class="bt bt--oro" type="button" id="btnRespaldo2"><?= icon('database') ?> Crear respaldo ahora</button>
    </div>
  <?php else: ?>
    <div class="tabla-caja">
      <table class="tabla" style="min-width:auto">
        <thead><tr><th>Archivo</th><th>Fecha</th><th class="num">Tamaño</th><th></th></tr></thead>
        <tbody id="listaRespaldos">
          <?php foreach ($archivos as $a): ?>
            <tr data-archivo="<?= e($a['nombre']) ?>">
              <td class="mono" style="font-size:13px"><?= e($a['nombre']) ?></td>
              <td style="color:var(--p-tenue);font-size:13px"><?= e(dt($a['fecha'])) ?></td>
              <td class="num"><?= e($a['peso']) ?></td>
              <td class="tabla__acciones">
                <a class="bt bt--sm bt--linea" href="<?= e(url('panel/respaldo/bajar/' . $a['nombre'])) ?>">
                  <?= icon('download', 'ico-sm') ?> Descargar</a>
                <button class="bt bt--sm bt--suave" type="button" data-borrar-respaldo="<?= e($a['nombre']) ?>">
                  <?= icon('trash', 'ico-sm') ?></button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
  var M = window.MGPanel;
  function crear(btn) {
    var t = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="cargador" style="width:16px;height:16px"></span> Generando…';
    M.pedir('panel/respaldo/crear', {}).then(function (r) {
      btn.disabled = false; btn.innerHTML = t;
      if (r.ok) { M.avisar(r.mensaje, 'ok'); setTimeout(function () { location.reload(); }, 800); }
      else M.avisar(r.error, 'error');
    });
  }
  ['btnRespaldo', 'btnRespaldo2'].forEach(function (id) {
    var b = document.getElementById(id);
    if (b) b.addEventListener('click', function () { crear(b); });
  });

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-borrar-respaldo]');
    if (!b) return;
    M.confirmar('Se eliminará el respaldo ' + b.dataset.borrarRespaldo + '.', 'Eliminar respaldo', 'Sí, eliminar')
      .then(function (ok) {
        if (!ok) return;
        M.pedir('panel/respaldo/borrar', { archivo: b.dataset.borrarRespaldo }).then(function (r) {
          if (r.ok) { b.closest('tr').remove(); M.avisar(r.mensaje, 'ok'); }
          else M.avisar(r.error, 'error');
        });
      });
  });
})();
</script>
<?php View::stop(); ?>
