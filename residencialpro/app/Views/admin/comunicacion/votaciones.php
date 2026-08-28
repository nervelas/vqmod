<?php use App\Core\Vista; ?>
<div class="fila-entre mb-3">
  <div class="texto-3" style="font-size:.9rem">Cada vivienda vota una sola vez. Puede ponderar por coeficiente de participación.</div>
  <a class="btn btn-oro" href="<?= e(url('/admin/votaciones/nueva')) ?>"><?= ico('mas', 17) ?> Nueva votación</a>
</div>

<?php if ($items === []): ?>
  <div class="tarjeta">
    <?= Vista::parcial('partials/vacio', ['icono' => 'voto', 'titulo' => 'No hay votaciones',
        'texto' => 'Convoque una consulta para tomar decisiones con respaldo de los propietarios.',
        'accion' => '/admin/votaciones/nueva', 'accionTexto' => 'Crear votación']) ?>
  </div>
<?php else: ?>
  <div class="rejilla rejilla-2">
    <?php foreach ($items as $it): $v = $it['votacion']; $r = $it['resultados']; ?>
      <article class="tarjeta">
        <div class="tarjeta-cab">
          <div>
            <h3 style="margin:0;font-size:1.02rem"><a href="<?= e(url('/admin/votaciones/' . (int) $v['id'])) ?>"><?= e($v['titulo']) ?></a></h3>
            <div class="texto-3" style="font-size:.79rem">
              <?= e(fecha((string) $v['inicio'])) ?> al <?= e(fecha((string) $v['fin'])) ?> ·
              <?= $v['modo'] === 'coeficiente' ? 'por coeficiente' : 'una casa, un voto' ?>
            </div>
          </div>
          <span class="chip <?= e(estadoBadge((string) $v['estado'])) ?>"><?= e(ucfirst((string) $v['estado'])) ?></span>
        </div>
        <div class="tarjeta-cuerpo compacto">
          <?php foreach ($r['opciones'] as $o): ?>
            <div style="margin-bottom:10px">
              <div class="fila-entre" style="font-size:.86rem;margin-bottom:4px">
                <span><?= e(recortar((string) $o['texto'], 46)) ?></span>
                <b class="num"><?= e((string) $o['porcentaje']) ?>%</b>
              </div>
              <div class="progreso"><span style="width:<?= (float) $o['porcentaje'] ?>%"></span></div>
            </div>
          <?php endforeach; ?>
          <div class="fila-entre mt-2" style="font-size:.84rem">
            <span class="texto-3"><?= (int) $r['votos'] ?> viviendas · quórum <?= e((string) $r['quorum']) ?>% de <?= e((string) $v['quorum']) ?>% requerido</span>
            <a class="btn btn-sm btn-claro" href="<?= e(url('/doc/acta/' . (int) $v['id'])) ?>" target="_blank" rel="noopener"><?= ico('archivo', 14) ?> Acta</a>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
