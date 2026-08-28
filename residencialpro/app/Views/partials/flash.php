<?php
use App\Core\Sesion;
$mensajes = Sesion::tomarFlash();
foreach ($mensajes as $m):
    $tipo = $m['tipo'] === 'exito' ? 'ok' : ($m['tipo'] === 'error' ? 'error' : ($m['tipo'] === 'alerta' ? 'alerta' : 'info'));
    $icono = $tipo === 'ok' ? 'checkCirculo' : ($tipo === 'error' ? 'equisCirculo' : 'info');
?>
<div class="aviso-caja <?= e($tipo) ?> mb-2" role="status" data-auto-cerrar>
  <?= ico($icono, 20) ?>
  <div class="crecer"><?= e($m['mensaje']) ?></div>
</div>
<?php endforeach; ?>
