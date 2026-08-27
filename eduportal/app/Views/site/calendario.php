<section class="seccion">
  <div class="seccion__int">
    <div class="seccion__cab">
      <span class="etq">Ciclo <?= e(date('Y')) ?></span>
      <h2>Calendario escolar</h2>
      <p>Actividades, feriados, evaluaciones y entregas de calificaciones.</p>
    </div>
    <?php if ($eventos === []): ?>
      <div class="tarjeta vacio"><?= icono('calendario', 44) ?><p>Aún no hay actividades publicadas.</p></div>
    <?php else: ?>
      <div class="tarjeta">
        <div class="linea-tiempo">
          <?php foreach ($eventos as $ev): ?>
            <div class="linea-tiempo__item">
              <div class="linea-tiempo__fecha">
                <div class="d"><?= e(date('d', strtotime((string)$ev['fecha_inicio']))) ?></div>
                <div class="m"><?= e(mb_substr(mes_nombre((int)date('n', strtotime((string)$ev['fecha_inicio']))), 0, 3)) ?></div>
              </div>
              <div style="min-width:0">
                <strong><?= e($ev['titulo']) ?></strong>
                <span class="badge badge--<?= e(['feriado' => 'bad', 'examen' => 'warn', 'entrega' => 'ok'][$ev['tipo']] ?? 'info') ?>"
                      style="margin-left:8px"><?= e(ucfirst((string)$ev['tipo'])) ?></span>
                <div class="sm txt-2"><?= e($ev['descripcion'] ?? '') ?></div>
                <?php if (!empty($ev['fecha_fin']) && $ev['fecha_fin'] !== $ev['fecha_inicio']): ?>
                  <div class="xs txt-3">Hasta el <?= e(fecha((string)$ev['fecha_fin'])) ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>
