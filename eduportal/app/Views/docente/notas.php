<?php
$alumnos = $datos['alumnos'];
$actividades = $datos['actividades'];
$notas = $datos['notas'];
$resumen = $datos['resumen'];
$cerrado = (int)$periodo['cerrado'] === 1;
$pondZona = App\Models\Evaluacion::pondZona();
$pondExamen = App\Models\Evaluacion::pondExamen();
?>
<div class="pagina-cab">
  <div>
    <h1><?= e($asignacion['materia']) ?></h1>
    <p class="pagina-cab__sub"><?= e($asignacion['grupo']) ?> · <?= count($alumnos) ?> alumnos ·
      zona <?= e(number_format($ponderacion['zona'], 0)) ?>/<?= e(number_format($pondZona, 0)) ?> ·
      examen <?= e(number_format($ponderacion['examen'], 0)) ?>/<?= e(number_format($pondExamen, 0)) ?></p>
  </div>
  <div class="acciones">
    <form method="get" class="flex" style="gap:8px">
      <select name="periodo" data-auto-envio aria-label="Periodo">
        <?php foreach ($periodos as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)$periodo['id'] ? 'selected' : '' ?>>
            <?= e($p['nombre']) ?><?= (int)$p['cerrado'] === 1 ? ' (cerrado)' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php if (!$cerrado): ?>
      <button type="button" class="btn" data-modal="modal-actividad"
        data-valores='{"id":"","nombre":"","tipo":"zona","ponderacion":"10","fecha":""}'><?= icono('mas', 17) ?> Actividad</button>
    <?php endif; ?>
    <a href="<?= e(url('notas')) ?>" class="btn btn--linea"><?= icono('atras', 17) ?> Volver</a>
  </div>
</div>

<?php if ($cerrado): ?>
  <div class="aviso aviso--warn"><?= icono('escudo', 18) ?><span>Este periodo está cerrado: las notas son de solo lectura.</span></div>
<?php endif; ?>

<?php if ($actividades === []): ?>
  <div class="tarjeta vacio">
    <?= icono('notas', 44) ?>
    <h3>Aún no hay actividades</h3>
    <p class="txt-2">Cree las actividades de zona y la evaluación del periodo para capturar los punteos.</p>
    <?php if (!$cerrado): ?>
      <button type="button" class="btn mt-3" data-modal="modal-actividad"
        data-valores='{"id":"","nombre":"","tipo":"zona","ponderacion":"10","fecha":""}'>Crear primera actividad</button>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="flex flex--sep mb-3">
    <span class="sm txt-3" data-notas-estado>Los cambios se guardan automáticamente.</span>
    <div class="flex" style="gap:6px">
      <?php foreach ($actividades as $a): ?>
        <?php if (!$cerrado): ?>
          <button type="button" class="btn btn--fantasma btn--sm" aria-label="Editar" data-modal="modal-actividad"
            data-valores='<?= e(json_encode([
              'id' => (string)$a['id'], 'nombre' => $a['nombre'], 'tipo' => $a['tipo'],
              'ponderacion' => (string)$a['ponderacion'], 'fecha' => (string)($a['fecha'] ?? ''),
            ], JSON_UNESCAPED_UNICODE)) ?>' title="Editar <?= e($a['nombre']) ?>"><?= icono('editar', 14) ?></button>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="notas-env" tabindex="0">
    <table class="notas" data-notas data-columnas="<?= count($actividades) ?>"
           data-minima="<?= e((string)App\Models\Evaluacion::notaMinima()) ?>">
      <thead>
        <tr>
          <th class="col-alumno">Alumno</th>
          <?php foreach ($actividades as $a): ?>
            <th title="<?= e($a['nombre']) ?>">
              <?= e(recorta((string)$a['nombre'], 16)) ?>
              <span class="p"><?= e(number_format((float)$a['ponderacion'], 0)) ?> pts · <?= e($a['tipo'] === 'examen' ? 'Ex' : 'Zona') ?></span>
            </th>
          <?php endforeach; ?>
          <th>Zona</th><th>Examen</th><th>Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($alumnos as $al): $r = $resumen[(int)$al['id']] ?? null; ?>
          <tr>
            <td class="col-alumno"><?= e($al['nombre_completo']) ?>
              <div class="xs txt-3"><?= e($al['codigo']) ?></div></td>
            <?php foreach ($actividades as $a): ?>
              <td>
                <input class="nota" type="text" inputmode="decimal"
                       value="<?= e($notas[(int)$al['id']][(int)$a['id']] ?? '') ?>"
                       data-actividad="<?= (int)$a['id'] ?>" data-alumno="<?= (int)$al['id'] ?>"
                       data-max="<?= e((string)$a['ponderacion']) ?>"
                       aria-label="<?= e($a['nombre'] . ' de ' . $al['nombre_completo']) ?>"
                       <?= $cerrado ? 'readonly' : '' ?>>
              </td>
            <?php endforeach; ?>
            <td class="total" data-total="zona"><?= e(number_format((float)($r['zona'] ?? 0), 2)) ?></td>
            <td class="total" data-total="examen"><?= e(number_format((float)($r['examen'] ?? 0), 2)) ?></td>
            <td class="total <?= e(nota_clase((float)($r['total'] ?? 0))) ?>" data-total="total">
              <?= e(number_format((float)($r['total'] ?? 0), 2)) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($alumnos === []): ?>
          <tr><td colspan="<?= count($actividades) + 4 ?>" class="tabla__vacio">El grupo no tiene alumnos inscritos.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if (!$cerrado && $alumnos !== []): ?>
    <div class="tarjeta mt-5">
      <div class="tarjeta__cab"><h2>Conducta y comentarios</h2></div>
      <form method="post" action="<?= e(url('notas/conducta')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="asignacion_id" value="<?= (int)$asignacion['id'] ?>">
        <input type="hidden" name="periodo_id" value="<?= (int)$periodo['id'] ?>">
        <div class="tabla-env" tabindex="0">
          <table class="tabla">
            <thead><tr><th>Alumno</th><th style="width:130px">Conducta</th><th>Comentario</th></tr></thead>
            <tbody>
            <?php foreach ($alumnos as $al): $r = $resumen[(int)$al['id']] ?? null; ?>
              <tr>
                <td class="sm"><?= e($al['nombre_completo']) ?></td>
                <td><input type="number" name="conducta[<?= (int)$al['id'] ?>]" min="0" max="100" step="0.01"
                           value="<?= e($r['conducta'] ?? '') ?>" aria-label="Conducta de <?= e($al['nombre_completo']) ?>"></td>
                <td><input type="text" name="comentario[<?= (int)$al['id'] ?>]" maxlength="255"
                           value="<?= e($r['comentario'] ?? '') ?>" aria-label="Comentario para <?= e($al['nombre_completo']) ?>"></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button type="submit" class="btn mt-4"><?= icono('check', 17) ?> Guardar conducta</button>
      </form>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if (!$cerrado): ?>
