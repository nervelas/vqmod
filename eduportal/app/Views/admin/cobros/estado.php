<?php use App\Core\Auth; use App\Models\Cobranza; ?>
<div class="pagina-cab">
  <div>
    <h1>Estado de cuenta</h1>
    <p class="pagina-cab__sub">
      <a href="<?= e(url('alumnos/' . (int)$alumno['id'])) ?>"><?= e(App\Models\Alumno::nombre($alumno)) ?></a>
      · <?= e($alumno['codigo']) ?> · <?= e($alumno['grupo'] ?? 'Sin grado') ?>
    </p>
  </div>
  <div class="acciones">
    <?php $enc = $encargado; if ($enc && !empty($enc['telefono']) && $cuenta['saldo'] > 0): ?>
      <a class="btn btn--linea" target="_blank" rel="noopener"
         href="<?= e(App\Servicios\Recordatorios::enlaceWhatsApp((int)$alumno['id'], (float)$cuenta['saldo'], date('Y-m-d'))) ?>">
        <?= icono('whatsapp', 17) ?> Recordar por WhatsApp
      </a>
    <?php endif; ?>
    <?php if (Auth::can('cobranza.editar')): ?>
      <button type="button" class="btn btn--linea" data-modal="modal-cargo"><?= icono('mas', 17) ?> Cargo manual</button>
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

<?php if (Auth::can('cobranza.editar')): ?>
<form method="post" action="<?= e(url('cobranza/cobrar/' . (int)$alumno['id'])) ?>"
      data-total-cobro="#total-cobro" data-moneda="<?= e(App\Core\Settings::get('moneda', 'Q')) ?>">
  <?= csrf_field() ?>
<?php endif; ?>

<div class="tarjeta tarjeta--plana mb-4">
  <div class="tarjeta__cab">
    <h2>Cargos del ciclo</h2>
    <?php if (Auth::can('cobranza.editar')): ?>
      <button type="button" class="btn btn--linea btn--sm" data-pagar-todo>Pagar todo el saldo</button>
    <?php endif; ?>
  </div>
  <div class="tabla-env" tabindex="0" style="border:0">
    <table class="tabla">
      <thead><tr>
        <th>Concepto</th><th>Vence</th><th class="num">Monto</th><th class="num">Desc.</th>
        <th class="num">Mora</th><th class="num">Pagado</th><th class="num">Saldo</th><th class="cen">Estado</th>
        <?php if (Auth::can('cobranza.editar')): ?><th class="num" style="min-width:130px">Abonar</th><?php endif; ?>
      </tr></thead>
      <tbody>
      <?php $hoy = hoy(); foreach ($cuenta['cargos'] as $c): $saldo = Cobranza::saldo($c); ?>
        <tr>
          <td><?= e($c['descripcion']) ?></td>
          <td class="sm <?= $saldo > 0 && $c['fecha_vencimiento'] < $hoy ? 'nota-baja' : '' ?>"><?= e(fecha((string)$c['fecha_vencimiento'])) ?></td>
          <td class="num"><?= e(moneda((float)$c['monto'])) ?></td>
          <td class="num"><?= (float)$c['descuento'] > 0 ? e(moneda((float)$c['descuento'])) : '—' ?></td>
          <td class="num"><?= (float)$c['mora'] > 0 ? '<span class="nota-baja">' . e(moneda((float)$c['mora'])) . '</span>' : '—' ?></td>
          <td class="num"><?= e(moneda((float)$c['pagado'])) ?></td>
          <td class="num negrita"><?= e(moneda($saldo)) ?></td>
          <td class="cen"><span class="badge badge--<?= e(estado_badge((string)$c['estado'])) ?>"><?= e(ucfirst((string)$c['estado'])) ?></span></td>
          <?php if (Auth::can('cobranza.editar')): ?>
            <td class="num">
              <?php if ($saldo > 0): ?>
                <input type="number" name="monto[<?= (int)$c['id'] ?>]" data-monto data-saldo="<?= e(number_format($saldo, 2, '.', '')) ?>"
                       min="0" max="<?= e(number_format($saldo, 2, '.', '')) ?>" step="0.01" placeholder="0.00" style="text-align:right">
              <?php else: ?>
                <span class="txt-3 sm">—</span>
              <?php endif; ?>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if ($cuenta['cargos'] === []): ?>
        <tr><td colspan="9" class="tabla__vacio"><?= icono('dinero', 40) ?><p>Este alumno no tiene cargos en el ciclo actual.</p></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (Auth::can('cobranza.editar')): ?>
  <div class="tarjeta mb-5">
    <div class="tarjeta__cab"><h2>Registrar pago</h2>
      <strong class="serif" style="font-size:1.3rem" id="total-cobro">Q0.00</strong></div>
    <div class="fila">
      <div class="campo">
        <label for="p-metodo">Método <span class="oro">*</span></label>
        <select id="p-metodo" name="metodo" required>
          <?php foreach (['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'deposito' => 'Depósito',
                          'tarjeta' => 'Tarjeta', 'linea' => 'Pago en línea'] as $k => $v): ?>
            <option value="<?= e($k) ?>"><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="p-fecha">Fecha <span class="oro">*</span></label>
        <input type="date" id="p-fecha" name="fecha" required value="<?= e(hoy()) ?>" max="<?= e(hoy()) ?>">
      </div>
      <div class="campo">
        <label for="p-ref">Referencia / boleta</label>
        <input type="text" id="p-ref" name="referencia" maxlength="90">
      </div>
    </div>
    <div class="campo">
      <label for="p-notas">Notas</label>
      <input type="text" id="p-notas" name="notas" maxlength="255">
    </div>
    <button type="submit" class="btn"><?= icono('recibo', 17) ?> Registrar pago y emitir recibo</button>
  </div>
