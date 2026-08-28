<?php
/** @var array $grupos */
use MenuGold\Core\Security;
use MenuGold\Core\View;
View::set('titulo', 'Modificadores');
View::set('subtitulo', 'Tamaños, términos, extras y quitar ingredientes');
$s = (string)($r['simbolo'] ?? 'Q');

View::start('acciones');
?>
<button class="bt bt--oro" type="button" data-modal="modalGrupo" data-limpiar="1" data-titulo="Nuevo grupo">
  <?= icon('plus') ?><span>Nuevo grupo</span>
</button>
<?php View::stop(); ?>

<?php if (!$grupos): ?>
  <div class="tarjeta-p">
    <div class="vacio-p">
      <?= icon('grid', 'ico-lg') ?>
      <h3>Sin grupos de modificadores</h3>
      <p>Crea grupos reutilizables como «Término de la carne», «Tamaño» o «Extras»<br>
         y asígnalos a los platillos que los necesiten.</p>
      <button class="bt bt--oro" type="button" data-modal="modalGrupo" data-limpiar="1"><?= icon('plus') ?> Crear el primero</button>
    </div>
  </div>
<?php endif; ?>

<div class="rejilla rejilla--2">
<?php foreach ($grupos as $g): ?>
  <div class="tarjeta-p" data-grupo="<?= (int)$g['id'] ?>">
    <div class="tarjeta-p__cab">
      <h2 class="tarjeta-p__titulo">
        <?= icon($g['tipo'] === 'unico' ? 'target' : 'grid') ?>
        <?= e((string)$g['nombre']) ?>
      </h2>
      <div class="acciones">
        <button class="bt bt--sm bt--suave" type="button" data-modal="modalGrupo" data-titulo="Editar grupo"
                data-rellenar='<?= e(json_encode([
                    'id' => (int)$g['id'], 'nombre' => $g['nombre'], 'nombre_en' => $g['nombre_en'],
                    'tipo' => $g['tipo'], 'obligatorio' => (int)$g['obligatorio'],
                    'min_sel' => (int)$g['min_sel'], 'max_sel' => (int)$g['max_sel'],
                    'activo' => (int)$g['activo'],
                ], JSON_UNESCAPED_UNICODE)) ?>'><?= icon('edit', 'ico-sm') ?></button>
        <button class="bt bt--sm bt--suave" type="button" data-borrar-grupo="<?= (int)$g['id'] ?>"
                data-nombre="<?= e((string)$g['nombre']) ?>" data-usado="<?= (int)$g['usado'] ?>">
          <?= icon('trash', 'ico-sm') ?>
        </button>
      </div>
    </div>

    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
      <span class="insignia"><?= $g['tipo'] === 'unico' ? 'Elige una' : 'Hasta ' . (int)$g['max_sel'] ?></span>
      <?php if ((int)$g['obligatorio'] === 1): ?><span class="insignia insignia--oro">Obligatorio</span><?php endif; ?>
      <?php if ((int)$g['activo'] !== 1): ?><span class="insignia insignia--peligro">Inactivo</span><?php endif; ?>
      <span class="insignia insignia--info">En <?= (int)$g['usado'] ?> platillo(s)</span>
    </div>

    <?php foreach ($g['opciones'] as $o): ?>
      <div class="entre" style="padding:9px 0;border-top:1px solid var(--p-borde)">
        <span class="crece truncar">
          <?= e((string)$o['nombre']) ?>
          <?php if ((int)$o['predeterminado'] === 1): ?><span class="insignia insignia--oro">Por defecto</span><?php endif; ?>
          <?php if ((int)$o['agotado'] === 1): ?><span class="insignia insignia--peligro">Agotada</span><?php endif; ?>
        </span>
        <span class="mono" style="color:var(--p-oro);flex:0 0 auto">
          <?= (float)$o['precio_extra'] > 0 ? '+' . e(money($o['precio_extra'], $s)) : '—' ?>
        </span>
        <button class="bt bt--sm bt--suave" type="button" data-modal="modalOpcion" data-titulo="Editar opción"
                data-rellenar='<?= e(json_encode([
                    'option_id' => (int)$o['id'], 'group_id' => (int)$g['id'], 'nombre' => $o['nombre'],
                    'nombre_en' => $o['nombre_en'], 'precio_extra' => (float)$o['precio_extra'],
                    'activo' => (int)$o['activo'], 'agotado' => (int)$o['agotado'],
                    'predeterminado' => (int)$o['predeterminado'],
                ], JSON_UNESCAPED_UNICODE)) ?>'><?= icon('edit', 'ico-sm') ?></button>
        <button class="bt bt--sm bt--suave" type="button" data-borrar-opcion="<?= (int)$o['id'] ?>"><?= icon('x', 'ico-sm') ?></button>
      </div>
    <?php endforeach; ?>

    <button class="bt bt--sm bt--linea bt--bloque" type="button" style="margin-top:12px"
            data-modal="modalOpcion" data-limpiar="1" data-titulo="Nueva opción"
            data-rellenar='<?= e(json_encode(['group_id' => (int)$g['id'], 'option_id' => 0])) ?>'>
      <?= icon('plus') ?> Agregar opción
    </button>
  </div>
<?php endforeach; ?>
</div>

