<?php
$sym = (string) $company['currency_symbol'];
$statuses = \App\Models\Quote::STATUSES;
page('barActions', '<a class="btn btn--ghost btn--sm" href="' . e(url('/panel/cotizaciones')) . '">Ver como lista</a>'
    . '<a class="btn btn--accent btn--sm" href="' . e(url('/panel/cotizaciones/nueva')) . '">Nueva cotización</a>');
?>
<form class="filterbar" method="get" action="<?= e(url('/panel/tablero')) ?>">
  <div class="field" style="min-width:240px">
    <label for="bq">Buscar</label>
    <input class="input" id="bq" name="q" value="<?= e(\App\Core\Request::str('q')) ?>" placeholder="Número, cliente o correo…">
  </div>
  <?php if (!\App\Core\Auth::isSeller() && $sellers): ?>
    <div class="field">
      <label for="bv">Vendedor</label>
      <select class="select" id="bv" name="vendedor" data-autosubmit>
        <option value="">Todos</option>
        <?php foreach ($sellers as $s): ?>
          <option value="<?= e($s['id']) ?>"<?= (int) $userFilter === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <button class="btn btn--ghost btn--sm" type="submit">Filtrar</button>
  <?php if (\App\Core\Request::str('q') !== '' || $userFilter): ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/tablero')) ?>">Limpiar</a>
  <?php endif; ?>
  <p class="small muted ml-auto" style="margin:0">Arrastre las tarjetas entre columnas. El semáforo indica días sin contacto.</p>
</form>

<div class="board" data-board>
  <?php foreach ($statuses as $key => $meta): $col = $columns[$key]; ?>
    <section class="bcol" data-status="<?= e($key) ?>" data-label="<?= e($meta['label']) ?>" aria-label="<?= e($meta['label']) ?>">
      <header class="bcol__head">
        <div class="bcol__title">
          <span><?= e($meta['label']) ?></span>
          <span class="bcol__n"><?= e($col['total']) ?></span>
        </div>
        <div class="bcol__sum"><?= e(money($col['monto'], $sym)) ?></div>
      </header>
      <div class="bcol__list">
        <?php foreach ($col['rows'] as $q): $light = \App\Models\Quote::trafficLight($q); ?>
          <article class="qcard qcard--<?= e($light) ?>" data-id="<?= e($q['id']) ?>">
            <div class="flex" style="gap:8px">
              <span class="qcard__num"><?= e($q['number']) ?></span>
              <?php if ($q['source'] === 'web'): ?><span class="badge badge--accent" style="margin-left:auto;font-size:.5625rem;padding:2px 6px">Web</span><?php endif; ?>
            </div>
            <h3 class="qcard__client"><a href="<?= e(url('/panel/cotizaciones/' . $q['id'])) ?>"><?= e(str_limit((string) ($q['contact_company'] ?: $q['contact_name']), 40)) ?></a></h3>
            <p class="small muted" style="margin:0"><?= e(str_limit((string) $q['contact_name'], 30)) ?> · <?= e((int) $q['item_count']) ?> ítems</p>
            <div class="qcard__meta">
              <span class="qcard__dot dot-<?= e($light) ?>" aria-hidden="true"></span>
              <span><?= e(humanDays((string) ($q['last_contact_at'] ?: $q['created_at']))) ?></span>
              <span class="qcard__amount"><?= e(money((float) $q['total'], $sym)) ?></span>
            </div>
            <?php if ($q['seller_name']): ?><p class="small muted" style="margin:8px 0 0;font-size:.6875rem"><?= e($q['seller_name']) ?></p><?php endif; ?>
          </article>
        <?php endforeach; ?>
        <?php if (!$col['rows']): ?>
          <p class="small muted" style="text-align:center;padding:22px 8px;margin:0">Sin cotizaciones</p>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>
