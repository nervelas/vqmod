<?php
/** @var array $mesas, $zonas, $limites; string $urlGeneral */
use MenuGold\Core\Security;
use MenuGold\Core\View;
View::set('titulo', 'Mesas y QR');
View::set('subtitulo', count($mesas) . ' mesa(s)'
    . ((int)$limites['max_mesas'] > 0 ? ' · tu plan permite ' . (int)$limites['max_mesas'] : ''));

View::start('acciones');
?>
<a class="bt bt--linea" href="<?= e(url('panel/qr')) ?>"><?= icon('qr') ?><span class="oculto-movil">Imprimir QR</span></a>
<button class="bt bt--oro" type="button" data-modal="modalMesa" data-limpiar="1" data-titulo="Nueva mesa">
  <?= icon('plus') ?><span>Nueva</span>
</button>
<?php View::stop(); ?>

<div class="rejilla" style="grid-template-columns:minmax(0,2fr) minmax(260px,1fr);align-items:start">
  <div>
    <div class="tarjeta-p">
      <div class="tarjeta-p__cab">
        <h2 class="tarjeta-p__titulo"><?= icon('table') ?> Tus mesas</h2>
        <button class="bt bt--sm bt--linea" type="button" data-modal="modalLote"><?= icon('layers') ?> Crear varias</button>
      </div>

      <?php if (!$mesas): ?>
        <div class="vacio-p">
          <?= icon('table', 'ico-lg') ?>
          <h3>Aún no registras mesas</h3>
          <p>Cada mesa tiene su propio QR firmado. El cliente lo escanea y su pedido<br>
             llega a la cocina con el número de mesa.</p>
          <button class="bt bt--oro" type="button" data-modal="modalLote"><?= icon('plus') ?> Crear mesas en lote</button>
        </div>
      <?php else: ?>
        <div class="tabla-caja">
          <table class="tabla">
            <thead><tr><th>Mesa</th><th>Zona</th><th class="num">Capacidad</th><th>Estado</th><th>QR</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($mesas as $m): ?>
                <tr>
                  <td><strong><?= e((string)$m['nombre']) ?></strong>
                      <?php if ((int)$m['activo'] !== 1): ?><span class="insignia">Inactiva</span><?php endif; ?></td>
                  <td style="color:var(--p-suave)"><?= e($m['zone_id'] ? (string)(array_column($zonas, 'nombre', 'id')[(int)$m['zone_id']] ?? '') : '—') ?></td>
                  <td class="num"><?= (int)$m['capacidad'] ?></td>
                  <td>
                    <span class="insignia insignia--<?= $m['estado'] === 'libre' ? '' : ($m['estado'] === 'llamada' ? 'peligro' : ($m['estado'] === 'cuenta' ? 'aviso' : 'info')) ?>">
                      <?= e(ucfirst((string)$m['estado'])) ?>
                    </span>
                  </td>
                  <td>
                    <button class="bt bt--sm bt--suave" type="button" data-ver-qr="<?= (int)$m['id'] ?>"
                            data-nombre="<?= e((string)$m['nombre']) ?>" data-url="<?= e((string)$m['url']) ?>">
                      <?= icon('qr', 'ico-sm') ?> Ver
                    </button>
                  </td>
                  <td class="tabla__acciones">
                    <button class="bt bt--sm bt--suave" type="button" data-modal="modalMesa" data-titulo="Editar mesa"
                            data-rellenar='<?= e(json_encode([
                                'id' => (int)$m['id'], 'nombre' => $m['nombre'], 'capacidad' => (int)$m['capacidad'],
                                'zone_id' => (int)$m['zone_id'], 'activo' => (int)$m['activo'],
                            ], JSON_UNESCAPED_UNICODE)) ?>'><?= icon('edit', 'ico-sm') ?></button>
                    <button class="bt bt--sm bt--suave" type="button" data-borrar-mesa="<?= (int)$m['id'] ?>"
                            data-nombre="<?= e((string)$m['nombre']) ?>"><?= icon('trash', 'ico-sm') ?></button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="tarjeta-p">
      <div class="tarjeta-p__cab">
        <h2 class="tarjeta-p__titulo"><?= icon('map') ?> Zonas</h2>
        <button class="bt bt--sm bt--suave" type="button" data-modal="modalZona" data-limpiar="1"><?= icon('plus', 'ico-sm') ?></button>
      </div>
      <?php if (!$zonas): ?>
        <p style="color:var(--p-tenue);margin:0;font-size:13.5px">Sin zonas. Útiles si tienes terraza, salón y barra.</p>
      <?php else: ?>
        <?php foreach ($zonas as $z): ?>
          <div class="entre" style="padding:9px 0;border-bottom:1px solid var(--p-borde)">
            <span><?= e((string)$z['nombre']) ?></span>
            <div class="acciones">
              <button class="bt bt--sm bt--suave" type="button" data-modal="modalZona" data-titulo="Editar zona"
                      data-rellenar='<?= e(json_encode(['id' => (int)$z['id'], 'nombre' => $z['nombre']], JSON_UNESCAPED_UNICODE)) ?>'><?= icon('edit', 'ico-sm') ?></button>
              <button class="bt bt--sm bt--suave" type="button" data-borrar-zona="<?= (int)$z['id'] ?>"><?= icon('x', 'ico-sm') ?></button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="tarjeta-p">
      <div class="tarjeta-p__cab"><h2 class="tarjeta-p__titulo"><?= icon('globe') ?> QR general</h2></div>
      <p class="ayuda-p" style="margin-top:0">Para la entrada, la vitrina o tus redes. Muestra el menú sin mesa asignada.</p>
      <div style="background:#fff;border-radius:12px;padding:12px;text-align:center;margin-bottom:12px">
        <img src="<?= e(url('panel/qr/png/0')) ?>" alt="QR general del menú" style="width:100%;max-width:190px;margin:0 auto">
      </div>
      <input class="entrada" type="text" value="<?= e($urlGeneral) ?>" readonly id="urlGeneral" style="font-size:12.5px">
      <button class="bt bt--sm bt--linea bt--bloque" type="button" data-copiar="#urlGeneral" style="margin-top:8px">
        <?= icon('copy') ?> Copiar enlace
      </button>
    </div>
  </div>
