<?php $q = http_build_query(array_filter(['nivel' => $filtros['nivel_id'], 'seccion' => $filtros['seccion_id']])); ?>
<div class="pagina-cab">
  <div><h1>Reporte de morosidad</h1>
    <p class="pagina-cab__sub"><?= count($filas) ?> alumnos con saldo vencido · total <strong><?= e(moneda($total)) ?></strong></p></div>
  <div class="acciones">
    <a href="<?= e(url('cobranza/morosidad/pdf' . ($q ? '?' . $q : ''))) ?>" class="btn btn--linea" target="_blank" rel="noopener"><?= icono('descargar', 17) ?> PDF</a>
    <a href="<?= e(url('cobranza/morosidad/excel' . ($q ? '?' . $q : ''))) ?>" class="btn btn--linea"><?= icono('descargar', 17) ?> Excel</a>
  </div>
</div>

<form method="get" class="filtros">
  <div class="campo"><label for="f-nivel">Nivel</label>
    <select id="f-nivel" name="nivel" data-auto-envio>
      <option value="">Todos</option>
      <?php foreach ($niveles as $n): ?>
        <option value="<?= (int)$n['id'] ?>" <?= (int)$filtros['nivel_id'] === (int)$n['id'] ? 'selected' : '' ?>><?= e($n['nombre']) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="campo"><label for="f-seccion">Grado y sección</label>
    <select id="f-seccion" name="seccion" data-auto-envio>
      <option value="">Todos</option>
      <?php foreach ($secciones as $s): ?>
        <option value="<?= (int)$s['id'] ?>" <?= (int)$filtros['seccion_id'] === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['etiqueta']) ?></option>
      <?php endforeach; ?>
    </select></div>
  <button type="submit" class="btn btn--linea"><?= icono('filtro', 17) ?> Filtrar</button>
</form>

<div class="tabla-env" tabindex="0">
  <table class="tabla">
    <thead><tr><th>Código</th><th>Alumno</th><th>Grado</th><th class="cen">Cargos</th>
      <th>Más antiguo</th><th class="num">Saldo</th><th class="cen">Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
      <tr>
        <td class="sm"><?= e($f['codigo']) ?></td>
        <td><a href="<?= e(url('cobranza/estado/' . (int)$f['id'])) ?>"><?= e(trim($f['apellidos'] . ', ' . $f['nombres'])) ?></a></td>
        <td class="sm txt-2"><?= e($f['grupo']) ?></td>
        <td class="cen"><?= (int)$f['cargos_vencidos'] ?></td>
        <td class="sm nota-baja"><?= e(fecha((string)$f['mas_antiguo'])) ?></td>
        <td class="num negrita"><?= e(moneda((float)$f['saldo'])) ?></td>
        <td class="cen">
          <?php $wa = App\Servicios\Recordatorios::enlaceWhatsApp((int)$f['id'], (float)$f['saldo'], (string)$f['mas_antiguo']); ?>
          <div class="flex" style="justify-content:center;gap:4px">
            <?php if ($wa !== ''): ?>
              <a class="btn btn--fantasma btn--sm" target="_blank" rel="noopener" href="<?= e($wa) ?>" title="Recordar por WhatsApp"><?= icono('whatsapp', 16) ?></a>
            <?php endif; ?>
            <a class="btn btn--fantasma btn--sm" href="<?= e(url('cobranza/estado/' . (int)$f['id'])) ?>" title="Estado de cuenta"><?= icono('ver', 16) ?></a>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($filas === []): ?>
      <tr><td colspan="7" class="tabla__vacio"><?= icono('check', 40) ?><p>No hay alumnos con saldo vencido. ¡Excelente!</p></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
