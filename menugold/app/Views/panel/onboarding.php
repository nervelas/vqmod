<?php
/** @var int $paso; array $cats, $temas */
use MenuGold\Core\Security;
use MenuGold\Core\View;
use MenuGold\Models\Restaurant;
View::set('titulo', 'Vamos a preparar tu menú');
View::set('subtitulo', 'Paso ' . $paso . ' de 4 · toma menos de cinco minutos');
$s = (string)($r['simbolo'] ?? 'Q');
$pasos = ['Tu restaurante', 'Logo y colores', 'Tu primer platillo', 'Descarga tus QR'];
?>
<div class="tarjeta-p">
  <div style="display:flex;gap:6px;margin-bottom:6px">
    <?php for ($i = 1; $i <= 4; $i++): ?>
      <div style="flex:1;height:5px;border-radius:3px;background:<?= $i <= $paso ? 'var(--p-oro)' : 'var(--p-superficie-3)' ?>"></div>
    <?php endfor; ?>
  </div>
  <div style="display:flex;justify-content:space-between;font-size:11.5px;color:var(--p-tenue)">
    <?php foreach ($pasos as $i => $t): ?>
      <span style="<?= $i + 1 === $paso ? 'color:var(--p-oro);font-weight:700' : '' ?>"><?= e($t) ?></span>
    <?php endforeach; ?>
  </div>
</div>

