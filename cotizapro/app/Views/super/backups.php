<?php page('barActions', ''); ?>
<div class="cols cols--sidebar">
  <div class="card">
    <div class="card__head"><span class="secnum">01/</span><h2>Respaldos disponibles</h2>
      <span class="badge ml-auto"><?= e(\App\Controllers\Super\PlatformController::human($dirSize)) ?></span></div>
    <div class="card__body card__body--flush tablescroll">
      <?php if (!$rows): ?>
        <p class="muted" style="padding:36px;text-align:center;margin:0">Aún no hay respaldos. Cree el primero a la derecha.</p>
      <?php else: ?>
        <table class="datatable" style="border:0;border-radius:0">
          <caption class="sr-only">Respaldos de la base de datos</caption>
          <thead><tr><th scope="col">Archivo</th><th scope="col">Tipo</th><th scope="col">Fecha</th><th scope="col" class="num">Tamaño</th><th scope="col"></th></tr></thead>
          <tbody>
            <?php foreach ($rows as $b): ?>
              <tr>
                <td class="small"><?= e($b['filename']) ?><?= !$b['exists'] ? ' <span class="badge badge--bad">archivo ausente</span>' : '' ?></td>
                <td><span class="badge"><?= e(ucfirst((string) $b['kind'])) ?></span></td>
                <td class="small"><?= e(fechaHora((string) $b['created_at'])) ?></td>
                <td class="num nowrap"><?= e(\App\Controllers\Super\PlatformController::human((int) $b['size'])) ?></td>
                <td class="nowrap">
                  <?php if ($b['exists']): ?>
                    <a class="btn btn--ghost btn--xs" href="<?= e(url('/super/respaldos/descargar/' . rawurlencode((string) $b['filename']))) ?>">Descargar</a>
                  <?php endif; ?>
                  <button class="btn btn--ghost btn--xs" type="submit" form="delbk<?= e($b['id']) ?>">Eliminar</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="stack">
    <form class="card" method="post" action="<?= e(url('/super/respaldos/crear')) ?>">
      <?= csrf_field() ?>
      <div class="card__head"><span class="secnum">02/</span><h2>Crear respaldo</h2></div>
      <div class="card__body">
        <p class="small muted">Genera un volcado SQL completo (comprimido con gzip) de todas las empresas y sus datos.</p>
        <button class="btn btn--accent btn--block" style="margin-top:14px" type="submit">Respaldar ahora</button>
      </div>
    </form>
    <div class="card">
      <div class="card__head"><h2>Respaldo automático</h2></div>
      <div class="card__body">
        <p class="small muted" style="margin:0">El cron semanal crea un respaldo cada domingo y conserva los 10 más recientes en
          <code>/storage/backups/</code>. Esa carpeta está bloqueada por .htaccess: solo se descarga desde aquí.</p>
      </div>
    </div>
  </div>
</div>
<?php foreach ($rows as $b): ?>
  <form id="delbk<?= e($b['id']) ?>" method="post" action="<?= e(url('/super/respaldos/eliminar')) ?>" class="hide" data-confirm="¿Eliminar este respaldo?">
    <?= csrf_field() ?><input type="hidden" name="name" value="<?= e($b['filename']) ?>">
  </form>
<?php endforeach; ?>
