<?php
use App\Core\Auth;
use App\Core\Notificador;
use App\Core\Settings;

$u = Auth::user();
$logo = (string)Settings::get('colegio_logo', '');
$pendientes = $u ? Notificador::pendientes((int)$u['id']) : 0;
?>
<header class="barra">
  <button type="button" class="barra__accion" data-menu aria-label="Mostrar u ocultar el menú" aria-expanded="false">
    <?= icono('menu') ?>
  </button>
  <a href="<?= e(url(Auth::is('padre') ? '/portal' : '/panel')) ?>" class="barra__marca">
    <?php if ($logo !== ''): ?>
      <img src="<?= e(archivo_url($logo)) ?>" alt="">
    <?php else: ?>
      <?= icono('escuela', 26) ?>
    <?php endif; ?>
    <span><?= e(Settings::get('colegio_nombre', 'EduPortal')) ?></span>
  </a>

  <div class="barra__esp"></div>

  <button type="button" class="barra__accion" data-oscuro-toggle aria-label="Cambiar entre modo claro y oscuro">
    <?= icono((int)($u['modo_oscuro'] ?? 0) === 1 ? 'sol' : 'luna') ?>
  </button>

  <button type="button" class="barra__accion" data-notif aria-label="Notificaciones" aria-expanded="false"
          aria-controls="panel-notificaciones">
    <?= icono('campana') ?>
    <?php if ($pendientes > 0): ?><span class="punto"><?= (int)$pendientes > 9 ? '9+' : (int)$pendientes ?></span><?php endif; ?>
  </button>

  <a href="<?= e(url('perfil')) ?>" class="barra__accion" aria-label="Mi perfil"><?= icono('usuarios') ?></a>

  <form method="post" action="<?= e(url('salir')) ?>" style="display:inline">
    <?= csrf_field() ?>
    <button type="submit" class="barra__accion" aria-label="Cerrar sesión"><?= icono('salir') ?></button>
  </form>
</header>

<div class="notif" id="panel-notificaciones" role="dialog" aria-label="Notificaciones">
  <div class="notif__cab">
    <strong>Notificaciones</strong>
    <button type="button" class="btn btn--fantasma btn--sm" data-notif>Cerrar</button>
  </div>
  <div data-notif-lista></div>
</div>
