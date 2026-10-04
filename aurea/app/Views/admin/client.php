<div class="card">
  <div class="card-head"><div><h2 style="margin:0;font-size:1.8rem"><?= e($c['name']) ?></h2>
    <span class="hint">+<?= e($c['phone_cc']) ?> <?= e($c['phone']) ?> · <?= e($c['email'] ?: __('sin correo')) ?><?= $c['nit'] ? ' · NIT ' . e($c['nit']) : '' ?></span>
    <?php if ($c['blocked']): ?> <span class="badge badge-bad"><?= e(__('Bloqueado')) ?></span><?php endif; ?> <?php if ($c['noshow_count']): ?><span class="badge badge-warn"><?= (int)$c['noshow_count'] ?> <?= e(__('inasistencias')) ?></span><?php endif; ?></div>
    <div class="btn-row" style="margin:0"><a class="btn btn-gold btn-sm" href="<?= e(url('/admin/citas/nueva?cliente=' . $c['id'])) ?>">+ <?= e(__('Nueva %s', mb_strtolower(term('appt')))) ?></a>
      <a class="btn btn-line btn-sm" target="_blank" rel="noopener" href="<?= e(\Aurea\Core\Util::waLink($c['phone_cc'], $c['phone'], 'Hola ' . explode(' ', $c['name'])[0] . ',')) ?>">WhatsApp</a>
      <a class="btn btn-line btn-sm" href="<?= e(url('/admin/clientes/' . $c['id'] . '/editar')) ?>"><?= e(__('Editar')) ?></a>
      <?php if (\Aurea\Core\Auth::role() !== 'professional'): ?><form class="inline-form" method="post" action="<?= e(url('/admin/clientes/' . $c['id'] . '/bloquear')) ?>"><?= csrf_field() ?><button class="btn btn-line btn-sm" type="submit"><?= e($c['blocked'] ? __('Desbloquear') : __('Bloquear')) ?></button></form><?php endif; ?></div></div>
  <?php if ($c['consent_at']): ?><p class="hint"><?= e(__('Consentimiento de privacidad registrado el %s (versión %s, IP %s)', fdatetime($c['consent_at']), $c['consent_version'], $c['consent_ip'] ?: '—')) ?></p><?php endif; ?>
</div>
<div class="grid grid-2">
  <div class="card"><h3><?= e(__('Notas internas y etiquetas')) ?></h3>
    <form method="post" action="<?= e(url('/admin/clientes/' . $c['id'] . '/nota')) ?>"><?= csrf_field() ?>
      <div class="field"><label for="tg"><?= e(__('Etiquetas')) ?></label><input id="tg" name="tags" value="<?= e($c['tags']) ?>"></div>
      <div class="field"><label for="nt"><?= e(__('Notas privadas')) ?></label><textarea id="nt" name="notes"><?= e($c['notes']) ?></textarea></div>
      <button class="btn btn-ink btn-sm" type="submit"><?= e(__('Guardar')) ?></button></form></div>
  <div class="card"><h3><?= e(__('Archivos adjuntos')) ?></h3>
    <?php foreach ($files as $f): ?><div style="display:flex;justify-content:space-between;gap:10px;padding:6px 0;border-bottom:1px solid var(--border)"><a href="<?= e(url('/admin/archivos/' . $f['id'])) ?>"><?= e($f['original_name']) ?></a>
      <form class="inline-form" method="post" action="<?= e(url('/admin/archivos/' . $f['id'] . '/eliminar')) ?>" data-confirm="<?= e(__('¿Eliminar el archivo?')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">✕</button></form></div><?php endforeach; ?>
    <?php if (!$files): ?><p class="hint"><?= e(__('Sin archivos.')) ?></p><?php endif; ?>
    <form method="post" action="<?= e(url('/admin/clientes/' . $c['id'] . '/archivo')) ?>" enctype="multipart/form-data" style="margin-top:14px"><?= csrf_field() ?><div class="field"><input type="file" name="file" required aria-label="<?= e(__('Archivo')) ?>" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xlsx,.txt"><div class="help"><?= e(__('PDF, imágenes, Word, Excel o texto. Se guardan protegidos.')) ?></div></div><button class="btn btn-ink btn-sm" type="submit"><?= e(__('Subir')) ?></button></form></div>
</div>
<?php if (\Aurea\Core\Auth::can('packages_sell')): ?>
<div class="card"><h3><?= e(__('Paquetes de sesiones')) ?></h3>
  <?php foreach ($packages as $p): ?><div style="padding:8px 0;border-bottom:1px solid var(--border)"><b><?= e($p['name']) ?></b> — <?= (int)$p['sessions_total'] - (int)$p['sessions_used'] ?> <?= e(__('de')) ?> <?= (int)$p['sessions_total'] ?> <?= e(__('sesiones disponibles')) ?><?= $p['expires_at'] ? ' · ' . e(__('vence')) . ' ' . e(fdate($p['expires_at'])) : '' ?> · <?= e(money($p['price'])) ?></div><?php endforeach; ?>
  <?php if ($catalog): ?><form method="post" action="<?= e(url('/admin/clientes/' . $c['id'] . '/paquete')) ?>" class="toolbar" style="margin-top:14px"><?= csrf_field() ?><div class="field"><label for="pk"><?= e(__('Vender paquete')) ?></label><select id="pk" name="package_id"><?php foreach ($catalog as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (<?= (int)$p['sessions'] ?> · <?= e(money($p['price'])) ?>)</option><?php endforeach; ?></select></div><button class="btn btn-gold btn-sm" type="submit"><?= e(__('Asignar')) ?></button></form><?php endif; ?></div>
<?php endif; ?>
<div class="card"><h3><?= e(__('Historial de %s', mb_strtolower(term('appts')))) ?></h3>
  <?php if (!$apps): ?><p class="hint"><?= e(__('Sin citas registradas.')) ?></p><?php else: ?><div class="tbl-wrap"><table class="tbl"><tbody><?php foreach ($apps as $a): ?><tr><td class="num"><a class="strong" href="<?= e(url('/admin/citas/' . $a['id'])) ?>"><?= e(fdatetime($a['start_at'])) ?></a></td><td><?= e($a['service_name']) ?></td><td><?= e($a['prof_name']) ?></td><td><span class="st-<?= e($a['status']) ?>"><?= e($statuses[$a['status']]) ?></span></td><td class="r"><?= e(money($a['total'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
<?php if (\Aurea\Core\Auth::role() === 'admin'): ?>
<div class="card"><h3><?= e(__('Protección de datos')) ?></h3><div class="btn-row" style="justify-content:flex-start">
  <a class="btn btn-line btn-sm" href="<?= e(url('/admin/clientes/' . $c['id'] . '/datos')) ?>"><?= e(__('Exportar todos sus datos (JSON)')) ?></a>
  <form class="inline-form" method="post" action="<?= e(url('/admin/clientes/' . $c['id'] . '/eliminar')) ?>" data-confirm="<?= e(__('Se eliminarán definitivamente el cliente, sus citas, pagos y archivos. ¿Continuar?')) ?>"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit"><?= e(__('Eliminar definitivamente sus datos')) ?></button></form></div></div>
<?php endif; ?>
