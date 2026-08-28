<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,360px)">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Proveedores registrados</h3><span class="chip oro"><?= count($proveedores) ?></span></div>
    <div class="tabla-caja">
      <table class="tabla apilar">
        <thead><tr><th>Proveedor</th><th>Servicio</th><th>Contacto</th><th class="d">Pagado este año</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($proveedores as $p): ?>
            <tr>
              <td data-et="Proveedor">
                <b><?= e($p['nombre']) ?></b>
                <?php if (!empty($p['nit'])): ?><div class="meta texto-3">NIT <?= e($p['nit']) ?></div><?php endif; ?>
                <?php if ((int) $p['activo'] === 0): ?><span class="chip neutro">Inactivo</span><?php endif; ?>
              </td>
              <td data-et="Servicio" class="texto-2"><?= e(recortar((string) $p['servicio'], 34) ?: '—') ?></td>
              <td data-et="Contacto" class="texto-3" style="font-size:.85rem">
                <?= e($p['contacto'] ?? '') ?><?php if (!empty($p['telefono'])): ?><div><?= e($p['telefono']) ?></div><?php endif; ?>
              </td>
              <td data-et="Pagado" class="d num"><?= e(q((float) $p['pagado_anio'])) ?></td>
              <td data-et="" class="d">
                <button class="btn btn-sm btn-fantasma" type="button" aria-label="Editar proveedor"
                        data-proveedor="<?= e(json_encode($p, JSON_UNESCAPED_UNICODE)) ?>"><?= ico('editar', 15) ?></button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </article>

  <form method="post" id="f-proveedor" style="align-self:start">
    <?= csrf() ?>
    <input type="hidden" name="id" id="prov-id" value="0">
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3>Agregar o editar</h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campo"><label for="prov-nombre">Nombre *</label>
          <input type="text" id="prov-nombre" name="nombre" required maxlength="140"></div>
        <div class="campos">
          <div class="campo"><label for="prov-nit">NIT</label><input type="text" id="prov-nit" name="nit" maxlength="30"></div>
          <div class="campo"><label for="prov-tel">Teléfono</label><input type="tel" id="prov-tel" name="telefono" maxlength="40"></div>
        </div>
        <div class="campo"><label for="prov-contacto">Persona de contacto</label>
          <input type="text" id="prov-contacto" name="contacto" maxlength="120"></div>
        <div class="campo"><label for="prov-correo">Correo</label>
          <input type="email" id="prov-correo" name="correo" maxlength="160"></div>
        <div class="campo"><label for="prov-servicio">Servicio que presta</label>
          <input type="text" id="prov-servicio" name="servicio" maxlength="120"></div>
        <label class="marca-check"><input type="checkbox" name="activo" value="1" checked id="prov-activo"><span>Proveedor activo</span></label>
      </div>
      <div class="tarjeta-pie fila-fin">
        <button type="button" class="btn btn-claro btn-sm" data-limpiar-proveedor>Limpiar</button>
        <button class="btn btn-oro btn-sm" type="submit"><?= ico('guardar', 15) ?> Guardar</button>
      </div>
    </div>
  </form>
</div>

<script<?= nonce() ?>>
document.querySelectorAll('[data-proveedor]').forEach(function (b) {
  b.addEventListener('click', function () {
    var p = JSON.parse(b.dataset.proveedor);
    document.getElementById('prov-id').value = p.id;
    document.getElementById('prov-nombre').value = p.nombre || '';
    document.getElementById('prov-nit').value = p.nit || '';
    document.getElementById('prov-tel').value = p.telefono || '';
    document.getElementById('prov-contacto').value = p.contacto || '';
    document.getElementById('prov-correo').value = p.correo || '';
    document.getElementById('prov-servicio').value = p.servicio || '';
    document.getElementById('prov-activo').checked = Number(p.activo) === 1;
    document.getElementById('f-proveedor').scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
});
document.querySelectorAll('[data-limpiar-proveedor]').forEach(function (b) {
  b.addEventListener('click', function () {
    document.getElementById('f-proveedor').reset();
    document.getElementById('prov-id').value = 0;
  });
});
</script>
