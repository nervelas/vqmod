<?php $ok = static fn(bool $b) => '<span class="badge ' . ($b ? 'badge-ok' : 'badge-bad') . '">' . ($b ? 'OK' : 'Falta') . '</span>'; ?>
<div class="grid grid-2">
  <div class="card"><h2><?= e(__('Estado del sistema')) ?></h2>
    <dl class="kv"><dt>AUREA</dt><dd><?= e($version) ?></dd><dt>PHP</dt><dd><?= e($php) ?> <?= version_compare($php, '8.0.0', '>=') ? $ok(true) : $ok(false) ?></dd><dt><?= e(__('Base de datos')) ?></dt><dd><?= e($dbv) ?></dd><dt><?= e(__('Zona horaria')) ?></dt><dd><?= e($tz) ?></dd>
      <dt>HTTPS</dt><dd><?= $https ? $ok(true) : '<span class="badge badge-warn">' . e(__('No detectado')) . '</span>' ?></dd><dt><?= e(__('Hash de contraseñas')) ?></dt><dd><?= $argon ? 'ARGON2ID' : 'BCRYPT (cost 12)' ?></dd><dt>SMTP</dt><dd><?= $smtp ? $ok(true) : '<span class="badge badge-warn">mail()</span>' ?></dd>
      <dt><?= e(__('Carpeta /instalar')) ?></dt><dd><?= $installer ? '<span class="badge badge-bad">' . e(__('Elimínala')) . '</span>' : $ok(true) ?></dd></dl>
    <h3 style="margin-top:20px"><?= e(__('Extensiones')) ?></h3><p><?php foreach ($checks as [$n, $v]): ?><span class="chip-link"><?= e($n) ?> <?= $v ? '✓' : '✗' ?></span> <?php endforeach; ?></p>
    <h3><?= e(__('Permisos de escritura')) ?></h3><p><?php foreach ($dirs as [$n, $v]): ?><span class="chip-link"><?= e($n) ?> <?= $v ? '✓' : '✗' ?></span> <?php endforeach; ?></p>
    <h3><?= e(__('Actualizaciones de base de datos')) ?></h3>
    <p><?= $pending ? e(__('%d pendientes', $pending)) : e(__('Al día.')) ?></p>
    <?php if ($pending): ?><form method="post" action="<?= e(url('/admin/sistema/migrar')) ?>"><?= csrf_field() ?><button class="btn btn-gold btn-sm" type="submit"><?= e(__('Aplicar ahora')) ?></button></form><?php endif; ?>
  </div>
  <div>
    <div class="card"><h2><?= e(__('Cron (tareas automáticas)')) ?></h2>
      <p><?= e(__('Última ejecución')) ?>: <b><?= $last ? e(date('d/m/Y H:i:s', $last)) : e(__('nunca')) ?></b></p>
      <p class="hint"><?= e(__('Opción A (cPanel → Cron Jobs, cada minuto o cada 5 minutos):')) ?></p><div class="field"><input readonly value="* * * * * <?= e($cronCli) ?>" aria-label="cron CLI"></div>
      <p class="hint"><?= e(__('Opción B (si solo permite wget/curl):')) ?></p><div class="field"><input id="cu" readonly value="curl -s '<?= e($cronUrl) ?>' >/dev/null" aria-label="cron URL"></div><button class="btn btn-ink btn-sm" type="button" data-copy="#cu"><?= e(__('Copiar')) ?></button>
      <form method="post" action="<?= e(url('/admin/sistema/cron')) ?>" class="inline-form"><?= csrf_field() ?><button class="btn btn-line btn-sm" type="submit"><?= e(__('Ejecutar ahora')) ?></button></form>
    </div>
    <div class="card"><h2><?= e(__('Correo de prueba')) ?></h2>
      <form method="post" action="<?= e(url('/admin/sistema/correo-prueba')) ?>" class="toolbar"><?= csrf_field() ?><div class="field grow"><label for="to"><?= e(__('Enviar a')) ?></label><input id="to" type="email" name="to" required value="<?= e($user['email']) ?>"></div><button class="btn btn-gold" type="submit"><?= e(__('Enviar correo de prueba')) ?></button></form>
      <p class="hint"><?= e(__('Configura SMTP en Marca y ajustes → Comunicación.')) ?></p></div>
    <div class="card"><h2><?= e(__('Respaldo')) ?></h2><p class="hint"><?= e(__('Descarga un archivo .sql con toda la base de datos. Guárdalo en un lugar seguro: contiene datos personales.')) ?></p><a class="btn btn-gold" href="<?= e(url('/admin/sistema/respaldo')) ?>"><?= e(__('Descargar respaldo (.sql)')) ?></a></div>
  </div>
</div>
<div class="card"><h2><?= e(__('Cola de mensajes')) ?></h2><p><?php foreach ($queue as $r): ?><span class="chip-link"><?= e($r['status']) ?>: <?= (int)$r['n'] ?></span> <?php endforeach; if (!$queue) { echo e(__('Vacía.')); } ?> <a href="<?= e(url('/admin/mensajes')) ?>"><?= e(__('Ver mensajes')) ?> →</a></p></div>
<div class="card"><h2><?= e(__('Registro de errores (últimas líneas)')) ?></h2><pre style="white-space:pre-wrap;font-size:.8rem;max-height:320px;overflow:auto;margin:0"><?= e($log !== '' ? $log : __('Sin errores registrados.')) ?></pre></div>
