<?php
$sym = (string) $company['currency_symbol'];
$statuses = \App\Models\Quote::STATUSES;
page('barActions', '<a class="btn btn--ghost btn--sm" href="' . e(url('/panel/tablero')) . '">Ver tablero</a>'
    . '<a class="btn btn--accent btn--sm" href="' . e(url('/panel/cotizaciones/nueva')) . '">Nueva cotización</a>');
$qsBase = static function (array $over = []) use ($filters, $status): string {
    $p = array_filter([
        'q' => $filters['q'], 'estado' => $status, 'vendedor' => $filters['user_id'] ?: '',
        'desde' => $filters['from'], 'hasta' => $filters['to'], 'orden' => $filters['sort'],
    ], static fn ($v) => (string) $v !== '');
    foreach ($over as $k => $v) { if ($v === null) { unset($p[$k]); } else { $p[$k] = $v; } }
    return $p ? '?' . http_build_query($p) : '';
};
?>
<form class="filterbar" method="get" action="<?= e(url('/panel/cotizaciones')) ?>">
  <div class="field" style="min-width:220px"><label for="fq">Buscar</label>
    <input class="input" id="fq" name="q" value="<?= e($filters['q']) ?>" placeholder="Número, cliente, correo…"></div>
  <div class="field"><label for="fe">Estado</label>
    <select class="select" id="fe" name="estado">
      <option value="">Todos</option>
      <?php foreach ($statuses as $k => $m): ?>
        <option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($m['label']) ?></option>
      <?php endforeach; ?>
    </select></div>
  <?php if (!\App\Core\Auth::isSeller() && $sellers): ?>
    <div class="field"><label for="fv">Vendedor</label>
      <select class="select" id="fv" name="vendedor">
        <option value="">Todos</option>
        <?php foreach ($sellers as $s): ?>
          <option value="<?= e($s['id']) ?>"<?= (int) $filters['user_id'] === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
  <?php endif; ?>
  <div class="field"><label for="fd">Desde</label><input class="input" id="fd" type="date" name="desde" value="<?= e($filters['from']) ?>"></div>
  <div class="field"><label for="fh">Hasta</label><input class="input" id="fh" type="date" name="hasta" value="<?= e($filters['to']) ?>"></div>
  <div class="field"><label for="fo">Orden</label>
    <select class="select" id="fo" name="orden">
      <?php foreach (['' => 'Más recientes', 'antigua' => 'Más antiguas', 'monto' => 'Mayor monto', 'seguimiento' => 'Sin seguimiento'] as $k => $lbl): ?>
        <option value="<?= e($k) ?>"<?= $filters['sort'] === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
      <?php endforeach; ?>
    </select></div>
  <button class="btn btn--ghost btn--sm" type="submit">Filtrar</button>
  <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/cotizaciones')) ?>">Limpiar</a>
</form>

<div class="card">
  <div class="card__head">
    <span class="secnum">01/</span>
    <h2><?= e(number_format($total)) ?> cotización<?= $total === 1 ? '' : 'es' ?></h2>
  </div>
  <div class="card__body card__body--flush tablescroll">
    <?php if (!$rows): ?>
      <p class="muted" style="padding:36px;text-align:center;margin:0">No hay cotizaciones con esos criterios.</p>
    <?php else: ?>
      <table class="datatable" style="border:0;border-radius:0">
        <caption class="sr-only">Listado de cotizaciones</caption>
        <thead><tr>
          <th scope="col">Número</th><th scope="col">Cliente</th><th scope="col">Vendedor</th>
          <th scope="col">Estado</th><th scope="col">Seguimiento</th><th scope="col" class="num">Monto</th><th scope="col"></th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $r): $light = \App\Models\Quote::trafficLight($r); ?>
            <tr>
              <td class="nowrap"><a href="<?= e(url('/panel/cotizaciones/' . $r['id'])) ?>"><strong><?= e($r['number']) ?></strong></a>
                <br><span class="small muted"><?= e(fechaCorta((string) $r['created_at'])) ?><?= $r['source'] === 'web' ? ' · web' : '' ?></span></td>
              <td><?= e(str_limit((string) ($r['contact_company'] ?: $r['contact_name']), 34)) ?>
                <br><span class="small muted"><?= e(str_limit((string) $r['contact_name'], 28)) ?></span></td>
              <td class="small"><?= e($r['seller_name'] ?: '—') ?></td>
              <td><span class="badge<?= $r['status'] === 'aprobada' ? ' badge--ok' : ($r['status'] === 'perdida' ? ' badge--bad' : ($r['status'] === 'nueva' ? ' badge--accent' : '')) ?>"><?= e($statuses[$r['status']]['short']) ?></span></td>
              <td class="nowrap small"><span class="qcard__dot dot-<?= e($light) ?>" style="display:inline-block;margin-right:6px"></span><?= e(humanDays((string) ($r['last_contact_at'] ?: $r['created_at']))) ?></td>
              <td class="num nowrap"><strong><?= e(money((float) $r['total'], $sym)) ?></strong></td>
              <td class="nowrap"><a class="btn btn--ghost btn--xs" href="<?= e(url('/panel/cotizaciones/' . $r['id'])) ?>">Abrir</a></td>
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
    $mk = static fn (int $n): string => url('/panel/cotizaciones') . $qsBase(['p' => $n > 1 ? $n : null]);
    if ($page > 1) { echo '<a href="' . e($mk($page - 1)) . '" rel="prev">Anterior</a>'; }
    for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) {
        echo $i === $page ? '<span class="is-cur" aria-current="page">' . $i . '</span>' : '<a href="' . e($mk($i)) . '">' . $i . '</a>';
    }
    if ($page < $pages) { echo '<a href="' . e($mk($page + 1)) . '" rel="next">Siguiente</a>'; }
    ?>
  </nav>
<?php endif; ?>
