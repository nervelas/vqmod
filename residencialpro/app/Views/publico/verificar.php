<section class="seccion">
  <div class="contenedor-sm">
    <?php if ($pago === null): ?>
      <div class="tarjeta">
        <div class="tarjeta-cuerpo centrado" style="padding:48px 24px">
          <div style="color:var(--grave)"><?= ico('equisCirculo', 46) ?></div>
          <h2 class="mt-2">Recibo no encontrado</h2>
          <p class="texto-2">El código de verificación no corresponde a ningún recibo emitido por la administración.
            Si recibió este documento de un tercero, por favor confírmelo directamente con la administración.</p>
          <a class="btn btn-claro mt-2" href="<?= e(url('/')) ?>">Volver al inicio</a>
        </div>
      </div>
    <?php else: ?>
      <div class="tarjeta">
        <div class="tarjeta-cuerpo">
          <div class="centrado mb-3">
            <div style="color:var(--ok)"><?= ico('checkCirculo', 46) ?></div>
            <h2 class="mt-2" style="margin-bottom:2px">Recibo auténtico</h2>
            <p class="texto-3">Emitido por la administración del residencial.</p>
          </div>
          <table class="tabla apilar">
            <tbody>
              <tr><td data-et="Recibo">Recibo</td><td class="d fuerte"><?= e($pago['recibo']) ?></td></tr>
              <tr><td data-et="Fecha">Fecha de pago</td><td class="d"><?= e(fecha((string) $pago['fecha'])) ?></td></tr>
              <tr><td data-et="Vivienda">Vivienda</td><td class="d"><?= e($pago['casa']) ?></td></tr>
              <tr><td data-et="Monto">Monto recibido</td><td class="d fuerte"><?= e(q((float) $pago['monto'])) ?></td></tr>
              <tr><td data-et="Estado">Estado</td><td class="d"><span class="chip ok">Aprobado</span></td></tr>
            </tbody>
          </table>
          <?php if ($detalle !== []): ?>
            <h3 class="mt-3" style="font-family:var(--f-texto);font-size:1rem">Conceptos aplicados</h3>
            <table class="tabla">
              <tbody>
                <?php foreach ($detalle as $d): ?>
                  <tr><td><?= e($d['concepto']) ?></td><td class="d"><?= e(q((float) $d['monto'])) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>
