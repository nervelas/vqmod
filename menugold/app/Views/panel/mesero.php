<?php
/** @var array $tablero, $llamadas, $sinMesa */
use MenuGold\Core\Security;
use MenuGold\Core\View;
use MenuGold\Models\Order;
View::set('titulo', 'Mesas y caja');
View::set('subtitulo', 'Estado de tus mesas en tiempo real');
$s = (string)($r['simbolo'] ?? 'Q');
$estados = ['libre' => 'Libre', 'ocupada' => 'Ocupada', 'cuenta' => 'Pidió la cuenta', 'llamada' => 'Te llama'];
?>
<div id="cajaLlamadas">
  <?php if ($llamadas): ?>
    <div class="tarjeta-p" style="border-color:var(--p-peligro)">
      <div class="tarjeta-p__cab">
        <h2 class="tarjeta-p__titulo"><?= icon('bell') ?> Mesas que te llaman</h2>
        <span class="insignia insignia--peligro"><?= count($llamadas) ?></span>
      </div>
      <div id="listaLlamadas">
        <?php foreach ($llamadas as $l): ?>
          <div class="llamada-fila" data-llamada="<?= (int)$l['id'] ?>">
            <?= icon($l['tipo'] === 'cuenta' ? 'receipt' : 'bell') ?>
            <div class="crece">
              <strong><?= e((string)$l['mesa_nombre']) ?></strong> ·
              <?= $l['tipo'] === 'cuenta' ? 'pide la cuenta' : 'llama al mesero' ?>
              <div style="font-size:12px;color:var(--p-tenue)">Hace <?= (int)floor((time() - strtotime((string)$l['creado'])) / 60) ?> min</div>
            </div>
            <button class="bt bt--sm bt--exito" type="button" data-atender="<?= (int)$l['id'] ?>"><?= icon('check', 'ico-sm') ?> Atendida</button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="tarjeta-p">
  <div class="tarjeta-p__cab">
    <h2 class="tarjeta-p__titulo"><?= icon('table') ?> Tablero de mesas</h2>
    <div class="acciones">
      <span class="insignia insignia--exito" id="contLibres">—</span>
      <span class="insignia insignia--info" id="contOcupadas">—</span>
    </div>
  </div>
  <?php if (!$tablero): ?>
    <div class="vacio-p">
      <?= icon('table', 'ico-lg') ?>
      <h3>Sin mesas registradas</h3>
      <p>Registra tus mesas para llevar el control de la sala.</p>
      <a class="bt bt--oro" href="<?= e(url('panel/mesas')) ?>"><?= icon('plus') ?> Crear mesas</a>
    </div>
  <?php else: ?>
    <div class="mesas-rejilla" id="rejillaMesas">
      <?php foreach ($tablero as $m): ?>
        <a class="mesa-tarjeta mesa--<?= e((string)$m['estado']) ?>" href="<?= e(url('panel/mesero/mesa/' . $m['id'])) ?>"
           data-mesa="<?= (int)$m['id'] ?>">
          <?php if ((int)$m['llamadas'] > 0): ?>
            <span class="mesa-tarjeta__campana"><?= icon('bell', 'ico-sm') ?></span>
          <?php endif; ?>
          <span class="mesa-tarjeta__nombre"><?= e((string)$m['nombre']) ?></span>
          <?php if (!empty($m['zona'])): ?><span class="mesa-tarjeta__zona"><?= e((string)$m['zona']) ?></span><?php endif; ?>
          <span class="mesa-tarjeta__estado"><?= e($estados[(string)$m['estado']] ?? '') ?></span>
          <?php if ((float)$m['cuenta'] > 0): ?>
            <span class="mesa-tarjeta__total"><?= e(money($m['cuenta'], $s)) ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($sinMesa): ?>
  <div class="tarjeta-p tarjeta-p--plana">
    <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('box') ?> Para llevar y domicilio</h2></div>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Código</th><th>Cliente</th><th>Tipo</th><th>Estado</th><th class="num">Total</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($sinMesa as $o): ?>
            <tr>
              <td class="mono"><?= e((string)$o['codigo']) ?></td>
              <td><?= e($o['cliente_nombre'] ?: '—') ?><br>
                  <small style="color:var(--p-tenue)"><?= e((string)$o['cliente_telefono']) ?></small></td>
              <td><?= e(Order::etiquetaModo((string)$o['modo'])) ?></td>
              <td><span class="insignia insignia--<?= $o['estado'] === 'listo' ? 'exito' : 'aviso' ?>">
                  <?= e(Order::ETIQUETA_ESTADO[$o['estado']] ?? '') ?></span></td>
              <td class="num"><?= e(money($o['total'], $s)) ?></td>
              <td class="tabla__acciones">
                <a class="bt bt--sm bt--suave" href="<?= e(url('panel/pedidos/' . $o['id'])) ?>"><?= icon('eye', 'ico-sm') ?></a>
                <button class="bt bt--sm bt--oro" type="button" data-cobrar-directo="<?= (int)$o['id'] ?>"
                        data-total="<?= e((string)$o['total']) ?>"><?= icon('money', 'ico-sm') ?> Cobrar</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- ============ Modal de cobro rápido ============ -->
