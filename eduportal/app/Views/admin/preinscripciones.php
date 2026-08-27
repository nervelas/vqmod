<div class="pagina-cab">
  <div><h1>Pre-inscripciones y contactos</h1>
    <p class="pagina-cab__sub">Solicitudes recibidas desde el sitio web público</p></div>
</div>

<div class="pestanas" data-tabs>
  <button type="button" data-tab="tab-pre" class="activo" aria-selected="true">Pre-inscripciones (<?= count($filas) ?>)</button>
  <button type="button" data-tab="tab-con" aria-selected="false">Mensajes de contacto (<?= count($contactos) ?>)</button>
</div>

<div id="tab-pre" class="panel-tab activo">
  <div class="tabla-env" tabindex="0">
    <table class="tabla">
      <thead><tr><th>Fecha</th><th>Alumno</th><th>Grado</th><th>Encargado</th><th>Contacto</th><th class="cen">Estado</th><th class="cen">Acción</th></tr></thead>
      <tbody>
      <?php foreach ($filas as $f): ?>
        <tr>
          <td class="sm"><?= e(fecha((string)$f['creado_en'])) ?></td>
          <td><strong><?= e($f['alumno_nombre']) ?></strong>
            <?php if (!empty($f['fecha_nacimiento'])): ?><div class="xs txt-3"><?= e(fecha((string)$f['fecha_nacimiento'])) ?></div><?php endif; ?></td>
          <td class="sm txt-2"><?= e($f['grado'] ?? '—') ?></td>
          <td class="sm"><?= e($f['encargado']) ?></td>
          <td class="sm">
            <?= e($f['telefono']) ?>
            <?php if (!empty($f['email'])): ?><div class="xs txt-3"><?= e($f['email']) ?></div><?php endif; ?>
          </td>
          <td class="cen"><span class="badge badge--<?= e(estado_badge((string)$f['estado'])) ?>"><?= e(ucfirst((string)$f['estado'])) ?></span></td>
          <td class="cen">
            <form method="post" action="<?= e(url('preinscripciones/' . (int)$f['id'] . '/estado')) ?>" class="flex" style="gap:4px;justify-content:center">
              <?= csrf_field() ?>
              <select name="estado" aria-label="Estado">
                <?php foreach (['nueva' => 'Nueva', 'contactada' => 'Contactada', 'inscrita' => 'Inscrita', 'descartada' => 'Descartada'] as $k => $v): ?>
                  <option value="<?= e($k) ?>" <?= $f['estado'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn--sm" aria-label="Guardar estado"><?= icono('check', 15) ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($filas === []): ?>
        <tr><td colspan="7" class="tabla__vacio"><?= icono('escuela', 40) ?><p>No hay solicitudes de pre-inscripción.</p></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div id="tab-con" class="panel-tab">
  <div class="tabla-env" tabindex="0">
    <table class="tabla">
      <thead><tr><th>Fecha</th><th>Nombre</th><th>Contacto</th><th>Mensaje</th></tr></thead>
      <tbody>
      <?php foreach ($contactos as $c): ?>
        <tr>
          <td class="sm"><?= e(fecha_hora((string)$c['creado_en'])) ?></td>
          <td><?= e($c['nombre']) ?></td>
          <td class="sm txt-2"><?= e($c['email'] ?? '') ?><?php if (!empty($c['telefono'])): ?><div class="xs"><?= e($c['telefono']) ?></div><?php endif; ?></td>
          <td class="sm"><?= e($c['mensaje']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($contactos === []): ?><tr><td colspan="4" class="tabla__vacio">Sin mensajes de contacto.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