</div>

<!-- ============ Modales ============ -->
<div class="modal-p" id="modalMesa" role="dialog" aria-modal="true">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja" style="width:min(440px,calc(100vw - 28px))">
    <form data-ajax action="<?= e(url('panel/mesas/guardar')) ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="" data-limpiable>
      <div class="modal-p__cab">
        <h2 class="modal-p__titulo">Nueva mesa</h2>
        <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
      </div>
      <div class="modal-p__cuerpo">
        <div class="campo-p"><label for="mNombre">Nombre *</label>
          <input type="text" id="mNombre" name="nombre" required maxlength="40" placeholder="Ej. Mesa 1"></div>
        <div class="fila-campos">
          <div class="campo-p"><label for="mCap">Capacidad</label>
            <input type="number" id="mCap" name="capacidad" min="1" max="60" value="4" inputmode="numeric"></div>
          <div class="campo-p"><label for="mZona">Zona</label>
            <select id="mZona" name="zone_id"><option value="">Sin zona</option>
              <?php foreach ($zonas as $z): ?><option value="<?= (int)$z['id'] ?>"><?= e((string)$z['nombre']) ?></option><?php endforeach; ?>
            </select></div>
        </div>
        <label class="interruptor"><input type="checkbox" name="activo" value="1" checked>
          <span class="interruptor__pista"></span><span class="interruptor__texto">Mesa activa</span></label>
      </div>
      <div class="modal-p__pie">
        <button class="bt bt--linea" type="button" data-cerrar-modal>Cancelar</button>
        <button class="bt bt--oro" type="submit"><?= icon('save') ?> Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-p" id="modalLote" role="dialog" aria-modal="true">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja" style="width:min(440px,calc(100vw - 28px))">
    <form data-ajax action="<?= e(url('panel/mesas/lote')) ?>" method="post">
      <?= csrf_field() ?>
      <div class="modal-p__cab">
        <h2 class="modal-p__titulo">Crear varias mesas</h2>
        <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
      </div>
      <div class="modal-p__cuerpo">
        <div class="campo-p"><label for="lPref">Nombre base</label>
          <input type="text" id="lPref" name="prefijo" value="Mesa" maxlength="20"></div>
        <div class="fila-campos">
          <div class="campo-p"><label for="lDesde">Desde el número</label>
            <input type="number" id="lDesde" name="desde" min="1" value="1" inputmode="numeric"></div>
          <div class="campo-p"><label for="lHasta">Hasta el número</label>
            <input type="number" id="lHasta" name="hasta" min="1" value="12" inputmode="numeric"></div>
        </div>
        <div class="fila-campos">
          <div class="campo-p"><label for="lCap">Capacidad</label>
            <input type="number" id="lCap" name="capacidad" min="1" max="60" value="4" inputmode="numeric"></div>
          <div class="campo-p"><label for="lZona">Zona</label>
            <select id="lZona" name="zone_id"><option value="">Sin zona</option>
              <?php foreach ($zonas as $z): ?><option value="<?= (int)$z['id'] ?>"><?= e((string)$z['nombre']) ?></option><?php endforeach; ?>
            </select></div>
        </div>
        <p class="ayuda-p">Se crearán como «Mesa 1», «Mesa 2»… Las que ya existan se omiten.</p>
      </div>
      <div class="modal-p__pie">
        <button class="bt bt--linea" type="button" data-cerrar-modal>Cancelar</button>
        <button class="bt bt--oro" type="submit"><?= icon('plus') ?> Crear</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-p" id="modalZona" role="dialog" aria-modal="true">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja" style="width:min(400px,calc(100vw - 28px))">
    <form data-ajax action="<?= e(url('panel/mesas/zona')) ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="" data-limpiable>
      <div class="modal-p__cab">
        <h2 class="modal-p__titulo">Zona</h2>
        <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
      </div>
      <div class="modal-p__cuerpo">
        <div class="campo-p"><label for="zNombre">Nombre de la zona *</label>
          <input type="text" id="zNombre" name="nombre" required maxlength="80" placeholder="Ej. Terraza"></div>
      </div>
      <div class="modal-p__pie">
        <button class="bt bt--linea" type="button" data-cerrar-modal>Cancelar</button>
        <button class="bt bt--oro" type="submit"><?= icon('save') ?> Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-p" id="modalVerQr" role="dialog" aria-modal="true">
  <div class="modal-p__fondo" data-cerrar-modal></div>
  <div class="modal-p__caja" style="width:min(400px,calc(100vw - 28px))">
    <div class="modal-p__cab">
      <h2 class="modal-p__titulo" id="qrTitulo">QR de la mesa</h2>
      <button class="bt bt--icono bt--suave" type="button" data-cerrar-modal aria-label="Cerrar"><?= icon('x') ?></button>
    </div>
    <div class="modal-p__cuerpo" style="text-align:center">
      <div style="background:#fff;border-radius:14px;padding:16px;display:inline-block">
        <img id="qrImagen" src="" alt="Código QR" style="width:240px;height:240px">
      </div>
      <input class="entrada" type="text" id="qrUrl" readonly style="margin-top:14px;font-size:12.5px">
      <button class="bt bt--sm bt--linea bt--bloque" type="button" data-copiar="#qrUrl" style="margin-top:8px">
        <?= icon('copy') ?> Copiar enlace
      </button>
    </div>
    <div class="modal-p__pie">
      <a class="bt bt--linea" id="qrDescargar" href="#" download><?= icon('download') ?> PNG</a>
      <a class="bt bt--oro" id="qrPdf" href="#" target="_blank"><?= icon('pdf') ?> PDF para imprimir</a>
    </div>
  </div>
