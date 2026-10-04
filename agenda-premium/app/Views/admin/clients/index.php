<?php
/** @var array $rows @var int $total @var int $page @var int $pages @var array $f @var array $query @var array $tags @var string $tz @var bool $isHost */
use App\Core\Fmt;
use App\Core\Str;

$hasFilters = $f['q'] !== '' || $f['etiqueta'] !== '' || $f['estado'] !== '';
?>
<div class="page">
  <div class="page-head">
    <div>
      <h1 class="page-title serif">Clientes</h1>
      <p class="page-sub"><?= (int) $total ?> <?= $total === 1 ? 'persona' : 'personas' ?><?= $hasFilters ? ' con los filtros aplicados' : ' en tu base' ?></p>
    </div>
    <div class="page-actions">
      <a class="btn btn-outline" href="<?= e(url('/admin/clientes/exportar', $query)) ?>"><?= icon('download') ?>Exportar CSV</a>
      <?php if (!$isHost) : ?>
        <a class="btn btn-outline" href="<?= e(url('/admin/clientes/importar')) ?>"><?= icon('upload') ?>Importar</a>
        <a class="btn btn-gold" href="<?= e(url('/admin/clientes/nuevo')) ?>"><?= icon('plus') ?>Nuevo cliente</a>
      <?php endif; ?>
    </div>
  </div>

  <form class="card filters" method="get" action="<?= e(url('/admin/clientes')) ?>" role="search">
    <div class="card-body">
      <div class="filter-grid">
        <div class="field filter-q"><label for="c-q">Buscar</label><input class="input" id="c-q" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Nombre, correo, teléfono o NIT" maxlength="80"></div>
        <div class="field"><label for="c-s">Mostrar</label>
          <select class="select" id="c-s" name="estado">
            <option value="">Todos</option>
            <option value="inasistencias"<?= sel($f['estado'], 'inasistencias') ?>>Con inasistencias</option>
            <option value="bloqueados"<?= sel($f['estado'], 'bloqueados') ?>>Bloqueados</option>
          </select></div>
        <div class="field"><label for="c-o">Ordenar por</label>
          <select class="select" id="c-o" name="orden">
            <option value="nombre"<?= sel($f['orden'], 'nombre') ?>>Nombre</option>
            <option value="reciente"<?= sel($f['orden'], 'reciente') ?>>Más recientes</option>
            <option value="citas"<?= sel($f['orden'], 'citas') ?>>Más citas</option>
          </select></div>
        <?php if ($f['etiqueta'] !== '') : ?><input type="hidden" name="etiqueta" value="<?= e($f['etiqueta']) ?>"><?php endif; ?>
        <div class="filter-actions">
          <button class="btn btn-gold" type="submit"><?= icon('search') ?>Filtrar</button>
          <?php if ($hasFilters) : ?><a class="btn btn-ghost" href="<?= e(url('/admin/clientes')) ?>">Limpiar</a><?php endif; ?>
        </div>
      </div>
      <?php if ($tags) : ?>
      <div class="chips" aria-label="Filtrar por etiqueta">
        <?php foreach ($tags as $t => $n) : $q = array_merge($query, ['etiqueta' => $t]); unset($q['pagina']); ?>
          <a class="chip<?= mb_strtolower($f['etiqueta']) === mb_strtolower($t) ? ' is-active' : '' ?>" href="<?= e(url('/admin/clientes', $q)) ?>"><?= icon('tag') ?><?= e($t) ?> <span class="mono muted"><?= (int) $n ?></span></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </form>

  <?php if (!$isHost) : ?><p class="muted small"><a href="<?= e(url('/admin/clientes/duplicados')) ?>"><?= icon('users') ?>Revisar posibles duplicados</a></p><?php endif; ?>

  <?php if (!$rows) : ?>
    <div class="card"><div class="empty">
      <?= icon('users') ?>
      <p class="empty-title serif"><?= $hasFilters ? 'No encontramos clientes con esos filtros' : 'Aún no tienes clientes' ?></p>
      <p class="empty-text"><?= $hasFilters ? 'Prueba con otra búsqueda o limpia los filtros.' : 'Se crean solos cuando alguien reserva. También puedes agregarlos a mano o importarlos desde un CSV.' ?></p>
      <?php if (!$isHost) : ?><a class="btn btn-gold" href="<?= e(url('/admin/clientes/nuevo')) ?>">Agregar el primer cliente</a><?php endif; ?>
    </div></div>
  <?php else : ?>
    <div class="card">
      <div class="table-wrap">
        <table class="table">
          <caption class="sr-only">Lista de clientes</caption>
          <thead><tr><th scope="col">Nombre</th><th scope="col" class="hide-sm">Contacto</th><th scope="col" class="hide-sm">Etiquetas</th><th scope="col" class="right">Citas</th><th scope="col" class="hide-sm">Última cita</th><th scope="col">Estado</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r) : ?>
            <tr>
              <td><a class="row-link" href="<?= e(url('/admin/clientes/' . $r['id'])) ?>"><span class="avatar avatar-sm" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $r['name'], 0, 1))) ?></span> <strong><?= e($r['name']) ?></strong></a><?php if ((int) $r['blocked']) : ?> <span class="only-xs badge badge-err">Bloqueado</span><?php elseif ((int) $r['noshow_count'] > 0) : ?> <span class="only-xs badge badge-warn"><?= (int) $r['noshow_count'] ?> no asistió</span><?php endif; ?></td>
              <td class="hide-sm"><?= $r['email'] ? e($r['email']) : '' ?><?= $r['email'] && $r['phone'] ? '<br>' : '' ?><?= $r['phone'] ? '<span class="mono small">' . e(Str::phoneDisplay($r['phone'])) . '</span>' : '' ?></td>
              <td class="hide-sm"><?php foreach (\App\Controllers\Admin\A1Support::tagList($r['tags']) as $t) : ?><span class="badge badge-muted"><?= e($t) ?></span> <?php endforeach; ?></td>
              <td class="right mono"><?= (int) $r['n_bookings'] ?></td>
              <td class="hide-sm mono small"><?= $r['last_at'] ? e(Fmt::dateShort($r['last_at'], $tz)) : '<span class="muted">—</span>' ?></td>
              <td><?php if ((int) $r['blocked']) : ?><span class="badge badge-err">Bloqueado</span><?php elseif ((int) $r['noshow_count'] > 0) : ?><span class="badge badge-warn"><?= (int) $r['noshow_count'] ?> no asistió</span><?php else : ?><span class="badge badge-ok">Activo</span><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-foot"><?php partial('admin/bookings/_pager', ['page' => $page, 'pages' => $pages, 'base' => '/admin/clientes', 'query' => $query]); ?></div>
    </div>
  <?php endif; ?>
</div>
