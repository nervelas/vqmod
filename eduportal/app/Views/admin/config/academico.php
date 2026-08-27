<div class="pagina-cab">
  <div><h1>Estructura académica</h1><p class="pagina-cab__sub">Ciclos, niveles, grados, secciones, materias, periodos y asignaciones</p></div>
  <div class="acciones"><a href="<?= e(url('configuracion')) ?>" class="btn btn--linea"><?= icono('atras', 17) ?> Volver</a></div>
</div>

<div class="pestanas" data-tabs>
  <?php
  $tabs = ['ciclos' => 'Ciclos', 'niveles' => 'Niveles', 'grados' => 'Grados', 'secciones' => 'Secciones',
           'materias' => 'Materias', 'periodos' => 'Periodos', 'asignaciones' => 'Asignaciones'];
  $i = 0;
  foreach ($tabs as $k => $v): ?>
    <button type="button" data-tab="tab-<?= e($k) ?>" class="<?= $i === 0 ? 'activo' : '' ?>"
            aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"><?= e($v) ?></button>
  <?php $i++; endforeach; ?>
</div>

<!-- Ciclos -->
<div id="tab-ciclos" class="panel-tab activo">
  <div class="flex flex--fin mb-3">
    <button type="button" class="btn btn--sm" data-modal="m-ciclo" data-valores='{"id":"","nombre":"","fecha_inicio":"","fecha_fin":""}'>
      <?= icono('mas', 15) ?> Nuevo ciclo</button>
  </div>
  <div class="tabla-env" tabindex="0"><table class="tabla">
    <thead><tr><th>Ciclo</th><th>Inicio</th><th>Fin</th><th class="cen">Activo</th><th class="cen"></th></tr></thead>
    <tbody>
    <?php foreach ($ciclos as $c): ?>
      <tr>
        <td><strong><?= e($c['nombre']) ?></strong></td>
        <td class="sm"><?= e(fecha($c['fecha_inicio'] ?? '') ?: '—') ?></td>
        <td class="sm"><?= e(fecha($c['fecha_fin'] ?? '') ?: '—') ?></td>
        <td class="cen"><?= (int)$c['activo'] === 1 ? '<span class="badge badge--ok">Activo</span>' : '<span class="badge badge--mute">—</span>' ?></td>
        <td class="cen">
          <div class="flex" style="justify-content:center;gap:4px">
            <button type="button" class="btn btn--fantasma btn--sm" aria-label="Editar" data-modal="m-ciclo"
              data-valores='<?= e(json_encode(['id' => (string)$c['id'], 'nombre' => $c['nombre'],
                'fecha_inicio' => (string)($c['fecha_inicio'] ?? ''), 'fecha_fin' => (string)($c['fecha_fin'] ?? ''),
                'activo' => (string)$c['activo']], JSON_UNESCAPED_UNICODE)) ?>'><?= icono('editar', 16) ?></button>
            <form method="post" action="<?= e(url('configuracion/academico/ciclo/' . (int)$c['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar este ciclo?">
              <?= csrf_field() ?><button type="submit" class="btn btn--fantasma btn--sm" aria-label="Eliminar"><?= icono('borrar', 16) ?></button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<!-- Niveles -->
<div id="tab-niveles" class="panel-tab">
  <div class="flex flex--fin mb-3">
    <button type="button" class="btn btn--sm" data-modal="m-nivel" data-valores='{"id":"","nombre":"","orden":"0"}'>
      <?= icono('mas', 15) ?> Nuevo nivel</button>
  </div>
  <div class="tabla-env" tabindex="0"><table class="tabla">
    <thead><tr><th>Nivel</th><th class="cen">Orden</th><th class="cen"></th></tr></thead>
    <tbody>
    <?php foreach ($niveles as $n): ?>
      <tr>
        <td><strong><?= e($n['nombre']) ?></strong></td>
        <td class="cen"><?= (int)$n['orden'] ?></td>
        <td class="cen">
          <div class="flex" style="justify-content:center;gap:4px">
            <button type="button" class="btn btn--fantasma btn--sm" aria-label="Editar" data-modal="m-nivel"
              data-valores='<?= e(json_encode(['id' => (string)$n['id'], 'nombre' => $n['nombre'], 'orden' => (string)$n['orden']], JSON_UNESCAPED_UNICODE)) ?>'><?= icono('editar', 16) ?></button>
            <form method="post" action="<?= e(url('configuracion/academico/nivel/' . (int)$n['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar este nivel?">
              <?= csrf_field() ?><button type="submit" class="btn btn--fantasma btn--sm" aria-label="Eliminar"><?= icono('borrar', 16) ?></button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<!-- Grados -->
