<?php
use App\Core\Ajustes;
use App\Core\Auth;
use App\Core\Menu;
use App\Core\Url;
use App\Core\Vista;

$u    = Auth::usuario();
$tema = $u['tema'] ?? Ajustes::get('tema', 'verde-oro');
$modo = ($u['modo_oscuro'] ?? 0) ? 'oscuro' : 'claro';
?><!DOCTYPE html>
<html lang="es" data-tema="<?= e($tema) ?>" data-modo="<?= e($modo) ?>"
      data-base="<?= e(Url::basePath()) ?>" data-color-marca="<?= e(Ajustes::get('color_primario', '#0E4C5A')) ?>">
<head><?= Vista::parcial('partials/head', ['titulo' => $tituloPagina ?? 'Panel']) ?></head>
<body>
<a class="saltar-a" href="#contenido-principal">Ir al contenido</a>
<div class="app">
  <?= Vista::parcial('partials/barra') ?>
  <div class="principal">
    <?= Vista::parcial('partials/tope', [
        'tituloPagina' => $tituloPagina ?? 'Tablero',
        'subtitulo'    => $subtitulo ?? '',
        'accionesTope' => $accionesTope ?? '',
    ]) ?>
    <main class="contenido" id="contenido-principal">
      <?= Vista::parcial('partials/flash') ?>
      <?= $contenido ?>
    </main>
  </div>
</div>

<nav class="nav-movil" aria-label="Menú rápido">
  <?php
  $rapido = [
      ['url' => '/admin',            'texto' => 'Tablero',  'icono' => 'panel', 'exacto' => true],
      ['url' => '/admin/morosidad',  'texto' => 'Cobros',   'icono' => 'billetera'],
      ['url' => '/admin/comprobantes', 'texto' => 'Revisar', 'icono' => 'archivo'],
      ['url' => '/admin/visitas',    'texto' => 'Visitas',  'icono' => 'puerta'],
      ['url' => '/admin/avisos',     'texto' => 'Avisos',   'icono' => 'megafono'],
  ];
  foreach ($rapido as $item): ?>
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
