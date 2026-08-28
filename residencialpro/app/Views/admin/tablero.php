<?php
use App\Core\Ajustes;

$acento  = Ajustes::get('color_acento', '#B94E27');
$marca   = Ajustes::get('color_primario', '#0E4C5A');
$gSerie  = [
  'type' => 'line',
  'data' => [
    'labels' => array_column($serie, 'etiqueta'),
    'datasets' => [
      ['label' => 'Recaudado', 'data' => array_map(static fn($s) => round((float) $s['recaudado'], 2), $serie), 'borderColor' => $acento],
      ['label' => 'Esperado',  'data' => array_map(static fn($s) => round((float) $s['esperado'], 2), $serie),  'borderColor' => '#6E6A61', 'fill' => false, 'borderDash' => [5, 4]],
    ],
  ],
  'options' => ['formato' => 'moneda'],
];
$gFlujo = [
  'type' => 'bar',
  'data' => [
    'labels' => array_column($flujo, 'etiqueta'),
    'datasets' => [
      ['label' => 'Ingresos', 'backgroundColor' => '#47713F', 'data' => array_map(static fn($s) => round((float) $s['ingresos'], 2), $flujo)],
      ['label' => 'Egresos',  'backgroundColor' => '#B94E27', 'data' => array_map(static fn($s) => round((float) $s['egresos'], 2), $flujo)],
    ],
  ],
  'options' => ['formato' => 'moneda'],
];
$colores = ['#0E4C5A', '#B94E27', '#47713F', '#8B5D09', '#1D7A90', '#93251E', '#6E6A61', '#3B4429'];
$gEgresos = [
  'type' => 'doughnut',
  'data' => [
    'labels' => array_column($egresosCat, 'categoria'),
    'datasets' => [['data' => array_map(static fn($c) => round((float) $c['total'], 2), $egresosCat), 'backgroundColor' => $colores]],
  ],
  'options' => ['formato' => 'moneda', 'centro' => ['etiqueta' => 'Egresos del mes', 'valor' => q($kpi['egresos_mes'])]],
];
$gVisitas = [
  'type' => 'bar',
  'data' => [
    'labels' => array_column($visitasDia, 'etiqueta'),
    'datasets' => [['label' => 'Ingresos a garita', 'backgroundColor' => $marca, 'data' => array_column($visitasDia, 'n')]],
  ],
];
?>

<?php if ($kpi['comprobantes'] > 0 && esRol('admin', 'contabilidad')): ?>
  <div class="aviso-caja alerta mb-3">
    <?= ico('archivo', 20) ?>
    <div class="crecer">
      <strong>Tiene <?= (int) $kpi['comprobantes'] ?> comprobante(s) por revisar</strong>
      Los residentes están esperando la aprobación de su pago.
    </div>
    <a class="btn btn-sm btn-oro" href="<?= e(url('/admin/comprobantes')) ?>">Revisar ahora</a>
  </div>
<?php endif; ?>

<section class="kpis mb-3">
  <!-- La recaudación manda: es la cifra por la que se abre este tablero. -->
  <article class="kpi mayor">
    <div class="kpi-et"><?= ico('billetera', 15) ?> Recaudación del mes</div>
    <div class="kpi-valor"><?= e(q($kpi['recaudado'])) ?></div>
    <div class="kpi-nota">
      <?= $kpi['efectividad'] >= 100 ? ico('subeTendencia', 15) : ico('bajaTendencia', 15) ?>
      <?= e((string) $kpi['efectividad']) ?> % de lo esperado · meta <?= e(q($kpi['esperado'])) ?>
    </div>
    <div class="progreso mt-1" style="background:color-mix(in srgb,#fff 18%,transparent)">
      <span style="width:<?= min(100, (float) $kpi['efectividad']) ?>%;background:var(--arcilla-3)"></span>
    </div>
  </article>

  <article class="kpi <?= $kpi['casas_morosas'] > 0 ? 'grave' : 'ok' ?>">
    <div class="kpi-et"><?= ico('alerta', 15) ?> Cartera vencida</div>
    <div class="kpi-valor"><?= e(q($kpi['cartera'])) ?></div>
    <div class="kpi-nota">
      <span class="chip <?= $kpi['casas_morosas'] > 0 ? 'grave' : 'ok' ?>"><?= e((string) $kpi['morosidad_pct']) ?> %</span>
      <?= (int) $kpi['casas_morosas'] ?> de <?= (int) $kpi['casas_total'] ?> viviendas
    </div>
    <div class="progreso mt-1"><span style="width:<?= min(100, (float) $kpi['morosidad_pct']) ?>%;background:var(--grave)"></span></div>
  </article>

  <article class="kpi ok">
    <div class="kpi-et"><?= ico('moneda', 15) ?> Saldo en caja y bancos</div>
    <div class="kpi-valor"><?= e(q($kpi['saldo_bancos'])) ?></div>
    <div class="kpi-nota"><?= ico('bajaTendencia', 15) ?> Egresos del mes <?= e(q($kpi['egresos_mes'])) ?></div>
  </article>

  <article class="kpi oro">
    <div class="kpi-et"><?= ico('puerta', 15) ?> Movimiento en garita</div>
    <div class="kpi-valor"><?= (int) $kpi['visitas_hoy'] ?></div>
    <div class="kpi-nota"><span class="punto"></span> <?= (int) $kpi['adentro'] ?> persona(s) dentro ahora</div>
  </article>
