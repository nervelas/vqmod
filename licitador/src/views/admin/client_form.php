<?php $c = $c ?? null; $id = $c['id'] ?? 0; ?>
<p><a class="back" href="<?= e(url('admin/clientes')) ?>">← Clientes</a></p>
<div class="page-head"><div><p class="eyebrow">Cliente</p><h1><?= $c ? e($c['name']) : 'Nuevo cliente' ?></h1></div></div>
<div class="two-col top">
<form method="post" action="<?= e(url('admin/clientes/guardar')) ?>" class="card form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
  <?= f_input('name', 'Nombre', $c['name'] ?? '', 'text', 'required') ?>
  <?= f_input('email', 'Correo', $c['email'] ?? '', 'email', 'required') ?>
  <?= f_input('phone', 'Teléfono / WhatsApp', $c['phone'] ?? '') ?>
  <?php if (!$c): ?><?= f_input('pass', 'Contraseña inicial (vacío = generar una)', '', 'text', 'autocomplete="off"') ?><?php endif; ?>
  <?= f_select('status', 'Estado de la cuenta', ['activo' => 'Activo', 'suspendido' => 'Suspendido'], $c['status'] ?? 'activo') ?>
  <?= f_select('plan_id', 'Plan', ['' => 'Prueba gratis (sin plan)'] + array_column($plans, 'name', 'id'), $c['plan_id'] ?? '') ?>
  <?= f_input('sub_ends', 'Vence (fecha y hora)', $c && $c['sub_ends'] ? date('Y-m-d\TH:i', strtotime($c['sub_ends'])) : '', 'datetime-local') ?>
  <button class="btn btn-gold">Guardar</button>
</form>
<?php if ($c): ?>
<div>
  <section class="card"><h2>Acciones</h2>
    <form method="post" action="<?= e(url('admin/clientes/' . $id . '/accion')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="accion" value="extender"><input type="number" name="dias" value="7" min="1" max="365" aria-label="Días"><button class="btn btn-ghost btn-sm">Extender días</button></form>
    <div class="btn-row">
    <?php foreach (['recalcular' => 'Recalcular coincidencias', 'enlace' => 'Enviar enlace de contraseña', 'impersonar' => 'Entrar como el cliente (soporte)', $c['status'] === 'activo' ? 'suspender' : 'activar' => $c['status'] === 'activo' ? 'Suspender' : 'Activar'] as $a => $l): ?>
      <form method="post" action="<?= e(url('admin/clientes/' . $id . '/accion')) ?>"><?= csrf_field() ?><button name="accion" value="<?= e($a) ?>" class="btn btn-ghost btn-sm"><?= e($l) ?></button></form>
    <?php endforeach; ?>
      <form method="post" action="<?= e(url('admin/clientes/' . $id . '/accion')) ?>" data-confirm="¿Eliminar este cliente y todos sus datos?"><?= csrf_field() ?><button name="accion" value="eliminar" class="btn btn-danger btn-sm">Eliminar</button></form></div>
  </section>
  <section class="card"><h2>Perfil</h2><p class="muted small">Empresa: <?= e($p['company'] ?: '—') ?> · NIT <?= e($p['nit'] ?: '—') ?><br>Palabras clave: <?= e($p['keywords'] ?: '—') ?><br>Departamentos: <?= e($p['departments'] ?: 'todos') ?></p></section>
</div>
<?php endif; ?>
</div>
<?php if ($c): ?>
<section class="card"><h2>Coincidencias (<?= count($ms) ?>)</h2><div class="tbl-wrap"><table class="tbl"><tr><th>Concurso</th><th>Punt.</th><th>Estado</th><th>Vence</th></tr>
<?php foreach ($ms as $m): ?><tr><td data-l="Concurso"><a href="<?= e(url('admin/concursos/' . $m['id'])) ?>"><?= e($m['title']) ?></a><br><small class="muted"><?= e($m['reasons']) ?></small></td><td class="mono" data-l="Punt."><?= (int) $m['score'] ?></td><td data-l="Estado"><?= e($m['status']) ?></td><td class="mono" data-l="Vence"><?= e(fdate($m['deadline'])) ?></td></tr><?php endforeach; if (!$ms) echo '<tr><td colspan="4" class="muted">Sin coincidencias aún.</td></tr>'; ?></table></div></section>
<section class="card"><h2>Pagos</h2><ul class="similar"><?php foreach ($pays as $p2): ?><li><span><?= e(fdate($p2['created_at'])) ?> · <?= e($p2['status']) ?></span><strong class="mono"><?= e(money($p2['amount'])) ?></strong></li><?php endforeach; if (!$pays) echo '<li class="muted">Sin pagos.</li>'; ?></ul></section>
<?php endif; ?>
