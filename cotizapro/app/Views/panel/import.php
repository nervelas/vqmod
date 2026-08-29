<?php $step = $step ?? 1; ?>
<div class="tabs" aria-label="Pasos de la importación">
  <span<?= $step === 1 ? ' aria-selected="true"' : '' ?>>01/ Subir archivo</span>
  <span<?= $step === 2 ? ' aria-selected="true"' : '' ?>>02/ Mapear columnas</span>
  <span<?= $step === 3 ? ' aria-selected="true"' : '' ?>>03/ Resultado</span>
</div>

<?php if ($step === 1): ?>
  <div class="cols cols--sidebar">
    <form class="card" method="post" action="<?= e(url('/panel/importar/analizar')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="card__head"><span class="secnum">01/</span><h2>Suba el archivo del catálogo</h2></div>
      <div class="card__body">
        <p class="lead" style="font-size:.9375rem">Acepta el <strong>CSV que exporta WooCommerce</strong> (Productos → Exportar) y también la plantilla propia de Excel. En el siguiente paso podrá revisar y ajustar el mapeo de columnas.</p>
        <div class="dropzone" style="margin:20px 0">
          <label class="btn btn--ghost" for="file" style="cursor:pointer">Seleccionar archivo CSV o XLSX</label>
          <input class="input sr-only" id="file" name="file" type="file" accept=".csv,.xlsx,text/csv" required>
          <p class="hint" style="margin-top:12px">Hasta 20 MB. El archivo se procesa y se borra del servidor.</p>
        </div>
        <button class="btn btn--accent" type="submit">Analizar archivo <span class="arw" aria-hidden="true">&rarr;</span></button>
      </div>
    </form>
    <div class="card">
      <div class="card__head"><h2>Plantillas</h2></div>
      <div class="card__body stack-sm">
        <a class="btn btn--ghost btn--block" href="<?= e(url('/panel/importar/plantilla')) ?>">Plantilla de productos (XLSX)</a>
        <a class="btn btn--ghost btn--block" href="<?= e(url('/panel/importar/plantilla-clientes')) ?>">Plantilla de clientes (XLSX)</a>
        <p class="hint">Las columnas de WooCommerce (<em>SKU, Name, Regular price, Categories, Attribute 1 name…</em>) se reconocen automáticamente.</p>
      </div>
    </div>
  </div>