</form>
<?php endif; ?>

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
            <?php if (!empty($p['motivo_rechazo'])): ?><div class="xs txt-3"><?= e($p['motivo_rechazo']) ?></div><?php endif; ?></td>
          <td class="cen">
            <div class="flex" style="justify-content:center;gap:4px">
              <?php if ($p['estado'] === 'aprobado'): ?>
                <a class="btn btn--fantasma btn--sm" target="_blank" rel="noopener" href="<?= e(url('recibo/' . (int)$p['id'])) ?>" aria-label="Descargar recibo"><?= icono('descargar', 15) ?></a>
              <?php endif; ?>
              <?php if (Auth::is('superadmin') && in_array($p['estado'], ['aprobado', 'revision'], true)): ?>
                <form method="post" action="<?= e(url('pago/' . (int)$p['id'] . '/anular')) ?>"
                      data-confirmar="¿Anular este pago? Los cargos volverán a quedar pendientes.">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn--fantasma btn--sm" aria-label="Anular pago"><?= icono('x', 15) ?></button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($pagos === []): ?><tr><td colspan="6" class="tabla__vacio">Sin pagos registrados.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (Auth::can('cobranza.editar')): ?>
<div class="modal" id="modal-cargo" aria-hidden="true" role="dialog" aria-label="Cargo manual">
  <div class="modal__fondo" data-cerrar></div>
  <div class="modal__caja">
    <form method="post" action="<?= e(url('cobranza/cargo/' . (int)$alumno['id'])) ?>">
      <?= csrf_field() ?>
      <div class="modal__cab"><h3>Agregar cargo manual</h3>
        <button type="button" class="btn btn--fantasma btn--sm" data-cerrar>Cerrar</button></div>
      <div class="modal__cuerpo">
        <div class="campo">
          <label for="cm-desc">Descripción <span class="oro">*</span></label>
          <input type="text" id="cm-desc" name="descripcion" required maxlength="160" placeholder="Excursión educativa">
        </div>
        <div class="fila fila--3">
          <div class="campo">
            <label for="cm-monto">Monto <span class="oro">*</span></label>
            <input type="number" id="cm-monto" name="monto" required min="0.01" step="0.01">
          </div>
          <div class="campo">
            <label for="cm-desc-monto">Descuento</label>
            <input type="number" id="cm-desc-monto" name="descuento" min="0" step="0.01" value="0">
          </div>
          <div class="campo">
            <label for="cm-vence">Vence <span class="oro">*</span></label>
            <input type="date" id="cm-vence" name="fecha_vencimiento" required value="<?= e(date('Y-m-d', strtotime('+15 days'))) ?>">
          </div>
        </div>
      </div>
      <div class="modal__pie">
        <button type="button" class="btn btn--linea" data-cerrar>Cancelar</button>
        <button type="submit" class="btn">Agregar cargo</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