</div>

<?php View::start('scripts'); ?>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
  var M = window.MGPanel;
  document.addEventListener('click', function (ev) {
    var c = ev.target.closest('[data-copiar]');
    if (c) {
      var inp = document.querySelector(c.dataset.copiar);
      if (!inp) return;
      inp.select();
      if (navigator.clipboard) navigator.clipboard.writeText(inp.value);
      else document.execCommand('copy');
      M.avisar('Enlace copiado', 'ok');
      return;
    }
    var q = ev.target.closest('[data-ver-qr]');
    if (q) {
      var id = q.dataset.verQr;
      document.getElementById('qrTitulo').textContent = 'QR de ' + q.dataset.nombre;
      document.getElementById('qrImagen').src = M.url('panel/qr/png/' + id + '?escala=10');
      document.getElementById('qrUrl').value = q.dataset.url;
      document.getElementById('qrDescargar').href = M.url('panel/qr/png/' + id + '?escala=16');
      document.getElementById('qrPdf').href = M.url('panel/qr/pdf?mesas[]=' + id + '&diseno=tarjeta&tamano=a6');
      M.abrirModal('modalVerQr');
      return;
    }
    var b = ev.target.closest('[data-borrar-mesa]');
    if (b) {
      M.confirmar('Se eliminará "' + b.dataset.nombre + '" y su código QR dejará de funcionar.',
                  'Eliminar mesa', 'Sí, eliminar').then(function (ok) {
        if (!ok) return;
        M.pedir('panel/mesas/borrar', { id: Number(b.dataset.borrarMesa) }).then(function (r) {
          if (r.ok) { b.closest('tr').remove(); M.avisar(r.mensaje, 'ok'); }
          else M.avisar(r.error, 'error');
        });
      });
      return;
    }
    var z = ev.target.closest('[data-borrar-zona]');
    if (z) {
      M.confirmar('Las mesas de esta zona quedarán sin zona asignada.', 'Eliminar zona', 'Sí, eliminar')
        .then(function (ok) {
          if (!ok) return;
          M.pedir('panel/mesas/zona-borrar', { id: Number(z.dataset.borrarZona) }).then(function (r) {
            if (r.ok) location.reload(); else M.avisar(r.error, 'error');
          });
        });
    }
  });
})();
</script>
<?php View::stop(); ?>
