<?php
/** @var array $mesas, $disenos, $tamanos; string $urlGeneral */
use MenuGold\Core\Security;
use MenuGold\Core\View;
View::set('titulo', 'Imprimir códigos QR');
View::set('subtitulo', 'Elige el diseño y descarga el PDF listo para imprimir');
?>
<form method="get" action="<?= e(url('panel/qr/pdf')) ?>" target="_blank">
  <div class="rejilla" style="grid-template-columns:minmax(0,1.4fr) minmax(280px,1fr);align-items:start">
    <div>
      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('palette') ?> Diseño</h2></div>
        <div class="rejilla rejilla--3">
          <?php
          $previas = [
            'tarjeta' => 'Una tarjeta por mesa, con logo, nombre y QR. La más usada.',
            'tent'    => 'Se dobla por la mitad y se para sola en la mesa. Se lee de ambos lados.',
            'sticker' => 'Compacto, para pegar en la mesa, la barra o el mostrador.',
          ];
          foreach ($disenos as $k => $v): ?>
            <label class="tema-opcion" style="padding:0">
              <input type="radio" name="diseno" value="<?= e($k) ?>" <?= $k === 'tarjeta' ? 'checked' : '' ?>>
              <div style="padding:16px;text-align:center">
                <div style="height:74px;display:grid;place-items:center;color:var(--p-oro)">
                  <?= icon($k === 'tent' ? 'layers' : ($k === 'sticker' ? 'tag' : 'qr'), 'ico-lg') ?>
                </div>
                <strong style="display:block;font-size:14px;margin-bottom:4px"><?= e($v) ?></strong>
                <small style="color:var(--p-tenue);line-height:1.4"><?= e($previas[$k]) ?></small>
              </div>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="tarjeta-p">
        <div class="tarjeta-p__cab">
          <h2 class="tarjeta-p__titulo"><?= icon('table') ?> Mesas a incluir</h2>
          <div class="acciones">
            <button class="bt bt--sm bt--suave" type="button" id="todas">Todas</button>
            <button class="bt bt--sm bt--suave" type="button" id="ninguna">Ninguna</button>
          </div>
        </div>
        <label class="casilla" style="margin-bottom:10px">
          <input type="checkbox" name="general" value="1" checked>
          <span><strong>QR general del menú</strong> — sin mesa asignada, para la entrada o tus redes</span>
        </label>
        <?php if (!$mesas): ?>
          <p style="color:var(--p-tenue);margin:0">
            No tienes mesas registradas.
            <a href="<?= e(url('panel/mesas')) ?>" style="color:var(--p-oro);font-weight:600">Créalas aquí</a>.
          </p>
        <?php else: ?>
          <div class="pastillas-sel" id="listaMesas">
            <?php foreach ($mesas as $m): ?>
              <label class="pastilla-sel">
                <input type="checkbox" name="mesas[]" value="<?= (int)$m['id'] ?>" checked>
                <?= e((string)$m['nombre']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div>
      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('printer') ?> Tamaño de impresión</h2></div>
        <?php foreach ($tamanos as $k => $v): ?>
          <label class="casilla" style="width:100%;padding:11px;border:1px solid var(--p-borde);border-radius:11px;margin-bottom:8px">
            <input type="radio" name="tamano" value="<?= e($k) ?>" <?= $k === 'a6' ? 'checked' : '' ?>>
            <span><?= e($v[0]) ?></span>
          </label>
        <?php endforeach; ?>
        <p class="ayuda-p">
          Los QR se dibujan como vectores: se ven nítidos a cualquier tamaño de impresión.
          Recomendamos papel de 250 g o más para las tarjetas de mesa.
        </p>
        <button class="bt bt--oro bt--bloque bt--grande" type="submit" style="margin-top:8px">
          <?= icon('pdf') ?> Generar PDF
        </button>
      </div>

      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('info') ?> Cómo funciona</h2></div>
        <ol style="margin:0;padding-left:18px;color:var(--p-suave);font-size:13.5px;line-height:1.75;list-style:decimal">
          <li>Cada mesa lleva un QR <strong>firmado</strong>: nadie puede inventar pedidos falsos para tu mesa.</li>
          <li>El cliente escanea, ve tu menú y pide. El pedido llega con el número de mesa.</li>
          <li>Si cambias precios o marcas un platillo agotado, el QR sigue igual: se actualiza solo.</li>
          <li>Si eliminas una mesa, su QR deja de funcionar de inmediato.</li>
        </ol>
      </div>
    </div>
  </div>
</form>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
  function marcar(v) {
    document.querySelectorAll('#listaMesas input[type=checkbox]').forEach(function (c) { c.checked = v; });
  }
  var t = document.getElementById('todas');
  var n = document.getElementById('ninguna');
  if (t) t.addEventListener('click', function () { marcar(true); });
  if (n) n.addEventListener('click', function () { marcar(false); });
})();
</script>
<?php View::stop(); ?>
