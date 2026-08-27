<?php
use App\Core\Ajustes;
use App\Core\Auth;
use App\Core\Menu;
use App\Core\Notificar;
use App\Core\Url;
use App\Core\Vista;

$u    = Auth::usuario();
$tema = $u['tema'] ?? Ajustes::get('tema', 'verde-oro');
$modo = ($u['modo_oscuro'] ?? 0) ? 'oscuro' : 'claro';
$sinLeer = $u ? Notificar::noLeidas((int) $u['id']) : 0;
?><!DOCTYPE html>
<html lang="es" data-tema="<?= e($tema) ?>" data-modo="<?= e($modo) ?>"
      data-base="<?= e(Url::basePath()) ?>" data-color-marca="<?= e(Ajustes::get('color_primario', '#0F2E24')) ?>">
<head><?= Vista::parcial('partials/head', ['titulo' => $tituloPagina ?? 'Portal del residente']) ?></head>
<body>
<a class="saltar-a" href="#contenido-principal">Ir al contenido</a>
<div class="app">
  <aside class="barra">
    <div class="barra-marca">
      <?php $logo = Ajustes::get('logo', ''); if ($logo !== '' && is_file(RUTA_BASE . '/uploads/logos/' . $logo)): ?>
        <img src="<?= e(subida($logo, 'logos')) ?>" alt="" width="38" height="38">
      <?php else: ?><span class="escudo"><?= ico('casa', 21) ?></span><?php endif; ?>
      <div class="crecer">
        <b><?= e(recortar(Ajustes::get('nombre', 'ResidencialPro'), 22)) ?></b>
        <span>Portal del residente</span>
      </div>
    </div>
    <nav class="barra-nav" aria-label="Menú del portal">
      <div class="nav-grupo">
        <?php foreach (Menu::portalCompleto() as $item): ?>
          <a class="nav-enlace <?= Menu::esActivo($item) ? 'is-activo' : '' ?>" href="<?= e(url($item['url'])) ?>">
            <?= ico($item['icono'], 19) ?><span><?= e($item['texto']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php if (Auth::esStaff()): ?>
      <div class="nav-grupo">
        <span>Administración</span>
        <a class="nav-enlace" href="<?= e(url('/admin')) ?>"><?= ico('panel', 19) ?><span>Panel administrativo</span></a>
      </div>
      <?php endif; ?>
    </nav>
    <div class="barra-pie">
      <a class="barra-usuario" href="<?= e(url('/perfil')) ?>">
        <span class="avatar"><?= e(iniciales((string) ($u['nombre'] ?? ''))) ?></span>
        <span class="crecer">
          <b><?= e(recortar((string) ($u['nombre'] ?? ''), 20)) ?></b>
          <span><?= e($casaActual['codigo'] ?? 'Residente') ?></span>
        </span>
      </a>
    </div>
  </aside>

  <div class="principal">
    <header class="tope">
      <button class="icono-btn" data-alternar-barra aria-label="Mostrar u ocultar el menú"><?= ico('menu', 21) ?></button>
      <div class="crecer">
        <h1><?= e($tituloPagina ?? 'Mi residencial') ?></h1>
        <?php if (!empty($casaActual)): ?>
          <div class="sub"><?= e($casaActual['codigo']) ?><?= !empty($casaActual['fase']) ? ' · ' . e($casaActual['fase']) : '' ?></div>
        <?php endif; ?>
      </div>
      <div class="tope-acciones">
        <?php if (!empty($casas) && count($casas) > 1): ?>
          <div class="desplegable">
            <button class="btn btn-sm btn-claro" data-desplegable="menu-casas"><?= ico('casa', 16) ?> Cambiar vivienda</button>
            <div class="desplegable-menu" id="menu-casas">
              <?php foreach ($casas as $c): ?>
                <form method="post" action="<?= e(url('/portal/casa/' . (int) $c['id'])) ?>">
                  <?= csrf() ?>
                  <button type="submit"><?= ico('casa', 16) ?> <?= e($c['codigo']) ?><?= !empty($c['fase']) ? ' · ' . e($c['fase']) : '' ?></button>
                </form>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
        <button class="icono-btn" data-modo-oscuro aria-label="Cambiar entre modo claro y oscuro">
          <?= ico($modo === 'oscuro' ? 'sol' : 'luna', 20) ?>
        </button>
        <div class="desplegable">
          <button class="icono-btn" data-desplegable="menu-notif" data-al-abrir="notificaciones" aria-label="Notificaciones">
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
        <a class="icono-btn" href="<?= e(url('/salir')) ?>" aria-label="Cerrar sesión"><?= ico('salir', 20) ?></a>
      </div>
    </header>
    <main class="contenido" id="contenido-principal">
      <?= Vista::parcial('partials/flash') ?>
      <?= $contenido ?>
    </main>
  </div>
</div>

<nav class="nav-movil" aria-label="Menú rápido">
  <?php foreach (Menu::portal() as $item): ?>
    <a href="<?= e(url($item['url'])) ?>" class="<?= Menu::esActivo($item) ? 'is-activo' : '' ?>">
      <?= ico($item['icono'], 21) ?><span><?= e($item['texto']) ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<script<?= nonce() ?> src="<?= e(url('/assets/vendor/grafica.js')) ?>?v=<?= RPRO_VERSION ?>"></script>
<script<?= nonce() ?> src="<?= e(url('/assets/js/app.js')) ?>?v=<?= RPRO_VERSION ?>"></script>
<?php if (!empty($scripts)): ?><?= $scripts ?><?php endif; ?>
</body>
</html>
