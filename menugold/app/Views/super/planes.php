<?php
/** @var array $planes */
use MenuGold\Core\Security;
use MenuGold\Core\View;
View::set('titulo', 'Planes');
View::set('subtitulo', 'Lo que ofreces y los límites de cada nivel');

View::start('acciones');
?>
<button class="bt bt--oro" type="button" data-modal="modalPlan" data-limpiar="1" data-titulo="Nuevo plan">
  <?= icon('plus') ?><span>Nuevo plan</span>
</button>
<?php View::stop(); ?>

<div class="rejilla rejilla--3">
  <?php foreach ($planes as $p): ?>
    <div class="tarjeta-p" style="<?= (int)$p['destacado'] === 1 ? 'border-color:var(--p-oro);border-width:2px' : '' ?>">
      <div class="entre" style="margin-bottom:10px">
        <h3 style="margin:0;font-size:18px"><?= e((string)$p['nombre']) ?></h3>
        <?php if ((int)$p['destacado'] === 1): ?><span class="insignia insignia--oro">Destacado</span><?php endif; ?>
      </div>
      <div style="font-size:28px;font-weight:800;color:var(--p-oro);margin-bottom:2px">
        <?= e(money($p['precio_mensual'], 'Q')) ?><span style="font-size:14px;color:var(--p-tenue);font-weight:500">/mes</span>
      </div>
      <?php if ((float)$p['precio_anual'] > 0): ?>
        <p class="ayuda-p" style="margin-top:0"><?= e(money($p['precio_anual'], 'Q')) ?> al año</p>
      <?php endif; ?>
      <p style="color:var(--p-suave);font-size:13.5px;margin:8px 0 12px"><?= e((string)$p['descripcion']) ?></p>

      <div style="font-size:13px;color:var(--p-tenue);line-height:1.9;border-top:1px solid var(--p-borde);padding-top:10px">
        Platillos: <strong><?= (int)$p['max_productos'] === 0 ? 'ilimitados' : (int)$p['max_productos'] ?></strong><br>
        Mesas: <strong><?= (int)$p['max_mesas'] === 0 ? 'ilimitadas' : (int)$p['max_mesas'] ?></strong><br>
        Sucursales: <strong><?= (int)$p['max_sucursales'] ?></strong><br>
        Usuarios: <strong><?= (int)$p['max_usuarios'] === 0 ? 'ilimitados' : (int)$p['max_usuarios'] ?></strong><br>
        En uso por: <strong><?= (int)$p['restaurantes'] ?></strong> restaurante(s)
      </div>

      <?php if (!empty($p['caracteristicas'])): ?>
        <ul style="margin:12px 0 0;padding:0;list-style:none;font-size:13px;color:var(--p-suave)">
          <?php foreach (array_slice($p['caracteristicas'], 0, 8) as $c): ?>
            <li style="display:flex;gap:7px;align-items:flex-start;padding:3px 0">
              <span style="color:var(--p-exito);flex:0 0 auto"><?= icon('check', 'ico-sm') ?></span><?= e((string)$c) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <div class="acciones" style="margin-top:14px">
        <button class="bt bt--sm bt--linea" type="button" data-modal="modalPlan" data-titulo="Editar plan"
                data-rellenar='<?= e(json_encode([
                    'id' => (int)$p['id'], 'nombre' => $p['nombre'], 'descripcion' => $p['descripcion'],
                    'precio_mensual' => (float)$p['precio_mensual'], 'precio_anual' => (float)$p['precio_anual'],
                    'max_productos' => (int)$p['max_productos'], 'max_mesas' => (int)$p['max_mesas'],
                    'max_sucursales' => (int)$p['max_sucursales'], 'max_usuarios' => (int)$p['max_usuarios'],
                    'caracteristicas' => implode("\n", (array)$p['caracteristicas']),
                    'destacado' => (int)$p['destacado'], 'activo' => (int)$p['activo'],
                ], JSON_UNESCAPED_UNICODE)) ?>'><?= icon('edit', 'ico-sm') ?> Editar</button>
        <button class="bt bt--sm bt--suave" type="button" data-borrar-plan="<?= (int)$p['id'] ?>"
                data-nombre="<?= e((string)$p['nombre']) ?>"><?= icon('trash', 'ico-sm') ?></button>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="modal-p" id="modalPlan" role="dialog" aria-modal="true">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja">
    <form data-ajax action="<?= e(url('super/planes/guardar')) ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="" data-limpiable>
      <div class="modal-p__cab">
        <h2 class="modal-p__titulo">Nuevo plan</h2>
        <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
      </div>
      <div class="modal-p__cuerpo">
        <div class="campo-p"><label for="plNombre">Nombre *</label>
          <input type="text" id="plNombre" name="nombre" required maxlength="60" placeholder="Ej. Pro"></div>
        <div class="campo-p"><label for="plDesc">Descripción corta</label>
          <input type="text" id="plDesc" name="descripcion" maxlength="255"></div>
        <div class="fila-campos">
          <div class="campo-p"><label for="plMensual">Precio mensual</label>
            <div class="grupo-prefijo"><span>Q</span>
              <input type="number" id="plMensual" name="precio_mensual" step="0.01" min="0" value="0"></div></div>
          <div class="campo-p"><label for="plAnual">Precio anual</label>
            <div class="grupo-prefijo"><span>Q</span>
              <input type="number" id="plAnual" name="precio_anual" step="0.01" min="0" value="0"></div></div>
        </div>
        <div class="fila-campos">
          <div class="campo-p"><label for="plProd">Máx. platillos</label>
            <input type="number" id="plProd" name="max_productos" min="0" value="0"><p class="ayuda-p">0 = ilimitado</p></div>
          <div class="campo-p"><label for="plMesas">Máx. mesas</label>
            <input type="number" id="plMesas" name="max_mesas" min="0" value="0"></div>
          <div class="campo-p"><label for="plSuc">Máx. sucursales</label>
            <input type="number" id="plSuc" name="max_sucursales" min="1" value="1"></div>
          <div class="campo-p"><label for="plUsr">Máx. usuarios</label>
            <input type="number" id="plUsr" name="max_usuarios" min="0" value="0"></div>
        </div>
        <div class="campo-p"><label for="plCarac">Características (una por línea)</label>
          <textarea id="plCarac" name="caracteristicas" rows="7" maxlength="2000"
                    placeholder="Menú digital ilimitado&#10;QR por mesa&#10;Pantalla de cocina"></textarea></div>
        <label class="interruptor" style="margin-bottom:6px">
          <input type="checkbox" name="destacado" value="1">
          <span class="interruptor__pista"></span><span class="interruptor__texto">Destacar en la página de venta</span></label><br>
        <label class="interruptor">
          <input type="checkbox" name="activo" value="1" checked>
          <span class="interruptor__pista"></span><span class="interruptor__texto">Plan disponible</span></label>
      </div>
      <div class="modal-p__pie">
        <button class="bt bt--linea" type="button" data-cerrar-modal>Cancelar</button>
        <button class="bt bt--oro" type="submit"><?= icon('save') ?> Guardar</button>
      </div>
    </form>
  </div>
</div>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
document.addEventListener('click', function (ev) {
  var b = ev.target.closest('[data-borrar-plan]');
  if (!b) return;
  var M = window.MGPanel;
  M.confirmar('Se eliminará el plan "' + b.dataset.nombre + '".', 'Eliminar plan', 'Sí, eliminar').then(function (ok) {
    if (!ok) return;
    M.pedir('super/planes/borrar', { id: Number(b.dataset.borrarPlan) }).then(function (r) {
      if (r.ok) location.reload(); else M.avisar(r.error, 'error');
    });
  });
});
</script>
<?php View::stop(); ?>
