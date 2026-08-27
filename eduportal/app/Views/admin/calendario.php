<?php
$mes = max(1, min(12, (int)($_GET['mes'] ?? date('n'))));
$anio = (int)($_GET['anio'] ?? date('Y'));
$primero = mktime(0, 0, 0, $mes, 1, $anio);
$diasMes = (int)date('t', $primero);
$inicioSemana = (int)date('w', $primero);
$hoy = hoy();
$porDia = [];
foreach ($eventos as $ev) {
    $ini = strtotime((string)$ev['fecha_inicio']);
    $fin = strtotime((string)($ev['fecha_fin'] ?: $ev['fecha_inicio']));
    for ($t = $ini; $t <= $fin; $t += 86400) {
        $porDia[date('Y-m-d', $t)][] = $ev;
    }
}
?>
<div class="pagina-cab">
  <div><h1>Calendario escolar</h1><p class="pagina-cab__sub"><?= e(mes_nombre($mes)) ?> <?= e((string)$anio) ?></p></div>
  <div class="acciones">
    <?php if (App\Core\Auth::can('calendario.editar')): ?>
      <button type="button" class="btn" data-modal="modal-evento"
        data-valores='{"id":"","titulo":"","descripcion":"","tipo":"evento","fecha_inicio":"<?= e($hoy) ?>","fecha_fin":""}'>
        <?= icono('mas', 17) ?> Nuevo evento</button>
    <?php endif; ?>
  </div>
</div>

<form method="get" class="filtros">
  <div class="campo campo--corto"><label for="c-mes">Mes</label>
    <select id="c-mes" name="mes" data-auto-envio>
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= $m === $mes ? 'selected' : '' ?>><?= e(mes_nombre($m)) ?></option>
      <?php endfor; ?>
    </select></div>
  <div class="campo campo--corto"><label for="c-anio">Año</label>
    <input type="number" id="c-anio" name="anio" value="<?= e((string)$anio) ?>" min="2000" max="2100"></div>
  <button type="submit" class="btn btn--linea"><?= icono('filtro', 17) ?> Ver</button>
</form>

<div class="tarjeta">
  <div class="cal mb-2">
    <?php foreach (['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'] as $d): ?>
      <div class="cal__dia-nombre"><?= e($d) ?></div>
    <?php endforeach; ?>
  </div>
  <div class="cal">
    <?php for ($i = 0; $i < $inicioSemana; $i++): ?>
      <div class="cal__celda fuera"></div>
    <?php endfor; ?>
    <?php for ($d = 1; $d <= $diasMes; $d++): $iso = sprintf('%04d-%02d-%02d', $anio, $mes, $d); ?>
      <div class="cal__celda <?= $iso === $hoy ? 'hoy' : '' ?>">
        <div class="cal__num"><?= $d ?></div>
        <?php foreach (array_slice($porDia[$iso] ?? [], 0, 3) as $ev): ?>
          <span class="cal__ev <?= e($ev['tipo']) ?>" title="<?= e($ev['titulo']) ?>"><?= e($ev['titulo']) ?></span>
        <?php endforeach; ?>
        <?php if (count($porDia[$iso] ?? []) > 3): ?>
          <span class="xs txt-3">+<?= count($porDia[$iso]) - 3 ?> más</span>
        <?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>
</div>

