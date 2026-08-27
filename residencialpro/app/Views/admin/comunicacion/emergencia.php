<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,340px)">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Directorio de emergencia</h3>
      <span class="texto-3" style="font-size:.82rem">Visible en la garita y en el portal</span>
    </div>
    <div class="tabla-caja">
      <table class="tabla">
        <thead><tr><th>Nombre</th><th>Tipo</th><th class="d">Teléfono</th><th class="c">Orden</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($contactos as $c): ?>
            <tr>
              <td class="fuerte"><?= e($c['nombre']) ?></td>
              <td class="texto-2"><?= e($c['tipo'] ?? '—') ?></td>
              <td class="d fuerte"><?= e($c['telefono']) ?></td>
              <td class="c num"><?= (int) $c['orden'] ?></td>
              <td class="d">
                <button class="btn btn-sm btn-fantasma" type="button" aria-label="Editar contacto"
                        data-contacto="<?= e(json_encode($c, JSON_UNESCAPED_UNICODE)) ?>"><?= ico('editar', 15) ?></button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </article>

  <form method="post" id="f-contacto" style="align-self:start">
    <?= csrf() ?>
    <input type="hidden" name="id" id="con-id" value="0">
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3>Agregar o editar</h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campo"><label for="con-nombre">Nombre *</label>
          <input type="text" id="con-nombre" name="nombre" required maxlength="120" placeholder="Bomberos Voluntarios"></div>
        <div class="campo"><label for="con-telefono">Teléfono *</label>
          <input type="text" id="con-telefono" name="telefono" required maxlength="60" placeholder="122"></div>
        <div class="campo"><label for="con-tipo">Tipo</label>
          <input type="text" id="con-tipo" name="tipo" maxlength="60" placeholder="Emergencias"></div>
        <div class="campo"><label for="con-orden">Orden</label>
          <input type="number" id="con-orden" name="orden" min="0" value="<?= count($contactos) + 1 ?>"></div>
      </div>
      <div class="tarjeta-pie fila-fin">
        <button class="btn btn-oro btn-sm" type="submit"><?= ico('guardar', 15) ?> Guardar</button>
      </div>
    </div>
  </form>
</div>
<script<?= nonce() ?>>
document.querySelectorAll('[data-contacto]').forEach(function (b) {
  b.addEventListener('click', function () {
    var c = JSON.parse(b.dataset.contacto);
    document.getElementById('con-id').value = c.id;
    document.getElementById('con-nombre').value = c.nombre || '';
    document.getElementById('con-telefono').value = c.telefono || '';
    document.getElementById('con-tipo').value = c.tipo || '';
    document.getElementById('con-orden').value = c.orden || 0;
  });
});
</script>