<div id="tab-grados" class="panel-tab">
  <div class="flex flex--fin mb-3">
    <button type="button" class="btn btn--sm" data-modal="m-grado" data-valores='{"id":"","nombre":"","orden":"0"}'>
      <?= icono('mas', 15) ?> Nuevo grado</button>
  </div>
  <div class="tabla-env" tabindex="0"><table class="tabla">
    <thead><tr><th>Grado</th><th>Nivel</th><th class="cen">Orden</th><th class="cen"></th></tr></thead>
    <tbody>
    <?php foreach ($grados as $g): ?>
      <tr>
        <td><strong><?= e($g['nombre']) ?></strong></td>
        <td class="sm txt-2"><?= e($g['nivel'] ?? '') ?></td>
        <td class="cen"><?= (int)$g['orden'] ?></td>
        <td class="cen">
          <div class="flex" style="justify-content:center;gap:4px">
            <button type="button" class="btn btn--fantasma btn--sm" aria-label="Editar" data-modal="m-grado"
              data-valores='<?= e(json_encode(['id' => (string)$g['id'], 'nombre' => $g['nombre'],
                'nivel_id' => (string)$g['nivel_id'], 'orden' => (string)$g['orden']], JSON_UNESCAPED_UNICODE)) ?>'><?= icono('editar', 16) ?></button>
            <form method="post" action="<?= e(url('configuracion/academico/grado/' . (int)$g['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar este grado?">
              <?= csrf_field() ?><button type="submit" class="btn btn--fantasma btn--sm" aria-label="Eliminar"><?= icono('borrar', 16) ?></button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<!-- Secciones -->
<div id="tab-secciones" class="panel-tab">
  <div class="flex flex--fin mb-3">
    <button type="button" class="btn btn--sm" data-modal="m-seccion" data-valores='{"id":"","nombre":"","capacidad":"30"}'>
      <?= icono('mas', 15) ?> Nueva sección</button>
  </div>
  <div class="tabla-env" tabindex="0"><table class="tabla">
    <thead><tr><th>Sección</th><th>Nivel</th><th class="cen">Inscritos</th><th class="cen">Capacidad</th><th>Docente guía</th><th class="cen"></th></tr></thead>
    <tbody>
    <?php foreach ($secciones as $s): ?>
      <tr>
        <td><strong><?= e($s['etiqueta']) ?></strong></td>
        <td class="sm txt-2"><?= e($s['nivel'] ?? '') ?></td>
        <td class="cen"><?= (int)$s['inscritos'] ?></td>
        <td class="cen"><?= (int)$s['capacidad'] ?></td>
        <td class="sm txt-2"><?= e($s['guia'] ?? '—') ?></td>
        <td class="cen">
          <div class="flex" style="justify-content:center;gap:4px">
            <a class="btn btn--fantasma btn--sm" href="<?= e(url('carnes/' . (int)$s['id'])) ?>" target="_blank" rel="noopener" title="Carnés del grupo"><?= icono('recibo', 16) ?></a>
            <button type="button" class="btn btn--fantasma btn--sm" aria-label="Editar" data-modal="m-seccion"
              data-valores='<?= e(json_encode(['id' => (string)$s['id'], 'nombre' => $s['nombre'],
                'grado_id' => (string)$s['grado_id'], 'capacidad' => (string)$s['capacidad'],
                'docente_guia_id' => (string)($s['docente_guia_id'] ?? '')], JSON_UNESCAPED_UNICODE)) ?>'><?= icono('editar', 16) ?></button>
            <form method="post" action="<?= e(url('configuracion/academico/seccion/' . (int)$s['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar esta sección?">
              <?= csrf_field() ?><button type="submit" class="btn btn--fantasma btn--sm" aria-label="Eliminar"><?= icono('borrar', 16) ?></button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<!-- Materias -->
