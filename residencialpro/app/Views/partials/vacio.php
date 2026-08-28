<div class="vacio">
  <?= ico($icono ?? 'carpeta', 44) ?>
  <h3><?= e($titulo ?? 'Todavía no hay información') ?></h3>
  <?php if (!empty($texto)): ?><p><?= e($texto) ?></p><?php endif; ?>
  <?php if (!empty($accion)): ?>
    <a class="btn btn-oro" href="<?= e(url($accion)) ?>"><?= ico('mas', 18) ?> <?= e($accionTexto ?? 'Agregar') ?></a>
  <?php endif; ?>
</div>
