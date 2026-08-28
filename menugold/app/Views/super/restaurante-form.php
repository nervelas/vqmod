<?php
/** @var array|null $rest, $dueno; array $planes */
use MenuGold\Core\Security;
use MenuGold\Core\View;
use MenuGold\Models\Restaurant;
$nuevo = $rest === null;
View::set('titulo', $nuevo ? 'Nuevo restaurante' : (string)$rest['nombre']);
View::set('subtitulo', $nuevo ? 'Se crea con su usuario dueño y su menú listo para llenar' : 'Datos de la cuenta');
?>
<form method="post" action="<?= e(url('super/restaurantes/guardar')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)($rest['id'] ?? 0) ?>">

  <div class="rejilla rejilla--2">
    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('store') ?> Datos del negocio</h2></div>
      <div class="campo-p"><label for="sNombre">Nombre del restaurante *</label>
        <input type="text" id="sNombre" name="nombre" required maxlength="120" autofocus
               value="<?= e((string)($rest['nombre'] ?? '')) ?>"></div>
      <div class="campo-p"><label for="sSlug">Dirección web</label>
        <div class="grupo-prefijo">
          <span><?= e(rtrim(url(''), '/')) ?>/r/</span>
          <input type="text" id="sSlug" name="slug" maxlength="60" value="<?= e((string)($rest['slug'] ?? '')) ?>"
                 placeholder="mi-restaurante" pattern="[a-z0-9-]+"></div>
        <p class="ayuda-p">Solo minúsculas, números y guiones. Si lo dejas vacío se genera del nombre.</p></div>
      <div class="campo-p"><label for="sDominio">Dominio o subdominio propio</label>
        <input type="text" id="sDominio" name="dominio" maxlength="190"
               value="<?= e((string)($rest['dominio'] ?? '')) ?>" placeholder="menu.mirestaurante.com">
        <p class="ayuda-p">
          Apunta el dominio a este hosting y escríbelo aquí. El menú se servirá desde la raíz de ese dominio.
        </p></div>
      <div class="fila-campos">
        <div class="campo-p"><label for="sEmail">Correo de contacto</label>
          <input type="email" id="sEmail" name="email" maxlength="190" value="<?= e((string)($rest['email'] ?? '')) ?>"></div>
        <div class="campo-p"><label for="sTel">Teléfono</label>
          <input type="tel" id="sTel" name="telefono" maxlength="30" value="<?= e((string)($rest['telefono'] ?? '')) ?>"></div>
      </div>
      <div class="campo-p"><label for="sWa">WhatsApp</label>
        <input type="tel" id="sWa" name="whatsapp" maxlength="30" value="<?= e((string)($rest['whatsapp'] ?? '')) ?>"></div>
      <label class="interruptor">
        <input type="checkbox" name="demo" value="1" <?= (int)($rest['demo'] ?? 0) === 1 ? 'checked' : '' ?>>
        <span class="interruptor__pista"></span>
        <span class="interruptor__texto">Es el restaurante de demostración</span>
      </label>
    </div>

    <div>
      <div class="tarjeta-p">
        <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('crown') ?> Plan y vigencia</h2></div>
        <div class="campo-p"><label for="sPlan">Plan contratado</label>
          <select id="sPlan" name="plan_id">
            <option value="">Sin plan (sin límites)</option>
            <?php foreach ($planes as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= (int)($rest['plan_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                <?= e((string)$p['nombre']) ?> — <?= e(money($p['precio_mensual'], 'Q')) ?>/mes
                (<?= (int)$p['max_productos'] === 0 ? '∞' : (int)$p['max_productos'] ?> platillos,
                 <?= (int)$p['max_mesas'] === 0 ? '∞' : (int)$p['max_mesas'] ?> mesas)
              </option>
            <?php endforeach; ?>
          </select></div>
        <div class="fila-campos">
          <div class="campo-p"><label for="sEstado">Estado</label>
            <select id="sEstado" name="estado">
              <?php foreach (['activo' => 'Activo', 'prueba' => 'En prueba', 'suspendido' => 'Suspendido'] as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= ($rest['estado'] ?? 'prueba') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="campo-p"><label for="sVence">Vence el</label>
            <input type="date" id="sVence" name="vence_el" value="<?= e((string)($rest['vence_el'] ?? date('Y-m-d', strtotime('+30 days')))) ?>">
            <p class="ayuda-p">Al vencer, el menú deja de estar disponible.</p></div>
        </div>
      </div>

      <?php if ($nuevo): ?>
        <div class="tarjeta-p">
          <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('user') ?> Usuario dueño</h2></div>
          <div class="campo-p"><label for="dNombre">Nombre del dueño</label>
            <input type="text" id="dNombre" name="dueno_nombre" maxlength="120" placeholder="Ej. Mariana Solís"></div>
          <div class="campo-p"><label for="dEmail">Correo (será su usuario)</label>
            <input type="email" id="dEmail" name="dueno_email" maxlength="190"></div>
          <div class="campo-p"><label for="dClave">Contraseña</label>
            <input type="text" id="dClave" name="dueno_password" maxlength="200" placeholder="Se genera una si lo dejas vacío">
            <p class="ayuda-p">Le enviaremos sus accesos por correo si escribiste uno.</p></div>
        </div>
      <?php else: ?>
        <div class="tarjeta-p">
          <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('user') ?> Dueño de la cuenta</h2></div>
          <?php if ($dueno): ?>
            <p style="margin:0 0 4px"><strong><?= e((string)$dueno['nombre']) ?></strong></p>
            <p style="margin:0;color:var(--p-suave);font-size:13.5px"><?= e((string)$dueno['email']) ?> ·
              <span class="mono"><?= e((string)$dueno['usuario']) ?></span></p>
            <p style="margin:8px 0 0;font-size:13px;color:var(--p-tenue)">
              Último acceso: <?= e($dueno['ultimo_acceso'] ? dt((string)$dueno['ultimo_acceso']) : 'nunca') ?>
            </p>
          <?php else: ?>
            <p style="color:var(--p-tenue);margin:0">Este restaurante aún no tiene usuario dueño.</p>
          <?php endif; ?>
          <div class="acciones" style="margin-top:14px">
            <a class="bt bt--sm bt--linea" href="<?= e(url('super/entrar/' . $rest['id'])) ?>"><?= icon('login', 'ico-sm') ?> Entrar a su panel</a>
            <a class="bt bt--sm bt--suave" href="<?= e(url('r/' . $rest['slug'])) ?>" target="_blank"><?= icon('eye', 'ico-sm') ?> Ver menú</a>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="tarjeta-p" style="display:flex;justify-content:space-between;gap:9px;flex-wrap:wrap">
    <a class="bt bt--linea" href="<?= e(url('super/restaurantes')) ?>">Volver</a>
    <div class="acciones">
      <?php if (!$nuevo): ?>
        <button class="bt bt--peligro" type="button" id="btnBorrarRest"><?= icon('trash') ?> Eliminar restaurante</button>
      <?php endif; ?>
      <button class="bt bt--oro" type="submit"><?= icon('save') ?> <?= $nuevo ? 'Crear restaurante' : 'Guardar' ?></button>
    </div>
  </div>
</form>

<?php if (!$nuevo): ?>
<div class="modal-p" id="modalBorrarRest" role="dialog" aria-modal="true">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja" style="width:min(460px,calc(100vw - 28px))">
    <div class="modal-p__cab">
      <h2 class="modal-p__titulo">Eliminar restaurante</h2>
      <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
    </div>
    <div class="modal-p__cuerpo">
      <div class="aviso aviso--error">
        <?= icon('alert') ?>
        <span>Se eliminará <strong>todo</strong>: menú, pedidos, clientes, usuarios y configuración.
          Esta acción no se puede deshacer.</span>
      </div>
      <div class="campo-p">
        <label for="confirmarSlug">Para confirmar, escribe <strong class="mono"><?= e((string)$rest['slug']) ?></strong></label>
        <input type="text" id="confirmarSlug" class="mono" autocomplete="off">
      </div>
    </div>
    <div class="modal-p__pie">
      <button class="bt bt--linea" type="button" data-cerrar-modal>Cancelar</button>
      <button class="bt bt--peligro" type="button" id="confirmarBorrarRest">Eliminar definitivamente</button>
    </div>
  </div>
</div>
<?php endif; ?>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
  var M = window.MGPanel;
  var b = document.getElementById('btnBorrarRest');
  if (b) b.addEventListener('click', function () { M.abrirModal('modalBorrarRest'); });
  var c = document.getElementById('confirmarBorrarRest');
  if (c) c.addEventListener('click', function () {
    c.disabled = true;
    M.pedir('super/restaurantes/borrar', {
      id: <?= (int)($rest['id'] ?? 0) ?>,
      confirmar: document.getElementById('confirmarSlug').value.trim()
    }).then(function (r) {
      c.disabled = false;
      if (r.ok) { M.avisar(r.mensaje, 'ok'); setTimeout(function () { location.href = M.url('super/restaurantes'); }, 900); }
      else M.avisar(r.error, 'error');
    });
  });

  // Sugerir la dirección web al escribir el nombre
  var n = document.getElementById('sNombre'), s = document.getElementById('sSlug');
  if (n && s && !s.value) {
    n.addEventListener('input', function () {
      s.value = n.value.toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60);
    });
  }
})();
</script>
<?php View::stop(); ?>
