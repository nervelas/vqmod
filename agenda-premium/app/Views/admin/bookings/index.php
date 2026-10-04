<?php
/** @var array $rows @var int $total @var int $page @var int $pages @var array $f @var array $query @var array $counts @var array $hosts @var array $events @var string $tz @var string $today */
use App\Controllers\Admin\A1Support;
use App\Core\Auth;
use App\Core\Fmt;

$scoped = Auth::scopedHostId() !== null;
$allCount = array_sum($counts);
$tabs = ['' => 'Todas'] + A1Support::STATUS_LABELS;
$hasFilters = $f['q'] !== '' || $f['anfitrion'] || $f['evento'] || $f['desde'] !== '' || $f['hasta'] !== '' || $f['estado'] !== '';
?>
<div class="page">
  <div class="page-head">
    <div>
      <h1 class="page-title serif">Citas</h1>
      <p class="page-sub"><?= (int) $total ?> <?= $total === 1 ? 'cita' : 'citas' ?><?= $hasFilters ? ' con los filtros aplicados' : ' en total' ?></p>
    </div>
    <div class="page-actions">
      <a class="btn btn-outline" href="<?= e(url('/admin/citas/exportar', $query)) ?>"><?= icon('download') ?>Exportar CSV</a>
      <a class="btn btn-gold" href="<?= e(url('/admin/citas/nueva')) ?>"><?= icon('plus') ?>Nueva cita</a>
    </div>
  </div>

  <nav class="tabs status-tabs" aria-label="Filtrar por estado">
    <?php foreach ($tabs as $key => $label) :
        $n = $key === '' ? $allCount : (int) ($counts[$key] ?? 0);
        $q = array_merge($query, ['estado' => $key]);
        if ($key === '') { unset($q['estado']); }
        unset($q['pagina']); ?>
      <a class="tab<?= $f['estado'] === $key ? ' is-active' : '' ?>" href="<?= e(url('/admin/citas', $q)) ?>"<?= $f['estado'] === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?> <span class="tab-count mono"><?= $n ?></span></a>
    <?php endforeach; ?>
  </nav>

  <form class="card filters" method="get" action="<?= e(url('/admin/citas')) ?>" role="search">
    <div class="card-body">
      <?php if ($f['estado'] !== '') : ?><input type="hidden" name="estado" value="<?= e($f['estado']) ?>"><?php endif; ?>
      <div class="filter-grid">
        <div class="field filter-q">
          <label for="f-q">Buscar</label>
          <input class="input" id="f-q" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Nombre, correo, teléfono o n.º de cita" maxlength="80">
        </div>
        <?php if (!$scoped && count($hosts) > 1) : ?>
        <div class="field">
          <label for="f-h"><?= e(ucfirst((string) setting('host_label', 'profesional'))) ?></label>
          <select class="select" id="f-h" name="anfitrion"><option value="">Todos</option>
            <?php foreach ($hosts as $h) : ?><option value="<?= (int) $h['id'] ?>"<?= sel($f['anfitrion'], $h['id']) ?>><?= e($h['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="field">
          <label for="f-e">Tipo de cita</label>
          <select class="select" id="f-e" name="evento"><option value="">Todos</option>
            <?php foreach ($events as $ev) : ?><option value="<?= (int) $ev['id'] ?>"<?= sel($f['evento'], $ev['id']) ?>><?= e($ev['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="f-d">Desde</label>
          <input class="input" id="f-d" type="date" name="desde" value="<?= e($f['desde']) ?>">
        </div>
        <div class="field">
          <label for="f-a">Hasta</label>
          <input class="input" id="f-a" type="date" name="hasta" value="<?= e($f['hasta']) ?>">
        </div>
        <div class="filter-actions">
          <button class="btn btn-gold" type="submit"><?= icon('search') ?>Filtrar</button>
          <?php if ($hasFilters) : ?><a class="btn btn-ghost" href="<?= e(url('/admin/citas')) ?>">Limpiar</a><?php endif; ?>
        </div>
      </div>
      <div class="chips quick-ranges" aria-label="Atajos de fecha">
        <a class="chip" href="<?= e(url('/admin/citas', array_merge($query, ['desde' => $today, 'hasta' => $today, 'pagina' => null]))) ?>">Hoy</a>
        <a class="chip" href="<?= e(url('/admin/citas', array_merge($query, ['desde' => $today, 'hasta' => A1Support::addDays($today, 6), 'pagina' => null]))) ?>">Próximos 7 días</a>
        <a class="chip" href="<?= e(url('/admin/citas', array_merge($query, ['desde' => A1Support::addDays($today, -29), 'hasta' => $today, 'pagina' => null]))) ?>">Últimos 30 días</a>
      </div>
    </div>
  </form>

  <?php if (!$rows) : ?>
    <div class="card"><div class="empty">
      <?= icon('calendar') ?>
      <p class="empty-title serif"><?= $hasFilters ? 'No encontramos citas con esos filtros' : 'Todavía no hay citas' ?></p>
      <p class="empty-text"><?= $hasFilters ? 'Prueba con otro rango de fechas o limpia los filtros.' : 'Cuando alguien reserve o tú agendes una cita manual, aparecerá aquí.' ?></p>
      <a class="btn btn-gold" href="<?= e(url('/admin/citas/nueva')) ?>">Agendar una cita</a>
    </div></div>
  <?php else : ?>
    <div class="card">
      <div class="table-wrap">
        <table class="table">
          <caption class="sr-only">Lista de citas</caption>
          <thead><tr>
            <th scope="col">Fecha y hora</th><th scope="col">Persona</th><th scope="col" class="hide-sm">Tipo de cita</th>
            <?php if (!$scoped) : ?><th scope="col" class="hide-sm"><?= e(ucfirst((string) setting('host_label', 'profesional'))) ?></th><?php endif; ?>
            <th scope="col">Estado</th><th scope="col" class="hide-sm right">Pago</th>
          </tr></thead>
          <tbody>
          <?php foreach ($rows as $r) : ?>
            <tr>
              <td class="nowrap"><a class="row-link" href="<?= e(url('/admin/citas/' . $r['id'])) ?>"><span class="mono"><?= e(Fmt::dateShort($r['starts_at'], $tz)) ?></span> <span class="mono muted"><?= e(Fmt::time($r['starts_at'], $tz)) ?></span></a></td>
              <td><strong><?= e($r['guest_name']) ?></strong><?php if ($r['guest_phone']) : ?><br><span class="muted small mono"><?= e(\App\Core\Str::phoneDisplay($r['guest_phone'])) ?></span><?php endif; ?></td>
              <td class="hide-sm"><span class="dot-c" <?= vars(['--c' => $r['event_color']]) ?>></span> <?= e($r['event_name']) ?></td>
              <?php if (!$scoped) : ?><td class="hide-sm"><?= e($r['host_name']) ?></td><?php endif; ?>
              <td><span class="badge <?= e(A1Support::statusBadge($r['status'])) ?>"><?= e(A1Support::statusLabel($r['status'])) ?></span></td>
              <td class="hide-sm right mono"><?= (float) $r['total'] > 0 ? e(money($r['total'])) : '<span class="muted">—</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-foot"><?php partial('admin/bookings/_pager', ['page' => $page, 'pages' => $pages, 'base' => '/admin/citas', 'query' => $query]); ?></div>
    </div>
  <?php endif; ?>
</div>
