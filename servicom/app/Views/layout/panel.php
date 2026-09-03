<?php
/** Panel de empresa. */
$withPanelCss = true;
$withPanelJs  = true;
$noindex = true;
$path = \App\Core\Request::path();
$jsConfig = array_merge([
    'boardMoveUrl'      => url('/panel/tablero/mover'),
    'categoryOrderUrl'  => url('/panel/categorias/orden'),
    'productSearchUrl'  => url('/panel/productos/buscar'),
    'currency'          => (string) ($company['currency_symbol'] ?? 'Q'),
], $jsConfig ?? []);
$nav = [
    ['Operación', [
        ['/panel', 'Tablero de control', 'M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z'],
        ['/panel/tablero', 'Cotizaciones · Kanban', 'M4 4h4v16H4V4Zm6 0h4v10h-4V4Zm6 0h4v7h-4V4Z'],
        ['/panel/cotizaciones', 'Listado de cotizaciones', 'M4 4h16v3H4V4Zm0 6h16v3H4v-3Zm0 6h10v3H4v-3Z'],
        ['/panel/clientes', 'Clientes', 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 8a8 8 0 0 1 16 0H4Z'],
    ]],
    ['Catálogo', [
        ['/panel/productos', 'Productos', 'M3 7l9-4 9 4-9 4-9-4Zm0 5l9 4 9-4M3 17l9 4 9-4'],
        ['/panel/categorias', 'Categorías', 'M4 5h16M4 12h16M4 19h10'],
        ['/panel/marcas', 'Marcas', 'M4 4h9l7 7-9 9-7-7V4Zm3.5 3.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z'],
        ['/panel/atributos', 'Atributos técnicos', 'M4 6h16M4 12h10M4 18h13M18 10v4'],
        ['/panel/listas-precios', 'Listas de precios', 'M4 4h16v4H4V4Zm0 6h16v4H4v-4Zm0 6h10v4H4v-4Z'],
        ['/panel/importar', 'Importar', 'M12 3v12m0 0 4-4m-4 4-4-4M4 19h16'],
    ]],
    ['Gestión', [
        ['/panel/reportes', 'Reportes', 'M4 20V10m5 10V4m5 16v-7m5 7V7'],
        ['/panel/usuarios', 'Usuarios y roles', 'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3 20a6 6 0 0 1 12 0M15 20h6a5 5 0 0 0-4-4.9'],
        ['/panel/ajustes', 'Configuración', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8-3a8 8 0 0 1-.1 1.2l2 1.6-2 3.4-2.4-1a8 8 0 0 1-2 1.2L15 21H9l-.5-2.6a8 8 0 0 1-2-1.2l-2.4 1-2-3.4 2-1.6a8 8 0 0 1 0-2.4l-2-1.6 2-3.4 2.4 1a8 8 0 0 1 2-1.2L9 3h6l.5 2.6a8 8 0 0 1 2 1.2l2.4-1 2 3.4-2 1.6c.07.4.1.8.1 1.2Z'],
        ['/panel/bitacora', 'Bitácora', 'M6 3h9l4 4v14H6V3Zm3 7h8M9 14h8M9 18h5'],
        ['/panel/respaldos', 'Respaldos', 'M4 7c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3Zm0 5c0 1.7 3.6 3 8 3s8-1.3 8-3M4 7v10c0 1.7 3.6 3 8 3s8-1.3 8-3V7'],
    ]],
];
?>
<!doctype html>
<html lang="es">
<head><?= \App\Core\View::partial('partials/head', get_defined_vars()) ?></head>
<body>
<a class="skip" href="#contenido">Saltar al contenido</a>
<div class="app">
  <aside class="side" id="sidebar">
    <a class="side__brand" href="<?= e(url('/panel')) ?>">
      <?php if (!empty($company['logo'])): ?>
        <img src="<?= e(upload($company['logo'])) ?>" alt="<?= e($company['name']) ?>" width="120" height="30">
      <?php else: ?>
        <span class="side__mark" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($company['name'], 0, 2))) ?></span>
      <?php endif; ?>
      <span><b><?= e(str_limit($company['name'], 22)) ?></b><small>Panel de cotizaciones</small></span>
    </a>
    <nav aria-label="Panel">
      <?php foreach ($nav as [$group, $items]): ?>
        <div class="side__label"><?= e($group) ?></div>
        <?php foreach ($items as [$href, $label, $d]):
            $active = $href === '/panel' ? $path === '/panel' : str_starts_with($path, $href);
            if (($auth['role'] ?? '') === 'visor' && !in_array($href, ['/panel', '/panel/cotizaciones', '/panel/clientes', '/panel/productos', '/panel/reportes'], true)) { continue; }
            if (($auth['role'] ?? '') === 'vendedor' && in_array($href, ['/panel/usuarios', '/panel/ajustes', '/panel/bitacora', '/panel/respaldos', '/panel/listas-precios'], true)) { continue; }
        ?>
          <a href="<?= e(url($href)) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
            <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= e($d) ?>"/></svg>
            <span><?= e($label) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="side__foot">
      <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Ver mi sitio público ↗</a>
      <a href="<?= e(url('/panel/perfil')) ?>"><?= e($auth['name'] ?? '') ?> · <?= e($auth['role'] ?? '') ?></a>
      <a href="<?= e(url('/salir')) ?>">Cerrar sesión</a>
    </div>
  </aside>

  <div class="main">
    <div class="pbar">
      <button class="sidetoggle" type="button" aria-expanded="false" aria-controls="sidebar" aria-label="Abrir menú">
        <svg width="18" height="14" viewBox="0 0 18 14" aria-hidden="true"><path d="M0 1h18M0 7h18M0 13h18" stroke="currentColor" stroke-width="1.6"/></svg>
      </button>
      <h1><?= e($title ?? 'Panel') ?></h1>
      <div class="pbar__actions">
        <?= $barActions ?? '' ?>
        <div class="dropdown">
          <button class="bell" type="button" data-dropdown="notifmenu" aria-expanded="false" aria-label="Notificaciones">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
            <?php if (($notifCount ?? 0) > 0): ?><span class="bell__n"><?= e(min(99, (int) $notifCount)) ?></span><?php endif; ?>
          </button>
          <div class="dropdown__menu" id="notifmenu">
            <?php if (empty($notifs)): ?>
              <a href="#" data-nosweep><small>No tiene avisos nuevos.</small></a>
            <?php else: foreach ($notifs as $n): ?>
              <a href="<?= e(url((string) $n['link'] ?: '/panel')) ?>"><b><?= e($n['title']) ?></b><small><?= e($n['body']) ?> · <?= e(humanDays((string) $n['created_at'])) ?></small></a>
            <?php endforeach; ?>
              <a href="<?= e(url('/panel/notificaciones/leer')) ?>"><small>Marcar todo como leído</small></a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="pbody" id="contenido">
      <?= \App\Core\View::partial('partials/flash', get_defined_vars()) ?>
      <?= $content ?>
    </div>
  </div>
</div>
<?= \App\Core\View::partial('partials/scripts', get_defined_vars()) ?>
</body>
</html>