<div class="modal-p" id="modalCobro" role="dialog" aria-modal="true">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja" style="width:min(440px,calc(100vw - 28px))">
    <div class="modal-p__cab">
      <h2 class="modal-p__titulo">Cobrar pedido</h2>
      <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
    </div>
    <div class="modal-p__cuerpo">
      <div style="text-align:center;margin-bottom:16px">
        <div style="font-size:13px;color:var(--p-tenue)">Total a cobrar</div>
        <div style="font-size:32px;font-weight:800;color:var(--p-oro)" id="cobroTotal">—</div>
      </div>
      <div class="campo-p">
        <label for="cobroMetodo">Método de pago</label>
        <select id="cobroMetodo">
          <?php foreach (array_filter(array_map('trim', explode(',', (string)$r['metodos_pago']))) as $m): ?>
            <option value="<?= e($m) ?>"><?= e(ucfirst($m)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo-p">
        <label for="cobroRecibido">Con cuánto paga (opcional)</label>
        <div class="grupo-prefijo">
          <span><?= e($s) ?></span>
          <input type="number" id="cobroRecibido" step="0.01" min="0" inputmode="decimal" placeholder="0.00">
        </div>
        <p class="ayuda-p" id="cobroCambio"></p>
      </div>
    </div>
    <div class="modal-p__pie">
      <button class="bt bt--linea" type="button" data-cerrar-modal>Cancelar</button>
      <button class="bt bt--exito" type="button" id="cobroConfirmar"><?= icon('check') ?> Cobrar</button>
    </div>
  </div>
</div>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
  var M = window.MGPanel;
  var ESTADOS = <?= json_encode($estados, JSON_UNESCAPED_UNICODE) ?>;
  var pedidoCobro = null;

  function pintar(d) {
    var libres = 0, ocupadas = 0;
    (d.mesas || []).forEach(function (m) {
      var el = document.querySelector('[data-mesa="' + m.id + '"]');
      if (m.estado === 'libre') libres++; else ocupadas++;
      if (!el) return;
      el.className = 'mesa-tarjeta mesa--' + m.estado;
      var est = el.querySelector('.mesa-tarjeta__estado');
      if (est) est.textContent = ESTADOS[m.estado] || '';
      var tot = el.querySelector('.mesa-tarjeta__total');
      if (m.cuenta > 0) {
        if (!tot) { tot = document.createElement('span'); tot.className = 'mesa-tarjeta__total'; el.appendChild(tot); }
        tot.textContent = M.money(m.cuenta);
      } else if (tot) { tot.remove(); }
      var camp = el.querySelector('.mesa-tarjeta__campana');
      if (m.llamadas > 0 && !camp) {
        var c = document.createElement('span');
        c.className = 'mesa-tarjeta__campana';
        c.innerHTML = '<svg class="ico ico-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>';
        el.appendChild(c);
      } else if (m.llamadas === 0 && camp) { camp.remove(); }
    });
    var cl = document.getElementById('contLibres');
    var co = document.getElementById('contOcupadas');
    if (cl) cl.textContent = libres + ' libres';
    if (co) co.textContent = ocupadas + ' ocupadas';
    pintarLlamadas(d.llamadas || []);
  }

  function pintarLlamadas(lista) {
    var caja = document.getElementById('cajaLlamadas');
    if (!caja) return;
    if (!lista.length) { caja.innerHTML = ''; return; }
    var h = '<div class="tarjeta-p" style="border-color:var(--p-peligro)">'
      + '<div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo">'
      + '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>'
      + ' Mesas que te llaman</h2><span class="insignia insignia--peligro">' + lista.length + '</span></div><div>';
    lista.forEach(function (l) {
      h += '<div class="llamada-fila"><div class="crece"><strong>' + M.esc(l.mesa) + '</strong> · '
        + (l.tipo === 'cuenta' ? 'pide la cuenta' : 'llama al mesero')
        + '<div style="font-size:12px;color:var(--p-tenue)">Hace ' + l.hace + ' min</div></div>'
        + '<button class="bt bt--sm bt--exito" type="button" data-atender="' + l.id + '">Atendida</button></div>';
    });
    caja.innerHTML = h + '</div></div>';
  }

  document.addEventListener('click', function (ev) {
    var a = ev.target.closest('[data-atender]');
    if (a) {
      ev.preventDefault();
      a.disabled = true;
      M.pedir('panel/mesero/llamada', { id: Number(a.dataset.atender) }).then(function (r) {
        if (r.ok) { pintar(r); M.avisar(r.mensaje, 'ok'); }
        else { a.disabled = false; M.avisar(r.error, 'error'); }
      });
      return;
    }
    var c = ev.target.closest('[data-cobrar-directo]');
    if (c) {
      ev.preventDefault();
      pedidoCobro = { id: Number(c.dataset.cobrarDirecto), total: Number(c.dataset.total) };
      document.getElementById('cobroTotal').textContent = M.money(pedidoCobro.total);
      document.getElementById('cobroRecibido').value = '';
      document.getElementById('cobroCambio').textContent = '';
      M.abrirModal('modalCobro');
    }
  });

  var rec = document.getElementById('cobroRecibido');
  if (rec) rec.addEventListener('input', function () {
    var v = parseFloat(rec.value) || 0;
    var cambio = document.getElementById('cobroCambio');
    if (!pedidoCobro || v <= 0) { cambio.textContent = ''; return; }
    var d = v - pedidoCobro.total;
    cambio.textContent = d >= 0 ? 'Cambio: ' + M.money(d) : 'Faltan ' + M.money(-d);
    cambio.style.color = d >= 0 ? 'var(--p-exito)' : 'var(--p-peligro)';
  });

  var conf = document.getElementById('cobroConfirmar');
  if (conf) conf.addEventListener('click', function () {
    if (!pedidoCobro) return;
    conf.disabled = true;
    M.pedir('panel/mesero/cobrar', {
      order_id: pedidoCobro.id,
      metodo_pago: document.getElementById('cobroMetodo').value,
      pagado_con: parseFloat(document.getElementById('cobroRecibido').value) || 0
    }).then(function (r) {
      conf.disabled = false;
      if (r.ok) {
        M.cerrarModal('modalCobro');
        M.avisar(r.mensaje + (r.cambio > 0 ? ' · Cambio ' + M.money(r.cambio) : ''), 'ok');
        if (r.ticket) window.open(r.ticket, '_blank');
        setTimeout(function () { location.reload(); }, 900);
      } else M.avisar(r.error, 'error');
    });
  });

  // Tiempo real
  var sse = null, sondeo = null;
  function conectar() {
    if (!window.EventSource) { sondear(); return; }
    try {
      sse = new EventSource(M.url('panel/mesero/sse'));
      sse.addEventListener('mesas', function (ev) {
        try { pintar(JSON.parse(ev.data)); } catch (e) {}
      });
      sse.onerror = function () { if (sse && sse.readyState === 2) { sse = null; sondear(); } };
    } catch (e) { sondear(); }
  }
  function sondear() {
    if (sondeo) return;
    sondeo = setInterval(function () {
      if (document.hidden) return;
      M.pedir('panel/mesero/datos').then(function (r) { if (r.ok) pintar(r); });
    }, 5000);
  }
  conectar();
})();
</script>
<?php View::stop(); ?>
