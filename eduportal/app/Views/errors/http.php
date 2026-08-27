<div class="caja">
  <div class="codigo"><?= e((string)$codigo) ?></div>
  <h1><?php
    echo e(match ((int)$codigo) {
        403 => 'Acceso restringido',
        404 => 'Página no encontrada',
        405 => 'Método no permitido',
        419 => 'Sesión expirada',
        500 => 'Error del sistema',
        default => 'Ocurrió un problema',
    });
  ?></h1>
  <p class="txt-2"><?= e($mensaje ?: 'No pudimos completar su solicitud.') ?></p>
  <div class="acciones" style="justify-content:center;margin-top:24px">
    <a href="<?= e(url('/')) ?>" class="btn btn--linea">Ir al inicio</a>
    <?php if (App\Core\Auth::check()): ?>
      <a href="<?= e(url(App\Core\Auth::is('padre') ? 'portal' : 'panel')) ?>" class="btn">Volver al panel</a>
    <?php else: ?>
      <a href="<?= e(url('ingresar')) ?>" class="btn">Ingresar</a>
    <?php endif; ?>
  </div>
</div>
