<?php use App\Core\Auth; ?>
<div class="pagina-cab">
  <div>
    <h1>Alumnos</h1>
    <p class="pagina-cab__sub"><?= number_format((float)$total) ?> registros</p>
  </div>
  <div class="acciones">
    <?php if (Auth::can('alumnos.editar')): ?>
      <a href="<?= e(url('alumnos/importar')) ?>" class="btn btn--linea"><?= icono('subir', 17) ?> Importar</a>
      <a href="<?= e(url('alumnos/nuevo')) ?>" class="btn"><?= icono('mas', 17) ?> Nuevo alumno</a>
    <?php endif; ?>
    <a href="<?= e(url('alumnos/exportar?' . http_build_query(array_filter(['q' => $filtros['q'], 'seccion' => $filtros['seccion_id'], 'nivel' => $filtros['nivel_id'], 'estado' => $filtros['estado']])))) ?>"
       class="btn btn--linea"><?= icono('descargar', 17) ?> Excel</a>
  </div>
</div>

<form method="get" class="filtros">
  <div class="campo">
    <label for="f-q">Buscar</label>
    <input type="search" id="f-q" name="q" value="<?= e($filtros['q']) ?>" placeholder="Nombre o código" data-buscar>
  </div>
  <div class="campo campo--corto">
    <label for="f-nivel">Nivel</label>
    <select id="f-nivel" name="nivel" data-auto-envio>
      <option value="">Todos</option>
      <?php foreach ($niveles as $n): ?>
        <option value="<?= (int)$n['id'] ?>" <?= (int)$filtros['nivel_id'] === (int)$n['id'] ? 'selected' : '' ?>><?= e($n['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="campo">
    <label for="f-seccion">Grado y sección</label>
    <select id="f-seccion" name="seccion" data-auto-envio>
      <option value="">Todos</option>
      <?php foreach ($secciones as $s): ?>
        <option value="<?= (int)$s['id'] ?>" <?= (int)$filtros['seccion_id'] === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['etiqueta']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="campo campo--corto">
    <label for="f-estado">Estado</label>
    <select id="f-estado" name="estado" data-auto-envio>
      <option value="">Todos</option>
      <?php foreach (['activo' => 'Activo', 'retirado' => 'Retirado', 'graduado' => 'Graduado'] as $k => $v): ?>
        <option value="<?= e($k) ?>" <?= $filtros['estado'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn--linea"><?= icono('buscar', 17) ?> Filtrar</button>
</form>

<div class="tabla-env" tabindex="0">
  <table class="tabla">
    <thead>
      <tr><th>Código</th><th>Alumno</th><th>Grado</th><th>Estado</th><th class="cen">Acciones</th></tr>
    </thead>
    <tbody>
      <?php foreach ($alumnos as $a): ?>
        <tr>
          <td class="sm"><?= e($a['codigo']) ?></td>
          <td>
            <div class="flex">
              <?php if (!empty($a['foto'])): ?>
                <img class="avatar" src="<?= e(archivo_url($a['foto'])) ?>" alt="" loading="lazy">
              <?php else: ?>
                <span class="avatar iniciales"><?= e(mb_strtoupper(mb_substr($a['nombres'], 0, 1) . mb_substr($a['apellidos'], 0, 1))) ?></span>
              <?php endif; ?>
              <a href="<?= e(url('alumnos/' . (int)$a['id'])) ?>"><?= e(trim($a['apellidos'] . ', ' . $a['nombres'])) ?></a>
            </div>
          </td>
          <td class="sm txt-2"><?= e($a['grupo'] ?? '—') ?></td>
          <td><span class="badge badge--<?= e(estado_badge((string)$a['estado'])) ?>"><?= e(ucfirst((string)$a['estado'])) ?></span></td>
          <td class="cen">
            <div class="flex" style="justify-content:center;gap:4px">
              <a class="btn btn--fantasma btn--sm" href="<?= e(url('alumnos/' . (int)$a['id'])) ?>" title="Ver ficha"><?= icono('ver', 16) ?></a>
              <?php if (Auth::can('cobranza.ver')): ?>
                <a class="btn btn--fantasma btn--sm" href="<?= e(url('cobranza/estado/' . (int)$a['id'])) ?>" title="Estado de cuenta"><?= icono('dinero', 16) ?></a>
              <?php endif; ?>
              <a class="btn btn--fantasma btn--sm" href="<?= e(url('boleta/' . (int)$a['id'])) ?>" title="Boleta PDF" target="_blank" rel="noopener"><?= icono('notas', 16) ?></a>
              <?php if (Auth::can('alumnos.editar')): ?>
                <a class="btn btn--fantasma btn--sm" href="<?= e(url('alumnos/' . (int)$a['id'] . '/editar')) ?>" title="Editar"><?= icono('editar', 16) ?></a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($alumnos === []): ?>
        <tr><td colspan="5" class="tabla__vacio"><?= icono('alumnos', 40) ?><p>No se encontraron alumnos con estos filtros.</p></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?= App\Core\View::partial('partials/paginacion', ['total' => $total, 'pagina' => $pagina, 'porPagina' => $porPagina]) ?>
