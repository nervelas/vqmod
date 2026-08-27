<div class="pagina-cab">
  <div><h1>Tareas</h1><p class="pagina-cab__sub"><?= e(App\Models\Alumno::nombre($alumno)) ?> · <?= count($tareas) ?> tareas</p></div>
</div>

<?php if ($tareas === []): ?>
  <div class="tarjeta vacio"><?= icono('tarea', 44) ?><p>No hay tareas asignadas por el momento.</p></div>
<?php else: ?>
  <div class="col">
    <?php foreach ($tareas as $t): ?>
      <?php $vencida = !empty($t['fecha_entrega']) && $t['fecha_entrega'] < hoy() && empty($t['entrega_estado']); ?>
      <article class="tarjeta">
        <div class="flex flex--sep flex--envuelve mb-2">
          <div style="min-width:0">
            <h3 class="mb-0"><?= e($t['titulo']) ?></h3>
            <p class="sm txt-2 mb-0"><?= e($t['materia']) ?> · <?= e($t['docente'] ?? '') ?></p>
          </div>
          <div class="flex" style="gap:8px">
            <span class="badge badge--<?= $vencida ? 'bad' : ($t['entrega_estado'] ? estado_badge((string)$t['entrega_estado']) : 'warn') ?>">
              <?= $t['entrega_estado'] ? e(ucfirst((string)$t['entrega_estado'])) : ($vencida ? 'Vencida' : 'Pendiente') ?></span>
            <span class="sm txt-3">Entrega <?= e(fecha($t['fecha_entrega'] ?? '') ?: 'sin fecha') ?></span>
          </div>
        </div>
        <?php if (!empty($t['descripcion'])): ?>
          <p class="txt-2"><?= nl2br(e($t['descripcion'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($t['adjunto'])): ?>
          <p><a class="btn btn--linea btn--sm" target="_blank" rel="noopener"
             href="<?= e(archivo_url($t['adjunto'])) ?>"><?= icono('descargar', 15) ?> Material de la tarea</a></p>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="mt-3"
              action="<?= e(url('portal/tarea/' . (int)$t['id'] . '/entregar')) ?>">
          <?= csrf_field() ?>
          <div class="fila">
            <div class="campo">
              <label for="ent-arch-<?= (int)$t['id'] ?>">Archivo de la entrega</label>
              <input type="file" id="ent-arch-<?= (int)$t['id'] ?>" name="archivo"
                     accept=".pdf,.jpg,.jpeg,.png,.webp,.xlsx,.csv">
            </div>
            <div class="campo">
              <label for="ent-com-<?= (int)$t['id'] ?>">Comentario</label>
              <input type="text" id="ent-com-<?= (int)$t['id'] ?>" name="comentario" maxlength="255">
            </div>
          </div>
          <button type="submit" class="btn btn--sm"><?= icono('check', 15) ?>
            <?= $t['entrega_estado'] ? 'Actualizar entrega' : 'Confirmar entrega' ?></button>
          <?php if (!empty($t['entregado_en'])): ?>
            <span class="sm txt-3" style="margin-left:10px">Entregada el <?= e(fecha_hora((string)$t['entregado_en'])) ?></span>
          <?php endif; ?>
        </form>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
