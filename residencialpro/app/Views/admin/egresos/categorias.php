<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,340px)">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Categorías de gasto</h3></div>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Categoría</th><th class="c">Color</th><th class="c">Estado</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($categorias as $c): ?>
            <tr>
              <td class="fuerte"><?= e($c['nombre']) ?></td>
              <td class="c"><span style="display:inline-block;width:26px;height:14px;border-radius:4px;background:<?= e($c['color']) ?>"></span></td>
              <td class="c"><span class="chip <?= (int) $c['activo'] === 1 ? 'ok' : 'neutro' ?>"><?= (int) $c['activo'] === 1 ? 'Activa' : 'Inactiva' ?></span></td>
              <td class="d">
                <button class="btn btn-sm btn-fantasma" type="button" aria-label="Editar categoría"
                        data-categoria="<?= e(json_encode($c, JSON_UNESCAPED_UNICODE)) ?>"><?= ico('editar', 15) ?></button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </article>

  <form method="post" id="f-categoria" style="align-self:start">
    <?= csrf() ?>
    <input type="hidden" name="id" id="cat-id" value="0">
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3>Agregar o editar</h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campo"><label for="cat-nombre">Nombre *</label>
          <input type="text" id="cat-nombre" name="nombre" required maxlength="120" placeholder="Seguridad"></div>
        <div class="campo"><label for="cat-color">Color en las gráficas</label>
          <input type="color" id="cat-color" name="color" value="#C9A961"></div>
        <label class="marca-check"><input type="checkbox" name="activo" value="1" checked id="cat-activo"><span>Categoría activa</span></label>
      </div>
      <div class="tarjeta-pie fila-fin">
        <button class="btn btn-oro btn-sm" type="submit"><?= ico('guardar', 15) ?> Guardar</button>
      </div>
    </div>
  </form>
</div>
<script<?= nonce() ?>>
document.querySelectorAll('[data-categoria]').forEach(function (b) {
  b.addEventListener('click', function () {
    var c = JSON.parse(b.dataset.categoria);
    document.getElementById('cat-id').value = c.id;
    document.getElementById('cat-nombre').value = c.nombre || '';
    document.getElementById('cat-color').value = c.color || '#C9A961';
    document.getElementById('cat-activo').checked = Number(c.activo) === 1;
  });
});
</script>
