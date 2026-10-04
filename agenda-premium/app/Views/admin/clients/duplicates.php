<?php /** @var array $groups */ use App\Core\Str; ?>
<div class="page page-narrow">
  <div class="page-head"><div>
    <p class="crumbs muted"><a href="<?= e(url('/admin/clientes')) ?>">Clientes</a> / Duplicados</p>
    <h1 class="page-title serif">Posibles duplicados</h1>
    <p class="page-sub">Personas que comparten teléfono, correo, NIT o nombre. Fusiónalas para unir su historial.</p>
  </div></div>
  <?php if (!$groups) : ?>
    <div class="card"><div class="empty"><?= icon('check') ?><p class="empty-title serif">Sin duplicados</p><p class="empty-text">Tu base de clientes está ordenada.</p></div></div>
  <?php else : foreach ($groups as $g) : $m = $g['members']; ?>
    <section class="card"><div class="card-head"><h2 class="serif"><?= e(ucfirst($g['why'])) ?></h2></div>
      <ul class="card-body list-plain"><?php foreach ($m as $i => $x) : ?>
        <li class="row row-between row-wrap"><span><a href="<?= e(url('/admin/clientes/' . $x['id'])) ?>"><strong><?= e($x['name']) ?></strong></a> <span class="muted small"><?= e(trim(($x['email'] ?? '') . ' ' . ($x['phone'] ? Str::phoneDisplay($x['phone']) : ''))) ?></span></span>
        <?php if ($i > 0) : ?><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/clientes/fusionar', ['a' => $m[0]['id'], 'b' => $x['id']])) ?>">Fusionar con <?= e(mb_substr($m[0]['name'], 0, 20)) ?></a><?php endif; ?></li>
      <?php endforeach; ?></ul></section>
  <?php endforeach; endif; ?>
</div>