<!-- ============ Modal grupo ============ -->
<div class="modal-p" id="modalGrupo" role="dialog" aria-modal="true">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja">
    <form data-ajax action="<?= e(url('panel/modificadores/guardar')) ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="" data-limpiable>
      <div class="modal-p__cab">
        <h2 class="modal-p__titulo">Nuevo grupo</h2>
        <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
      </div>
      <div class="modal-p__cuerpo">
        <div class="campo-p">
          <label for="gNombre">Nombre del grupo *</label>
          <input type="text" id="gNombre" name="nombre" required maxlength="120" placeholder="Ej. Término de la carne">
        </div>
        <div class="campo-p">
          <label for="gTipo">Tipo de selección</label>
          <select id="gTipo" name="tipo">
            <option value="unico">Una sola opción (radio)</option>
            <option value="multiple">Varias opciones (casillas)</option>
          </select>
        </div>
        <div class="fila-campos" id="cajaLimites">
          <div class="campo-p">
            <label for="gMin">Mínimo a elegir</label>
            <input type="number" id="gMin" name="min_sel" min="0" max="10" value="0" inputmode="numeric">
          </div>
          <div class="campo-p">
            <label for="gMax">Máximo a elegir</label>
            <input type="number" id="gMax" name="max_sel" min="1" max="20" value="1" inputmode="numeric">
          </div>
        </div>
        <label class="interruptor" style="margin-bottom:6px">
          <input type="checkbox" name="obligatorio" value="1">
          <span class="interruptor__pista"></span>
          <span class="interruptor__texto">Obligatorio (el cliente debe elegir)</span>
        </label><br>
        <label class="interruptor">
          <input type="checkbox" name="activo" value="1" checked>
          <span class="interruptor__pista"></span>
          <span class="interruptor__texto">Grupo activo</span>
        </label>
        <div class="campo-p" style="margin-top:14px">
          <label for="gNombreEn">Nombre en inglés</label>
          <input type="text" id="gNombreEn" name="nombre_en" maxlength="120">
        </div>
      </div>
      <div class="modal-p__pie">
        <button class="bt bt--linea" type="button" data-cerrar-modal>Cancelar</button>
        <button class="bt bt--oro" type="submit"><?= icon('save') ?> Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal opción ============ -->
<div class="modal-p" id="modalOpcion" role="dialog" aria-modal="true">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja" style="width:min(460px,calc(100vw - 28px))">
    <form data-ajax action="<?= e(url('panel/modificadores/opcion')) ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="option_id" value="" data-limpiable>
      <input type="hidden" name="group_id" value="" data-limpiable>
      <div class="modal-p__cab">
        <h2 class="modal-p__titulo">Nueva opción</h2>
        <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
      </div>
      <div class="modal-p__cuerpo">
        <div class="campo-p">
          <label for="oNombre">Nombre de la opción *</label>
          <input type="text" id="oNombre" name="nombre" required maxlength="120" placeholder="Ej. Término medio">
        </div>
        <div class="campo-p">
          <label for="oPrecio">Costo adicional</label>
          <div class="grupo-prefijo">
            <span><?= e($s) ?></span>
            <input type="number" id="oPrecio" name="precio_extra" step="0.01" min="0" value="0" inputmode="decimal">
          </div>
          <p class="ayuda-p">Deja 0 si no cuesta más.</p>
        </div>
        <label class="interruptor" style="margin-bottom:6px">
          <input type="checkbox" name="predeterminado" value="1">
          <span class="interruptor__pista"></span><span class="interruptor__texto">Seleccionada por defecto</span>
        </label><br>
        <label class="interruptor" style="margin-bottom:6px">
          <input type="checkbox" name="agotado" value="1">
          <span class="interruptor__pista"></span><span class="interruptor__texto">Agotada por hoy</span>
        </label><br>
        <label class="interruptor">
          <input type="checkbox" name="activo" value="1" checked>
          <span class="interruptor__pista"></span><span class="interruptor__texto">Activa</span>
        </label>
        <div class="campo-p" style="margin-top:14px">
          <label for="oNombreEn">Nombre en inglés</label>
          <input type="text" id="oNombreEn" name="nombre_en" maxlength="120">
        </div>
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
(function () {
  var M = window.MGPanel;
  var tipo = document.getElementById('gTipo');
  var caja = document.getElementById('cajaLimites');
  function alternar() { if (caja) caja.style.display = tipo.value === 'multiple' ? '' : 'none'; }
  if (tipo) { tipo.addEventListener('change', alternar); alternar(); }

  document.addEventListener('click', function (ev) {
    var g = ev.target.closest('[data-borrar-grupo]');
    if (g) {
      var usado = Number(g.dataset.usado);
      var txt = 'Se eliminará el grupo "' + g.dataset.nombre + '" y todas sus opciones.'
        + (usado > 0 ? ' Está en uso por ' + usado + ' platillo(s).' : '');
      M.confirmar(txt, 'Eliminar grupo', 'Sí, eliminar').then(function (ok) {
        if (!ok) return;
        M.pedir('panel/modificadores/borrar', { id: Number(g.dataset.borrarGrupo) }).then(function (r) {
          if (r.ok) location.reload(); else M.avisar(r.error, 'error');
        });
      });
      return;
    }
    var o = ev.target.closest('[data-borrar-opcion]');
    if (o) {
      M.confirmar('¿Eliminar esta opción?', 'Eliminar opción', 'Sí, eliminar').then(function (ok) {
        if (!ok) return;
        M.pedir('panel/modificadores/opcion-borrar', { option_id: Number(o.dataset.borrarOpcion) }).then(function (r) {
          if (r.ok) { o.parentElement.remove(); M.avisar(r.mensaje, 'ok'); }
          else M.avisar(r.error, 'error');
        });
      });
    }
  });
})();
</script>
<?php View::stop(); ?>