<div id="tab-materias" class="panel-tab">
  <div class="flex flex--fin mb-3">
    <button type="button" class="btn btn--sm" data-modal="m-materia" data-valores='{"id":"","nombre":"","codigo":""}'>
      <?= icono('mas', 15) ?> Nueva materia</button>
  </div>
  <div class="tabla-env" tabindex="0"><table class="tabla">
    <thead><tr><th>Materia</th><th>Código</th><th>Nivel</th><th class="cen"></th></tr></thead>
    <tbody>
    <?php foreach ($materias as $m): ?>
      <tr>
        <td><strong><?= e($m['nombre']) ?></strong></td>
        <td class="sm txt-2"><?= e($m['codigo'] ?? '—') ?></td>
        <td class="sm txt-2"><?= e($m['nivel'] ?? 'Todos') ?></td>
        <td class="cen">
          <div class="flex" style="justify-content:center;gap:4px">
            <button type="button" class="btn btn--fantasma btn--sm" aria-label="Editar" data-modal="m-materia"
              data-valores='<?= e(json_encode(['id' => (string)$m['id'], 'nombre' => $m['nombre'],
                'codigo' => (string)($m['codigo'] ?? ''), 'nivel_id' => (string)($m['nivel_id'] ?? '')], JSON_UNESCAPED_UNICODE)) ?>'><?= icono('editar', 16) ?></button>
            <form method="post" action="<?= e(url('configuracion/academico/materia/' . (int)$m['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar esta materia?">
              <?= csrf_field() ?><button type="submit" class="btn btn--fantasma btn--sm" aria-label="Eliminar"><?= icono('borrar', 16) ?></button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<!-- Periodos -->
<div id="tab-periodos" class="panel-tab">
  <div class="flex flex--fin mb-3">
    <button type="button" class="btn btn--sm" data-modal="m-periodo" data-valores='{"id":"","nombre":"","orden":"1"}'>
      <?= icono('mas', 15) ?> Nuevo periodo</button>
  </div>
  <div class="tabla-env" tabindex="0"><table class="tabla">
    <thead><tr><th>Periodo</th><th class="cen">Orden</th><th>Inicio</th><th>Fin</th><th class="cen">Estado</th><th class="cen"></th></tr></thead>
    <tbody>
    <?php foreach ($periodos as $p): ?>
      <tr>
        <td><strong><?= e($p['nombre']) ?></strong></td>
        <td class="cen"><?= (int)$p['orden'] ?></td>
        <td class="sm"><?= e(fecha($p['fecha_inicio'] ?? '') ?: '—') ?></td>
        <td class="sm"><?= e(fecha($p['fecha_fin'] ?? '') ?: '—') ?></td>
        <td class="cen"><span class="badge badge--<?= (int)$p['cerrado'] === 1 ? 'mute' : 'ok' ?>"><?= (int)$p['cerrado'] === 1 ? 'Cerrado' : 'Abierto' ?></span></td>
        <td class="cen">
          <div class="flex" style="justify-content:center;gap:4px">
            <form method="post" action="<?= e(url('periodo/' . (int)$p['id'] . '/cerrar')) ?>"
                  data-confirmar="<?= (int)$p['cerrado'] === 1 ? '¿Reabrir el periodo?' : '¿Cerrar el periodo? Las notas quedarán bloqueadas.' ?>">
              <?= csrf_field() ?>
              <?php if ((int)$p['cerrado'] === 1): ?><input type="hidden" name="abrir" value="1"><?php endif; ?>
              <button type="submit" class="btn btn--fantasma btn--sm"><?= icono((int)$p['cerrado'] === 1 ? 'check' : 'escudo', 16) ?></button>
            </form>
            <button type="button" class="btn btn--fantasma btn--sm" aria-label="Editar" data-modal="m-periodo"
              data-valores='<?= e(json_encode(['id' => (string)$p['id'], 'nombre' => $p['nombre'], 'orden' => (string)$p['orden'],
                'fecha_inicio' => (string)($p['fecha_inicio'] ?? ''), 'fecha_fin' => (string)($p['fecha_fin'] ?? '')], JSON_UNESCAPED_UNICODE)) ?>'><?= icono('editar', 16) ?></button>
            <form method="post" action="<?= e(url('configuracion/academico/periodo/' . (int)$p['id'] . '/eliminar')) ?>" data-confirmar="¿Eliminar este periodo?">
              <?= csrf_field() ?><button type="submit" class="btn btn--fantasma btn--sm" aria-label="Eliminar"><?= icono('borrar', 16) ?></button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<!-- Asignaciones -->
