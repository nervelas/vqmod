<?php
/** Seguimiento del pedido en vivo. @var array $r, $pedido, $eventos; bool $esMio */
use MenuGold\Core\Security;
use MenuGold\Core\View;
use MenuGold\Models\Order;

$simbolo = (string)($r['simbolo'] ?? 'Q');
$estados = ['nuevo' => 'Recibido', 'preparando' => 'En preparación', 'listo' => 'Listo', 'entregado' => 'Entregado'];
$orden   = array_keys($estados);
$actual  = (string)$pedido['estado'];
$idxActual = array_search($actual === 'pagado' ? 'entregado' : $actual, $orden, true);
if ($idxActual === false) $idxActual = 0;
$anulado = $actual === 'anulado';

$horas = [
    'nuevo'      => dt((string)$pedido['creado'], 'H:i'),
    'preparando' => '',
    'listo'      => $pedido['listo_en'] ? dt((string)$pedido['listo_en'], 'H:i') : '',
    'entregado'  => $pedido['entregado_en'] ? dt((string)$pedido['entregado_en'], 'H:i') : '',
];
foreach ($eventos as $ev) {
    if ($ev['estado'] === 'preparando' && $horas['preparando'] === '') $horas['preparando'] = dt((string)$ev['creado'], 'H:i');
}
?>
<header class="portada" style="min-height:190px">
  <?php if (!empty($r['portada'])): ?><img class="portada__foto" src="<?= e(uploaded((string)$r['portada'])) ?>" alt=""><?php endif; ?>
  <div class="portada__velo"></div>
  <div class="portada__contenido">
    <h1 class="portada__nombre" style="font-size:clamp(22px,5vw,32px)"><?= e((string)$r['nombre']) ?></h1>
    <div class="filete" aria-hidden="true"><?= icon('receipt') ?></div>
  </div>
</header>

<main id="contenido" class="seguimiento">
  <p class="centro" style="color:var(--texto-suave);margin:0 0 4px;font-size:13px;letter-spacing:2px;text-transform:uppercase">Pedido</p>
  <h2 class="seguimiento__codigo mono"><?= e((string)$pedido['codigo']) ?></h2>
  <p class="centro" style="color:var(--texto-tenue);font-size:13.5px">
    <?= e(Order::etiquetaModo((string)$pedido['modo'])) ?>
    <?php if (!empty($pedido['mesa_nombre'])): ?> · <?= e((string)$pedido['mesa_nombre']) ?><?php endif; ?>
    · <?= e(dt((string)$pedido['creado'], 'd/m/Y H:i')) ?>
  </p>

  <?php if ($anulado): ?>
    <div class="tarjeta" style="border-color:var(--peligro);text-align:center">
      <?= icon('x-circle', 'ico-lg') ?>
      <h3 style="margin:10px 0 4px">Este pedido fue anulado</h3>
      <?php if (!empty($pedido['motivo_anulacion'])): ?>
        <p style="color:var(--texto-suave);margin:0"><?= e((string)$pedido['motivo_anulacion']) ?></p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="pasos" id="pasos">
      <?php foreach ($orden as $i => $k): ?>
        <?php
          $clase = $i < $idxActual ? 'paso--hecho' : ($i === $idxActual ? 'paso--actual' : '');
          if ($actual === 'pagado') $clase = 'paso--hecho';
        ?>
        <div class="paso <?= $clase ?>" data-paso="<?= e($k) ?>">
          <span class="paso__punto"><?= $i < $idxActual || $actual === 'pagado' ? icon('check') : icon($k === 'nuevo' ? 'receipt' : ($k === 'preparando' ? 'chef' : ($k === 'listo' ? 'bell' : 'check'))) ?></span>
          <p class="paso__titulo"><?= e($estados[$k]) ?></p>
          <span class="paso__hora" data-hora="<?= e($k) ?>"><?= e($horas[$k]) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="centro" style="color:var(--texto-tenue);font-size:13px" id="avisoVivo">
      <span class="punto" style="display:inline-block;width:7px;height:7px;background:var(--exito);border-radius:50%;margin-right:5px"></span>
      Esta página se actualiza sola
    </p>
  <?php endif; ?>

  <div class="tarjeta">
    <h3 class="tarjeta__titulo">Tu pedido</h3>
    <?php foreach ($pedido['items'] as $l): ?>
      <div style="display:flex;gap:12px;padding:9px 0;border-bottom:1px solid var(--borde)">
        <span style="flex:0 0 auto;font-weight:700;color:var(--acento)"><?= (int)$l['cantidad'] ?>×</span>
        <div class="crece">
          <div style="font-weight:600;font-size:14.5px"><?= e((string)$l['nombre']) ?></div>
          <?php if (!empty($l['modificadores'])): ?>
            <div style="font-size:12px;color:var(--texto-tenue)">
              <?= e(implode(' · ', array_map(static fn($m) => (string)$m['opcion'], $l['modificadores']))) ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($l['notas'])): ?><div style="font-size:12px;color:var(--texto-tenue)">📝 <?= e((string)$l['notas']) ?></div><?php endif; ?>
        </div>
        <span class="mono" style="flex:0 0 auto"><?= e(money($l['subtotal'], $simbolo)) ?></span>
      </div>
    <?php endforeach; ?>

    <div class="resumen">
      <div class="resumen__fila"><span>Subtotal</span><span class="mono"><?= e(money($pedido['subtotal'], $simbolo)) ?></span></div>
      <?php if ((float)$pedido['descuento'] > 0): ?>
        <div class="resumen__fila resumen__fila--desc"><span>Descuento<?= $pedido['cupon_codigo'] ? ' (' . e((string)$pedido['cupon_codigo']) . ')' : '' ?></span><span class="valor mono">− <?= e(money($pedido['descuento'], $simbolo)) ?></span></div>
      <?php endif; ?>
      <?php if ((float)$pedido['costo_envio'] > 0): ?>
        <div class="resumen__fila"><span>Envío</span><span class="mono"><?= e(money($pedido['costo_envio'], $simbolo)) ?></span></div>
      <?php endif; ?>
      <?php if ((float)$pedido['propina'] > 0): ?>
        <div class="resumen__fila"><span>Propina</span><span class="mono"><?= e(money($pedido['propina'], $simbolo)) ?></span></div>
      <?php endif; ?>
      <div class="resumen__fila resumen__fila--total"><span>Total</span><span class="valor mono"><?= e(money($pedido['total'], $simbolo)) ?></span></div>
    </div>
  </div>

  <?php if (!empty($pedido['cliente_direccion'])): ?>
    <div class="tarjeta">
      <h3 class="tarjeta__titulo">Entrega</h3>
      <p style="margin:0 0 4px"><strong><?= e((string)$pedido['cliente_nombre']) ?></strong> · <?= e((string)$pedido['cliente_telefono']) ?></p>
      <p style="margin:0;color:var(--texto-suave)"><?= e((string)$pedido['cliente_direccion']) ?></p>
      <?php if (!empty($pedido['cliente_referencia'])): ?><p style="margin:4px 0 0;color:var(--texto-tenue);font-size:13px"><?= e((string)$pedido['cliente_referencia']) ?></p><?php endif; ?>
    </div>
  <?php endif; ?>

  <a class="btn btn--fantasma btn--bloque" href="<?= e(url('r/' . $r['slug'])) ?>"><?= icon('utensils') ?> Volver al menú</a>
