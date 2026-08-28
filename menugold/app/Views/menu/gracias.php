<?php
/** Pantalla de agradecimiento con invitación a reseña. @var array $r, $pedido; string $resena */
use MenuGold\Core\Security;
use MenuGold\Core\View;
use MenuGold\Models\Order;

$simbolo = (string)($r['simbolo'] ?? 'Q');
?>
<main id="contenido" class="seguimiento" style="padding-top:56px">
  <div class="centro" style="margin-bottom:24px">
    <div style="width:88px;height:88px;margin:0 auto 18px;border-radius:50%;display:grid;place-items:center;
                background:var(--exito);color:#fff;animation:escala 420ms cubic-bezier(.16,.84,.44,1) both">
      <?= icon('check', 'ico-lg') ?>
    </div>
    <h1 style="font-family:var(--fuente-titulo);font-size:clamp(26px,6vw,36px);margin:0 0 6px"><?= e(__('gracias')) ?></h1>
    <p style="color:var(--texto-suave);margin:0">
      <?= e((string)$r['nombre']) ?> ya recibió tu pedido <strong class="mono"><?= e((string)$pedido['codigo']) ?></strong>
    </p>
  </div>

  <div class="tarjeta">
    <div class="resumen__fila resumen__fila--total" style="border:0;margin:0;padding:0">
      <span>Total</span><span class="valor mono"><?= e(money($pedido['total'], $simbolo)) ?></span>
    </div>
    <p style="margin:10px 0 0;color:var(--texto-tenue);font-size:13px">
      <?= (int)count($pedido['items']) ?> platillo(s) · <?= e(Order::etiquetaModo((string)$pedido['modo'])) ?>
      <?php if (!empty($pedido['mesa_nombre'])): ?> · <?= e((string)$pedido['mesa_nombre']) ?><?php endif; ?>
    </p>
  </div>

  <a class="btn btn--oro btn--bloque" href="<?= e(url('r/' . $r['slug'] . '/pedido/' . $pedido['codigo'])) ?>" style="margin-bottom:12px">
    <?= icon('clock') ?> <?= e(__('seguir_pedido')) ?>
  </a>

  <?php if ((int)($pedido['calificacion'] ?? 0) === 0): ?>
  <div class="tarjeta" id="cajaResena">
    <h3 class="tarjeta__titulo centro"><?= e(__('califica')) ?></h3>
    <div class="estrellas" id="estrellas" role="radiogroup" aria-label="<?= e(__('califica')) ?>">
      <?php for ($i = 1; $i <= 5; $i++): ?>
        <button class="estrella" type="button" data-valor="<?= $i ?>" role="radio" aria-checked="false" aria-label="<?= $i ?> de 5"><?= icon('star') ?></button>
      <?php endfor; ?>
    </div>
    <div id="cajaComentario" class="oculto">
      <textarea class="campo-notas" id="comentario" maxlength="400" placeholder="Cuéntanos cómo estuvo todo (opcional)"></textarea>
      <button class="btn btn--oro btn--bloque" type="button" id="enviarResena" style="margin-top:10px">Enviar mi opinión</button>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($resena)): ?>
    <div class="tarjeta centro" id="cajaGoogle" style="<?= (int)($pedido['calificacion'] ?? 0) === 0 ? 'display:none' : '' ?>">
      <?= icon('star', 'ico-lg') ?>
      <p style="margin:10px 0 14px;color:var(--texto-suave)">Tu opinión ayuda mucho a nuestro equipo.</p>
      <a class="btn btn--fantasma btn--bloque" href="<?= e($resena) ?>" target="_blank" rel="noopener">
        <?= icon('external') ?> <?= e(__('dejar_resena')) ?>
      </a>
    </div>
  <?php endif; ?>

  <a class="btn btn--linea btn--bloque" href="<?= e(url('r/' . $r['slug'])) ?>" style="margin-top:12px">
    <?= icon('utensils') ?> Volver al menú
  </a>
</main>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
  var estrellas = document.querySelectorAll('#estrellas .estrella');
  if (!estrellas.length) return;
  var valor = 0;
  var caja = document.getElementById('cajaComentario');

  estrellas.forEach(function (b) {
    b.addEventListener('click', function () {
      valor = Number(b.dataset.valor);
      estrellas.forEach(function (x) {
        var on = Number(x.dataset.valor) <= valor;
        x.classList.toggle('activa', on);
        x.setAttribute('aria-checked', String(Number(x.dataset.valor) === valor));
      });
      if (caja) caja.classList.remove('oculto');
    });
  });

  var btn = document.getElementById('enviarResena');
  if (btn) btn.addEventListener('click', function () {
    if (!valor) return;
    btn.disabled = true;
    fetch(window.MG.base + '/api/resena', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: JSON.stringify({
        _token: window.MG.token,
        slug: window.MG.slug,
        codigo: <?= json_encode((string)$pedido['codigo']) ?>,
        calificacion: valor,
        comentario: (document.getElementById('comentario') || {}).value || ''
      })
    }).then(function (r) { return r.json(); }).then(function (j) {
      var cajaR = document.getElementById('cajaResena');
      if (cajaR) cajaR.style.display = 'none';
      var g = document.getElementById('cajaGoogle');
      if (g && valor >= 4) g.style.display = '';
      if (window.MGavisar) window.MGavisar(j.mensaje || '¡Gracias!', 'ok');
    }).catch(function () { btn.disabled = false; });
  });
})();
</script>
<?php View::stop(); ?>
