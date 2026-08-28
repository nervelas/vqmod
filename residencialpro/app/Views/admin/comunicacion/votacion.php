<div class="fila-entre mb-3">
  <a class="btn btn-claro btn-sm" href="<?= e(url('/admin/votaciones')) ?>"><?= ico('flechaIzq', 16) ?> Volver a votaciones</a>
  <div class="fila" style="gap:8px">
    <a class="btn btn-claro" href="<?= e(url('/doc/acta/' . (int) $votacion['id'])) ?>" target="_blank" rel="noopener"><?= ico('archivo', 17) ?> Acta en PDF</a>
    <?php foreach (['borrador' => 'Volver a borrador', 'abierta' => 'Abrir votación', 'cerrada' => 'Cerrar votación'] as $k => $et):
      if ($votacion['estado'] === $k) { continue; } ?>
      <form method="post" action="<?= e(url('/admin/votaciones/' . (int) $votacion['id'] . '/estado')) ?>" style="display:inline">
        <?= csrf() ?>
        <input type="hidden" name="estado" value="<?= e($k) ?>">
        <button class="btn <?= $k === 'abierta' ? 'btn-oro' : 'btn-claro' ?>" type="submit"><?= e($et) ?></button>
      </form>
    <?php endforeach; ?>
  </div>
</div>

<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,360px)">
  <article class="tarjeta">
    <div class="tarjeta-cab">
      <h3><?= e($votacion['titulo']) ?></h3>
      <span class="chip <?= e(estadoBadge((string) $votacion['estado'])) ?>"><?= e(ucfirst((string) $votacion['estado'])) ?></span>
    </div>
    <div class="tarjeta-cuerpo">
      <p class="texto-2"><?= nl2br(e((string) $votacion['detalle'])) ?></p>
      <hr>
      <?php foreach ($resultados['opciones'] as $o): ?>
        <div class="mb-2">
          <div class="fila-entre" style="margin-bottom:5px">
            <span><?= e($o['texto']) ?></span>
            <b class="num"><?= e((string) $o['porcentaje']) ?>% · <?= (int) $o['votos'] ?> viviendas</b>
          </div>
          <div class="progreso"><span style="width:<?= (float) $o['porcentaje'] ?>%"></span></div>
        </div>
      <?php endforeach; ?>
    </div>
  </article>

  <div class="columna">
    <article class="tarjeta">
      <div class="tarjeta-cuerpo centrado">
        <div class="mayus">Quórum alcanzado</div>
        <div class="kpi-valor"><?= e((string) $resultados['quorum']) ?>%</div>
        <div class="texto-3" style="font-size:.85rem">Requerido: <?= e((string) $votacion['quorum']) ?>%</div>
        <div class="progreso mt-2 <?= $resultados['quorum'] >= (float) $votacion['quorum'] ? 'ok' : 'grave' ?>">
          <span style="width:<?= min(100, (float) $resultados['quorum']) ?>%"></span>
        </div>
        <div class="chip <?= $resultados['quorum'] >= (float) $votacion['quorum'] ? 'ok' : 'aviso' ?> mt-2">
          <?= $resultados['quorum'] >= (float) $votacion['quorum'] ? 'Quórum válido' : 'Falta quórum' ?>
        </div>
      </div>
    </article>

    <article class="tarjeta">
      <div class="tarjeta-cab"><h3>Votos emitidos</h3><span class="chip oro"><?= count($votos) ?></span></div>
      <div class="tarjeta-cuerpo compacto desplaza" data-etiqueta="Detalle de votos emitidos" style="max-height:420px;overflow:auto">
        <table class="tabla">
          <tbody>
            <?php foreach ($votos as $v): ?>
              <tr>
                <td class="fuerte"><?= e($v['casa']) ?></td>
                <td class="texto-2" style="font-size:.84rem"><?= e(recortar((string) $v['opcion'], 26)) ?></td>
                <td class="d texto-3" style="font-size:.8rem"><?= e(fecha((string) $v['creado_en'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </article>
  </div>
</div>
