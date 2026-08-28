<?php use App\Core\Ajustes; use App\Core\Vista;
$colores = ['#47713F', '#8B5D09', '#B94E27', '#93251E', '#661813'];
$graf = ['type' => 'doughnut', 'data' => [
  'labels' => ['Por vencer', '1-30 días', '31-60 días', '61-90 días', '+90 días'],
  'datasets' => [['data' => [
      round((float) $resumen['corriente'], 2), round((float) $resumen['d30'], 2),
      round((float) $resumen['d60'], 2), round((float) $resumen['d90'], 2), round((float) $resumen['d120'], 2)],
      'backgroundColor' => $colores]],
], 'options' => ['formato' => 'moneda', 'centro' => ['etiqueta' => 'Cartera total', 'valor' => q((float) $resumen['total'])]]];
$tramos = ['' => 'Toda la cartera', 'd30' => '1 a 30 días', 'd60' => '31 a 60 días', 'd90' => '61 a 90 días', 'd120' => 'Más de 90 días'];
?>
<section class="rejilla mb-3" style="grid-template-columns:minmax(0,1fr) minmax(0,340px)">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Antigüedad de saldos</h3>
      <span class="chip grave"><?= (int) $resumen['casas'] ?> viviendas con saldo</span>
    </div>
    <div class="tarjeta-cuerpo">
      <div class="rejilla rejilla-4" style="gap:12px">
        <?php foreach ([
          ['Por vencer', 'corriente', 'ok'], ['1-30 días', 'd30', 'aviso'],
          ['31-60 días', 'd60', 'alerta'], ['61-90 días', 'd90', 'grave'], ['+90 días', 'd120', 'critico'],
        ] as [$et, $k, $cl]): ?>
          <div style="padding:12px 14px;border-radius:var(--r-sm);background:var(--lienzo-2);border:1px solid var(--linea)">
            <div class="mayus"><?= e($et) ?></div>
            <div class="num" style="font-size:1.2rem;font-weight:700;color:var(--<?= $cl === 'critico' ? 'critico' : ($cl === 'ok' ? 'ok' : ($cl === 'grave' ? 'grave' : ($cl === 'alerta' ? 'alerta' : 'aviso'))) ?>)">
              <?= e(q((float) $resumen[$k])) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <hr>
      <div class="fila-entre">
        <div>
          <div class="mayus">Cartera vencida total</div>
          <div style="font-family:var(--f-titulo);font-size:2.2rem;color:var(--grave)"><?= e(q((float) $resumen['total'])) ?></div>
        </div>
        <div class="fila envolver" style="gap:8px">
          <a class="btn btn-claro" href="<?= e(url('/doc/morosidad', $filtros)) ?>" target="_blank" rel="noopener"><?= ico('archivo', 17) ?> PDF</a>
          <a class="btn btn-claro" href="<?= e(url('/excel/morosidad', $filtros)) ?>"><?= ico('descargar', 17) ?> Excel</a>
        </div>
      </div>
    </div>
  </article>
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Distribución</h3></div>
    <div class="tarjeta-cuerpo">
      <canvas role="img" height="240" data-grafica="<?= e(json_encode($graf, JSON_UNESCAPED_UNICODE)) ?>" aria-label="Distribución de la cartera vencida"></canvas>
    </div>
  </article>
</section>

<form method="get" class="fila envolver mb-3" style="gap:10px">
  <select aria-label="Filtrar por fase" name="fase" data-auto-enviar style="max-width:200px">
    <option value="">Todas las fases</option>
    <?php foreach ($fases as $f): ?>
      <option value="<?= (int) $f['id'] ?>" <?= $filtros['fase'] === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
  <select aria-label="Filtrar por antigüedad del saldo" name="tramo" data-auto-enviar style="max-width:200px">
    <?php foreach ($tramos as $k => $et): ?>
      <option value="<?= e($k) ?>" <?= $tramo === $k ? 'selected' : '' ?>><?= e($et) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-claro btn-sm" type="submit"><?= ico('filtro', 16) ?> Filtrar</button>
</form>

