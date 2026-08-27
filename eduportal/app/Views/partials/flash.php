<?php
$mensajes = App\Core\Session::takeFlash();
if ($mensajes === []) {
    return;
}
?>
<div class="toasts" role="status" aria-live="polite">
  <?php foreach ($mensajes as $m): ?>
    <div class="toast toast--<?= e($m['tipo']) ?>" data-autocerrar>
      <div style="flex:1"><?= e($m['texto']) ?></div>
      <button type="button" data-cerrar-toast aria-label="Cerrar aviso">&times;</button>
    </div>
  <?php endforeach; ?>
</div>