<div id="tab-asignaciones" class="panel-tab">
  <div class="flex flex--fin mb-3">
    <button type="button" class="btn btn--sm" data-modal="m-asignacion" data-valores='{"seccion_id":"","materia_id":"","docente_id":""}'>
      <?= icono('mas', 15) ?> Asignar docente</button>
  </div>
  <div class="tabla-env" tabindex="0"><table class="tabla">
    <thead><tr><th>Grado y sección</th><th>Materia</th><th>Docente</th><th class="cen"></th></tr></thead>
    <tbody>
    <?php foreach ($asignaciones as $a): ?>
      <tr>
        <td><strong><?= e($a['grupo']) ?></strong></td>
        <td><?= e($a['materia']) ?></td>
        <td class="sm txt-2"><?= e($a['docente'] ?? 'Sin asignar') ?></td>
        <td class="cen">
          <div class="flex" style="justify-content:center;gap:4px">
            <button type="button" class="btn btn--fantasma btn--sm" aria-label="Editar" data-modal="m-asignacion"
              data-valores='<?= e(json_encode(['seccion_id' => (string)$a['seccion_id'], 'materia_id' => (string)$a['materia_id'],
                'docente_id' => (string)($a['docente_id'] ?? '')], JSON_UNESCAPED_UNICODE)) ?>'><?= icono('editar', 16) ?></button>
            <form method="post" action="<?= e(url('configuracion/academico/asignacion/' . (int)$a['id'] . '/eliminar')) ?>" data-confirmar="¿Quitar esta asignación?">
              <?= csrf_field() ?><button type="submit" class="btn btn--fantasma btn--sm" aria-label="Eliminar"><?= icono('borrar', 16) ?></button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<?php
