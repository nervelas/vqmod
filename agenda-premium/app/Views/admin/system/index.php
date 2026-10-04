<?php
/** Estado del sistema. */
use App\Controllers\Admin\A4Controller;

$badge = static fn (string $s): string => $s === 'ok' ? 'badge-ok' : ($s === 'warn' ? 'badge-warn' : 'badge-err');
$word = static fn (string $s): string => $s === 'ok' ? 'Correcto' : ($s === 'warn' ? 'Atención' : 'Problema');
$usedPct = ($storage['total'] ?? 0) > 0 && $storage['free'] !== null ? (int) round((1 - $storage['free'] / $storage['total']) * 100) : null;
$problems = 0;
foreach ($runtime as $r) {
    $problems += $r['status'] === 'err' ? 1 : 0;
}
foreach ($extensions as $x) {
    $problems += (!$x['ok'] && $x['required']) ? 1 : 0;
}
foreach ($dirs as $d) {
    $problems += !$d['writable'] ? 1 : 0;
}
$problems += $cron['state'] === 'err' ? 1 : 0;
?>
<div class="page p4-page" data-system-page>
  <div class="page-head">
    <div>
      <h1 class="page-title">Estado del sistema</h1>
      <p class="page-sub">Todo lo que necesita tu agenda para funcionar, revisado en un solo lugar.</p>
    </div>
    <div class="page-actions">
      <?php if ($problems === 0) : ?><span class="badge badge-ok"><?= icon('check') ?>Todo en orden</span><?php else : ?><span class="badge badge-err"><?= icon('alert') ?><?= (int) $problems ?> por resolver</span><?php endif; ?>
    </div>
  </div>

  <?php if ($installDir) : ?>
    <div class="alert alert-err p4-install-warn" role="alert">
      <?= icon('alert') ?>
      <div><strong>La carpeta <span class="mono">/instalar</span> sigue en tu servidor.</strong> Bórrala ahora: cualquiera podría intentar reinstalar el sistema.
        <ol class="p4-steps">
          <li>Entra al administrador de archivos de tu hosting (o a tu cliente FTP).</li>
          <li>Abre la carpeta donde subiste Agenda Premium.</li>
          <li>Elimina la carpeta <span class="mono">instalar</span> completa y vuelve a cargar esta página.</li>
        </ol>
      </div>
    </div>
  <?php endif; ?>

  <section class="card p4-cron-card is-<?= e($cron['state']) ?>" aria-labelledby="h-cron">
    <div class="card-body p4-cron">
      <div class="p4-light" role="img" aria-label="Estado del cron: <?= e($cron['text']) ?>"><span class="p4-lamp is-<?= e($cron['state']) ?>"></span></div>
      <div class="stack gap-1">
        <h2 id="h-cron" class="serif">Tareas programadas (cron): <?= e($cron['text']) ?></h2>
        <p class="muted"><?php if ($cron['last'] > 0) : ?>Último ciclo: <strong><?= e(A4Controller::local(gmdate('Y-m-d H:i:s', $cron['last']), 'd/m/Y H:i:s')) ?></strong> (<?= e($cron['ago']) ?>).<?php else : ?>Todavía no hay ningún ciclo registrado.<?php endif; ?></p>
        <?php if ($cron['help'] !== '') : ?><p class="p4-help"><?= e($cron['help']) ?> <a class="text-gold" href="<?= e(url('/admin/comunicaciones#cron')) ?>">Ver cómo programarlo</a></p><?php endif; ?>
      </div>
      <div class="p4-cron-actions">
        <form method="post" action="<?= e(url('/admin/sistema/cron')) ?>" data-busy="Ejecutando…">
          <?= csrf_field() ?>
          <button class="btn btn-gold" type="submit"<?= $cronReady ? '' : ' disabled aria-disabled="true" title="El servicio de cron aún no está instalado"' ?>><?= icon('play') ?>Ejecutar cron ahora</button>
        </form>
      </div>
    </div>
  </section>

  <div class="grid cols-2 mt-4">
    <section class="card" aria-labelledby="h-rt">
      <div class="card-head"><h2 id="h-rt" class="serif">Servidor</h2></div>
      <div class="card-body">
        <ul class="p4-checks">
          <?php foreach ($runtime as $r) : ?>
            <li>
              <div class="p4-check-main"><span class="p4-check-label"><?= e($r['label']) ?></span><span class="mono p4-check-value"><?= e($r['value']) ?></span></div>
              <span class="badge <?= e($badge($r['status'])) ?>"><?= e($word($r['status'])) ?></span>
              <?php if ($r['help'] !== '') : ?><p class="hint p4-check-help"><?= e($r['help']) ?></p><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>

    <section class="card" aria-labelledby="h-ext">
      <div class="card-head"><h2 id="h-ext" class="serif">Extensiones de PHP</h2></div>
      <div class="card-body">
        <ul class="p4-checks">
          <?php foreach ($extensions as $x) : $st = $x['ok'] ? 'ok' : ($x['required'] ? 'err' : 'warn'); ?>
            <li>
              <div class="p4-check-main"><span class="p4-check-label mono"><?= e($x['name']) ?></span><span class="muted p4-check-value"><?= e($x['why']) ?></span></div>
              <span class="badge <?= e($badge($st)) ?>"><?= $x['ok'] ? 'Instalada' : ($x['required'] ? 'Falta' : 'Opcional') ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>

    <section class="card" aria-labelledby="h-db">
      <div class="card-head"><h2 id="h-db" class="serif">Base de datos</h2></div>
      <div class="card-body stack">
        <?php if ($db['error'] !== '') : ?><div class="alert alert-err" role="alert"><?= e($db['error']) ?></div><?php else : ?>
          <div class="grid cols-3 p4-stats">
            <div class="stat"><span class="stat-label">Tablas</span><span class="stat-value mono"><?= (int) $db['tables'] ?></span></div>
            <div class="stat"><span class="stat-label">Tamaño aprox.</span><span class="stat-value mono"><?= e(A4Controller::bytes($db['size'])) ?></span></div>
            <div class="stat"><span class="stat-label">Migraciones</span><span class="stat-value mono"><?= (int) $db['applied'] ?></span></div>
          </div>
          <?php if ($db['pending']) : ?>
            <div class="alert alert-warn" role="status">Hay <?= count($db['pending']) ?> migración<?= count($db['pending']) === 1 ? '' : 'es' ?> pendiente<?= count($db['pending']) === 1 ? '' : 's' ?> (se aplican solas en la próxima visita): <span class="mono"><?= e(implode(', ', $db['pending'])) ?></span></div>
          <?php else : ?>
            <p class="text-ok"><?= icon('check') ?> Todas las migraciones están aplicadas.</p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="card" aria-labelledby="h-dir">
      <div class="card-head"><h2 id="h-dir" class="serif">Permisos de carpetas</h2></div>
      <div class="card-body">
        <ul class="p4-checks">
          <?php foreach ($dirs as $d) : $st = $d['writable'] ? 'ok' : 'err'; ?>
            <li>
              <div class="p4-check-main"><span class="p4-check-label"><?= e($d['label']) ?></span><span class="mono p4-check-value">/<?= e($d['path']) ?></span></div>
              <span class="badge <?= e($badge($st)) ?>"><?= $d['writable'] ? 'Escribible' : ($d['exists'] ? 'Sin permiso' : 'No existe') ?></span>
              <?php if (!$d['writable']) : ?><p class="hint p4-check-help">Dale permisos de escritura a esta carpeta (755 o 775) desde el administrador de archivos de tu hosting.</p><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>
  </div>

  <section class="card mt-4" aria-labelledby="h-q">
    <div class="card-head"><h2 id="h-q" class="serif">Colas y sincronizaciones</h2></div>
    <div class="card-body">
      <div class="grid cols-4 p4-queues">
        <?php foreach ($queues as $q) :
            $n = (int) $q['n'];
            $cls = $n < 0 ? 'is-na' : ($q['bad'] && $n > 0 ? 'is-bad' : ($n > 0 ? 'is-busy' : 'is-clear')); ?>
          <a class="stat p4-queue <?= e($cls) ?>" href="<?= e(url($q['link'])) ?>">
            <span class="stat-label"><?= e($q['label']) ?></span>
            <span class="stat-value mono"><?= $n < 0 ? '—' : number_format($n) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <div class="grid cols-2 mt-4">
    <section class="card" aria-labelledby="h-st">
      <div class="card-head"><h2 id="h-st" class="serif">Almacenamiento</h2></div>
      <div class="card-body stack">
        <?php if ($usedPct !== null) : ?>
          <div>
            <div class="row row-between"><span class="muted">Disco del servidor</span><span class="mono"><?= e(A4Controller::bytes($storage['free'])) ?> libres de <?= e(A4Controller::bytes($storage['total'])) ?></span></div>
            <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $usedPct ?>" aria-label="Disco usado"><span <?= vars(['--p' => $usedPct]) ?>></span></div>
          </div>
        <?php endif; ?>
        <table class="table table-sm">
          <tbody>
            <tr><th scope="row">Archivos subidos (<?= number_format($storage['files']) ?>)</th><td class="right mono"><?= e(A4Controller::bytes($storage['uploads'])) ?></td></tr>
            <tr><th scope="row">Respaldos</th><td class="right mono"><?= e(A4Controller::bytes($storage['backups'])) ?></td></tr>
            <tr><th scope="row">Registros</th><td class="right mono"><?= e(A4Controller::bytes($storage['logs'])) ?></td></tr>
            <tr><th scope="row">Caché</th><td class="right mono"><?= e(A4Controller::bytes($storage['cache'])) ?></td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card" aria-labelledby="h-tools">
      <div class="card-head"><h2 id="h-tools" class="serif">Herramientas</h2></div>
      <div class="card-body stack">
        <form class="row row-wrap gap-2" method="post" action="<?= e(url('/admin/sistema/cache')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-outline" type="submit"><?= icon('refresh') ?>Vaciar caché</button>
          <span class="muted">Libera la caché de horarios y páginas. Es seguro y se vuelve a llenar sola.</span>
        </form>
        <div class="divider"></div>
        <form class="stack" method="post" action="<?= e(url('/admin/sistema/correo-prueba')) ?>">
          <?= csrf_field() ?>
          <div class="field">
            <label for="f-sys-to">Enviar correo de prueba a</label>
            <div class="input-group">
              <input class="input" type="email" id="f-sys-to" name="to" required maxlength="190" value="<?= e($adminEmail) ?>">
              <button class="btn btn-outline" type="submit"><?= icon('mail') ?>Enviar</button>
            </div>
          </div>
        </form>
      </div>
    </section>
  </div>

  <section class="card mt-4" aria-labelledby="h-log">
    <div class="card-head row row-between row-wrap">
      <h2 id="h-log" class="serif">Registro de errores</h2>
      <div class="row row-wrap gap-2">
        <span class="muted mono"><?= e(A4Controller::bytes($logSize)) ?></span>
        <a class="btn btn-outline btn-sm<?= $logSize === 0 ? ' is-disabled' : '' ?>" href="<?= e(url('/admin/sistema/registro')) ?>"<?= $logSize === 0 ? ' aria-disabled="true" tabindex="-1"' : '' ?>><?= icon('download') ?>Descargar</a>
        <form method="post" action="<?= e(url('/admin/sistema/registro/vaciar')) ?>" class="inline">
          <?= csrf_field() ?>
          <button class="btn btn-danger btn-sm" type="submit"<?= $logSize === 0 ? ' disabled' : '' ?> data-confirm="¿Vaciar el registro de errores? No se puede deshacer."><?= icon('trash') ?>Vaciar</button>
        </form>
      </div>
    </div>
    <div class="card-body stack">
      <form class="row row-wrap gap-2" method="get" action="<?= e(url('/admin/sistema')) ?>#h-log">
        <label class="sr-only" for="f-logq">Filtrar el registro</label>
        <input class="input" type="search" id="f-logq" name="log" value="<?= e($logFilter) ?>" maxlength="80" placeholder="Filtrar por texto (por ejemplo, SMTP)">
        <button class="btn btn-ghost" type="submit"><?= icon('filter') ?>Filtrar</button>
        <?php if ($logFilter !== '') : ?><a class="btn btn-ghost" href="<?= e(url('/admin/sistema')) ?>#h-log">Limpiar</a><?php endif; ?>
      </form>
      <?php if (!$log) : ?>
        <div class="empty">
          <?= icon('check') ?>
          <p class="empty-title"><?= $logFilter !== '' ? 'Sin coincidencias' : 'Sin errores registrados' ?></p>
          <p class="empty-text"><?= $logFilter !== '' ? 'Prueba con otra palabra.' : 'Cuando algo falle, el detalle técnico aparecerá aquí para que lo puedas compartir con soporte.' ?></p>
        </div>
      <?php else : ?>
        <pre class="p4-log" tabindex="0" aria-label="Últimas líneas del registro de errores"><?php foreach ($log as $line) : ?><span class="p4-log-line"><?= e($line) ?></span>
<?php endforeach; ?></pre>
        <p class="hint">Se muestran las últimas <?= count($log) ?> líneas, la más reciente arriba. Las horas están en UTC.</p>
      <?php endif; ?>
    </div>
  </section>
</div>
