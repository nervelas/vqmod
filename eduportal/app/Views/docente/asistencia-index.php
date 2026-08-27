<div class="pagina-cab">
  <div><h1>Asistencia</h1><p class="pagina-cab__sub">Seleccione un grupo para pasar lista</p></div>
</div>
<div class="rejilla rejilla--3">
  <?php foreach ($secciones as $s): ?>
    <div class="tarjeta tarjeta--hover">
      <div class="flex flex--sep mb-2">
        <span class="badge badge--oro"><?= e($s['nivel'] ?? '') ?></span>
        <span class="sm txt-3"><?= (int)$s['inscritos'] ?> alumnos</span>
      </div>
      <h3 class="mb-2"><?= e($s['etiqueta']) ?></h3>
      <div class="flex" style="gap:6px">
        <a class="btn btn--sm" href="<?= e(url('asistencia/' . (int)$s['id'])) ?>"><?= icono('asistencia', 15) ?> Pasar lista</a>
        <a class="btn btn--linea btn--sm" href="<?= e(url('asistencia/' . (int)$s['id'] . '/reporte')) ?>"><?= icono('reporte', 15) ?> Reporte</a>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if ($secciones === []): ?>
    <div class="tarjeta vacio"><?= icono('asistencia', 44) ?><p>No tiene grupos asignados.</p></div>
  <?php endif; ?>
</div>
