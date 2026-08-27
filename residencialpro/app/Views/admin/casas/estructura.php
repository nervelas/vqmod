<section class="rejilla rejilla-2">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Fases o sectores</h3><span class="chip oro"><?= count($fases) ?></span></div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($fases === []): ?>
        <p class="texto-3 centrado" style="padding:18px 0">Aún no hay fases registradas.</p>
      <?php else: ?>
        <table class="tabla">
          <thead><tr><th>Fase</th><th class="c">Viviendas</th><th class="c">Orden</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($fases as $f): ?>
              <tr>
                <td>
                  <b><?= e($f['nombre']) ?></b>
                  <?php if (!empty($f['descripcion'])): ?><div class="meta texto-3"><?= e(recortar((string) $f['descripcion'], 60)) ?></div><?php endif; ?>
                </td>
                <td class="c num"><?= (int) $f['casas'] ?></td>
                <td class="c num"><?= (int) $f['orden'] ?></td>
                <td class="d">
                  <button class="btn btn-sm btn-fantasma" type="button" aria-label="Editar fase"
                          data-fase="<?= e(json_encode($f, JSON_UNESCAPED_UNICODE)) ?>"><?= ico('editar', 15) ?></button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <div class="tarjeta-pie">
      <form method="post" action="<?= e(url('/admin/estructura/fase')) ?>" id="f-fase">
        <?= csrf() ?>
        <input type="hidden" name="id" id="fase-id" value="0">
        <div class="campos">
          <div class="campo"><label for="fase-nombre">Nombre de la fase</label>
            <input type="text" id="fase-nombre" name="nombre" required maxlength="90" placeholder="Fase I · Los Robles"></div>
          <div class="campo"><label for="fase-orden">Orden</label>
            <input type="number" id="fase-orden" name="orden" value="<?= count($fases) + 1 ?>" min="0"></div>
          <div class="campo campo-ancho"><label for="fase-desc">Descripción</label>
            <input type="text" id="fase-desc" name="descripcion" maxlength="255"></div>
        </div>
        <label class="marca-check mb-2"><input type="checkbox" name="activo" value="1" checked id="fase-activo"><span>Fase activa</span></label>
        <div class="fila-fin">
          <button type="button" class="btn btn-claro btn-sm" data-limpiar-fase>Limpiar</button>
          <button class="btn btn-oro btn-sm" type="submit"><?= ico('guardar', 15) ?> Guardar fase</button>
        </div>
      </form>
    </div>
  </article>

  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Calles</h3><span class="chip oro"><?= count($calles) ?></span></div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($calles === []): ?>
        <p class="texto-3 centrado" style="padding:18px 0">Aún no hay calles registradas.</p>
      <?php else: ?>
        <table class="tabla">
          <thead><tr><th>Calle</th><th>Fase</th><th class="c">Orden</th></tr></thead>
          <tbody>
            <?php foreach ($calles as $c): ?>
              <tr><td><b><?= e($c['nombre']) ?></b></td><td class="texto-2"><?= e($c['fase'] ?? '') ?></td><td class="c num"><?= (int) $c['orden'] ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <div class="tarjeta-pie">
      <form method="post" action="<?= e(url('/admin/estructura/calle')) ?>">
        <?= csrf() ?>
        <div class="campos">
          <div class="campo"><label for="calle-fase">Fase</label>
            <select id="calle-fase" name="fase_id" required>
              <?php foreach ($fases as $f): ?><option value="<?= (int) $f['id'] ?>"><?= e($f['nombre']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="campo"><label for="calle-nombre">Nombre de la calle</label>
            <input type="text" id="calle-nombre" name="nombre" required maxlength="90" placeholder="Calle de los Cipreses"></div>
          <div class="campo"><label for="calle-orden">Orden</label>
            <input type="number" id="calle-orden" name="orden" value="<?= count($calles) + 1 ?>" min="0"></div>
        </div>
        <div class="fila-fin"><button class="btn btn-oro btn-sm" type="submit"><?= ico('mas', 15) ?> Agregar calle</button></div>
      </form>
    </div>
  </article>
</section>

<script<?= nonce() ?>>
document.querySelectorAll('[data-fase]').forEach(function (b) {
  b.addEventListener('click', function () {
    var f = JSON.parse(b.dataset.fase);
    document.getElementById('fase-id').value = f.id;
    document.getElementById('fase-nombre').value = f.nombre || '';
    document.getElementById('fase-desc').value = f.descripcion || '';
    document.getElementById('fase-orden').value = f.orden || 0;
    document.getElementById('fase-activo').checked = Number(f.activo) === 1;
    document.getElementById('f-fase').scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
});
document.querySelectorAll('[data-limpiar-fase]').forEach(function (b) {
  b.addEventListener('click', function () {
    document.getElementById('f-fase').reset();
    document.getElementById('fase-id').value = 0;
  });
});
</script>
