<div class="pagina-cab">
  <div><h1>Reportes</h1><p class="pagina-cab__sub">Consulte y exporte la información del colegio en PDF o Excel</p></div>
</div>

<div class="rejilla rejilla--4 mb-5">
  <div class="kpi"><div class="kpi__etq">Ingresos del mes</div><div class="kpi__valor"><?= e(moneda($kpi['ingresos_mes'])) ?></div></div>
  <div class="kpi"><div class="kpi__etq">Morosidad</div><div class="kpi__valor"><?= e(moneda($kpi['morosidad'])) ?></div></div>
  <div class="kpi"><div class="kpi__etq">Alumnos activos</div><div class="kpi__valor"><?= (int)$kpi['alumnos'] ?></div></div>
  <div class="kpi"><div class="kpi__etq">Por cobrar</div><div class="kpi__valor"><?= e(moneda($kpi['por_cobrar'])) ?></div></div>
</div>

<div class="rejilla rejilla--3">
  <?php
  $iconos = ['ingresos' => 'dinero', 'morosidad' => 'reloj', 'alumnos' => 'alumnos',
             'rendimiento' => 'notas', 'asistencia' => 'asistencia', 'proyeccion' => 'reporte'];
  foreach ($tipos as $clave => $nombre): ?>
    <a class="tarjeta tarjeta--hover" href="<?= e(url('reportes/' . $clave)) ?>" style="text-decoration:none;color:inherit">
      <div class="flex flex--sep mb-2">
        <span class="kpi__etq"><?= icono($iconos[$clave] ?? 'reporte', 15) ?> Reporte</span>
        <?= icono('flecha', 17) ?>
      </div>
      <h3 class="mb-0"><?= e($nombre) ?></h3>
    </a>
  <?php endforeach; ?>
</div>
