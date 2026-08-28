<?php
/** @var array $columnas, $cats, $limites; int $total */
use MenuGold\Core\Session;
use MenuGold\Core\View;
View::set('titulo', 'Importar desde Excel');
View::set('subtitulo', 'Carga tu carta completa en un par de minutos');
$previa = Session::pull('_import_previa', null);
$s = (string)($r['simbolo'] ?? 'Q');
?>
<div class="rejilla" style="grid-template-columns:minmax(0,1.4fr) minmax(280px,1fr);align-items:start">
  <div>
    <?php if ($previa): ?>
      <div class="tarjeta-p" style="border-color:<?= $previa['simulacion'] ? 'var(--p-info)' : 'var(--p-exito)' ?>">
        <div class="tarjeta-p__cab">
          <h2 class="tarjeta-p__titulo">
            <?= icon($previa['simulacion'] ? 'eye' : 'check-circle') ?>
            <?= $previa['simulacion'] ? 'Vista previa (no se guardó nada)' : 'Importación completada' ?>
          </h2>
          <div class="acciones">
            <span class="insignia insignia--exito"><?= (int)$previa['nuevos'] ?> nuevos</span>
            <span class="insignia insignia--info"><?= (int)$previa['actualizados'] ?> actualizados</span>
            <?php if ($previa['errores']): ?>
              <span class="insignia insignia--peligro"><?= count($previa['errores']) ?> con problemas</span>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($previa['errores']): ?>
          <div class="aviso aviso--aviso">
            <?= icon('alert') ?>
            <span>
              <strong>Revisa estas filas:</strong>
              <ul style="margin:6px 0 0;padding-left:16px;list-style:disc">
                <?php foreach (array_slice($previa['errores'], 0, 12) as $err): ?>
                  <li><?= e($err) ?></li>
                <?php endforeach; ?>
              </ul>
            </span>
          </div>
        <?php endif; ?>

        <?php if (!empty($previa['previa'])): ?>
          <div class="tabla-caja" style="max-height:340px;overflow-y:auto">
            <table class="tabla" style="min-width:auto">
              <thead><tr><th>Fila</th><th>Platillo</th><th>Categoría</th><th class="num">Precio</th><th>Acción</th></tr></thead>
              <tbody>
                <?php foreach ($previa['previa'] as $p): ?>
                  <tr>
                    <td style="color:var(--p-tenue)"><?= (int)$p['fila'] ?></td>
                    <td><?= e((string)$p['nombre']) ?></td>
                    <td style="color:var(--p-suave)"><?= e((string)$p['categoria']) ?></td>
                    <td class="num"><?= e(money($p['precio'], $s)) ?></td>
                    <td><span class="insignia insignia--<?= $p['accion'] === 'crear' ? 'exito' : 'info' ?>">
                        <?= $p['accion'] === 'crear' ? 'Se crea' : 'Se actualiza' ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <?php if (!$previa['simulacion']): ?>
          <a class="bt bt--oro" href="<?= e(url('panel/productos')) ?>" style="margin-top:14px">
            <?= icon('utensils') ?> Ver mi menú
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('upload') ?> Sube tu archivo</h2></div>
      <form method="post" action="<?= e(url('panel/importar')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label class="subir-foto" style="padding:30px">
          <input type="file" name="archivo" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required id="archivoImport">
          <?= icon('excel') ?>
          <span class="subir-foto__texto">
            <strong>Toca para elegir tu archivo</strong> o arrástralo aquí<br>
            <small>Formatos .xlsx o .csv · hasta 8 MB · máximo 3000 filas</small>
          </span>
        </label>
        <p class="ayuda-p" id="nombreArchivo" style="text-align:center"></p>

        <div class="aviso aviso--info" style="margin-top:14px">
          <?= icon('info') ?>
          <span>Si un platillo del archivo coincide por <strong>código</strong> o por <strong>nombre</strong> con uno
            que ya tienes, se actualiza en lugar de duplicarse.</span>
        </div>

        <div class="acciones" style="margin-top:14px">
          <button class="bt bt--linea" type="submit" name="simular" value="1"><?= icon('eye') ?> Ver antes de importar</button>
          <button class="bt bt--oro crece" type="submit"><?= icon('upload') ?> Importar ahora</button>
        </div>
      </form>
    </div>
  </div>

  <div>
    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('download') ?> Plantilla</h2></div>
      <p class="ayuda-p" style="margin-top:0">
        Descarga la plantilla con ejemplos reales y las instrucciones de cada columna.
        Llénala con tu carta y súbela aquí.
      </p>
      <a class="bt bt--oro bt--bloque" href="<?= e(url('panel/importar/plantilla')) ?>">
        <?= icon('excel') ?> Descargar plantilla
      </a>
      <p class="ayuda-p" style="margin-top:12px">
        Actualmente tienes <strong><?= (int)$total ?></strong> platillo(s)<?php
        if ((int)$limites['max_productos'] > 0) echo ' de ' . (int)$limites['max_productos'] . ' que permite tu plan'; ?>.
      </p>
    </div>

    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('list') ?> Columnas esperadas</h2></div>
      <ol style="margin:0;padding-left:18px;font-size:13.5px;color:var(--p-suave);line-height:1.9;list-style:decimal">
        <?php foreach ($columnas as $c): ?><li><?= e($c) ?></li><?php endforeach; ?>
      </ol>
      <p class="ayuda-p">Deben ir en este orden. Solo <strong>Categoría</strong>, <strong>Nombre</strong> y
        <strong>Precio</strong> son obligatorias.</p>
    </div>
  </div>
</div>

<?php View::start('scripts'); ?>
<script nonce="<?= e(\MenuGold\Core\Security::nonce()) ?>">
var inp = document.getElementById('archivoImport');
if (inp) inp.addEventListener('change', function () {
  var n = document.getElementById('nombreArchivo');
  if (inp.files && inp.files[0]) n.textContent = '📄 ' + inp.files[0].name;
});
</script>
<?php View::stop(); ?>
