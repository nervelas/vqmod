<div class="pagina-cab">
  <div><h1>Tareas</h1><p class="pagina-cab__sub">Publique tareas con fecha de entrega y archivos adjuntos</p></div>
  <div class="acciones">
    <?php if ($asignaciones !== []): ?>
      <button type="button" class="btn" data-modal="modal-tarea"
        data-valores='{"id":"","titulo":"","descripcion":"","fecha_entrega":"","puntos":"0"}'><?= icono('mas', 17) ?> Nueva tarea</button>
    <?php endif; ?>
  </div>
</div>

<div class="tabla-env" tabindex="0">
  <table class="tabla">
    <thead><tr><th>Tarea</th><th>Materia</th><th>Grupo</th><th>Entrega</th><th class="cen">Puntos</th><th class="cen">Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($tareas as $t): ?>
      <tr>
        <td><strong><?= e($t['titulo']) ?></strong>
          <div class="xs txt-3"><?= e(recorta($t['descripcion'] ?? '', 90)) ?></div></td>
        <td class="sm txt-2"><?= e($t['materia']) ?></td>
        <td class="sm txt-2"><?= e($t['grupo']) ?></td>
        <td class="sm"><?= e(fecha($t['fecha_entrega'] ?? '') ?: '—') ?></td>
        <td class="cen"><?= e(number_format((float)$t['puntos'], 0)) ?></td>
        <td class="cen">
          <div class="flex" style="justify-content:center;gap:4px">
            <a class="btn btn--fantasma btn--sm" href="<?= e(url('tareas/' . (int)$t['id'] . '/entregas')) ?>" title="Entregas"><?= icono('ver', 16) ?></a>
            <button type="button" class="btn btn--fantasma btn--sm" aria-label="Editar" data-modal="modal-tarea"
              data-valores='<?= e(json_encode([
                'id' => (string)$t['id'], 'titulo' => $t['titulo'], 'descripcion' => $t['descripcion'],
                'fecha_entrega' => (string)($t['fecha_entrega'] ?? ''), 'puntos' => (string)$t['puntos'],
                'asignacion_id' => (string)$t['asignacion_id'],
              ], JSON_UNESCAPED_UNICODE)) ?>'><?= icono('editar', 16) ?></button>
            <form method="post" action="<?= e(url('tareas/' . (int)$t['id'] . '/eliminar')) ?>"
                  data-confirmar="¿Eliminar esta tarea y sus entregas?" style="display:inline">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn--fantasma btn--sm" aria-label="Eliminar"><?= icono('borrar', 16) ?></button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($tareas === []): ?>
      <tr><td colspan="6" class="tabla__vacio"><?= icono('tarea', 40) ?><p>Aún no ha publicado tareas.</p></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal" id="modal-tarea" aria-hidden="true" role="dialog" aria-label="Tarea">
  <div class="modal__fondo" data-cerrar></div>
  <div class="modal__caja">
    <form method="post" enctype="multipart/form-data" action="<?= e(url('tareas')) ?>">
      <?= csrf_field() ?>
      <div class="modal__cab"><h3>Tarea</h3>
        <button type="button" class="btn btn--fantasma btn--sm" data-cerrar>Cerrar</button></div>
      <div class="modal__cuerpo">
        <input type="hidden" name="id" value="">
        <div class="campo">
          <label for="ta-asig">Materia y grupo <span class="oro">*</span></label>
          <select id="ta-asig" name="asignacion_id" required>
            <?php foreach ($asignaciones as $a): ?>
              <option value="<?= (int)$a['id'] ?>"><?= e($a['materia'] . ' · ' . $a['grupo']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="ta-titulo">Título <span class="oro">*</span></label>
          <input type="text" id="ta-titulo" name="titulo" required maxlength="180">
        </div>
        <div class="campo">
          <label for="ta-desc">Instrucciones</label>
          <textarea id="ta-desc" name="descripcion" maxlength="5000"></textarea>
        </div>
        <div class="fila">
          <div class="campo">
            <label for="ta-fecha">Fecha de entrega</label>
            <input type="date" id="ta-fecha" name="fecha_entrega">
          </div>
          <div class="campo">
            <label for="ta-puntos">Puntos</label>
            <input type="number" id="ta-puntos" name="puntos" min="0" max="100" step="0.5" value="0">
          </div>
        </div>
        <div class="campo">
          <label for="ta-adjunto">Archivo adjunto</label>
          <input type="file" id="ta-adjunto" name="adjunto" accept=".pdf,.jpg,.jpeg,.png,.webp,.xlsx,.csv">
        </div>
      </div>
      <div class="modal__pie">
        <button type="button" class="btn btn--linea" data-cerrar>Cancelar</button>
        <button type="submit" class="btn">Publicar tarea</button>
      </div>
    </form>
  </div>
</div>
