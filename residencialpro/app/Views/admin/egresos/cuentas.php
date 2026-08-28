<section class="rejilla rejilla-4 mb-3">
  <article class="kpi">
    <div class="kpi-et"><?= ico('moneda', 15) ?> Saldo total</div>
    <div class="kpi-valor"><?= e(q($total)) ?></div>
    <div class="kpi-nota">Caja y bancos</div>
  </article>
  <?php foreach ($cuentas as $c): ?>
    <article class="kpi">
      <div class="kpi-et"><?= ico($c['tipo'] === 'caja' ? 'billetera' : 'edificio', 15) ?> <?= e(recortar((string) $c['nombre'], 24)) ?></div>
      <div class="kpi-valor"><?= e(q((float) $c['saldo'])) ?></div>
      <div class="kpi-nota">+<?= e(q((float) $c['ingresos'])) ?> · −<?= e(q((float) $c['egresos'])) ?></div>
    </article>
  <?php endforeach; ?>
</section>

<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,340px)">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Cuentas</h3></div>
    <div class="tabla-caja">
      <table class="tabla apilar">
        <thead><tr><th>Cuenta</th><th>Banco</th><th>Número</th><th class="d">Saldo inicial</th><th class="d">Saldo actual</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($cuentas as $c): ?>
            <tr>
              <td data-et="Cuenta" class="fuerte"><?= e($c['nombre']) ?> <span class="chip neutro"><?= e(ucfirst((string) $c['tipo'])) ?></span></td>
              <td data-et="Banco" class="texto-2"><?= e($c['banco'] ?? '—') ?></td>
              <td data-et="Número" class="texto-3"><?= e($c['numero'] ?? '—') ?></td>
              <td data-et="Saldo inicial" class="d num"><?= e(q((float) $c['saldo_inicial'])) ?></td>
              <td data-et="Saldo actual" class="d num fuerte"><?= e(q((float) $c['saldo'])) ?></td>
              <td data-et="" class="d">
                <button class="btn btn-sm btn-fantasma" type="button" aria-label="Editar cuenta"
                        data-cuenta="<?= e(json_encode($c, JSON_UNESCAPED_UNICODE)) ?>"><?= ico('editar', 15) ?></button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </article>

  <form method="post" id="f-cuenta" style="align-self:start">
    <?= csrf() ?>
    <input type="hidden" name="id" id="cta-id" value="0">
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3>Agregar o editar</h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campo"><label for="cta-nombre">Nombre *</label>
          <input type="text" id="cta-nombre" name="nombre" required maxlength="120"></div>
        <div class="campo"><label for="cta-tipo">Tipo</label>
          <select id="cta-tipo" name="tipo"><option value="banco">Banco</option><option value="caja">Caja</option></select></div>
        <div class="campo"><label for="cta-banco">Banco</label><input type="text" id="cta-banco" name="banco" maxlength="90"></div>
        <div class="campo"><label for="cta-numero">Número de cuenta</label><input type="text" id="cta-numero" name="numero" maxlength="60"></div>
        <div class="campo"><label for="cta-saldo">Saldo inicial</label>
          <input type="number" id="cta-saldo" name="saldo_inicial" step="0.01" value="0"></div>
        <label class="marca-check"><input type="checkbox" name="activo" value="1" checked id="cta-activo"><span>Cuenta activa</span></label>
      </div>
      <div class="tarjeta-pie fila-fin">
        <button class="btn btn-oro btn-sm" type="submit"><?= ico('guardar', 15) ?> Guardar</button>
      </div>
    </div>
  </form>
</div>
<script<?= nonce() ?>>
document.querySelectorAll('[data-cuenta]').forEach(function (b) {
  b.addEventListener('click', function () {
    var c = JSON.parse(b.dataset.cuenta);
    document.getElementById('cta-id').value = c.id;
    document.getElementById('cta-nombre').value = c.nombre || '';
    document.getElementById('cta-tipo').value = c.tipo || 'banco';
    document.getElementById('cta-banco').value = c.banco || '';
    document.getElementById('cta-numero').value = c.numero || '';
    document.getElementById('cta-saldo').value = c.saldo_inicial || 0;
    document.getElementById('cta-activo').checked = Number(c.activo) === 1;
  });
});
</script>
