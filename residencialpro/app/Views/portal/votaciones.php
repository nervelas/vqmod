<?php if ($items === []): ?>
  <div class="tarjeta"><div class="vacio"><?= ico('voto', 44) ?>
    <h3>No hay votaciones</h3><p>Cuando la junta directiva convoque una votación, aparecerá aquí.</p></div></div>
<?php endif; ?>

<div class="rejilla rejilla-2">
  <?php foreach ($items as $it):
    $v = $it['votacion'];
    $abierta = $v['estado'] === 'abierta' && strtotime((string) $v['inicio']) <= time() && strtotime((string) $v['fin']) >= time();
  ?>
    <article class="tarjeta">
      <div class="tarjeta-cab">
        <div>
          <h3 style="margin:0;font-size:1.05rem"><?= e($v['titulo']) ?></h3>
          <div class="texto-3" style="font-size:.8rem">
            <?= $v['modo'] === 'coeficiente' ? 'Voto ponderado por coeficiente' : 'Una vivienda, un voto' ?>
            · cierra <?= e(fechahora((string) $v['fin'])) ?>
          </div>
        </div>
        <span class="chip <?= $abierta ? 'ok' : 'neutro' ?>"><?= $abierta ? 'Abierta' : ucfirst((string) $v['estado']) ?></span>
      </div>
      <div class="tarjeta-cuerpo">
        <p class="texto-2" style="font-size:.92rem"><?= nl2br(e((string) $v['detalle'])) ?></p>

        <?php if ($abierta && !$it['yaVoto']): ?>
          <form method="post">
            <?= csrf() ?>
            <input type="hidden" name="votacion_id" value="<?= (int) $v['id'] ?>">
            <div class="columna" style="gap:8px">
              <?php foreach ($it['opciones'] as $o): ?>
                <label class="marca-check" style="border:1.5px solid var(--linea);border-radius:var(--r-sm);padding:12px 14px">
                  <input type="radio" name="opcion_id" value="<?= (int) $o['id'] ?>" required style="accent-color:var(--arcilla)">
                  <span><?= e($o['texto']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <button class="btn btn-oro btn-bloque mt-2" type="submit"><?= ico('voto', 17) ?> Emitir mi voto</button>
            <p class="ayuda centrado">Su voto es definitivo y no puede modificarse.</p>
          </form>
        <?php else: ?>
          <?php if ($it['yaVoto']): ?>
            <div class="aviso-caja ok mb-2"><?= ico('checkCirculo', 19) ?><div>Su vivienda ya emitió su voto. ¡Gracias por participar!</div></div>
          <?php endif; ?>
          <?php foreach ($it['resultados']['opciones'] as $o): ?>
            <div class="mb-2">
              <div class="fila-entre" style="margin-bottom:5px;font-size:.9rem">
                <span><?= e($o['texto']) ?></span>
                <b class="num"><?= e((string) $o['porcentaje']) ?>%</b>
              </div>
              <div class="progreso"><span style="width:<?= (float) $o['porcentaje'] ?>%"></span></div>
              <div class="texto-3" style="font-size:.78rem"><?= (int) $o['votos'] ?> vivienda(s)</div>
            </div>
          <?php endforeach; ?>
          <div class="fila-entre mt-2" style="font-size:.85rem">
            <span class="texto-2">Participación</span>
            <b><?= (int) $it['resultados']['votos'] ?> viviendas · quórum <?= e((string) $it['resultados']['quorum']) ?>%</b>
          </div>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>
