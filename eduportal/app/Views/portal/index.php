<div class="resumen-alumno mb-5">
  <?php if (!empty($alumno['foto'])): ?>
    <img class="avatar" src="<?= e(archivo_url($alumno['foto'])) ?>" alt="">
  <?php else: ?>
    <span class="avatar iniciales" style="width:76px;height:76px;font-size:1.5rem">
      <?= e(mb_strtoupper(mb_substr($alumno['nombres'], 0, 1) . mb_substr($alumno['apellidos'], 0, 1))) ?></span>
  <?php endif; ?>
  <div class="datos">
    <h2><?= e(App\Models\Alumno::nombre($alumno)) ?></h2>
    <p class="meta mb-0"><?= e($alumno['grupo'] ?? 'Sin grado asignado') ?> · Código <?= e($alumno['codigo']) ?></p>
  </div>
</div>

<div class="rejilla rejilla--4 mb-5">
  <a class="kpi tarjeta--hover" href="<?= e(url('portal/cuenta')) ?>" style="text-decoration:none">
    <div class="kpi__etq"><?= icono('dinero', 14) ?> Saldo pendiente</div>
    <div class="kpi__valor"><?= e(moneda($cuenta['saldo'])) ?></div>
    <div class="kpi__pie"><?= $cuenta['vencido'] > 0 ? 'Vencido: ' . e(moneda($cuenta['vencido'])) : 'Sin saldos vencidos' ?></div>
  </a>
  <a class="kpi tarjeta--hover" href="<?= e(url('portal/notas')) ?>" style="text-decoration:none">
    <div class="kpi__etq"><?= icono('notas', 14) ?> Promedio general</div>
    <div class="kpi__valor"><?= e(number_format((float)$boleta['promedio'], 2)) ?></div>
    <div class="kpi__pie"><?= count($boleta['materias']) ?> materias</div>
  </a>
  <a class="kpi tarjeta--hover" href="<?= e(url('portal/asistencia')) ?>" style="text-decoration:none">
    <div class="kpi__etq"><?= icono('asistencia', 14) ?> Asistencia del mes</div>
    <div class="kpi__valor"><?= (int)$asistencia['presente'] ?>/<?= (int)$asistencia['total'] ?></div>
    <div class="kpi__pie"><?= (int)$asistencia['ausente'] ?> ausencias · <?= (int)$asistencia['tarde'] ?> tardanzas</div>
  </a>
  <a class="kpi tarjeta--hover" href="<?= e(url('portal/tareas')) ?>" style="text-decoration:none">
    <div class="kpi__etq"><?= icono('tarea', 14) ?> Tareas recientes</div>
    <div class="kpi__valor"><?= count($tareas) ?></div>
    <div class="kpi__pie">Consulte instrucciones y entregas</div>
  </a>
</div>

<div class="split">
  <div class="col">
    <div class="tarjeta">
      <div class="tarjeta__cab"><h2>Avisos del colegio</h2>
        <a href="<?= e(url('portal/avisos')) ?>" class="btn btn--fantasma btn--sm">Ver todos</a></div>
      <?php if ($avisos === []): ?>
        <div class="vacio sm"><?= icono('aviso', 40) ?><p>No hay avisos por el momento.</p></div>
      <?php else: ?>
        <div class="pila">
          <?php foreach ($avisos as $a): ?>
            <a href="<?= e(url('avisos/' . (int)$a['id'])) ?>" class="cargo-fila" style="color:inherit">
              <div class="cargo-fila__desc">
                <strong><?= e($a['titulo']) ?></strong>
                <?php if ((int)$a['leido'] === 0): ?><span class="badge badge--oro">Nuevo</span><?php endif; ?>
                <div class="sm txt-2"><?= e(recorta($a['contenido'] ?? '', 110)) ?></div>
              </div>
              <span class="xs txt-3"><?= e(fecha($a['publicar_en'] ?? $a['creado_en'])) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="tarjeta">
      <div class="tarjeta__cab"><h2>Tareas próximas</h2>
        <a href="<?= e(url('portal/tareas')) ?>" class="btn btn--fantasma btn--sm">Ver todas</a></div>
      <?php if ($tareas === []): ?>
        <div class="vacio sm"><?= icono('tarea', 40) ?><p>Sin tareas asignadas.</p></div>
      <?php else: ?>
        <div class="pila">
          <?php foreach ($tareas as $t): ?>
            <div class="cargo-fila <?= !empty($t['fecha_entrega']) && $t['fecha_entrega'] < hoy() && empty($t['entrega_estado']) ? 'vencido' : '' ?>">
              <div class="cargo-fila__desc">
                <strong><?= e($t['titulo']) ?></strong>
                <div class="sm txt-2"><?= e($t['materia']) ?> · entrega <?= e(fecha($t['fecha_entrega'] ?? '') ?: 'sin fecha') ?></div>
              </div>
              <span class="badge badge--<?= $t['entrega_estado'] ? e(estado_badge((string)$t['entrega_estado'])) : 'mute' ?>">
                <?= $t['entrega_estado'] ? e(ucfirst((string)$t['entrega_estado'])) : 'Pendiente' ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col">
    <div class="tarjeta">
      <div class="tarjeta__cab"><h2>Calificaciones</h2>
        <a href="<?= e(url('boleta/' . (int)$alumno['id'])) ?>" class="btn btn--fantasma btn--sm" target="_blank" rel="noopener">
          <?= icono('descargar', 15) ?> Boleta</a></div>
      <?php if ($boleta['materias'] === []): ?>
        <p class="sm txt-3">Aún no hay calificaciones registradas.</p>
      <?php else: ?>
        <div class="pila sm">
          <?php foreach (array_slice($boleta['materias'], 0, 8) as $m): ?>
            <div class="flex flex--sep">
              <span class="truncar"><?= e($m['materia']) ?></span>
              <strong class="<?= $m['promedio'] !== null ? e(nota_clase((float)$m['promedio'])) : '' ?>">
                <?= $m['promedio'] !== null ? e(number_format((float)$m['promedio'], 2)) : '—' ?></strong>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="tarjeta">
      <div class="tarjeta__cab"><h2>Próximas actividades</h2></div>
      <?php if ($eventos === []): ?>
        <p class="sm txt-3">Sin actividades programadas.</p>
      <?php else: ?>
        <div class="linea-tiempo">
          <?php foreach (array_slice($eventos, 0, 5) as $ev): ?>
            <div class="linea-tiempo__item">
              <div class="linea-tiempo__fecha">
                <div class="d"><?= e(date('d', strtotime((string)$ev['fecha_inicio']))) ?></div>
                <div class="m"><?= e(mb_substr(mes_nombre((int)date('n', strtotime((string)$ev['fecha_inicio']))), 0, 3)) ?></div>
              </div>
              <div><strong class="sm"><?= e($ev['titulo']) ?></strong>
                <div class="xs txt-3"><?= e(ucfirst((string)$ev['tipo'])) ?></div></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
