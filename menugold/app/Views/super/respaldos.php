<?php
/** @var array $archivos, $espacio; bool $semanal; string $cron */
use MenuGold\Core\Security;
use MenuGold\Core\View;
View::set('titulo', 'Respaldos de la plataforma');
View::set('subtitulo', $espacio['archivos'] . ' archivo(s) · ' . $espacio['peso']);

View::start('acciones');
?>
<button class="bt bt--oro" type="button" id="btnRespaldo"><?= icon('database') ?><span>Crear respaldo</span></button>
<?php View::stop(); ?>

<div class="aviso aviso--<?= $semanal ? 'info' : 'aviso' ?>">
  <?= icon($semanal ? 'info' : 'alert') ?>
  <span>
    <?php if ($semanal): ?>
      El respaldo automático semanal está <strong>activo</strong>. Se ejecuta con el cron los domingos.
    <?php else: ?>
      El respaldo automático semanal está <strong>apagado</strong>. Actívalo en Ajustes.
    <?php endif; ?>
    <br>Configura el cron en cPanel:
    <code style="font-size:12px;background:var(--p-superficie-2);padding:2px 6px;border-radius:5px">
      */10 * * * * curl -s "<?= e(url('cron/run.php', ['token' => $cron])) ?>"
    </code>
  </span>
</div>

<div class="tarjeta-p tarjeta-p--plana">
  <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('database') ?> Archivos disponibles</h2></div>
  <?php if (!$archivos): ?>
    <div class="vacio-p"><?= icon('database', 'ico-lg') ?><h3>Sin respaldos</h3>
      <p>Crea el primero. Incluye todos los restaurantes de la plataforma.</p></div>
  <?php else: ?>
    <div class="tabla-caja">
      <table class="tabla" style="min-width:auto">
        <thead><tr><th>Archivo</th><th>Fecha</th><th class="num">Tamaño</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($archivos as $a): ?>
            <tr>
              <td class="mono" style="font-size:13px"><?= e($a['nombre']) ?></td>
              <td style="color:var(--p-tenue);font-size:13px"><?= e(dt($a['fecha'])) ?></td>
              <td class="num"><?= e($a['peso']) ?></td>
              <td class="tabla__acciones">
                <a class="bt bt--sm bt--linea" href="<?= e(url('super/respaldos/bajar/' . $a['nombre'])) ?>">
                  <?= icon('download', 'ico-sm') ?> Descargar</a>
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
var b = document.getElementById('btnRespaldo');
if (b) b.addEventListener('click', function () {
  var M = window.MGPanel, t = b.innerHTML;
  b.disabled = true; b.innerHTML = '<span class="cargador" style="width:16px;height:16px"></span> Generando…';
  M.pedir('super/respaldos/crear', {}).then(function (r) {
    b.disabled = false; b.innerHTML = t;
    if (r.ok) { M.avisar(r.mensaje, 'ok'); setTimeout(function () { location.reload(); }, 800); }
    else M.avisar(r.error, 'error');
  });
});
</script>
<?php View::stop(); ?>
