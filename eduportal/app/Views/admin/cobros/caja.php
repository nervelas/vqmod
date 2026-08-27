<div class="pagina-cab">
  <div><h1>Cierre de caja</h1>
    <p class="pagina-cab__sub"><?= e(dia_nombre($fecha)) ?> <?= e(fecha($fecha)) ?> · total <strong><?= e(moneda($cierre['total'])) ?></strong></p></div>
  <div class="acciones">
    <a href="<?= e(url('cobranza/caja/pdf?' . http_build_query(['fecha' => $fecha, 'usuario' => $usuarioId]))) ?>"
       class="btn btn--linea" target="_blank" rel="noopener"><?= icono('descargar', 17) ?> PDF</a>
  </div>
</div>

<form method="get" class="filtros">
  <div class="campo"><label for="f-fecha">Fecha</label>
    <input type="date" id="f-fecha" name="fecha" value="<?= e($fecha) ?>" max="<?= e(hoy()) ?>" data-auto-envio></div>
  <?php if (App\Core\Auth::is('superadmin')): ?>
    <div class="campo"><label for="f-usuario">Cajero</label>
      <select id="f-usuario" name="usuario" data-auto-envio>
        <option value="0">Todos</option>
        <?php foreach ($usuarios as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= (int)$usuarioId === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['nombre']) ?></option>
        <?php endforeach; ?>
      </select></div>
  <?php endif; ?>
  <button type="submit" class="btn btn--linea"><?= icono('filtro', 17) ?> Consultar</button>
</form>

<div class="rejilla rejilla--4 mb-5">
  <?php foreach (['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'deposito' => 'Depósito', 'tarjeta' => 'Tarjeta'] as $k => $v): ?>
    <div class="kpi"><div class="kpi__etq"><?= e($v) ?></div>
      <div class="kpi__valor"><?= e(moneda($cierre['por_metodo'][$k] ?? 0)) ?></div></div>
  <?php endforeach; ?>
</div>

<div class="tabla-env" tabindex="0">
  <table class="tabla">
    <thead><tr><th>Recibo</th><th>Alumno</th><th>Método</th><th>Cajero</th><th class="num">Monto</th></tr></thead>
    <tbody>
    <?php foreach ($cierre['pagos'] as $p): ?>
      <tr>
        <td class="sm"><?= e($p['recibo_no'] ?? '—') ?></td>
        <td><?= e(trim($p['nombres'] . ' ' . $p['apellidos'])) ?><div class="xs txt-3"><?= e($p['codigo']) ?></div></td>
        <td class="sm txt-2"><?= e(ucfirst((string)$p['metodo'])) ?></td>
        <td class="sm txt-2"><?= e($p['cajero'] ?? '—') ?></td>
        <td class="num"><?= e(moneda((float)$p['monto'])) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($cierre['pagos'] === []): ?><tr><td colspan="5" class="tabla__vacio">No hubo movimientos en esta fecha.</td></tr><?php endif; ?>
    </tbody>
    <?php if ($cierre['pagos'] !== []): ?>
      <tfoot><tr><th colspan="4" class="num">TOTAL DEL DÍA</th><th class="num"><?= e(moneda($cierre['total'])) ?></th></tr></tfoot>
    <?php endif; ?>
  </table>
</div>
