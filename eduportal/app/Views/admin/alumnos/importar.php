<div class="pagina-cab">
  <div>
    <h1>Importación masiva</h1>
    <p class="pagina-cab__sub">Cargue un archivo Excel (.xlsx) o CSV con la lista de alumnos.</p>
  </div>
  <div class="acciones">
    <a href="<?= e(url('alumnos/plantilla')) ?>" class="btn btn--linea"><?= icono('descargar', 17) ?> Descargar plantilla</a>
    <a href="<?= e(url('alumnos')) ?>" class="btn btn--linea"><?= icono('atras', 17) ?> Volver</a>
  </div>
</div>

<?php if (!empty($resultado)): ?>
  <div class="tarjeta mb-4">
    <div class="tarjeta__cab"><h2>Resultado de la importación</h2></div>
    <div class="rejilla rejilla--3 mb-4">
      <div class="kpi"><div class="kpi__etq">Creados</div><div class="kpi__valor"><?= (int)$resultado['creados'] ?></div></div>
      <div class="kpi"><div class="kpi__etq">Actualizados</div><div class="kpi__valor"><?= (int)$resultado['actualizados'] ?></div></div>
      <div class="kpi"><div class="kpi__etq">Con error</div><div class="kpi__valor"><?= count($resultado['errores']) ?></div></div>
    </div>
    <?php if ($resultado['errores'] !== []): ?>
      <div class="aviso aviso--warn"><?= icono('aviso', 18) ?><span>Algunas filas no se importaron:</span></div>
      <ul class="sm txt-2">
        <?php foreach (array_slice($resultado['errores'], 0, 40) as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="aviso aviso--ok"><?= icono('check', 18) ?><span>Todas las filas se procesaron correctamente.</span></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="split">
  <div class="tarjeta">
    <div class="tarjeta__cab"><h2>Cargar archivo</h2></div>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('alumnos/importar')) ?>">
      <?= csrf_field() ?>
      <div class="campo">
        <label for="i-seccion">Grado y sección de destino <span class="oro">*</span></label>
        <select id="i-seccion" name="seccion_id" required>
          <option value="">Seleccione…</option>
          <?php foreach ($secciones as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= e($s['etiqueta']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="i-archivo">Archivo Excel o CSV <span class="oro">*</span></label>
        <input type="file" id="i-archivo" name="archivo" required accept=".xlsx,.csv">
      </div>
      <button type="submit" class="btn"><?= icono('subir', 17) ?> Importar alumnos</button>
    </form>
  </div>
  <div class="tarjeta">
    <div class="tarjeta__cab"><h2>Formato esperado</h2></div>
    <p class="sm txt-2">La primera fila debe contener los nombres de las columnas. Se aceptan:</p>
    <ul class="sm txt-2">
      <li><code>codigo</code> — si se omite, se genera automáticamente</li>
      <li><code>nombres</code> y <code>apellidos</code> — obligatorios</li>
      <li><code>dpi</code>, <code>fecha_nacimiento</code> (aaaa-mm-dd), <code>genero</code> (M/F/O)</li>
      <li><code>direccion</code>, <code>beca_pct</code></li>
      <li><code>encargado</code>, <code>parentesco</code>, <code>telefono</code>, <code>email</code></li>
    </ul>
    <p class="sm txt-3">Si el código ya existe, el alumno se actualiza en lugar de duplicarse.</p>
  </div>
</div>
