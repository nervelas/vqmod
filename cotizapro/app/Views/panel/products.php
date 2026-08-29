<?php
$sym = (string) $company['currency_symbol'];
page('barActions', '<a class="btn btn--ghost btn--sm" href="' . e(url('/panel/importar')) . '">Importar</a>'
    . '<a class="btn btn--accent btn--sm" href="' . e(url('/panel/productos/nuevo')) . '">Nuevo producto</a>');
?>

<form class="filterbar" method="get" action="<?= e(url('/panel/productos')) ?>">
  <div class="field" style="min-width:240px"><label for="pq">Buscar</label>
    <input class="input" id="pq" name="q" value="<?= e(\App\Core\Request::str('q')) ?>" placeholder="Código, nombre o descripción…"></div>
  <div class="field"><label for="pc">Categoría</label>
    <select class="select" id="pc" name="categoria">
      <option value="">Todas</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= e($c['id']) ?>"<?= \App\Core\Request::int('categoria') === $c['id'] ? ' selected' : '' ?>><?= e($c['label']) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="field"><label for="pm">Marca</label>
    <select class="select" id="pm" name="marca">
      <option value="">Todas</option>
      <?php foreach ($brands as $b): ?>
        <option value="<?= e($b['id']) ?>"<?= \App\Core\Request::int('marca') === (int) $b['id'] ? ' selected' : '' ?>><?= e($b['name']) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="field"><label for="pe">Estado</label>
    <select class="select" id="pe" name="estado">
      <option value=""<?= \App\Core\Request::str('estado') === '' ? ' selected' : '' ?>>Todos</option>
      <option value="1"<?= \App\Core\Request::str('estado') === '1' ? ' selected' : '' ?>>Activos</option>
      <option value="0"<?= \App\Core\Request::str('estado') === '0' ? ' selected' : '' ?>>Ocultos</option>
    </select></div>
  <button class="btn btn--ghost btn--sm" type="submit">Filtrar</button>
  <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/productos')) ?>">Limpiar</a>
</form>

<div class="card">
  <div class="card__head"><span class="secnum">01/</span><h2><?= e(number_format($total)) ?> producto<?= $total === 1 ? '' : 's' ?></h2></div>
  <div class="card__body card__body--flush tablescroll">
    <?php if (!$rows): ?>
      <p class="muted" style="padding:36px;text-align:center;margin:0">No hay productos con esos criterios.</p>
    <?php else: ?>
      <table class="datatable" style="border:0;border-radius:0">
        <caption class="sr-only">Catálogo de productos</caption>
        <thead><tr>
          <th scope="col"><span class="sr-only">Foto</span></th><th scope="col">Código</th><th scope="col">Producto</th>
          <th scope="col">Categoría</th><th scope="col" class="num">Precio</th><th scope="col" class="num">Cotizado</th>
          <th scope="col">Estado</th><th scope="col"></th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $p): $img = $p['image'] ?? null; ?>
            <tr>
              <td style="width:56px"><img class="thumb" src="<?= e($img ? upload($img['path_thumb'] ?: $img['path']) : url('/assets/img/plates/sello-mecanico.svg')) ?>" alt="" aria-hidden="true" loading="lazy" width="46" height="36"></td>
              <td class="nowrap"><span class="code-chip"><?= e($p['code']) ?></span></td>
              <td><a href="<?= e(url('/panel/productos/' . $p['id'])) ?>"><strong><?= e(str_limit((string) $p['name'], 50)) ?></strong></a>
                <?php if ($p['featured']): ?> <span class="badge badge--accent">Destacado</span><?php endif; ?></td>
              <td class="small muted"><?= e($p['category_name'] ?: '—') ?></td>
              <td class="num nowrap"><?= (float) $p['price'] > 0 ? e(money((float) $p['price'], $sym)) : '—' ?></td>
              <td class="num"><?= e((int) $p['quote_count']) ?></td>
              <td><span class="badge<?= $p['active'] ? ' badge--ok' : '' ?>"><?= $p['active'] ? 'Activo' : 'Oculto' ?></span></td>
              <td class="nowrap">
                <a class="btn btn--ghost btn--xs" href="<?= e(url('/panel/productos/' . $p['id'])) ?>">Editar</a>
                <a class="btn btn--ghost btn--xs" href="<?= e(url('/producto/' . $p['slug'])) ?>" target="_blank" rel="noopener">Ver</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Paginación">
    <?php
    $q = $_GET; unset($q['p']);
    $mk = static fn (int $n): string => url('/panel/productos') . '?' . http_build_query(array_merge($q, $n > 1 ? ['p' => $n] : []));
    if ($page > 1) { echo '<a href="' . e($mk($page - 1)) . '" rel="prev">Anterior</a>'; }
    for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) {
        echo $i === $page ? '<span class="is-cur" aria-current="page">' . $i . '</span>' : '<a href="' . e($mk($i)) . '">' . $i . '</a>';
    }
    if ($page < $pages) { echo '<a href="' . e($mk($page + 1)) . '" rel="next">Siguiente</a>'; }
    ?>
  </nav>
<?php endif; ?>
