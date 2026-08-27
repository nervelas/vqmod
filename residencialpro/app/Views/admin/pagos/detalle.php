<div class="fila-entre mb-3">
  <a class="btn btn-claro btn-sm" href="<?= e(url('/admin/pagos')) ?>"><?= ico('flechaIzq', 16) ?> Volver a pagos</a>
  <div class="fila" style="gap:8px">
    <?php if ($pago['estado'] === 'aprobado'): ?>
      <a class="btn btn-claro" href="<?= e(url('/doc/recibo/' . (int) $pago['id'])) ?>" target="_blank" rel="noopener"><?= ico('archivo', 17) ?> Recibo PDF</a>
      <?php if (!empty($pago['verificacion'])): ?>
        <button class="btn btn-claro" type="button" data-copiar="<?= e(\App\Core\Url::absoluta('/verificar/' . $pago['verificacion'])) ?>">
          <?= ico('qr', 17) ?> Copiar enlace de verificación
        </button>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,360px)">
  <article class="tarjeta">
    <div class="tarjeta-cab">
      <h3>Recibo <?= e($pago['recibo'] ?? '—') ?></h3>
      <span class="chip <?= e(estadoBadge((string) $pago['estado'])) ?>"><?= e(ucfirst((string) $pago['estado'])) ?></span>
    </div>
    <div class="tarjeta-cuerpo">
      <table class="tabla apilar mb-3">
        <tbody>
          <tr><td data-et="Vivienda" class="texto-3">Vivienda</td><td class="d fuerte"><a href="<?= e(url('/admin/casas/' . (int) $pago['casa_id'])) ?>"><?= e($pago['casa']) ?></a></td></tr>
          <tr><td data-et="Residente" class="texto-3">Residente</td><td class="d"><?= e($residente['nombre'] ?? '—') ?></td></tr>
          <tr><td data-et="Fecha" class="texto-3">Fecha del pago</td><td class="d"><?= e(fecha((string) $pago['fecha'])) ?></td></tr>
          <tr><td data-et="Forma" class="texto-3">Forma de pago</td><td class="d"><?= e(ucfirst((string) $pago['metodo'])) ?></td></tr>
          <?php if (!empty($pago['banco'])): ?><tr><td data-et="Banco" class="texto-3">Banco</td><td class="d"><?= e($pago['banco']) ?></td></tr><?php endif; ?>
          <?php if (!empty($pago['referencia'])): ?><tr><td data-et="Referencia" class="texto-3">Referencia</td><td class="d"><?= e($pago['referencia']) ?></td></tr><?php endif; ?>
          <?php if (!empty($pago['notas'])): ?><tr><td data-et="Notas" class="texto-3">Notas</td><td class="d"><?= e($pago['notas']) ?></td></tr><?php endif; ?>
        </tbody>
      </table>

      <h4>Conceptos aplicados</h4>
      <table class="tabla">
        <thead><tr><th>Concepto</th><th class="d">Monto</th></tr></thead>
        <tbody>
          <?php foreach ($detalle as $d): ?>
            <tr>
              <td><?= e($d['concepto']) ?><?= empty($d['cargo_id']) ? ' <span class="chip oro">A favor</span>' : '' ?></td>
              <td class="d num"><?= e(q((float) $d['monto'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td>Total recibido</td><td class="d num"><?= e(q((float) $pago['monto'])) ?></td></tr></tfoot>
      </table>
    </div>

    <?php if ($pago['estado'] === 'aprobado' && esRol('admin')): ?>
      <div class="tarjeta-pie">
        <form method="post" action="<?= e(url('/admin/pagos/' . (int) $pago['id'] . '/anular')) ?>"
              data-confirmar="Los cargos volverán a quedar pendientes y el recibo dejará de ser válido."
              data-confirmar-titulo="¿Anular este pago?" data-confirmar-boton="Sí, anular el pago">
          <?= csrf() ?>
          <div class="fila envolver" style="gap:10px">
            <input type="text" name="motivo" placeholder="Motivo de la anulación" required minlength="5" class="crecer">
            <button class="btn btn-peligro" type="submit"><?= ico('equisCirculo', 16) ?> Anular pago</button>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </article>

  <div class="columna">
    <article class="tarjeta">
      <div class="tarjeta-cuerpo centrado">
        <div class="mayus">Monto recibido</div>
        <div class="kpi-valor"><?= e(q((float) $pago['monto'])) ?></div>
        <div class="texto-3" style="font-size:.85rem">
          Saldo actual de la vivienda: <b><?= e(q($saldo)) ?></b>
        </div>
      </div>
    </article>

    <?php if (!empty($pago['comprobante'])): ?>
      <article class="tarjeta">
        <div class="tarjeta-cab"><h3>Comprobante</h3></div>
        <div class="tarjeta-cuerpo compacto">
          <?php $ext = strtolower(pathinfo((string) $pago['comprobante'], PATHINFO_EXTENSION)); ?>
          <?php if ($ext === 'pdf'): ?>
            <a class="btn btn-claro btn-bloque" target="_blank" rel="noopener"
               href="<?= e(url('/archivo/comprobantes/' . $pago['comprobante'])) ?>"><?= ico('archivo', 17) ?> Ver el PDF</a>
          <?php else: ?>
            <a target="_blank" rel="noopener" href="<?= e(url('/archivo/comprobantes/' . $pago['comprobante'])) ?>">
              <img src="<?= e(url('/archivo/comprobantes/' . $pago['comprobante'])) ?>" alt="Comprobante de pago"
                   style="border-radius:var(--r-sm);width:100%">
            </a>
          <?php endif; ?>
        </div>
      </article>
    <?php endif; ?>

    <?php if (!empty($pago['verificacion'])): ?>
      <article class="tarjeta">
        <div class="tarjeta-cab"><h3>Verificación pública</h3></div>
        <div class="tarjeta-cuerpo compacto centrado">
          <p class="texto-3" style="font-size:.84rem">Cualquiera puede confirmar la autenticidad de este recibo con el código:</p>
          <code style="font-size:1rem;letter-spacing:.05em"><?= e(strtoupper(substr((string) $pago['verificacion'], 0, 12))) ?></code>
          <div class="mt-2">
            <a class="btn btn-sm btn-claro" target="_blank" rel="noopener"
               href="<?= e(url('/verificar/' . $pago['verificacion'])) ?>"><?= ico('escudo', 15) ?> Probar verificación</a>
          </div>
        </div>
      </article>
    <?php endif; ?>
  </div>
</div>
