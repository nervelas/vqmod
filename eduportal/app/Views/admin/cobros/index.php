<?php use App\Core\Auth; ?>
<div class="pagina-cab">
  <div><h1>Cobranza</h1><p class="pagina-cab__sub">Control de ingresos, cargos y comprobantes</p></div>
  <div class="acciones">
    <?php if (Auth::can('cobranza.editar')): ?>
      <a href="<?= e(url('cobranza/conceptos')) ?>" class="btn btn--linea"><?= icono('config', 17) ?> Conceptos</a>
      <a href="<?= e(url('cobranza/generar')) ?>" class="btn btn--linea"><?= icono('mas', 17) ?> Generar cargos</a>
    <?php endif; ?>
    <a href="<?= e(url('cobranza/caja')) ?>" class="btn btn--linea"><?= icono('recibo', 17) ?> Cierre de caja</a>
    <a href="<?= e(url('cobranza/morosidad')) ?>" class="btn"><?= icono('reporte', 17) ?> Morosidad</a>
  </div>
</div>

<div class="rejilla rejilla--4 mb-5">
  <div class="kpi"><div class="kpi__etq">Ingresos del mes</div><div class="kpi__valor"><?= e(moneda($kpi['ingresos_mes'])) ?></div></div>
  <div class="kpi"><div class="kpi__etq">Por cobrar (ciclo)</div><div class="kpi__valor"><?= e(moneda($kpi['por_cobrar'])) ?></div></div>
  <div class="kpi"><div class="kpi__etq">Vencido</div><div class="kpi__valor" style="color:var(--bad)"><?= e(moneda($kpi['morosidad'])) ?></div></div>
  <div class="kpi"><div class="kpi__etq">Por aprobar</div><div class="kpi__valor"><?= (int)$kpi['por_aprobar'] ?></div></div>
</div>

<div class="tarjeta tarjeta--plana mb-5">
  <div class="tarjeta__cab"><h2>Comprobantes en revisión</h2></div>
  <div class="tabla-env" tabindex="0" style="border:0">
    <table class="tabla">
      <thead><tr><th>Fecha</th><th>Alumno</th><th>Método</th><th class="num">Monto</th><th>Comprobante</th><th class="cen">Acción</th></tr></thead>
      <tbody>
      <?php foreach ($porAprobar as $p): ?>
        <tr>
          <td class="sm"><?= e(fecha((string)$p['fecha'])) ?></td>
          <td><a href="<?= e(url('cobranza/estado/' . (int)$p['alumno_id'])) ?>"><?= e(trim($p['nombres'] . ' ' . $p['apellidos'])) ?></a>
            <div class="xs txt-3"><?= e($p['codigo']) ?></div></td>
          <td class="sm"><?= e(ucfirst((string)$p['metodo'])) ?>
            <?php if (!empty($p['referencia'])): ?><div class="xs txt-3">Ref. <?= e($p['referencia']) ?></div><?php endif; ?></td>
          <td class="num negrita"><?= e(moneda((float)$p['monto'])) ?></td>
          <td>
            <?php if (!empty($p['comprobante'])): ?>
              <a href="<?= e(archivo_url($p['comprobante'])) ?>" target="_blank" rel="noopener" class="btn btn--fantasma btn--sm"><?= icono('ver', 15) ?> Ver</a>
            <?php else: ?><span class="txt-3 sm">—</span><?php endif; ?>
          </td>
          <td class="cen">
            <?php if (Auth::can('pagos.aprobar')): ?>
              <div class="flex" style="justify-content:center;gap:4px">
                <form method="post" action="<?= e(url('pago/' . (int)$p['id'] . '/aprobar')) ?>"
                      data-confirmar="¿Aprobar este pago y emitir el recibo?">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn--sm"><?= icono('check', 15) ?> Aprobar</button>
                </form>
                <button type="button" class="btn btn--linea btn--sm" aria-label="Rechazar comprobante" data-modal="modal-rechazo"
                        data-valores='{"pago_id":"<?= (int)$p['id'] ?>"}'><?= icono('x', 15) ?></button>
              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($porAprobar === []): ?>
        <tr><td colspan="6" class="tabla__vacio"><?= icono('check', 40) ?><p>No hay comprobantes pendientes de revisión.</p></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="split">
  <div class="tarjeta tarjeta--plana">
    <div class="tarjeta__cab"><h2>Últimos pagos aprobados</h2>
      <a href="<?= e(url('cobranza/pagos')) ?>" class="btn btn--fantasma btn--sm">Ver todos</a></div>
    <div class="tabla-env" tabindex="0" style="border:0">
      <table class="tabla">
        <thead><tr><th>Recibo</th><th>Alumno</th><th>Fecha</th><th class="num">Monto</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($ultimos as $p): ?>
          <tr>
            <td class="sm"><?= e($p['recibo_no'] ?? '—') ?></td>
            <td class="truncar"><?= e(trim($p['nombres'] . ' ' . $p['apellidos'])) ?></td>
            <td class="sm"><?= e(fecha((string)$p['fecha'])) ?></td>
            <td class="num"><?= e(moneda((float)$p['monto'])) ?></td>
            <td class="cen"><a class="btn btn--fantasma btn--sm" target="_blank" rel="noopener"
                 href="<?= e(url('recibo/' . (int)$p['id'])) ?>" aria-label="Descargar recibo"><?= icono('descargar', 15) ?></a></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($ultimos === []): ?><tr><td colspan="5" class="tabla__vacio">Sin pagos registrados.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tarjeta tarjeta--plana">
    <div class="tarjeta__cab"><h2>Próximos vencimientos</h2></div>
    <div class="tabla-env" tabindex="0" style="border:0">
      <table class="tabla">
        <thead><tr><th>Alumno</th><th>Concepto</th><th>Vence</th><th class="num">Saldo</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($proximos, 0, 12) as $c): ?>
          <tr>
            <td class="truncar sm"><?= e(trim($c['nombres'] . ' ' . $c['apellidos'])) ?></td>
            <td class="sm txt-2 truncar"><?= e($c['descripcion']) ?></td>
            <td class="sm"><?= e(fecha((string)$c['fecha_vencimiento'])) ?></td>
            <td class="num"><?= e(moneda(App\Models\Cobranza::saldo($c))) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($proximos === []): ?><tr><td colspan="4" class="tabla__vacio">Sin vencimientos próximos.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if (Auth::can('pagos.aprobar')): ?>
<div class="modal" id="modal-rechazo" aria-hidden="true" role="dialog" aria-label="Rechazar comprobante">
  <div class="modal__fondo" data-cerrar></div>
  <div class="modal__caja">
    <form method="post" action="<?= e(url('pago/0/rechazar')) ?>" data-form-rechazo>
      <?= csrf_field() ?>
      <div class="modal__cab"><h3>Rechazar comprobante</h3>
        <button type="button" class="btn btn--fantasma btn--sm" data-cerrar>Cerrar</button></div>
      <div class="modal__cuerpo">
        <input type="hidden" name="pago_id" value="" data-pago-id>
        <div class="campo">
          <label for="motivo">Motivo del rechazo <span class="oro">*</span></label>
          <textarea id="motivo" name="motivo" required minlength="5" maxlength="255"
                    placeholder="Ejemplo: el comprobante no corresponde al monto indicado."></textarea>
          <p class="ayuda">El encargado recibirá este mensaje en su portal.</p>
        </div>
      </div>
      <div class="modal__pie">
        <button type="button" class="btn btn--linea" data-cerrar>Cancelar</button>
        <button type="submit" class="btn btn--peligro">Rechazar</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