<?php elseif ($step === 2): ?>
  <form method="post" action="<?= e(url('/panel/importar/ejecutar')) ?>">
    <?= csrf_field() ?>
    <div class="cols cols--sidebar">
      <div class="stack">
        <div class="card">
          <div class="card__head"><span class="secnum">02/</span><h2>Mapeo de columnas</h2>
            <span class="badge ml-auto"><?= e(number_format($rowCount)) ?> filas</span></div>
          <div class="card__body">
            <p class="small muted" style="margin-bottom:18px">Archivo: <strong><?= e($fileName) ?></strong>. Verifique que cada campo apunte a la columna correcta.</p>
            <div class="row">
              <?php foreach ($fields as $key => $def): ?>
                <div class="field">
                  <label for="map<?= e($key) ?>"><?= e($def['label']) ?><?= $key === 'name' ? ' *' : '' ?></label>
                  <select class="select" id="map<?= e($key) ?>" name="map[<?= e($key) ?>]">
                    <option value="">— No importar —</option>
                    <?php foreach ($headers as $i => $h): ?>
                      <option value="<?= e($i) ?>"<?= (string) ($map[$key] ?? '') === (string) $i ? ' selected' : '' ?>><?= e($h !== '' ? $h : 'Columna ' . ($i + 1)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card__head"><h2>Vista previa (primeras 12 filas)</h2></div>
          <div class="card__body card__body--flush tablescroll">
            <table class="datatable" style="border:0;border-radius:0">
              <caption class="sr-only">Vista previa del archivo</caption>
              <thead><tr><?php foreach ($headers as $h): ?><th scope="col"><?= e(str_limit((string) $h, 22)) ?></th><?php endforeach; ?></tr></thead>
              <tbody>
                <?php foreach ($preview as $row): ?>
                  <tr><?php foreach ($headers as $i => $h): ?><td class="small"><?= e(str_limit((string) ($row[$i] ?? ''), 34)) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><h2>Opciones</h2></div>
        <div class="card__body">
          <label class="check"><input type="checkbox" name="update_existing" value="1" checked><span>Actualizar productos que ya existan (mismo código)</span></label>
          <label class="check"><input type="checkbox" name="default_active" value="1"><span>Publicar los productos importados de inmediato</span></label>
          <p class="hint">Si no marca la segunda opción, los productos entran ocultos y usted los revisa antes de publicarlos.</p>
          <button class="btn btn--accent btn--block" style="margin-top:16px" type="submit">Importar ahora <span class="arw" aria-hidden="true">&rarr;</span></button>
          <a class="btn btn--ghost btn--block" style="margin-top:8px" href="<?= e(url('/panel/importar')) ?>">Cancelar</a>
        </div>
      </div>
    </div>
  </form>

<?php else: ?>
  <div class="cols cols--sidebar">
    <div class="card">
      <div class="card__head"><span class="secnum">03/</span><h2>Resultado de la importación</h2></div>
      <div class="card__body">
        <dl class="kpis" style="margin-bottom:20px">
          <div class="kpi"><dt>Filas leídas</dt><dd><?= e(number_format($result['total'])) ?></dd></div>
          <div class="kpi"><dt>Importadas</dt><dd><?= e(number_format($result['ok'])) ?></dd></div>
          <div class="kpi"><dt>Con error</dt><dd><?= e(number_format($result['err'])) ?></dd></div>
        </dl>
        <?php if ($result['errors']): ?>
          <div class="cota" style="margin-bottom:12px">Detalle de las filas no importadas</div>
          <div class="tablescroll" style="max-height:340px;overflow-y:auto">
            <table class="datatable" style="border:0">
              <caption class="sr-only">Errores de importación</caption>
              <thead><tr><th scope="col">Fila</th><th scope="col">Motivo</th></tr></thead>
              <tbody>
                <?php foreach ($result['errors'] as $er): ?>
                  <tr><td class="num"><?= e((int) $er['fila'] ?: '—') ?></td><td class="small"><?= e($er['motivo']) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="alert alert--ok"><span aria-hidden="true">✓</span><span>Todas las filas se importaron sin errores.</span></div>
        <?php endif; ?>
        <div class="flex" style="gap:10px;margin-top:20px">
          <a class="btn btn--accent" href="<?= e(url('/panel/productos')) ?>">Ver el catálogo <span class="arw" aria-hidden="true">&rarr;</span></a>
          <a class="btn btn--ghost" href="<?= e(url('/panel/importar')) ?>">Importar otro archivo</a>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card__head"><h2>Importaciones recientes</h2></div>
      <div class="card__body">
        <?php foreach ($history as $h): ?>
          <div class="stat-line" style="align-items:flex-start">
            <span style="flex:1"><strong><?= e(str_limit((string) $h['filename'], 26)) ?></strong>
              <br><span class="small muted"><?= e(fechaCorta((string) $h['created_at'])) ?> · <?= e($h['user_name'] ?: '—') ?></span></span>
            <b class="small"><?= e((int) $h['rows_ok']) ?> ok</b>
          </div>
        <?php endforeach; ?>
        <?php if (!$history): ?><p class="small muted" style="margin:0">Sin importaciones previas.</p><?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($step === 1 && $history): ?>
  <div class="card" style="margin-top:20px">
    <div class="card__head"><h2>Importaciones recientes</h2></div>
    <div class="card__body card__body--flush tablescroll">
      <table class="datatable" style="border:0;border-radius:0">
        <caption class="sr-only">Importaciones recientes</caption>
        <thead><tr><th scope="col">Archivo</th><th scope="col">Fecha</th><th scope="col">Usuario</th><th scope="col" class="num">Filas</th><th scope="col" class="num">Importadas</th><th scope="col" class="num">Errores</th></tr></thead>
        <tbody>
          <?php foreach ($history as $h): ?>
            <tr><td><?= e(str_limit((string) $h['filename'], 42)) ?></td>
              <td class="small"><?= e(fechaHora((string) $h['created_at'])) ?></td>
              <td class="small"><?= e($h['user_name'] ?: '—') ?></td>
              <td class="num"><?= e((int) $h['rows_total']) ?></td>
              <td class="num"><?= e((int) $h['rows_ok']) ?></td>
              <td class="num"><?= e((int) $h['rows_error']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