</main>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
  var codigo = <?= json_encode((string)$pedido['codigo']) ?>;
  var slug   = <?= json_encode((string)$r['slug']) ?>;
  var anulado = <?= $anulado ? 'true' : 'false' ?>;
  if (anulado) return;
  var ORDEN = ['nuevo', 'preparando', 'listo', 'entregado'];
  var ultimo = <?= json_encode($actual) ?>;

  function pintar(p) {
    if (!p || !p.estado) return;
    if (p.estado === ultimo) return;
    ultimo = p.estado;
    var idx = ORDEN.indexOf(p.estado === 'pagado' ? 'entregado' : p.estado);
    if (idx < 0) idx = 0;
    ORDEN.forEach(function (k, i) {
      var el = document.querySelector('[data-paso="' + k + '"]');
      if (!el) return;
      el.classList.remove('paso--hecho', 'paso--actual');
      if (i < idx || p.estado === 'pagado') el.classList.add('paso--hecho');
      else if (i === idx) el.classList.add('paso--actual');
    });
    var h = document.querySelector('[data-hora="' + p.estado + '"]');
    if (h && !h.textContent.trim()) h.textContent = new Date().toLocaleTimeString('es-GT', { hour: '2-digit', minute: '2-digit' });
    if (window.MGavisar) window.MGavisar('Tu pedido ahora está: ' + p.etiqueta, 'ok');
    if (p.estado === 'entregado' || p.estado === 'pagado') {
      setTimeout(function () { window.location.href = window.MG.base + '/r/' + slug + '/gracias/' + codigo; }, 2200);
    }
  }

  // El seguimiento del cliente usa sondeo cada 5 s.
  // Los clientes pueden ser muchos a la vez y en hosting compartido cada
  // conexión abierta ocupa un proceso PHP; las pantallas del personal
  // (cocina y mesero), que son pocas, sí usan tiempo real con SSE.
  var sondeo = null;
  function sondear() {
    if (sondeo) return;
    consultar();
    sondeo = setInterval(function () {
      if (document.hidden) return;
      consultar();
    }, 5000);
  }
  function consultar() {
    fetch(window.MG.base + '/api/estado/' + encodeURIComponent(slug) + '/' + encodeURIComponent(codigo),
          { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (j.ok) pintar(j.pedido); })
      .catch(function () {});
  }
  function detener() { if (sondeo) { clearInterval(sondeo); sondeo = null; } }

  sondear();
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) detener(); else sondear();
  });
  window.addEventListener('pagehide', detener);
})();
</script>
<?php View::stop(); ?>
