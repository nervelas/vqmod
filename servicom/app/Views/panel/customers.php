<?php
$sym = (string) $company['currency_symbol'];
page('barActions', '<a class="btn btn--ghost btn--sm" href="' . e(url('/panel/clientes/exportar')) . '">Exportar Excel</a>'
    . '<a class="btn btn--accent btn--sm" href="' . e(url('/panel/clientes/nuevo')) . '">Nuevo cliente</a>');
?>
<form class="filterbar" method="get" action="<?= e(url('/panel/clientes')) ?>">
  <div class="field" style="min-width:260px"><label for="cq">Buscar</label>
    <input class="input" id="cq" name="q" value="<?= e(\App\Core\Request::str('q')) ?>" placeholder="Nombre, NIT, correo o teléfono…"></div>
  <?php if (!\App\Core\Auth::isSeller() && $sellers): ?>
    <div class="field"><label for="cv">Vendedor</label>
      <select class="select" id="cv" name="vendedor" data-autosubmit>
        <option value="">Todos</option>
        <?php foreach ($sellers as $s): ?>
          <option value="<?= e($s['id']) ?>"<?= \App\Core\Request::int('vendedor') === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
  <?php endif; ?>
  <button class="btn btn--ghost btn--sm" type="submit">Filtrar</button>
  <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/clientes')) ?>">Limpiar</a>
</form>

<div class="card">
  <div class="card__head"><span class="secnum">01/</span><h2><?= e(number_format($total)) ?> cliente<?= $total === 1 ? '' : 's' ?></h2></div>
  <div class="card__body card__body--flush tablescroll">
    <?php if (!$rows): ?>
      <p class="muted" style="padding:36px;text-align:center;margin:0">Aún no hay clientes registrados.</p>
    <?php else: ?>
      <table class="datatable" style="border:0;border-radius:0">
        <caption class="sr-only">Clientes</caption>
        <thead><tr><th scope="col">Cliente</th><th scope="col">NIT</th><th scope="col">Contacto</th><th scope="col">Vendedor</th>
          <th scope="col" class="num">Cotiz.</th><th scope="col" class="num">Ganado</th><th scope="col">Seguimiento</th><th scope="col"></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $c): ?>
            <tr>
              <td><a href="<?= e(url('/panel/clientes/' . $c['id'])) ?>"><strong><?= e(str_limit((string) $c['name'], 38)) ?></strong></a>
                <?php if ($c['price_list_name']): ?><br><span class="badge"><?= e($c['price_list_name']) ?></span><?php endif; ?></td>
              <td class="small"><?= e($c['nit'] ?: '—') ?></td>
              <td class="small"><?= e($c['email'] ?: '—') ?><?php if ($c['phone']): ?><br><span class="muted"><?= e($c['phone']) ?></span><?php endif; ?></td>
              <td class="small"><?= e($c['seller_name'] ?: '—') ?></td>
              <td class="num"><?= e((int) $c['quote_count']) ?></td>
              <td class="num nowrap"><?= e(money((float) $c['won_total'], $sym)) ?></td>
              <td class="small"><?= $c['next_followup'] ? e(fechaCorta((string) $c['next_followup'])) : '—' ?></td>
              <td class="nowrap"><a class="btn btn--ghost btn--xs" href="<?= e(url('/panel/clientes/' . $c['id'])) ?>">Abrir</a></td>
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
    $g = $_GET; unset($g['p']);
    $mk = static fn (int $n): string => url('/panel/clientes') . '?' . http_build_query(array_merge($g, $n > 1 ? ['p' => $n] : []));
    if ($page > 1) { echo '<a href="' . e($mk($page - 1)) . '">Anterior</a>'; }
    for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) {
        echo $i === $page ? '<span class="is-cur" aria-current="page">' . $i . '</span>' : '<a href="' . e($mk($i)) . '">' . $i . '</a>';
    }
    if ($page < $pages) { echo '<a href="' . e($mk($page + 1)) . '">Siguiente</a>'; }
    ?>
  </nav>
<?php endif; ?>
