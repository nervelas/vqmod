<?php
/** @var array $mensajes; int $sinLeer */
use MenuGold\Core\Security;
use MenuGold\Core\View;
View::set('titulo', 'Mensajes');
View::set('subtitulo', count($mensajes) . ' mensaje(s) · ' . $sinLeer . ' sin leer');
?>
<?php if (!$mensajes): ?>
  <div class="tarjeta-p"><div class="vacio-p"><?= icon('mail', 'ico-lg') ?>
    <h3>Sin mensajes</h3><p>Los interesados que escriban desde tu página de venta aparecerán aquí.</p></div></div>
<?php else: ?>
  <div class="rejilla rejilla--2">
    <?php foreach ($mensajes as $m): ?>
      <div class="tarjeta-p" data-mensaje="<?= (int)$m['id'] ?>"
           style="<?= (int)$m['leido'] === 0 ? 'border-color:var(--p-oro)' : '' ?>">
        <div class="entre" style="margin-bottom:8px">
          <strong><?= e((string)$m['nombre']) ?></strong>
          <span style="font-size:12.5px;color:var(--p-tenue)"><?= e(dt((string)$m['creado'])) ?></span>
        </div>
        <div style="font-size:13.5px;color:var(--p-suave);margin-bottom:8px">
          <a href="mailto:<?= e((string)$m['email']) ?>" style="color:var(--p-oro)"><?= e((string)$m['email']) ?></a>
          <?php if (!empty($m['telefono'])): ?>
            · <a href="https://wa.me/<?= e(preg_replace('/\D/', '', (string)$m['telefono'])) ?>" target="_blank" rel="noopener"><?= e((string)$m['telefono']) ?></a>
          <?php endif; ?>
        </div>
        <?php if (!empty($m['restaurante']) || !empty($m['plan'])): ?>
          <div style="margin-bottom:8px">
            <?php if (!empty($m['restaurante'])): ?><span class="insignia"><?= e((string)$m['restaurante']) ?></span><?php endif; ?>
            <?php if (!empty($m['plan'])): ?><span class="insignia insignia--oro">Plan <?= e((string)$m['plan']) ?></span><?php endif; ?>
          </div>
        <?php endif; ?>
        <p style="margin:0 0 12px;font-size:14px;white-space:pre-line"><?= e((string)$m['mensaje']) ?></p>
        <div class="acciones">
          <?php if ((int)$m['leido'] === 0): ?>
            <button class="bt bt--sm bt--linea" type="button" data-leido="<?= (int)$m['id'] ?>"><?= icon('check', 'ico-sm') ?> Marcar leído</button>
          <?php endif; ?>
          <a class="bt bt--sm bt--suave" href="mailto:<?= e((string)$m['email']) ?>?subject=<?= e(rawurlencode('Tu menú digital')) ?>">
            <?= icon('send', 'ico-sm') ?> Responder</a>
          <button class="bt bt--sm bt--suave" type="button" data-borrar-mensaje="<?= (int)$m['id'] ?>"><?= icon('trash', 'ico-sm') ?></button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
document.addEventListener('click', function (ev) {
  var M = window.MGPanel;
  var l = ev.target.closest('[data-leido]');
  if (l) {
    M.pedir('super/mensajes/leido', { id: Number(l.dataset.leido) }).then(function (r) {
      if (r.ok) { l.closest('.tarjeta-p').style.borderColor = ''; l.remove(); M.avisar(r.mensaje, 'ok'); }
    });
    return;
  }
  var b = ev.target.closest('[data-borrar-mensaje]');
  if (b) {
    M.confirmar('¿Eliminar este mensaje?', 'Eliminar', 'Sí, eliminar').then(function (ok) {
      if (!ok) return;
      M.pedir('super/mensajes/leido', { id: Number(b.dataset.borrarMensaje), borrar: 1 }).then(function (r) {
        if (r.ok) b.closest('.tarjeta-p').remove();
      });
    });
  }
});
</script>
<?php View::stop(); ?>