<form method="post" action="<?= e(url('panel/inicio')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="paso" value="<?= (int)$paso ?>">

  <?php if ($paso === 1): ?>
    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('store') ?> Cuéntanos de tu restaurante</h2></div>
      <div class="campo-p"><label for="oNombre">Nombre del restaurante *</label>
        <input type="text" id="oNombre" name="nombre" required maxlength="120" autofocus value="<?= e((string)$r['nombre']) ?>"></div>
      <div class="campo-p"><label for="oEslogan">Eslogan (opcional)</label>
        <input type="text" id="oEslogan" name="eslogan" maxlength="180" value="<?= e((string)$r['eslogan']) ?>"
               placeholder="Ej. Cocina de autor · Antigua Guatemala"></div>
      <div class="campo-p"><label for="oDir">Dirección</label>
        <input type="text" id="oDir" name="direccion" maxlength="255" value="<?= e((string)$r['direccion']) ?>"></div>
      <div class="fila-campos">
        <div class="campo-p"><label for="oTel">Teléfono</label>
          <input type="tel" id="oTel" name="telefono" maxlength="30" value="<?= e((string)$r['telefono']) ?>"></div>
        <div class="campo-p"><label for="oWa">WhatsApp</label>
          <input type="tel" id="oWa" name="whatsapp" maxlength="30" value="<?= e((string)$r['whatsapp']) ?>" placeholder="50212345678"></div>
      </div>
    </div>

  <?php elseif ($paso === 2): ?>
    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('palette') ?> Cómo se verá tu menú</h2></div>
      <label class="etiqueta-campo">Elige un tema</label>
      <div class="temas-rejilla" style="margin-bottom:18px">
        <?php foreach ($temas as $k => $t): ?>
          <label class="tema-opcion">
            <input type="radio" name="tema" value="<?= e($k) ?>" <?= $r['tema'] === $k ? 'checked' : '' ?>>
            <span class="tema-opcion__muestra">
              <span style="background:<?= e($t[1]) ?>"></span>
              <span style="background:<?= e($t[2]) ?>"></span>
              <span style="background:<?= e($t[3]) ?>"></span>
            </span>
            <span class="tema-opcion__nombre"><?= e($t[0]) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <label class="etiqueta-campo">Tipografía</label>
      <div class="pastillas-sel" style="margin-bottom:18px">
        <?php foreach (['clasica' => 'Clásica', 'moderna' => 'Moderna', 'editorial' => 'Editorial'] as $k => $v): ?>
          <label class="pastilla-sel"><input type="radio" name="tipografia" value="<?= e($k) ?>"
                 <?= $r['tipografia'] === $k ? 'checked' : '' ?>><?= e($v) ?></label>
        <?php endforeach; ?>
      </div>

      <div class="rejilla rejilla--2">
        <div>
          <label class="etiqueta-campo">Tu logo</label>
          <div class="previa-foto" id="previaLogo" style="<?= empty($r['logo']) ? 'display:none' : '' ?>;margin-bottom:10px">
            <img src="<?= e(!empty($r['logo']) ? uploaded((string)$r['logo']) : '') ?>" alt="">
          </div>
          <label class="subir-foto">
            <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" data-previsualizar="#previaLogo">
            <?= icon('upload') ?><span class="subir-foto__texto">Sube tu logo (cuadrado)</span>
          </label>
        </div>
        <div>
          <label class="etiqueta-campo">Foto de portada</label>
          <div class="previa-foto" id="previaPortada" style="<?= empty($r['portada']) ? 'display:none' : '' ?>;margin-bottom:10px">
            <img src="<?= e(!empty($r['portada']) ? uploaded((string)$r['portada']) : '') ?>" alt="">
          </div>
          <label class="subir-foto">
            <input type="file" name="portada" accept="image/jpeg,image/png,image/webp" data-previsualizar="#previaPortada">
            <?= icon('image') ?><span class="subir-foto__texto">Una foto horizontal de tu local o un platillo</span>
          </label>
        </div>
      </div>
    </div>

  <?php elseif ($paso === 3): ?>
    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('utensils') ?> Agrega tu primer platillo</h2></div>
      <p class="ayuda-p" style="margin-top:0">Con uno basta para empezar. Después podrás agregar el resto o importarlos desde Excel.</p>
      <div class="campo-p"><label for="pNombre">Nombre del platillo *</label>
        <input type="text" id="pNombre" name="nombre" maxlength="160" autofocus placeholder="Ej. Ceviche del chef"></div>
      <div class="campo-p"><label for="pDesc">Descripción</label>
        <textarea id="pDesc" name="descripcion" maxlength="600" placeholder="Corvina fresca, leche de tigre de chile cobanero y cilantro criollo"></textarea></div>
      <div class="fila-campos">
        <div class="campo-p"><label for="pPrecio">Precio *</label>
          <div class="grupo-prefijo"><span><?= e($s) ?></span>
            <input type="number" id="pPrecio" name="precio" step="0.01" min="0" value="0" inputmode="decimal"></div></div>
        <div class="campo-p"><label for="pCat">Categoría</label>
          <select id="pCat" name="category_id">
            <option value="0">Crear «Nuestra carta»</option>
            <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e((string)$c['nombre']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="campo-p"><label for="pTiempo">Minutos de preparación</label>
          <input type="number" id="pTiempo" name="tiempo_prep" min="1" max="240" value="15" inputmode="numeric"></div>
      </div>
      <label class="etiqueta-campo">Foto del platillo</label>
      <div class="previa-foto" id="previaPlato" style="display:none;margin-bottom:10px"><img src="" alt=""></div>
      <label class="subir-foto">
        <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" data-previsualizar="#previaPlato">
        <?= icon('upload') ?><span class="subir-foto__texto">Una buena foto vende sola</span>
      </label>
    </div>

  <?php else: ?>
    <div class="tarjeta-p" style="text-align:center;padding:34px 22px">
      <div style="width:84px;height:84px;margin:0 auto 18px;border-radius:50%;display:grid;place-items:center;
                  background:var(--p-exito);color:#fff"><?= icon('check', 'ico-lg') ?></div>
      <h2 style="font-size:22px;margin:0 0 8px">¡Tu menú ya está en línea!</h2>
      <p style="color:var(--p-suave);max-width:520px;margin:0 auto 22px">
        Comparte este enlace o imprime tus códigos QR y ponlos en las mesas.
        Todo lo que cambies en el panel se actualiza al instante.
      </p>
      <div style="max-width:520px;margin:0 auto 18px">
        <input class="entrada mono" type="text" id="urlMenu" readonly value="<?= e(Restaurant::urlMenu($r)) ?>">
      </div>
      <div class="acciones" style="justify-content:center">
        <button class="bt bt--linea" type="button" data-copiar="#urlMenu"><?= icon('copy') ?> Copiar enlace</button>
        <a class="bt bt--linea" href="<?= e(url('r/' . $r['slug'])) ?>" target="_blank"><?= icon('external') ?> Ver mi menú</a>
        <a class="bt bt--linea" href="<?= e(url('panel/qr')) ?>"><?= icon('qr') ?> Imprimir QR</a>
      </div>
    </div>
  <?php endif; ?>

  <div class="tarjeta-p" style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
    <?php if ($paso > 1): ?>
      <a class="bt bt--suave" href="<?= e(url('panel/inicio', ['paso' => $paso - 1])) ?>"><?= icon('arrow-left') ?> Atrás</a>
    <?php else: ?><span></span><?php endif; ?>
    <div class="acciones">
      <?php if ($paso < 4): ?>
        <a class="bt bt--suave" href="<?= e(url('panel/inicio', ['paso' => $paso + 1])) ?>">Omitir</a>
      <?php endif; ?>
      <button class="bt bt--oro" type="submit">
        <?= $paso === 4 ? 'Entrar al panel' : 'Continuar' ?> <?= icon('arrow-right') ?>
      </button>
    </div>
  </div>
</form>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
document.addEventListener('click', function (ev) {
  var c = ev.target.closest('[data-copiar]');
  if (!c) return;
  var i = document.querySelector(c.dataset.copiar);
  if (!i) return;
  i.select();
  if (navigator.clipboard) navigator.clipboard.writeText(i.value);
  window.MGPanel.avisar('Enlace copiado', 'ok');
});
</script>
<?php View::stop(); ?>