$modales = [
  'm-ciclo' => ['ciclo', 'Ciclo escolar'],
  'm-nivel' => ['nivel', 'Nivel'],
  'm-grado' => ['grado', 'Grado'],
  'm-seccion' => ['seccion', 'Sección'],
  'm-materia' => ['materia', 'Materia'],
  'm-periodo' => ['periodo', 'Periodo o bimestre'],
  'm-asignacion' => ['asignacion', 'Asignación docente'],
];
foreach ($modales as $id => [$tipo, $titulo]): ?>
<div class="modal" id="<?= e($id) ?>" aria-hidden="true" role="dialog" aria-label="<?= e($titulo) ?>">
  <div class="modal__fondo" data-cerrar></div>
  <div class="modal__caja">
    <form method="post" action="<?= e(url('configuracion/academico/' . $tipo)) ?>">
      <?= csrf_field() ?>
      <div class="modal__cab"><h3><?= e($titulo) ?></h3>
        <button type="button" class="btn btn--fantasma btn--sm" data-cerrar>Cerrar</button></div>
      <div class="modal__cuerpo">
        <input type="hidden" name="id" value="">
        <?php if ($tipo !== 'asignacion'): ?>
          <div class="campo">
            <label for="<?= e($id) ?>-nombre">Nombre <span class="oro">*</span></label>
            <input type="text" id="<?= e($id) ?>-nombre" name="nombre" required maxlength="90">
          </div>
        <?php endif; ?>
        <?php if ($tipo === 'ciclo' || $tipo === 'periodo'): ?>
          <div class="fila">
            <div class="campo"><label for="<?= e($id) ?>-ini">Inicio</label>
              <input type="date" id="<?= e($id) ?>-ini" name="fecha_inicio"></div>
            <div class="campo"><label for="<?= e($id) ?>-fin">Fin</label>
              <input type="date" id="<?= e($id) ?>-fin" name="fecha_fin"></div>
          </div>
        <?php endif; ?>
        <?php if ($tipo === 'ciclo'): ?>
          <label class="check"><input type="checkbox" name="activo" value="1"> Marcar como ciclo activo</label>
        <?php endif; ?>
        <?php if ($tipo === 'nivel' || $tipo === 'grado' || $tipo === 'periodo'): ?>
          <div class="campo"><label for="<?= e($id) ?>-orden">Orden</label>
            <input type="number" id="<?= e($id) ?>-orden" name="orden" min="0" max="99" value="0"></div>
        <?php endif; ?>
        <?php if ($tipo === 'grado' || $tipo === 'materia'): ?>
          <div class="campo">
            <label for="<?= e($id) ?>-nivel">Nivel<?= $tipo === 'grado' ? ' <span class="oro">*</span>' : '' ?></label>
            <select id="<?= e($id) ?>-nivel" name="nivel_id" <?= $tipo === 'grado' ? 'required' : '' ?>>
              <?php if ($tipo === 'materia'): ?><option value="">Todos los niveles</option><?php endif; ?>
              <?php foreach ($niveles as $n): ?>
                <option value="<?= (int)$n['id'] ?>"><?= e($n['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <?php if ($tipo === 'materia'): ?>
          <div class="campo"><label for="<?= e($id) ?>-codigo">Código</label>
            <input type="text" id="<?= e($id) ?>-codigo" name="codigo" maxlength="20"></div>
        <?php endif; ?>
        <?php if ($tipo === 'seccion'): ?>
          <div class="campo">
            <label for="<?= e($id) ?>-grado">Grado <span class="oro">*</span></label>
            <select id="<?= e($id) ?>-grado" name="grado_id" required>
              <?php foreach ($grados as $g): ?>
                <option value="<?= (int)$g['id'] ?>"><?= e(($g['nivel'] ?? '') . ' · ' . $g['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fila">
            <div class="campo"><label for="<?= e($id) ?>-cap">Capacidad</label>
              <input type="number" id="<?= e($id) ?>-cap" name="capacidad" min="1" max="200" value="30"></div>
            <div class="campo"><label for="<?= e($id) ?>-guia">Docente guía</label>
              <select id="<?= e($id) ?>-guia" name="docente_guia_id">
                <option value="">Sin asignar</option>
                <?php foreach ($docentes as $d): ?>
                  <option value="<?= (int)$d['id'] ?>"><?= e($d['nombre']) ?></option>
                <?php endforeach; ?>
              </select></div>
          </div>
        <?php endif; ?>
        <?php if ($tipo === 'asignacion'): ?>
          <div class="campo">
            <label for="<?= e($id) ?>-sec">Grado y sección <span class="oro">*</span></label>
            <select id="<?= e($id) ?>-sec" name="seccion_id" required>
              <?php foreach ($secciones as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= e($s['etiqueta']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="<?= e($id) ?>-mat">Materia <span class="oro">*</span></label>
            <select id="<?= e($id) ?>-mat" name="materia_id" required>
              <?php foreach ($materias as $m): ?>
                <option value="<?= (int)$m['id'] ?>"><?= e($m['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="<?= e($id) ?>-doc">Docente</label>
            <select id="<?= e($id) ?>-doc" name="docente_id">
              <option value="">Sin asignar</option>
              <?php foreach ($docentes as $d): ?>
                <option value="<?= (int)$d['id'] ?>"><?= e($d['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal__pie">
        <button type="button" class="btn btn--linea" data-cerrar>Cancelar</button>
        <button type="submit" class="btn">Guardar</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
