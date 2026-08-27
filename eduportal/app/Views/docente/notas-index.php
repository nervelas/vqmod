<div class="pagina-cab">
  <div><h1>Captura de notas</h1>
    <p class="pagina-cab__sub">Seleccione la materia y el grupo para abrir la cuadrícula de calificaciones</p></div>
  <div class="acciones">
    <a href="<?= e(url('notas/cuadro-honor')) ?>" class="btn btn--linea"><?= icono('estrella', 17) ?> Cuadro de honor</a>
  </div>
</div>

<?php if ($periodoActual): ?>
  <div class="aviso aviso--info"><?= icono('calendario', 18) ?>
    <span>Periodo en curso: <strong><?= e($periodoActual['nombre']) ?></strong>
      (<?= e(fecha($periodoActual['fecha_inicio'] ?? '')) ?> al <?= e(fecha($periodoActual['fecha_fin'] ?? '')) ?>)
      <?php if ((int)$periodoActual['cerrado'] === 1): ?> · <strong>cerrado</strong><?php endif; ?>
    </span></div>
<?php endif; ?>

<div class="tabla-env" tabindex="0">
  <table class="tabla">
    <thead><tr><th>Materia</th><th>Grado y sección</th><th>Docente</th><th class="cen">Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($asignaciones as $a): ?>
      <tr>
        <td><strong><?= e($a['materia']) ?></strong></td>
        <td class="sm txt-2"><?= e($a['grupo']) ?></td>
        <td class="sm txt-2"><?= e($a['docente'] ?? 'Sin asignar') ?></td>
        <td class="cen">
          <div class="flex" style="justify-content:center;gap:4px">
            <a class="btn btn--sm" href="<?= e(url('notas/' . (int)$a['id'])) ?>"><?= icono('editar', 15) ?> Capturar</a>
            <a class="btn btn--linea btn--sm" target="_blank" rel="noopener"
               href="<?= e(url('boletas/' . (int)$a['seccion_id'])) ?>"><?= icono('descargar', 15) ?> Boletas</a>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($asignaciones === []): ?>
      <tr><td colspan="4" class="tabla__vacio"><?= icono('notas', 40) ?><p>No hay materias asignadas.</p></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
