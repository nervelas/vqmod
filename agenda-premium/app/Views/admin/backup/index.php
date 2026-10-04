<?php
/** Respaldo e importación. */
use App\Controllers\Admin\A4Controller;

$lastLocal = $last !== '' ? A4Controller::local($last) : '';
?>
<div class="page p4-page">
  <div class="page-head">
    <div>
      <h1 class="page-title">Respaldo e importación</h1>
      <p class="page-sub">Cuida tus datos con copias de seguridad y lleva tu configuración de un negocio a otro en minutos.</p>
    </div>
  </div>

  <?php if ($importError !== '' && !$preview) : ?><div class="alert alert-err" role="alert"><?= e($importError) ?></div><?php endif; ?>

  <?php if ($preview) : ?>
  <section class="card card-gold" id="vista-previa" aria-labelledby="h-prev">
    <div class="card-head row row-between row-wrap">
      <h2 id="h-prev" class="serif">Vista previa de la importación</h2>
      <span class="badge badge-gold"><?= e($importName) ?></span>
    </div>
    <div class="card-body stack">
      <?php if ($importError !== '') : ?><div class="alert alert-err" role="alert"><?= e($importError) ?></div><?php endif; ?>
      <p class="muted">Todavía no se cambió nada. Revisa lo que traería el archivo y elige cómo aplicarlo.<?php if (!empty($preview['meta']['exported_at'])) : ?> Exportado el <span class="mono"><?= e($preview['meta']['exported_at']) ?></span>.<?php endif; ?></p>

      <div class="grid cols-3 p4-stats">
        <div class="stat"><span class="stat-label">Ajustes que cambian</span><span class="stat-value mono"><?= count($preview['changed']) ?></span></div>
        <div class="stat"><span class="stat-label">Ajustes nuevos</span><span class="stat-value mono"><?= count($preview['added']) ?></span></div>
        <div class="stat"><span class="stat-label">Ajustes iguales</span><span class="stat-value mono"><?= (int) $preview['same'] ?></span></div>
      </div>

      <?php if ($preview['changed'] || $preview['added']) : ?>
        <div class="table-wrap p4-diff">
          <table class="table table-sm">
            <caption class="sr-only">Ajustes que cambiarían</caption>
            <thead><tr><th scope="col">Ajuste</th><th scope="col">Ahora</th><th scope="col">Con el archivo</th></tr></thead>
            <tbody>
              <?php foreach ($preview['changed'] as $c) : ?><tr><td class="mono"><?= e($c['key']) ?></td><td class="muted"><?= e($c['old']) ?></td><td><?= e($c['new']) ?></td></tr><?php endforeach; ?>
              <?php foreach ($preview['added'] as $c) : ?><tr><td class="mono"><?= e($c['key']) ?> <span class="badge badge-ok">nuevo</span></td><td class="muted">—</td><td><?= e($c['new']) ?></td></tr><?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else : ?>
        <p class="text-ok"><?= icon('check') ?> Los ajustes del archivo son iguales a los actuales.</p>
      <?php endif; ?>

      <?php if ($preview['sections']) : ?>
        <div class="table-wrap">
          <table class="table table-sm">
            <caption class="sr-only">Contenido del archivo por sección</caption>
            <thead><tr><th scope="col">Sección</th><th scope="col" class="right">En el archivo</th><th scope="col" class="right">Ahora</th></tr></thead>
            <tbody><?php foreach ($preview['sections'] as $s) : ?><tr><td><?= e($s['label']) ?></td><td class="right mono"><?= (int) $s['incoming'] ?></td><td class="right mono"><?= (int) $s['current'] ?></td></tr><?php endforeach; ?></tbody>
          </table>
        </div>
      <?php endif; ?>

      <form class="stack" method="post" action="<?= e(url('/admin/respaldo/importar/aplicar')) ?>" data-import-form>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($importToken) ?>">
        <fieldset class="fieldset">
          <legend>¿Cómo quieres aplicarlo?</legend>
          <label class="check"><input type="radio" name="mode" value="merge" checked data-import-mode><span><strong>Combinar</strong>: agrega lo nuevo y actualiza lo que coincide. No borra nada de lo que ya tienes.</span></label>
          <label class="check"><input type="radio" name="mode" value="replace" data-import-mode><span><strong>Reemplazar</strong>: sustituye la configuración actual por la del archivo (eventos, horarios, flujos…). Tus citas y clientes no se tocan.</span></label>
        </fieldset>
        <div class="field p4-confirm" data-import-confirm hidden>
          <div class="alert alert-warn" role="note"><?= icon('alert') ?><span>Reemplazar no se puede deshacer. Crea un respaldo antes de continuar.</span></div>
          <label for="f-confirm">Escribe REEMPLAZAR para confirmar</label>
          <input class="input mono" type="text" id="f-confirm" name="confirm" autocomplete="off" spellcheck="false" maxlength="20" placeholder="REEMPLAZAR">
        </div>
        <div class="form-actions">
          <button class="btn btn-gold" type="submit"><?= icon('upload') ?>Aplicar importación</button>
          <a class="btn btn-ghost" href="<?= e(url('/admin/respaldo')) ?>">Cancelar</a>
        </div>
      </form>
    </div>
  </section>
  <?php endif; ?>

  <section class="card mt-4" aria-labelledby="h-bk">
    <div class="card-head row row-between row-wrap">
      <h2 id="h-bk" class="serif">Respaldos de la base de datos</h2>
      <form method="post" action="<?= e(url('/admin/respaldo/crear')) ?>" data-busy="Creando respaldo…">
        <?= csrf_field() ?>
        <button class="btn btn-gold" type="submit"<?= $ready ? '' : ' disabled aria-disabled="true"' ?>><?= icon('download') ?>Crear respaldo ahora</button>
      </form>
    </div>
    <div class="card-body stack">
      <p class="muted">Incluye tus citas, clientes y configuración. <?= $lastLocal !== '' ? 'El último se creó el <strong>' . e($lastLocal) . '</strong>.' : 'Todavía no has creado ninguno.' ?> Guarda una copia en tu computadora o en la nube: un respaldo que solo vive en el mismo servidor no te protege si el servidor falla.</p>
      <?php if (!$backups) : ?>
        <div class="empty">
          <?= icon('shield') ?>
          <p class="empty-title">Aún no hay respaldos</p>
          <p class="empty-text">Crea el primero con un clic. Tarda unos segundos.</p>
        </div>
      <?php else : ?>
        <div class="table-wrap">
          <table class="table table-sm">
            <thead><tr><th scope="col">Archivo</th><th scope="col">Creado</th><th scope="col" class="right">Tamaño</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
            <tbody>
              <?php foreach ($backups as $b) : ?>
                <tr>
                  <td class="mono"><?= e($b['name']) ?></td>
                  <td class="nowrap"><?= e($b['at'] !== '' ? A4Controller::local($b['at']) : '—') ?></td>
                  <td class="right mono nowrap"><?= e(A4Controller::bytes($b['size'])) ?></td>
                  <td class="right nowrap">
                    <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/respaldo/descargar/' . rawurlencode($b['name']))) ?>"><?= icon('download') ?>Descargar</a>
                    <form class="inline" method="post" action="<?= e(url('/admin/respaldo/eliminar/' . rawurlencode($b['name']))) ?>">
                      <?= csrf_field() ?>
                      <button class="btn btn-danger btn-sm" type="submit" data-confirm="¿Eliminar el respaldo <?= e($b['name']) ?>? No se puede deshacer." aria-label="Eliminar <?= e($b['name']) ?>"><?= icon('trash') ?></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      <form class="row row-wrap gap-2 p4-keep" method="post" action="<?= e(url('/admin/respaldo/conservar')) ?>">
        <?= csrf_field() ?>
        <div class="field">
          <label for="f-keep">Conservar los últimos</label>
          <input class="input" type="number" id="f-keep" name="keep" min="1" max="60" value="<?= (int) $keep ?>">
        </div>
        <button class="btn btn-outline" type="submit">Aplicar</button>
        <span class="muted">Los respaldos más antiguos se eliminan solos al crear uno nuevo.</span>
      </form>
    </div>
  </section>

  <div class="grid cols-2 mt-4">
    <section class="card" aria-labelledby="h-exp">
      <div class="card-head"><h2 id="h-exp" class="serif">Exportar configuración</h2></div>
      <div class="card-body stack">
        <p class="muted">Descarga un archivo JSON con tu marca, horarios, feriados, eventos, preguntas, flujos, formularios, paquetes y cupones. <strong>No incluye contraseñas ni claves</strong> ni tus clientes o citas. Ideal para instalar rápido el mismo esquema en otro negocio.</p>
        <a class="btn btn-gold" href="<?= e(url('/admin/respaldo/exportar')) ?>"<?= $configReady ? '' : ' aria-disabled="true"' ?>><?= icon('download') ?>Exportar configuración</a>
      </div>
    </section>

    <section class="card" aria-labelledby="h-imp">
      <div class="card-head"><h2 id="h-imp" class="serif">Importar configuración</h2></div>
      <form class="card-body stack" method="post" action="<?= e(url('/admin/respaldo/importar')) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="f-file">Archivo de configuración (.json)</label>
          <input class="input" type="file" id="f-file" name="file" accept="application/json,.json" required aria-describedby="imp-hint">
          <p class="hint" id="imp-hint">Primero verás una vista previa de lo que cambiaría; nada se aplica hasta que lo confirmes. Máximo 5 MB.</p>
        </div>
        <div class="form-actions"><button class="btn btn-outline" type="submit"<?= $configReady ? '' : ' disabled aria-disabled="true"' ?>><?= icon('eye') ?>Ver vista previa</button></div>
      </form>
    </section>
  </div>
</div>
