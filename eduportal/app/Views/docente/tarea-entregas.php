<div class="pagina-cab">
  <div><h1><?= e($tarea['titulo']) ?></h1>
    <p class="pagina-cab__sub"><?= e($tarea['materia']) ?> · <?= e($tarea['grupo']) ?> ·
      entrega <?= e(fecha($tarea['fecha_entrega'] ?? '') ?: 'sin fecha') ?></p></div>
  <div class="acciones"><a href="<?= e(url('tareas')) ?>" class="btn btn--linea"><?= icono('atras', 17) ?> Volver</a></div>
</div>

<?php if (!empty($tarea['descripcion'])): ?>
  <div class="tarjeta mb-4"><p class="mb-0"><?= nl2br(e($tarea['descripcion'])) ?></p></div>
<?php endif; ?>

<div class="tabla-env" tabindex="0">
  <table class="tabla">
    <thead><tr><th>Alumno</th><th class="cen">Estado</th><th>Entregado</th><th>Comentario</th><th class="cen">Archivo</th><th class="cen"></th></tr></thead>
    <tbody>
    <?php foreach ($alumnos as $al): $en = $entregas[(int)$al['id']] ?? null; ?>
      <tr>
        <td><?= e($al['nombre_completo']) ?><div class="xs txt-3"><?= e($al['codigo']) ?></div></td>
        <td class="cen">
          <span class="badge badge--<?= $en ? e(estado_badge((string)$en['estado'])) : 'mute' ?>">
            <?= $en ? e(ucfirst((string)$en['estado'])) : 'Pendiente' ?></span>
        </td>
        <td class="sm"><?= $en ? e(fecha_hora((string)$en['entregado_en'])) : '—' ?></td>
        <td class="sm txt-2"><?= e($en['comentario'] ?? '') ?></td>
        <td class="cen">
          <?php if ($en && !empty($en['archivo'])): ?>
            <a class="btn btn--fantasma btn--sm" target="_blank" rel="noopener" href="<?= e(archivo_url($en['archivo'])) ?>" aria-label="Descargar entrega"><?= icono('descargar', 15) ?></a>
          <?php else: ?><span class="txt-3">—</span><?php endif; ?>
        </td>
        <td class="cen">
          <?php if ($en && $en['estado'] !== 'revisado'): ?>
            <form method="post" action="<?= e(url('entrega/' . (int)$en['id'] . '/revisar')) ?>">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn--sm"><?= icono('check', 15) ?> Revisar</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($alumnos === []): ?><tr><td colspan="6" class="tabla__vacio">El grupo no tiene alumnos.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
