<?php
/** Importación desde Excel o CSV. */
use MenuGold\Core\Csrf;
$view->extend('layouts/panel');
$view->set('title', 'Importar menú');
?>
<?php $view->start('content') ?>
<div class="grid grid-side">
  <div class="card">
    <div class="card-head"><h2>Sube tu Excel</h2><p>Acepta .xlsx y .csv. Si un platillo ya existe con el mismo nombre, se actualiza.</p></div>

    <?php if ($result): ?>
      <div class="alert alert-success">
        <span>
          Importación terminada: <b><?= (int)$result['created'] ?></b> creados,
          <b><?= (int)$result['updated'] ?></b> actualizados,
          <b><?= (int)$result['skipped'] ?></b> omitidos.
        </span>
      </div>
      <?php foreach ($result['errors'] as $err): ?>
        <div class="alert alert-error"><span><?= e($err) ?></span></div>
      <?php endforeach; ?>
      <p><a class="btn btn-sm" href="<?= e(mg_url('/panel/menu')) ?>">Ver el menú</a></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" action="<?= e(mg_url('/panel/menu/importar')) ?>">
      <?= Csrf::field() ?>
      <div class="field"><label for="file">Archivo</label>
        <input class="input" id="file" name="file" type="file" required
               accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"></div>
      <button class="btn" type="submit">Importar</button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h3>Formato de las columnas</h3></div>
    <ol class="stack" style="gap:.55rem;font-size:var(--step--1);counter-reset:c">
      <?php foreach (array(
        'A · Categoría (se crea si no existe)',
        'B · Nombre del platillo',
        'C · Descripción',
        'D · Precio (solo números)',
        'E · Etiquetas separadas por coma: popular, nuevo, picante, vegano',
        'F · Minutos de preparación',
        'G · Activo (1 o 0)',
      ) as $line): ?>
        <li class="muted"><?= e($line) ?></li>
      <?php endforeach; ?>
    </ol>
    <p class="field-hint mt-2">La primera fila se toma como encabezado y se ignora.</p>
    <a class="btn btn-ghost btn-block mt-2" href="<?= e(mg_url('/panel/menu/plantilla.xlsx')) ?>">Descargar plantilla</a>
  </div>
</div>
<?php $view->stop() ?>
