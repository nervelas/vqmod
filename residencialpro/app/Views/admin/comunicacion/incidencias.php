<?php use App\Core\Vista; ?>
<div class="fila envolver mb-3" style="gap:10px">
  <div class="btn-grupo">
    <?php foreach (['' => 'Todas', 'recibida' => 'Recibidas', 'proceso' => 'En proceso', 'resuelta' => 'Resueltas', 'cerrada' => 'Cerradas'] as $k => $et): ?>
      <a href="<?= e(url('/admin/incidencias', $k !== '' ? ['estado' => $k] : [])) ?>" class="<?= $estado === $k ? 'is-activo' : '' ?>"><?= e($et) ?></a>
    <?php endforeach; ?>
  </div>
  <span class="chip <?= $abiertas > 0 ? 'aviso' : 'ok' ?>"><?= (int) $abiertas ?> abierta(s)</span>
</div>

<?php if ($incidencias === []): ?>
  <div class="tarjeta">
    <?= Vista::parcial('partials/vacio', ['icono' => 'llave_inglesa', 'titulo' => 'Sin incidencias',
        'texto' => 'Cuando un residente reporte algo, aparecerá aquí.']) ?>
  </div>
<?php else: ?>
  <div class="rejilla rejilla-2">
    <?php foreach ($incidencias as $i): ?>
      <article class="tarjeta">
        <div class="tarjeta-cab">
          <div>
            <h3 style="margin:0;font-size:1.02rem">
              <a href="<?= e(url('/admin/incidencias/' . (int) $i['id'])) ?>"><?= e($i['titulo']) ?></a>
            </h3>
            <div class="texto-3" style="font-size:.79rem">
              <?= e(ucfirst((string) $i['categoria'])) ?> ·
              <?= e($i['casa'] ?? 'Área común') ?> ·
              <?= e(hace((string) $i['creado_en'])) ?>
            </div>
          </div>
          <div class="fila" style="gap:6px">
            <span class="chip <?= $i['prioridad'] === 'alta' ? 'grave' : ($i['prioridad'] === 'media' ? 'aviso' : 'neutro') ?>"><?= e(ucfirst((string) $i['prioridad'])) ?></span>
            <span class="chip <?= e(estadoBadge((string) $i['estado'])) ?>"><?= e(ucfirst((string) $i['estado'])) ?></span>
          </div>
        </div>
        <div class="tarjeta-cuerpo compacto">
          <p class="texto-2" style="font-size:.9rem"><?= e(recortar((string) $i['descripcion'], 180)) ?></p>
          <?php if (!empty($i['ubicacion'])): ?>
            <div class="texto-3" style="font-size:.83rem"><?= ico('pin', 14) ?> <?= e($i['ubicacion']) ?></div>
          <?php endif; ?>
          <div class="fila-fin mt-2">
            <a class="btn btn-sm btn-claro" href="<?= e(url('/admin/incidencias/' . (int) $i['id'])) ?>"><?= ico('ojo', 15) ?> Atender</a>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
