<?php
/** @var array|null $producto; array $cats, $grupos, $gruposSel */
use MenuGold\Core\Security;
use MenuGold\Core\View;
use MenuGold\Models\Product;

$nuevo = $producto === null;
View::set('titulo', $nuevo ? 'Nuevo platillo' : 'Editar platillo');
View::set('subtitulo', $nuevo ? 'Aparecerá en tu menú al guardarlo' : e((string)$producto['nombre']));
$s = (string)($r['simbolo'] ?? 'Q');
$val = static fn(string $k, $d = '') => $producto !== null ? ($producto[$k] ?? $d) : old($k, (string)$d);
$etq = $producto ? Product::etiquetasArray($producto) : [];
$alg = $producto ? Product::alergenosArray($producto) : [];
$dias = $producto ? array_filter(array_map('trim', explode(',', (string)$producto['dias']))) : [];
$extras = $producto ? jdec($producto['imagenes'] ?? []) : [];
$diasNombre = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
?>
<form method="post" action="<?= e(url('panel/productos/guardar')) ?>" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)($producto['id'] ?? 0) ?>">

  <div class="rejilla" style="grid-template-columns:minmax(0,1.6fr) minmax(280px,1fr);align-items:start">
    <!-- ================= Columna principal ================= -->
    <div>
      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('utensils') ?> Lo básico</h2></div>

        <div class="campo-p">
          <label for="nombre">Nombre del platillo *</label>
          <input type="text" id="nombre" name="nombre" required maxlength="160" autofocus
                 value="<?= e($val('nombre')) ?>" placeholder="Ej. Ceviche del chef">
        </div>

        <div class="campo-p">
          <label for="descripcion">Descripción</label>
          <textarea id="descripcion" name="descripcion" maxlength="900"
                    placeholder="Describe los ingredientes de forma apetitosa. Ej. Corvina fresca, leche de tigre de chile cobanero y cilantro criollo."><?= e($val('descripcion')) ?></textarea>
          <p class="ayuda-p">Una buena descripción vende. Menciona el ingrediente estrella y la técnica.</p>
        </div>

        <div class="fila-campos">
          <div class="campo-p">
            <label for="category_id">Categoría</label>
            <select id="category_id" name="category_id">
              <option value="">Sin categoría</option>
              <?php foreach ($cats as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)$val('category_id') === (int)$c['id'] ? 'selected' : '' ?>>
                  <?= e((string)$c['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo-p">
            <label for="estacion">Estación de preparación</label>
            <select id="estacion" name="estacion">
              <?php foreach (['cocina' => 'Cocina', 'bar' => 'Bar', 'postres' => 'Postres'] as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= (string)$val('estacion', 'cocina') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="ayuda-p">Define en qué columna aparece en la pantalla de cocina.</p>
          </div>
        </div>

        <div class="fila-campos">
          <div class="campo-p">
            <label for="precio">Precio *</label>
            <div class="grupo-prefijo">
              <span><?= e($s) ?></span>
              <input type="number" id="precio" name="precio" step="0.01" min="0" max="999999" required
                     value="<?= e((string)$val('precio', '0.00')) ?>" inputmode="decimal">
            </div>
          </div>
          <div class="campo-p">
            <label for="precio_promo">Precio de promoción</label>
            <div class="grupo-prefijo">
              <span><?= e($s) ?></span>
              <input type="number" id="precio_promo" name="precio_promo" step="0.01" min="0"
                     value="<?= e((string)($producto['precio_promo'] ?? '')) ?>" inputmode="decimal">
            </div>
            <p class="ayuda-p">Déjalo vacío si no hay promoción.</p>
          </div>
          <div class="campo-p">
            <label for="costo">Costo (opcional)</label>
            <div class="grupo-prefijo">
              <span><?= e($s) ?></span>
              <input type="number" id="costo" name="costo" step="0.01" min="0"
                     value="<?= e((string)($producto['costo'] ?? '')) ?>" inputmode="decimal">
            </div>
            <p class="ayuda-p">Solo para tus reportes. Nunca se muestra al cliente.</p>
          </div>
        </div>

        <div class="fila-campos">
          <div class="campo-p">
            <label for="tiempo_prep">Tiempo de preparación (minutos)</label>
            <input type="number" id="tiempo_prep" name="tiempo_prep" min="0" max="240" inputmode="numeric"
                   value="<?= e((string)$val('tiempo_prep', '15')) ?>">
          </div>
          <div class="campo-p">
            <label for="calorias">Calorías (opcional)</label>
            <input type="number" id="calorias" name="calorias" min="0" max="9999" inputmode="numeric"
                   value="<?= e((string)($producto['calorias'] ?? '')) ?>">
          </div>
          <div class="campo-p">
            <label for="sku">Código interno</label>
            <input type="text" id="sku" name="sku" maxlength="40" value="<?= e($val('sku')) ?>">
          </div>
        </div>
      </div>

      <!-- ================= Etiquetas y alérgenos ================= -->
      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('tag') ?> Etiquetas y alérgenos</h2></div>
        <label class="etiqueta-campo">Etiquetas visibles en el menú</label>
        <div class="pastillas-sel" style="margin-bottom:16px">
          <?php foreach (Product::ETIQUETAS as $k => $v): ?>
            <label class="pastilla-sel">
              <input type="checkbox" name="etiquetas[]" value="<?= e($k) ?>" <?= in_array($k, $etq, true) ? 'checked' : '' ?>>
              <?= icon($v[1]) ?><?= e($v[0]) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <label class="etiqueta-campo">Alérgenos</label>
        <div class="pastillas-sel">
          <?php foreach (Product::ALERGENOS as $a): ?>
            <label class="pastilla-sel">
              <input type="checkbox" name="alergenos[]" value="<?= e($a) ?>" <?= in_array($a, $alg, true) ? 'checked' : '' ?>>
              <?= e(ucfirst($a)) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ================= Modificadores ================= -->
      <div class="tarjeta-p">
        <div class="tarjeta-p__cab">
          <h2 class="tarjeta-p__titulo"><?= icon('grid') ?> Opciones y extras</h2>
          <a class="bt bt--sm bt--suave" href="<?= e(url('panel/modificadores')) ?>" target="_blank">Administrar grupos</a>
        </div>
        <?php if (!$grupos): ?>
          <p style="color:var(--p-tenue);margin:0">
            Aún no tienes grupos de modificadores (tamaño, término, extras…).
            <a href="<?= e(url('panel/modificadores')) ?>" style="color:var(--p-oro);font-weight:600">Crea el primero</a>.
          </p>
        <?php else: ?>
          <p class="ayuda-p" style="margin-top:0">Marca los grupos que el cliente podrá elegir al pedir este platillo.</p>
          <div class="rejilla rejilla--3">
            <?php foreach ($grupos as $g): ?>
              <label class="casilla" style="align-items:flex-start;padding:11px;border:1px solid var(--p-borde);border-radius:11px">
                <input type="checkbox" name="grupos[]" value="<?= (int)$g['id'] ?>" <?= in_array((int)$g['id'], $gruposSel, true) ? 'checked' : '' ?>>
                <span>
                  <strong style="display:block"><?= e((string)$g['nombre']) ?></strong>
                  <small style="color:var(--p-tenue)">
                    <?= $g['tipo'] === 'unico' ? 'Elige una' : 'Hasta ' . (int)$g['max_sel'] ?>
                    <?= (int)$g['obligatorio'] === 1 ? ' · obligatorio' : '' ?>
                    · <?= count($g['opciones']) ?> opción(es)
                  </small>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- ================= Traducción ================= -->
      <details class="tarjeta-p">
        <summary style="cursor:pointer;font-weight:700;font-size:15px;display:flex;align-items:center;gap:8px">
          <?= icon('globe') ?> Versión en inglés (opcional)
        </summary>
        <div style="margin-top:14px">
          <div class="campo-p">
            <label for="nombre_en">Nombre en inglés</label>
            <input type="text" id="nombre_en" name="nombre_en" maxlength="160" value="<?= e($val('nombre_en')) ?>">
          </div>
          <div class="campo-p">
            <label for="descripcion_en">Descripción en inglés</label>
            <textarea id="descripcion_en" name="descripcion_en" maxlength="900"><?= e($val('descripcion_en')) ?></textarea>
          </div>
        </div>
      </details>
    </div>

    <!-- ================= Columna lateral ================= -->
    <div>
      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('image') ?> Foto principal</h2></div>
        <div class="previa-foto" id="previaFoto" data-previa
             style="<?= empty($producto['imagen']) ? 'display:none' : '' ?>;margin-bottom:12px">
          <img src="<?= e(!empty($producto['imagen']) ? uploaded((string)$producto['imagen']) : '') ?>" alt="Vista previa">
          <button class="previa-foto__quitar" type="button"
                  data-quitar-previa="#previaFoto" data-campo="#campoImagen" data-marca="#quitarImagen"
                  aria-label="Quitar foto"><?= icon('x') ?></button>
        </div>
        <input type="hidden" name="quitar_imagen" id="quitarImagen" value="0">
        <label class="subir-foto">
          <input type="file" id="campoImagen" name="imagen" accept="image/jpeg,image/png,image/webp"
                 data-previsualizar="#previaFoto">
          <?= icon('upload') ?>
          <span class="subir-foto__texto"><strong>Toca para subir</strong> o arrastra la foto aquí<br>
          <small>JPG, PNG o WEBP · hasta 12 MB · se optimiza sola</small></span>
        </label>
        <p class="ayuda-p">Usa fotos horizontales bien iluminadas. Se recortan y comprimen automáticamente.</p>
      </div>

      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('layers') ?> Fotos adicionales</h2></div>
        <?php if ($extras): ?>
          <div class="rejilla rejilla--3" style="gap:8px;margin-bottom:12px">
            <?php foreach ($extras as $img): ?>
              <div class="previa-foto">
                <img src="<?= e(uploaded((string)$img)) ?>" alt="">
                <button class="previa-foto__quitar" type="button" data-borrar-extra="<?= e((string)$img) ?>"
                        aria-label="Quitar"><?= icon('x') ?></button>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <label class="subir-foto">
          <input type="file" name="imagenes[]" accept="image/jpeg,image/png,image/webp" multiple>
          <?= icon('plus') ?><span class="subir-foto__texto">Agregar más fotos (hasta 6)</span>
        </label>
      </div>

      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('eye') ?> Disponibilidad</h2></div>
        <label class="interruptor" style="margin-bottom:8px">
          <input type="checkbox" name="activo" value="1" <?= (int)$val('activo', 1) === 1 ? 'checked' : '' ?>>
          <span class="interruptor__pista"></span>
          <span class="interruptor__texto">Visible en el menú</span>
        </label><br>
        <label class="interruptor" style="margin-bottom:8px">
          <input type="checkbox" name="agotado" value="1" <?= (int)$val('agotado', 0) === 1 ? 'checked' : '' ?>>
          <span class="interruptor__pista"></span>
          <span class="interruptor__texto">Agotado por hoy</span>
        </label><br>
        <label class="interruptor" style="margin-bottom:14px">
          <input type="checkbox" name="destacado" value="1" <?= (int)$val('destacado', 0) === 1 ? 'checked' : '' ?>>
          <span class="interruptor__pista"></span>
          <span class="interruptor__texto">Recomendado de la casa</span>
        </label>

        <label class="etiqueta-campo">Disponible solo en horario</label>
        <div class="fila-campos" style="margin-bottom:10px">
          <div class="campo-p" style="margin:0">
            <input type="time" name="hora_inicio" value="<?= e(substr((string)$val('hora_inicio', ''), 0, 5)) ?>" aria-label="Desde">
          </div>
          <div class="campo-p" style="margin:0">
            <input type="time" name="hora_fin" value="<?= e(substr((string)$val('hora_fin', ''), 0, 5)) ?>" aria-label="Hasta">
          </div>
        </div>
        <p class="ayuda-p">Ej. desayunos solo hasta las 11:00. Vacío = todo el día.</p>

        <label class="etiqueta-campo">Días disponibles</label>
        <div class="pastillas-sel">
          <?php foreach ($diasNombre as $i => $d): ?>
            <label class="pastilla-sel">
              <input type="checkbox" name="dias[]" value="<?= $i ?>" <?= in_array((string)$i, $dias, true) ? 'checked' : '' ?>>
              <?= e($d) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="ayuda-p">Sin marcar = disponible todos los días.</p>
      </div>
    </div>
  </div>

  <div class="tarjeta-p" style="position:sticky;bottom:0;z-index:10;display:flex;gap:9px;justify-content:flex-end;flex-wrap:wrap">
    <a class="bt bt--linea" href="<?= e(url('panel/productos')) ?>">Cancelar</a>
    <?php if (!$nuevo): ?>
      <button class="bt bt--peligro" type="button" id="btnBorrarProd"><?= icon('trash') ?> Eliminar</button>
    <?php endif; ?>
    <button class="bt bt--oro" type="submit"><?= icon('save') ?> <?= $nuevo ? 'Crear platillo' : 'Guardar cambios' ?></button>
  </div>
</form>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
  var M = window.MGPanel;
  var quitar = document.querySelector('[data-quitar-previa]');
  if (quitar) quitar.addEventListener('click', function () {
    document.getElementById('quitarImagen').value = '1';
  });

  document.addEventListener('click', function (ev) {
    var x = ev.target.closest('[data-borrar-extra]');
    if (x) {
      ev.preventDefault();
      M.pedir('panel/productos/imagen-borrar', {
        id: <?= (int)($producto['id'] ?? 0) ?>, ruta: x.dataset.borrarExtra
      }).then(function (res) {
        if (res.ok) { x.closest('.previa-foto').remove(); M.avisar(res.mensaje, 'ok'); }
        else M.avisar(res.error, 'error');
      });
    }
  });

  var b = document.getElementById('btnBorrarProd');
  if (b) b.addEventListener('click', function () {
    M.confirmar('Se eliminará este platillo de tu menú. Esta acción no se puede deshacer.',
                'Eliminar platillo', 'Sí, eliminar').then(function (ok) {
      if (!ok) return;
      M.pedir('panel/productos/borrar', { id: <?= (int)($producto['id'] ?? 0) ?> }).then(function (res) {
        if (res.ok) location.href = M.url('panel/productos');
        else M.avisar(res.error, 'error');
      });
    });
  });
})();
</script>
<?php View::stop(); ?>
<?php unset($_SESSION['_old']); ?>
