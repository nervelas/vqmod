<?php
/** Respaldos de la base de datos. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Respaldos');
?>
<?php $view->start('content') ?>
<div class="grid grid-side">
  <div class="card">
    <div class="card-head"><h2>Respaldos guardados</h2><p>Se conservan los 8 más recientes en /storage/backups.</p></div>
    <?php if ($files): ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Archivo</th><th>Fecha</th><th class="num">Tamaño</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($files as $f): ?>
              <tr>
                <td class="tabular" style="font-size:12px"><?= e($f['name']) ?></td>
                <td class="muted"><?= e(mg_date($f['time'])) ?></td>
                <td class="num tabular"><?= e(number_format($f['size'] / 1024, 0)) ?> KB</td>
                <td class="num"><a class="btn btn-ghost btn-sm" href="<?= e(mg_url('/super/respaldo/descargar?archivo=' . rawurlencode($f['name']))) ?>">Descargar</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty"><h3>Sin respaldos</h3><p>Crea el primero con el botón de la derecha.</p></div>
    <?php endif; ?>
  </div>

  <div class="stack">
    <form class="card" method="post" action="<?= e(mg_url('/super/respaldo/crear')) ?>">
      <?= Csrf::field() ?>
      <div class="card-head"><h3>Crear ahora</h3></div>
      <p class="muted" style="font-size:var(--step--1)">Vuelca todas las tablas a un archivo .sql comprimido. En bases grandes puede tardar unos segundos.</p>
      <button class="btn btn-block mt-2" type="submit">Crear respaldo</button>
    </form>

    <div class="card">
      <div class="card-head"><h3>Respaldo automático</h3></div>
      <p class="muted" style="font-size:var(--step--1)">El cron semanal crea uno solo. Configúralo en cPanel:</p>
      <div class="copy-box mt-1">
        <pre>*/10 * * * * curl -s "<?= e(\MenuGold\Core\Url::abs('/cron/run.php')) ?>?token=<?= e(\MenuGold\Core\Config::get('security.cron_token', 'TU-TOKEN')) ?>"</pre>
      </div>
    </div>
  </div>
</div>
<?php $view->stop() ?>