<div class="modal" id="modal-actividad" aria-hidden="true" role="dialog" aria-label="Actividad evaluada">
  <div class="modal__fondo" data-cerrar></div>
  <div class="modal__caja">
    <form method="post" action="<?= e(url('notas/actividad')) ?>">
      <?= csrf_field() ?>
      <div class="modal__cab"><h3>Actividad evaluada</h3>
        <button type="button" class="btn btn--fantasma btn--sm" data-cerrar>Cerrar</button></div>
      <div class="modal__cuerpo">
        <input type="hidden" name="id" value="">
        <input type="hidden" name="asignacion_id" value="<?= (int)$asignacion['id'] ?>">
        <input type="hidden" name="periodo_id" value="<?= (int)$periodo['id'] ?>">
        <div class="campo">
          <label for="ac-nombre">Nombre <span class="oro">*</span></label>
          <input type="text" id="ac-nombre" name="nombre" required maxlength="120" placeholder="Tarea 1, Laboratorio, Examen final…">
        </div>
        <div class="fila fila--3">
          <div class="campo">
            <label for="ac-tipo">Tipo <span class="oro">*</span></label>
            <select id="ac-tipo" name="tipo" required>
              <option value="zona">Zona</option>
              <option value="examen">Examen</option>
            </select>
          </div>
          <div class="campo">
            <label for="ac-pond">Puntos <span class="oro">*</span></label>
            <input type="number" id="ac-pond" name="ponderacion" required min="0.5" max="100" step="0.5" value="10">
          </div>
          <div class="campo">
            <label for="ac-fecha">Fecha</label>
            <input type="date" id="ac-fecha" name="fecha">
          </div>
        </div>
        <p class="ayuda">La suma de puntos de zona no puede pasar de <?= e(number_format($pondZona, 0)) ?>
          ni la de examen de <?= e(number_format($pondExamen, 0)) ?>.</p>
      </div>
      <div class="modal__pie">
        <button type="button" class="btn btn--linea" data-cerrar>Cancelar</button>
        <button type="submit" class="btn">Guardar actividad</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
