<?php
use App\Core\Ajustes;
use App\Core\Auth;
use App\Core\Menu;
use App\Core\Url;
use App\Core\Vista;

$u = Auth::usuario();
?><!DOCTYPE html>
<html lang="es" data-tema="<?= e(Ajustes::get('tema', 'verde-oro')) ?>" data-modo="claro"
      data-base="<?= e(Url::basePath()) ?>" data-color-marca="<?= e(Ajustes::get('color_primario', '#0E4C5A')) ?>">
<head><?= Vista::parcial('partials/head', ['titulo' => $tituloPagina ?? 'Garita']) ?></head>
<body>
<div class="garita">
  <header class="garita-tope">
    <?= ico('escudo', 24) ?>
    <b><?= e(recortar(Ajustes::get('nombre', 'ResidencialPro'), 26)) ?></b>
    <span class="garita-estado" data-conexion><i class="luz"></i> <span>En línea</span></span>
    <span class="reloj" data-reloj>--:--:--</span>
    <div class="desplegable">
      <button class="icono-btn" style="color:#E9EEE9" data-desplegable="menu-garita" aria-label="Menú">
        <?= ico('menu', 22) ?>
      </button>
      <div class="desplegable-menu" id="menu-garita">
        <div style="padding:10px 12px">
          <b style="display:block;font-size:.9rem"><?= e((string) ($u['nombre'] ?? '')) ?></b>
          <small class="texto-3">Turno de garita</small>
        </div><hr>
        <?php foreach (Menu::garita() as $item): ?>
          <a href="<?= e(url($item['url'])) ?>"><?= ico($item['icono'], 17) ?> <?= e($item['texto']) ?></a>
        <?php endforeach; ?>
        <a href="<?= e(url('/garita/turno')) ?>"><?= ico('reloj', 17) ?> Cambio de turno</a>
        <hr>
        <a href="<?= e(url('/salir')) ?>"><?= ico('salir', 17) ?> Cerrar sesión</a>
      </div>
    </div>
  </header>

  <div style="padding:0 20px" class="no-imprimir">
    <?= Vista::parcial('partials/flash') ?>
  </div>

  <main class="garita-cuerpo"><?= $contenido ?></main>

  <nav class="garita-tope" style="border-top:1px solid rgba(255,255,255,.1);border-bottom:0;justify-content:center;gap:6px">
    <?php foreach (Menu::garita() as $item): ?>
      <a href="<?= e(url($item['url'])) ?>" class="btn btn-sm <?= Menu::esActivo($item) ? 'btn-oro' : 'btn-fantasma' ?>"
         style="<?= Menu::esActivo($item) ? '' : 'color:#D9E0DA;border-color:rgba(255,255,255,.2)' ?>">
        <?= ico($item['icono'], 16) ?> <?= e($item['texto']) ?>
      </a>
    <?php endforeach; ?>
  </nav>
</div>
<script<?= nonce() ?> src="<?= e(url('/assets/js/app.js')) ?>?v=<?= RPRO_VERSION ?>"></script>
<script<?= nonce() ?> src="<?= e(url('/assets/js/garita.js')) ?>?v=<?= RPRO_VERSION ?>"></script>
<?php if (!empty($scripts)): ?><?= $scripts ?><?php endif; ?>
</body>
</html>
