<?php use App\Models\Cobranza; ?>
<div class="pagina-cab">
  <div><h1>Estado de cuenta</h1>
    <p class="pagina-cab__sub"><?= e(App\Models\Alumno::nombre($alumno)) ?> · <?= e($alumno['grupo'] ?? '') ?></p></div>
  <div class="acciones">
    <?php if ($pagoLink !== ''): ?>
      <a href="<?= e($pagoLink) ?>" class="btn btn--oro" target="_blank" rel="noopener"><?= icono('dinero', 17) ?> Pagar en línea</a>
    <?php endif; ?>
  </div>
</div>

<div class="rejilla rejilla--4 mb-5">
  <div class="kpi"><div class="kpi__etq">Facturado</div><div class="kpi__valor"><?= e(moneda($cuenta['total'])) ?></div></div>
  <div class="kpi"><div class="kpi__etq">Pagado</div><div class="kpi__valor"><?= e(moneda($cuenta['pagado'])) ?></div></div>
  <div class="kpi"><div class="kpi__etq">Saldo</div><div class="kpi__valor"><?= e(moneda($cuenta['saldo'])) ?></div></div>
  <div class="kpi"><div class="kpi__etq">Vencido</div>
    <div class="kpi__valor" style="color:<?= $cuenta['vencido'] > 0 ? 'var(--bad)' : 'inherit' ?>"><?= e(moneda($cuenta['vencido'])) ?></div></div>
</div>

<form method="post" enctype="multipart/form-data" action="<?= e(url('portal/comprobante')) ?>"
      data-total-cobro="#total-portal" data-moneda="<?= e(App\Core\Settings::get('moneda', 'Q')) ?>">
  <?= csrf_field() ?>

  <div class="tarjeta mb-4">
    <div class="tarjeta__cab"><h2>Cargos pendientes</h2>
      <button type="button" class="btn btn--linea btn--sm" data-pagar-todo>Seleccionar todo el saldo</button></div>
    <?php
    $pendientes = array_values(array_filter($cuenta['cargos'], static fn($c) => Cobranza::saldo($c) > 0));
    ?>
    <?php if ($pendientes === []): ?>
      <div class="vacio sm"><?= icono('check', 44) ?><p>No tiene cargos pendientes. ¡Gracias por su puntualidad!</p></div>
    <?php else: ?>
      <div class="pila">
        <?php foreach ($pendientes as $c): $saldo = Cobranza::saldo($c); $vencido = $c['fecha_vencimiento'] < hoy(); ?>
          <div class="cargo-fila <?= $vencido ? 'vencido' : 'proximo' ?>">
            <div class="cargo-fila__desc">
              <strong><?= e($c['descripcion']) ?></strong>
              <div class="sm txt-2">Vence <?= e(fecha((string)$c['fecha_vencimiento'])) ?>
                <?php if ((float)$c['mora'] > 0): ?> · mora <?= e(moneda((float)$c['mora'])) ?><?php endif; ?>
                <?php if ((float)$c['descuento'] > 0): ?> · descuento <?= e(moneda((float)$c['descuento'])) ?><?php endif; ?>
              </div>
            </div>
            <span class="cargo-fila__monto"><?= e(moneda($saldo)) ?></span>
            <div style="flex:0 0 130px">
              <input type="number" name="monto[<?= (int)$c['id'] ?>]" data-monto
                     data-saldo="<?= e(number_format($saldo, 2, '.', '')) ?>"
                     min="0" max="<?= e(number_format($saldo, 2, '.', '')) ?>" step="0.01"
                     placeholder="0.00" style="text-align:right"
                     aria-label="Monto a reportar para <?= e($c['descripcion']) ?>">
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($pendientes !== []): ?>
    <div class="tarjeta mb-5">
      <div class="tarjeta__cab"><h2>Reportar un pago</h2>
        <strong class="serif" style="font-size:1.3rem" id="total-portal">Q0.00</strong></div>
      <p class="sm txt-2">Adjunte la boleta o comprobante. La secretaría lo revisará y le confirmaremos por este portal.</p>
      <div class="fila">
        <div class="campo">
          <label for="po-metodo">Método <span class="oro">*</span></label>
          <select id="po-metodo" name="metodo" required>
            <option value="transferencia">Transferencia</option>
            <option value="deposito">Depósito bancario</option>
            <option value="tarjeta">Tarjeta</option>
            <?php if ($pagoLink !== ''): ?><option value="linea">Pago en línea</option><?php endif; ?>
          </select>
        </div>
        <div class="campo">
          <label for="po-fecha">Fecha del pago <span class="oro">*</span></label>
          <input type="date" id="po-fecha" name="fecha" required value="<?= e(hoy()) ?>" max="<?= e(hoy()) ?>">
        </div>
        <div class="campo">
          <label for="po-ref">Número de boleta o referencia</label>
          <input type="text" id="po-ref" name="referencia" maxlength="90">
        </div>
      </div>
      <div class="campo">
        <label for="po-comp">Comprobante <span class="oro">*</span></label>
        <input type="file" id="po-comp" name="comprobante" required accept="image/jpeg,image/png,image/webp,application/pdf">
        <p class="ayuda">Imagen o PDF legible del comprobante.</p>
      </div>
      <div class="campo">
        <label for="po-notas">Comentario</label>
        <input type="text" id="po-notas" name="notas" maxlength="255">
      </div>
      <button type="submit" class="btn"><?= icono('subir', 17) ?> Enviar comprobante</button>
    </div>
  <?php endif; ?>
</form>

<div class="tarjeta tarjeta--plana">
  <div class="tarjeta__cab"><h2>Historial de pagos</h2></div>
  <div class="tabla-env" tabindex="0" style="border:0">
    <table class="tabla">
      <thead><tr><th>Recibo</th><th>Fecha</th><th>Método</th><th class="num">Monto</th><th class="cen">Estado</th><th class="cen"></th></tr></thead>
      <tbody>
      <?php foreach ($pagos as $p): ?>
        <tr>
          <td class="sm"><?= e($p['recibo_no'] ?? '—') ?></td>
          <td class="sm"><?= e(fecha((string)$p['fecha'])) ?></td>
          <td class="sm txt-2"><?= e(ucfirst((string)$p['metodo'])) ?></td>
          <td class="num"><?= e(moneda((float)$p['monto'])) ?></td>
          <td class="cen"><span class="badge badge--<?= e(estado_badge((string)$p['estado'])) ?>"><?= e(ucfirst((string)$p['estado'])) ?></span>
            <?php if (!empty($p['motivo_rechazo'])): ?><div class="xs nota-baja"><?= e($p['motivo_rechazo']) ?></div><?php endif; ?></td>
          <td class="cen">
            <?php if ($p['estado'] === 'aprobado'): ?>
              <a class="btn btn--fantasma btn--sm" target="_blank" rel="noopener" href="<?= e(url('recibo/' . (int)$p['id'])) ?>" aria-label="Descargar recibo"><?= icono('descargar', 15) ?></a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($pagos === []): ?><tr><td colspan="6" class="tabla__vacio">Aún no hay pagos registrados.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