</section>

<section class="rejilla mb-3" style="grid-template-columns:minmax(0,1.55fr) minmax(0,1fr)">
  <article class="tarjeta">
    <div class="tarjeta-cab">
      <h3>Recaudación mensual</h3>
      <a class="btn btn-sm btn-fantasma" href="<?= e(url('/admin/informes')) ?>"><?= ico('grafica', 15) ?> Ver informes</a>
    </div>
    <div class="tarjeta-cuerpo">
      <canvas role="img" id="g-serie" height="230" data-grafica="<?= e(json_encode($gSerie, JSON_UNESCAPED_UNICODE)) ?>"
              aria-label="Gráfica de recaudación mensual"></canvas>
    </div>
  </article>

  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Egresos por categoría</h3></div>
    <div class="tarjeta-cuerpo">
      <?php if ($egresosCat === []): ?>
        <?= App\Core\Vista::parcial('partials/vacio', ['icono' => 'moneda', 'titulo' => 'Sin egresos este mes', 'texto' => 'Registre los gastos del residencial para ver su distribución.', 'accion' => '/admin/egresos/nuevo', 'accionTexto' => 'Registrar egreso']) ?>
      <?php else: ?>
        <canvas role="img" id="g-egresos" height="230" data-grafica="<?= e(json_encode($gEgresos, JSON_UNESCAPED_UNICODE)) ?>"
                aria-label="Distribución de egresos por categoría"></canvas>
      <?php endif; ?>
    </div>
  </article>
</section>

