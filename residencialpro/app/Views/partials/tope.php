<?php
use App\Core\Auth;
use App\Core\Notificar;

$u        = Auth::usuario();
$sinLeer  = $u ? Notificar::noLeidas((int) $u['id']) : 0;
$modo     = ($u['modo_oscuro'] ?? 0) ? 'oscuro' : 'claro';
?>
<header class="tope">
  <button class="icono-btn" data-alternar-barra aria-label="Mostrar u ocultar el menú"><?= ico('menu', 21) ?></button>
  <div class="crecer">
    <?php if (!empty($subtitulo)): ?><span class="lema"><?= e($subtitulo) ?></span><?php endif; ?>
    <h1><?= e($tituloPagina ?? 'Tablero') ?></h1>
  </div>
  <div class="tope-acciones">
    <?php if (!empty($accionesTope)): ?><?= $accionesTope ?><?php endif; ?>

    <button class="icono-btn" data-modo-oscuro aria-label="Cambiar entre modo claro y oscuro">
      <?= ico($modo === 'oscuro' ? 'sol' : 'luna', 20) ?>
    </button>

    <div class="desplegable">
      <button class="icono-btn" data-desplegable="menu-notif" data-al-abrir="notificaciones"
              aria-label="Notificaciones" aria-haspopup="true">
        <?= ico('campana', 20) ?>
        <?php if ($sinLeer > 0): ?><span class="punto" id="notif-punto"></span><?php endif; ?>
      </button>
      <div class="desplegable-menu" id="menu-notif" style="width:340px">
        <div style="padding:10px 12px 6px" class="fila-entre">
          <b style="font-size:.86rem">Notificaciones</b>
          <button class="btn btn-sm btn-fantasma" data-activar-push type="button">Activar avisos</button>
        </div>
        <div class="notif-lista" id="notif-lista"></div>
      </div>
    </div>

    <div class="desplegable">
      <button class="icono-btn" data-desplegable="menu-usuario" aria-label="Menú de la cuenta" aria-haspopup="true">
        <span class="avatar" style="width:30px;height:30px;font-size:.6875rem"><?= e(iniciales((string) ($u['nombre'] ?? ''))) ?></span>
      </button>
      <div class="desplegable-menu" id="menu-usuario">
        <div style="padding:10px 12px">
          <b style="display:block;font-size:.9rem"><?= e((string) ($u['nombre'] ?? '')) ?></b>
          <small class="texto-3"><?= e(rolNombre((string) ($u['rol'] ?? ''))) ?></small>
        </div>
        <hr>
        <a href="<?= e(url('/perfil')) ?>"><?= ico('usuario', 17) ?> Mi perfil</a>
        <?php if (esRol('admin')): ?>
          <a href="<?= e(url('/admin/ajustes')) ?>"><?= ico('ajustes', 17) ?> Ajustes</a>
        <?php endif; ?>
        <?php if (!empty(App\Core\Auth::casas())): ?>
          <a href="<?= e(url('/portal')) ?>"><?= ico('casa', 17) ?> Portal del residente</a>
        <?php endif; ?>
        <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= ico('mapa', 17) ?> Ver sitio público</a>
        <hr>
        <a href="<?= e(url('/salir')) ?>"><?= ico('salir', 17) ?> Cerrar sesión</a>
      </div>
    </div>
  </div>
</header>
