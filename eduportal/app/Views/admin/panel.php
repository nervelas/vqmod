<?php use App\Core\Auth; ?>
<div class="pagina-cab">
  <div>
    <h1>Panel de control</h1>
    <p class="pagina-cab__sub">
      Ciclo <strong><?= e($ciclo['nombre'] ?? 'sin definir') ?></strong> ·
      <?= e(dia_nombre(hoy())) ?> <?= e(fecha(hoy())) ?>
    </p>
  </div>
  <div class="acciones">
    <?php if (Auth::can('cobranza.editar')): ?>
      <a href="<?= e(url('cobranza/generar')) ?>" class="btn btn--linea"><?= icono('mas', 17) ?> Generar cargos</a>
    <?php endif; ?>
    <a href="<?= e(url('reportes')) ?>" class="btn"><?= icono('reporte', 17) ?> Reportes</a>
  </div>
</div>

<div class="rejilla rejilla--4 mb-5">
  <div class="kpi">
    <div class="kpi__etq"><?= icono('dinero', 14) ?> Ingresos del mes</div>
    <div class="kpi__valor"><?= e(moneda($kpi['ingresos_mes'])) ?></div>
    <?php if ($kpi['meta'] > 0): ?>
      <div class="kpi__pie"><?= e((string)$kpi['meta_pct']) ?>% de la meta (<?= e(moneda($kpi['meta'])) ?>)</div>
      <div class="medidor medidor--ok"><i style="width:<?= (float)$kpi['meta_pct'] ?>%"></i></div>
    <?php else: ?>
      <div class="kpi__pie">Defina una meta en configuración de cobranza</div>
    <?php endif; ?>
  </div>
  <div class="kpi kpi--bad">
    <div class="kpi__etq"><?= icono('reloj', 14) ?> Morosidad</div>
    <div class="kpi__valor"><?= e(moneda($kpi['morosidad'])) ?></div>
    <div class="kpi__pie"><?= e((string)$kpi['morosidad_pct']) ?>% de lo facturado</div>
    <div class="medidor medidor--bad"><i style="width:<?= min(100, (float)$kpi['morosidad_pct']) ?>%"></i></div>
  </div>
  <div class="kpi">
    <div class="kpi__etq"><?= icono('alumnos', 14) ?> Alumnos activos</div>
    <div class="kpi__valor"><?= number_format((float)$kpi['alumnos']) ?></div>
    <div class="kpi__pie">Inscritos en el ciclo actual</div>
  </div>
  <div class="kpi">
    <div class="kpi__etq"><?= icono('asistencia', 14) ?> Asistencia de hoy</div>
    <div class="kpi__valor"><?= e((string)$kpi['asistencia']['porcentaje']) ?>%</div>
    <div class="kpi__pie">
      <?= (int)$kpi['asistencia']['total'] ?> registros ·
      <?= (int)$kpi['asistencia']['ausente'] ?> ausentes
    </div>
    <div class="medidor"><i style="width:<?= (float)$kpi['asistencia']['porcentaje'] ?>%"></i></div>
  </div>
</div>

<div class="rejilla rejilla--3 mb-5">
  <a class="kpi tarjeta--hover" href="<?= e(url('cobranza')) ?>" style="text-decoration:none">
    <div class="kpi__etq"><?= icono('recibo', 14) ?> Comprobantes por aprobar</div>
    <div class="kpi__valor"><?= (int)$kpi['por_aprobar'] ?></div>
    <div class="kpi__pie">Enviados por los encargados</div>
  </a>
  <a class="kpi tarjeta--hover" href="<?= e(url('preinscripciones')) ?>" style="text-decoration:none">
    <div class="kpi__etq"><?= icono('escuela', 14) ?> Pre-inscripciones nuevas</div>
    <div class="kpi__valor"><?= (int)$kpi['preinscritos'] ?></div>
    <div class="kpi__pie">Solicitudes desde el sitio web</div>
  </a>
  <div class="kpi">
    <div class="kpi__etq"><?= icono('calendario', 14) ?> Próximos vencimientos (7 días)</div>
    <div class="kpi__valor"><?= e(moneda($kpi['proximos_total'])) ?></div>
    <div class="kpi__pie"><?= count($kpi['proximos']) ?> cargos por vencer</div>
  </div>
</div>

<div class="split mb-5">
  <div class="tarjeta">
    <div class="tarjeta__cab"><h2>Ingresos del año <?= e(date('Y')) ?></h2></div>
    <div style="height:300px;position:relative" data-grafica>
      <div class="skel skel--alto"></div>
      <canvas id="g-ingresos" aria-label="Gráfica de ingresos por mes" role="img"></canvas>
    </div>
  </div>
  <div class="tarjeta">
    <div class="tarjeta__cab"><h2>Alumnos por nivel</h2></div>
    <div style="height:300px;position:relative" data-grafica>
      <div class="skel skel--alto"></div>
      <canvas id="g-distribucion" aria-label="Distribución de alumnos por nivel" role="img"></canvas>
    </div>
  </div>
</div>

<div class="split mb-5">
  <div class="tarjeta">
    <div class="tarjeta__cab">
      <h2>Morosidad por grado</h2>
      <a href="<?= e(url('cobranza/morosidad')) ?>" class="btn btn--fantasma btn--sm">Ver detalle</a>
    </div>
    <div style="height:300px;position:relative" data-grafica>
      <div class="skel skel--alto"></div>
      <canvas id="g-morosidad" aria-label="Morosidad por grado" role="img"></canvas>
    </div>
  </div>
  <div class="tarjeta">
    <div class="tarjeta__cab"><h2>Asistencia (últimos días)</h2></div>
    <div style="height:300px;position:relative" data-grafica>
      <div class="skel skel--alto"></div>
      <canvas id="g-asistencia" aria-label="Porcentaje de asistencia" role="img"></canvas>
    </div>
  </div>
</div>

<div class="split">
  <div class="tarjeta tarjeta--plana">
    <div class="tarjeta__cab"><h2>Últimos pagos</h2>
      <a href="<?= e(url('cobranza/pagos')) ?>" class="btn btn--fantasma btn--sm">Ver todos</a>
    </div>
    <div class="tabla-env" tabindex="0" style="border:0;border-radius:0">
      <table class="tabla">
        <thead><tr><th>Recibo</th><th>Alumno</th><th>Método</th><th class="num">Monto</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($ultimosPagos as $p): ?>
          <tr>
            <td><?= e($p['recibo_no'] ?? '—') ?></td>
            <td class="truncar"><?= e(trim($p['nombres'] . ' ' . $p['apellidos'])) ?></td>
            <td class="sm txt-2"><?= e(ucfirst((string)$p['metodo'])) ?></td>
            <td class="num"><?= e(moneda((float)$p['monto'])) ?></td>
            <td><span class="badge badge--<?= e(estado_badge((string)$p['estado'])) ?>"><?= e(ucfirst((string)$p['estado'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($ultimosPagos === []): ?>
          <tr><td colspan="5" class="tabla__vacio">Aún no hay pagos registrados.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="tarjeta">
    <div class="tarjeta__cab"><h2>Avisos recientes</h2>
      <a href="<?= e(url('avisos')) ?>" class="btn btn--fantasma btn--sm">Ver todos</a>
    </div>
    <?php if ($avisos === []): ?>
      <div class="vacio sm"><?= icono('aviso', 40) ?><p>No hay avisos publicados.</p></div>
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
