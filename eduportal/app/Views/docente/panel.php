<div class="pagina-cab">
  <div><h1>Mis grupos</h1>
    <p class="pagina-cab__sub">
      <?= count($asignaciones) ?> materias · <?= count($secciones) ?> grupos · <?= (int)$totalAlumnos ?> alumnos
      <?php if ($periodo): ?> · periodo activo: <strong><?= e($periodo['nombre']) ?></strong><?php endif; ?>
    </p></div>
  <div class="acciones">
    <a href="<?= e(url('asistencia')) ?>" class="btn btn--linea"><?= icono('asistencia', 17) ?> Pase de lista</a>
    <a href="<?= e(url('notas')) ?>" class="btn"><?= icono('notas', 17) ?> Capturar notas</a>
  </div>
</div>

<div class="rejilla rejilla--3 mb-5">
  <?php foreach ($asignaciones as $a): ?>
    <a class="tarjeta tarjeta--hover" href="<?= e(url('notas/' . (int)$a['id'])) ?>" style="text-decoration:none;color:inherit">
      <div class="flex flex--sep mb-2">
        <span class="badge badge--oro"><?= e($a['grupo']) ?></span>
        <?= icono('flecha', 17) ?>
      </div>
      <h3 class="mb-0"><?= e($a['materia']) ?></h3>
      <p class="sm txt-2 mb-0"><?= e($a['nivel'] ?? '') ?></p>
    </a>
  <?php endforeach; ?>
  <?php if ($asignaciones === []): ?>
    <div class="tarjeta vacio"><?= icono('libro', 44) ?><p>Aún no tiene materias asignadas. Comuníquese con la dirección.</p></div>
  <?php endif; ?>
</div>

<div class="split">
  <div class="tarjeta tarjeta--plana">
    <div class="tarjeta__cab"><h2>Tareas recientes</h2>
      <a href="<?= e(url('tareas')) ?>" class="btn btn--fantasma btn--sm">Ver todas</a></div>
    <div class="tabla-env" tabindex="0" style="border:0">
      <table class="tabla">
        <thead><tr><th>Tarea</th><th>Materia</th><th>Grupo</th><th>Entrega</th></tr></thead>
        <tbody>
        <?php foreach ($tareas as $t): ?>
          <tr>
            <td><a href="<?= e(url('tareas/' . (int)$t['id'] . '/entregas')) ?>"><?= e($t['titulo']) ?></a></td>
            <td class="sm txt-2"><?= e($t['materia']) ?></td>
            <td class="sm txt-2"><?= e($t['grupo']) ?></td>
            <td class="sm"><?= e(fecha($t['fecha_entrega'] ?? '') ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($tareas === []): ?><tr><td colspan="4" class="tabla__vacio">No ha publicado tareas.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tarjeta">
    <div class="tarjeta__cab"><h2>Avisos</h2>
      <a href="<?= e(url('avisos')) ?>" class="btn btn--fantasma btn--sm">Ver todos</a></div>
    <?php if ($avisos === []): ?>
      <div class="vacio sm"><?= icono('aviso', 40) ?><p>Sin avisos recientes.</p></div>
    <?php else: ?>
      <div class="pila">
        <?php foreach ($avisos as $a): ?>
          <a href="<?= e(url('avisos/' . (int)$a['id'])) ?>" style="display:block;color:inherit">
            <strong class="sm"><?= e($a['titulo']) ?></strong>
            <div class="xs txt-3"><?= e(fecha_hora($a['publicar_en'] ?? $a['creado_en'])) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