<form method="post" action="<?= e(url('/admin/morosidad/recordatorios')) ?>"
      data-confirmar="Se enviará un correo con el detalle del saldo a cada vivienda seleccionada."
      data-confirmar-titulo="¿Enviar los recordatorios?" data-confirmar-boton="Sí, enviar">
  <?= csrf() ?>
  <div class="tarjeta">
    <div class="tarjeta-cab">
      <h3>Viviendas con saldo pendiente</h3>
      <div class="fila" style="gap:8px">
        <button class="btn btn-sm btn-claro" type="button" data-marcar-todo="#tabla-morosidad"><?= ico('check', 15) ?> Seleccionar todo</button>
        <button class="btn btn-sm btn-oro" type="submit"><?= ico('correo', 15) ?> Enviar recordatorios</button>
      </div>
    </div>

    <?php if ($filas === []): ?>
      <?= Vista::parcial('partials/vacio', ['icono' => 'checkCirculo', 'titulo' => 'Todo el residencial está solvente',
          'texto' => 'No hay viviendas con saldo pendiente en este filtro.']) ?>
    <?php else: ?>
      <div class="tabla-caja">
        <table class="tabla" id="tabla-morosidad">
          <thead>
            <tr>
              <th style="width:34px"></th><th>Casa</th><th>Residente</th><th class="c">Vence</th><th class="c">Días</th>
              <th class="d">1-30</th><th class="d">31-60</th><th class="d">61-90</th><th class="d">+90</th>
              <th class="d">Saldo</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($filas as $f): $a = $f['antiguedad']; ?>
              <tr>
                <td><input type="checkbox" name="casas[]" value="<?= (int) $f['id'] ?>" aria-label="Seleccionar <?= e($f['codigo']) ?>"></td>
                <td class="fuerte">
                  <a href="<?= e(url('/admin/casas/' . (int) $f['id'])) ?>"><?= e($f['codigo']) ?></a>
                  <?php if ((int) $f['restringida'] === 1): ?><div><span class="chip grave">Restringida</span></div><?php endif; ?>
                </td>
                <td>
                  <?= e(recortar((string) $f['residente'], 26) ?: '—') ?>
                  <?php if (!empty($f['telefono'])): ?>
                    <div class="meta texto-3"><?= e($f['telefono']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="c texto-3"><?= e(fecha((string) $f['vence'])) ?></td>
                <td class="c"><span class="chip <?= e(semaforoMora((int) $f['dias'])) ?>"><?= (int) $f['dias'] ?></span></td>
                <td class="d num"><?= $a['d30'] > 0 ? e(q((float) $a['d30'])) : '—' ?></td>
                <td class="d num"><?= $a['d60'] > 0 ? e(q((float) $a['d60'])) : '—' ?></td>
                <td class="d num"><?= $a['d90'] > 0 ? e(q((float) $a['d90'])) : '—' ?></td>
                <td class="d num"><?= $a['d120'] > 0 ? e(q((float) $a['d120'])) : '—' ?></td>
                <td class="d num fuerte"><?= e(q((float) $f['saldo'])) ?></td>
                <td class="d nowrap">
                  <?php if (!empty($f['telefono'])):
                    $texto = plantilla(Ajustes::get('wa_recordatorio', 'Estimado(a) {residente}, la casa {casa} presenta un saldo de {saldo}.'), [
                      'residente' => (string) $f['residente'], 'casa' => (string) $f['codigo'],
                      'saldo' => q((float) $f['saldo']), 'vence' => fecha((string) $f['vence']),
                      'condominio' => Ajustes::get('nombre', ''),
                      'enlace' => Ajustes::get('enlace_pago', \App\Core\Url::absoluta('/portal')),
                    ]); ?>
                    <a class="btn btn-sm btn-fantasma" target="_blank" rel="noopener"
                       href="<?= e(whatsapp((string) $f['telefono'], $texto)) ?>" aria-label="Enviar por WhatsApp"><?= ico('chat', 15) ?></a>
                  <?php endif; ?>
                  <?php if ((int) $f['dias'] >= $diasCarta): ?>
                    <a class="btn btn-sm btn-fantasma" href="<?= e(url('/doc/carta/' . (int) $f['id'])) ?>" target="_blank" rel="noopener" aria-label="Carta de cobro"><?= ico('correo', 15) ?></a>
                  <?php endif; ?>
                  <a class="btn btn-sm btn-fantasma" href="<?= e(url('/doc/estado-cuenta/' . (int) $f['id'])) ?>" target="_blank" rel="noopener" aria-label="Estado de cuenta"><?= ico('archivo', 15) ?></a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</form>

<div class="aviso-caja info mt-3">
  <?= ico('info', 20) ?>
  <div>Escalamiento automático configurado: carta de cobro a los <strong><?= (int) $diasCarta ?> días</strong>
    y marca de <strong>restricción de servicios</strong> a los <strong><?= (int) $diasCorte ?> días</strong>.
    Puede cambiarlo en Ajustes del condominio.</div>
</div>

<script<?= nonce() ?>>
document.querySelectorAll('[data-marcar-todo]').forEach(function (b) {
  b.addEventListener('click', function () {
    var t = document.querySelector(b.dataset.marcarTodo);
    if (!t) return;
    var casillas = t.querySelectorAll('tbody input[type=checkbox]');
    var marcar = Array.from(casillas).some(function (c) { return !c.checked; });
    casillas.forEach(function (c) { if (c.closest('tr').style.display !== 'none') c.checked = marcar; });
  });
});
</script>