<div class="tarjeta tarjeta--plana mt-4">
  <div class="tarjeta__cab"><h2>Eventos del año</h2></div>
  <div class="tabla-env" tabindex="0" style="border:0">
    <table class="tabla">
      <thead><tr><th>Evento</th><th>Tipo</th><th>Inicio</th><th>Fin</th><th class="cen">Público</th><th class="cen"></th></tr></thead>
      <tbody>
      <?php foreach ($eventos as $ev): ?>
        <tr>
          <td><strong><?= e($ev['titulo']) ?></strong><div class="xs txt-3"><?= e(recorta($ev['descripcion'] ?? '', 80)) ?></div></td>
          <td><span class="badge badge--<?= e(['feriado' => 'bad', 'examen' => 'warn', 'entrega' => 'ok'][$ev['tipo']] ?? 'info') ?>"><?= e(ucfirst((string)$ev['tipo'])) ?></span></td>
          <td class="sm"><?= e(fecha((string)$ev['fecha_inicio'])) ?></td>
          <td class="sm"><?= e(fecha($ev['fecha_fin'] ?? '') ?: '—') ?></td>
          <td class="cen"><?= (int)$ev['publico'] === 1 ? 'Sí' : 'No' ?></td>
          <td class="cen">
            <?php if (App\Core\Auth::can('calendario.editar')): ?>
              <div class="flex" style="justify-content:center;gap:4px">
                <button type="button" class="btn btn--fantasma btn--sm" aria-label="Editar" data-modal="modal-evento"
                  data-valores='<?= e(json_encode([
                    'id' => (string)$ev['id'], 'titulo' => $ev['titulo'], 'descripcion' => $ev['descripcion'],
                    'tipo' => $ev['tipo'], 'fecha_inicio' => $ev['fecha_inicio'],
                    'fecha_fin' => (string)($ev['fecha_fin'] ?? ''), 'publico' => (string)$ev['publico'],
                  ], JSON_UNESCAPED_UNICODE)) ?>'><?= icono('editar', 16) ?></button>
                <form method="post" action="<?= e(url('eventos/' . (int)$ev['id'] . '/eliminar')) ?>"
                      data-confirmar="¿Eliminar este evento?" style="display:inline">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn--fantasma btn--sm" aria-label="Eliminar"><?= icono('borrar', 16) ?></button>
                </form>
              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($eventos === []): ?><tr><td colspan="6" class="tabla__vacio">Sin eventos registrados.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (App\Core\Auth::can('calendario.editar')): ?>
<div class="modal" id="modal-evento" aria-hidden="true" role="dialog" aria-label="Evento del calendario">
  <div class="modal__fondo" data-cerrar></div>
  <div class="modal__caja">
    <form method="post" action="<?= e(url('eventos')) ?>">
      <?= csrf_field() ?>
      <div class="modal__cab"><h3>Evento del calendario</h3>
        <button type="button" class="btn btn--fantasma btn--sm" data-cerrar>Cerrar</button></div>
      <div class="modal__cuerpo">
        <input type="hidden" name="id" value="">
        <div class="campo">
          <label for="ev-titulo">Título <span class="oro">*</span></label>
          <input type="text" id="ev-titulo" name="titulo" required maxlength="160">
        </div>
        <div class="campo">
          <label for="ev-desc">Descripción</label>
          <textarea id="ev-desc" name="descripcion" maxlength="2000"></textarea>
        </div>
        <div class="fila fila--3">
          <div class="campo">
            <label for="ev-tipo">Tipo <span class="oro">*</span></label>
            <select id="ev-tipo" name="tipo" required>
              <?php foreach (['evento' => 'Evento', 'feriado' => 'Feriado', 'examen' => 'Examen',
                              'entrega' => 'Entrega de notas', 'otro' => 'Otro'] as $k => $v): ?>
                <option value="<?= e($k) ?>"><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="ev-ini">Inicio <span class="oro">*</span></label>
            <input type="date" id="ev-ini" name="fecha_inicio" required>
          </div>
          <div class="campo">
            <label for="ev-fin">Fin</label>
            <input type="date" id="ev-fin" name="fecha_fin">
          </div>
        </div>
        <label class="check"><input type="checkbox" name="publico" value="1" checked> Mostrar en el sitio web público</label>
      </div>
      <div class="modal__pie">
        <button type="button" class="btn btn--linea" data-cerrar>Cancelar</button>
        <button type="submit" class="btn">Guardar evento</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