<section class="rejilla rejilla-2 mb-3">
  <article class="tarjeta">
    <div class="tarjeta-cab">
      <h3>Viviendas con mayor saldo</h3>
      <a class="btn btn-sm btn-fantasma" href="<?= e(url('/admin/morosidad')) ?>">Ver todas</a>
    </div>
    <?php if ($morosos === []): ?>
      <?= App\Core\Vista::parcial('partials/vacio', ['icono' => 'checkCirculo', 'titulo' => 'Todo el residencial está solvente', 'texto' => 'No hay viviendas con saldo pendiente. Excelente gestión de cobro.']) ?>
    <?php else: ?>
      <div class="tabla-caja">
        <table class="tabla">
          <thead><tr><th>Casa</th><th>Residente</th><th class="c">Días</th><th class="d">Saldo</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($morosos as $m): ?>
              <tr>
                <td class="fuerte"><?= e($m['codigo']) ?></td>
                <td><?= e(recortar((string) $m['residente'], 24)) ?></td>
                <td class="c"><span class="chip <?= e(semaforoMora((int) $m['dias'])) ?>"><?= (int) $m['dias'] ?></span></td>
                <td class="d fuerte"><?= e(q((float) $m['saldo'])) ?></td>
                <td class="d">
                  <a class="btn btn-sm btn-fantasma" href="<?= e(url('/admin/casas/' . (int) $m['id'])) ?>" aria-label="Ver la vivienda <?= e($m['codigo']) ?>"><?= ico('ojo', 15) ?></a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </article>

  <article class="tarjeta">
    <div class="tarjeta-cab">
      <h3>Últimos pagos recibidos</h3>
      <a class="btn btn-sm btn-fantasma" href="<?= e(url('/admin/pagos')) ?>">Ver todos</a>
    </div>
    <?php if ($ultimosPagos === []): ?>
      <?= App\Core\Vista::parcial('partials/vacio', ['icono' => 'tarjeta', 'titulo' => 'Aún no hay pagos registrados', 'accion' => '/admin/pagos/nuevo', 'accionTexto' => 'Registrar un pago']) ?>
    <?php else: ?>
      <div class="tabla-caja">
        <table class="tabla">
          <thead><tr><th>Recibo</th><th>Casa</th><th>Fecha</th><th class="d">Monto</th></tr></thead>
          <tbody>
            <?php foreach ($ultimosPagos as $p): ?>
              <tr>
                <td class="fuerte"><a href="<?= e(url('/admin/pagos/' . (int) $p['id'])) ?>"><?= e($p['recibo'] ?? '—') ?></a></td>
                <td><?= e($p['casa']) ?></td>
                <td class="texto-3"><?= e(fecha((string) $p['fecha'])) ?></td>
                <td class="d fuerte"><?= e(q((float) $p['monto'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </article>
</section>

<section class="rejilla rejilla-3 mb-3">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Ingresos y egresos</h3></div>
    <div class="tarjeta-cuerpo">
      <canvas role="img" id="g-flujo" height="200" data-grafica="<?= e(json_encode($gFlujo, JSON_UNESCAPED_UNICODE)) ?>"
              aria-label="Comparativo de ingresos y egresos"></canvas>
    </div>
  </article>

  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Movimiento en garita</h3></div>
    <div class="tarjeta-cuerpo">
      <canvas role="img" id="g-visitas" height="200" data-grafica="<?= e(json_encode($gVisitas, JSON_UNESCAPED_UNICODE)) ?>"
              aria-label="Visitas por día"></canvas>
    </div>
  </article>

  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Antigüedad de la cartera</h3></div>
    <div class="tarjeta-cuerpo">
      <?php
      $tramos = [
        'Por vencer' => ['corriente', 'ok'],
        '1 a 30 días' => ['d30', 'aviso'],
        '31 a 60 días' => ['d60', 'alerta'],
        '61 a 90 días' => ['d90', 'grave'],
        'Más de 90 días' => ['d120', 'critico'],
      ];
      $totalCartera = max(0.01, (float) $kpi['antiguedad']['total']);
      foreach ($tramos as $etiqueta => [$clave, $color]):
        $valor = (float) $kpi['antiguedad'][$clave];
      ?>
        <div class="mb-2">
          <div class="fila-entre" style="margin-bottom:5px">
            <span style="font-size:.85rem"><?= e($etiqueta) ?></span>
            <b class="num" style="font-size:.88rem"><?= e(q($valor)) ?></b>
          </div>
          <div class="progreso"><span style="width:<?= round($valor * 100 / $totalCartera, 1) ?>%;background:var(--<?= $color === 'critico' ? 'critico' : ($color === 'ok' ? 'ok' : ($color === 'grave' ? 'grave' : ($color === 'alerta' ? 'alerta' : 'aviso'))) ?>)"></span></div>
        </div>
      <?php endforeach; ?>
    </div>
  </article>
</section>

<section class="rejilla rejilla-3">
  <article class="tarjeta">
    <div class="tarjeta-cab">
      <h3>Incidencias abiertas</h3>
      <span class="chip <?= $kpi['incidencias'] > 0 ? 'aviso' : 'ok' ?>"><?= (int) $kpi['incidencias'] ?></span>
    </div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($incidencias === []): ?>
        <p class="texto-3 centrado" style="padding:18px 0;margin:0">Sin incidencias pendientes.</p>
      <?php else: ?>
        <ul class="lista-limpia">
          <?php foreach ($incidencias as $i): ?>
            <li class="item-lista">
              <span class="chip <?= $i['prioridad'] === 'alta' ? 'grave' : ($i['prioridad'] === 'media' ? 'aviso' : 'neutro') ?>"><?= e(ucfirst((string) $i['prioridad'])) ?></span>
              <div class="crecer">
                <b><?php if (esRol('admin', 'junta')): ?><a href="<?= e(url('/admin/incidencias/' . (int) $i['id'])) ?>"><?= e(recortar((string) $i['titulo'], 44)) ?></a><?php else: ?><?= e(recortar((string) $i['titulo'], 44)) ?><?php endif; ?></b>
                <div class="meta"><?= e($i['casa'] ?? 'Área común') ?> · <?= e(hace((string) $i['creado_en'])) ?></div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </article>

  <article class="tarjeta">
    <div class="tarjeta-cab">
      <h3>Reservas próximas</h3>
      <span class="chip oro"><?= (int) $kpi['reservas_semana'] ?> esta semana</span>
    </div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($reservas === []): ?>
        <p class="texto-3 centrado" style="padding:18px 0;margin:0">Sin reservas próximas.</p>
      <?php else: ?>
        <ul class="lista-limpia">
          <?php foreach ($reservas as $r): ?>
            <li class="item-lista">
              <span style="color:var(--arcilla)"><?= ico('calendario', 20) ?></span>
              <div class="crecer">
                <b><?= e($r['area']) ?></b>
                <div class="meta"><?= e($r['casa']) ?> · <?= e(fecha((string) $r['fecha'])) ?> <?= e(hora((string) $r['hora_desde'])) ?></div>
              </div>
              <span class="chip <?= e(estadoBadge((string) $r['estado'])) ?>"><?= e(ucfirst((string) $r['estado'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </article>

  <article class="tarjeta">
    <div class="tarjeta-cab">
      <h3>Dentro del residencial</h3>
      <span class="chip <?= count($adentro) > 0 ? 'info' : 'neutro' ?>"><?= count($adentro) ?></span>
    </div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($adentro === []): ?>
        <p class="texto-3 centrado" style="padding:18px 0;margin:0">No hay visitas dentro en este momento.</p>
      <?php else: ?>
        <ul class="lista-limpia">
          <?php foreach (array_slice($adentro, 0, 6) as $v): ?>
            <li class="item-lista">
              <span class="avatar sm"><?= e(iniciales((string) $v['visitante'])) ?></span>
              <div class="crecer">
                <b><?= e(recortar((string) $v['visitante'], 26)) ?></b>
                <div class="meta"><?= e($v['casa'] ?? 'Sin destino') ?> · desde <?= e(hora((string) $v['entrada'])) ?><?= !empty($v['placa']) ? ' · ' . e($v['placa']) : '' ?></div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </article>
</section>
