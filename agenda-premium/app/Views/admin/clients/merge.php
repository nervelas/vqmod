<?php /** @var ?array $a @var ?array $b @var array $sa @var array $sb @var string $error */ use App\Core\Str; ?>
<div class="page page-narrow">
  <div class="page-head"><div>
    <p class="crumbs muted"><a href="<?= e(url('/admin/clientes')) ?>">Clientes</a> / Fusionar</p>
    <h1 class="page-title serif">Fusionar clientes</h1>
    <p class="page-sub">Elige cuál ficha se conserva. Las citas, notas, archivos, pagos y paquetes de la otra pasan a la principal, y las inasistencias se suman.</p>
  </div></div>
  <?php if (!$a) : ?>
    <div class="card"><div class="empty"><?= icon('users') ?><p class="empty-title serif">Elige el primer cliente</p><p class="empty-text">Abre la ficha de una persona y usa «Fusionar con otro cliente», o revisa los posibles duplicados.</p><a class="btn btn-gold" href="<?= e(url('/admin/clientes/duplicados')) ?>">Ver posibles duplicados</a></div></div>
  <?php elseif (!$b) : ?>
    <div class="card" id="merge-pick" data-search-url="<?= e(url('/admin/buscar')) ?>" data-merge-url="<?= e(url('/admin/clientes/fusionar')) ?>" data-a="<?= (int) $a['id'] ?>">
      <div class="card-body stack">
        <p>Vas a fusionar a <strong><?= e($a['name']) ?></strong> con otra persona. ¿Con quién?</p>
        <div class="field"><label for="merge-q">Buscar al otro cliente</label><input class="input" id="merge-q" type="search" autocomplete="off" placeholder="Nombre, correo o teléfono" role="combobox" aria-expanded="false" aria-controls="merge-results"><ul class="client-results" id="merge-results" role="listbox" aria-label="Clientes encontrados" hidden></ul></div>
      </div>
    </div>
  <?php else : ?>
    <form method="post" action="<?= e(url('/admin/clientes/fusionar')) ?>" class="stack">
      <?= csrf_field() ?><input type="hidden" name="a" value="<?= (int) $a['id'] ?>"><input type="hidden" name="b" value="<?= (int) $b['id'] ?>">
      <fieldset class="grid cols-2 merge-pair"><legend class="sr-only">Cliente principal</legend>
        <?php foreach ([[$a, $sa], [$b, $sb]] as $i => [$x, $s]) : ?>
          <label class="card merge-card"><div class="card-body stack">
            <span class="check"><input type="radio" name="main" value="<?= (int) $x['id'] ?>"<?= $i === 0 ? ' checked' : '' ?>><span>Conservar esta ficha</span></span>
            <strong class="serif big"><?= e($x['name']) ?></strong>
            <span class="muted"><?= e($x['email'] ?: 'Sin correo') ?><br><?= $x['phone'] ? e(Str::phoneDisplay($x['phone'])) : 'Sin teléfono' ?></span>
            <span class="small"><?= (int) $s['bookings'] ?> citas · <?= (int) $s['notes'] ?> notas · <?= (int) $s['files'] ?> archivos · <?= (int) $x['noshow_count'] ?> inasistencias</span>
          </div></label>
        <?php endforeach; ?>
      </fieldset>
      <div class="alert alert-warn" role="note">La ficha que no elijas se eliminará. Esta acción no se puede deshacer.</div>
      <div class="form-actions"><a class="btn btn-ghost" href="<?= e(url('/admin/clientes/' . (int) $a['id'])) ?>">Cancelar</a><button class="btn btn-gold" type="submit" data-confirm="¿Fusionar estas dos fichas?"><?= icon('check') ?>Fusionar</button></div>
    </form>
  <?php endif; ?>
</div>
