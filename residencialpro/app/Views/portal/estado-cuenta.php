<?php use App\Models\Cuota; ?>
<section class="rejilla mb-3" style="grid-template-columns:minmax(0,1fr) minmax(0,320px)">
  <article class="tarjeta">
    <div class="tarjeta-cab">
      <h3>Detalle de mi cuenta</h3>
      <div class="fila" style="gap:8px">
        <a class="btn btn-sm btn-claro" href="<?= e(url('/doc/estado-cuenta/' . (int) $casaActual['id'])) ?>" target="_blank" rel="noopener"><?= ico('archivo', 15) ?> PDF</a>
        <a class="btn btn-sm btn-claro" href="<?= e(url('/excel/estado-cuenta/' . (int) $casaActual['id'])) ?>"><?= ico('descargar', 15) ?> Excel</a>
      </div>
    </div>
    <div class="tabla-caja">
      <table class="tabla apilar">
        <thead><tr><th>Concepto</th><th class="c">Vence</th><th class="d">Cargo</th><th class="d">Mora</th><th class="d">Saldo</th><th class="c">Estado</th></tr></thead>
        <tbody>
          <?php foreach ($cargos as $c): $s = Cuota::saldoCargo($c); ?>
            <tr>
              <td data-et="Concepto"><?= e($c['descripcion']) ?></td>
              <td data-et="Vence" class="c texto-3"><?= e(fecha((string) $c['fecha_vence'])) ?></td>
              <td data-et="Cargo" class="d num"><?= e(q((float) $c['monto'])) ?></td>
              <td data-et="Mora" class="d num"><?= (float) $c['mora'] > 0 ? e(q((float) $c['mora'])) : '—' ?></td>
              <td data-et="Saldo" class="d num fuerte"><?= e(q($s)) ?></td>
              <td data-et="Estado" class="c"><span class="chip <?= e(estadoBadge((string) $c['estado'])) ?>"><?= e(ucfirst((string) $c['estado'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($cargos === []): ?>
            <tr><td colspan="6" class="centrado texto-3" style="padding:30px">Todavía no hay cargos registrados para su vivienda.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </article>

  <div class="columna">
    <article class="tarjeta">
      <div class="tarjeta-cuerpo centrado">
        <div class="mayus">Saldo pendiente</div>
        <div class="kpi-valor"><?= e(q($saldo)) ?></div>
        <?php if ($aFavor > 0.009): ?><span class="chip ok">A favor: <?= e(q($aFavor)) ?></span><?php endif; ?>
        <?php if ($saldo > 0.009): ?>
          <a class="btn btn-oro btn-bloque mt-2" href="<?= e(url('/portal/pagar')) ?>"><?= ico('tarjeta', 17) ?> Reportar mi pago</a>
        <?php else: ?>
          <div class="chip ok mt-2">Vivienda solvente</div>
          <a class="btn btn-claro btn-bloque mt-2" href="<?= e(url('/doc/solvencia/' . (int) $casaActual['id'])) ?>" target="_blank" rel="noopener">
            <?= ico('escudo', 17) ?> Constancia de solvencia
          </a>
        <?php endif; ?>
      </div>
    </article>

    <?php if ($antiguedad['total'] > 0): ?>
      <article class="tarjeta">
        <div class="tarjeta-cab"><h3>Antigüedad</h3></div>
        <div class="tarjeta-cuerpo compacto">
          <?php foreach (['Por vencer' => 'corriente', '1-30 días' => 'd30', '31-60 días' => 'd60', '61-90 días' => 'd90', '+90 días' => 'd120'] as $et => $k):
            if ((float) $antiguedad[$k] <= 0) { continue; } ?>
            <div class="fila-entre" style="padding:6px 0;font-size:.88rem">
              <span class="texto-2"><?= e($et) ?></span><b class="num"><?= e(q((float) $antiguedad[$k])) ?></b>
            </div>
          <?php endforeach; ?>
        </div>
      </article>
    <?php endif; ?>
  </div>
</section>

<article class="tarjeta">
  <div class="tarjeta-cab"><h3>Mis pagos</h3></div>
  <?php if ($pagos === []): ?>
    <p class="texto-3 centrado" style="padding:30px 0;margin:0">Aún no hay pagos registrados.</p>
  <?php else: ?>
    <div class="tabla-caja">
      <table class="tabla apilar">
        <thead><tr><th>Recibo</th><th class="c">Fecha</th><th>Forma</th><th class="c">Estado</th><th class="d">Monto</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($pagos as $p): ?>
            <tr>
              <td data-et="Recibo" class="fuerte"><?= e($p['recibo'] ?? '—') ?></td>
              <td data-et="Fecha" class="c texto-3"><?= e(fecha((string) $p['fecha'])) ?></td>
              <td data-et="Forma"><?= e(ucfirst((string) $p['metodo'])) ?></td>
              <td data-et="Estado" class="c">
                <span class="chip <?= e(estadoBadge((string) $p['estado'])) ?>"><?= e(ucfirst((string) $p['estado'])) ?></span>
                <?php if ($p['estado'] === 'rechazado' && !empty($p['motivo_rechazo'])): ?>
                  <div class="meta texto-3"><?= e(recortar((string) $p['motivo_rechazo'], 44)) ?></div>
                <?php endif; ?>
              </td>
              <td data-et="Monto" class="d num fuerte"><?= e(q((float) $p['monto'])) ?></td>
              <td data-et="" class="d">
                <?php if ($p['estado'] === 'aprobado'): ?>
                  <a class="btn btn-sm btn-fantasma" href="<?= e(url('/doc/recibo/' . (int) $p['id'])) ?>" target="_blank" rel="noopener" aria-label="Descargar recibo"><?= ico('archivo', 15) ?></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</article>
