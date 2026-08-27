<div class="contenedor-sm" style="margin-left:0">
  <a class="btn btn-claro btn-sm mb-3" href="<?= e(url('/admin/avisos')) ?>"><?= ico('flechaIzq', 16) ?> Volver a avisos</a>
  <form method="post" enctype="multipart/form-data">
    <?= csrf() ?>
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3><?= $aviso === null ? 'Publicar un aviso' : 'Editar aviso' ?></h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campo">
          <label for="titulo">Título *</label>
          <input type="text" id="titulo" name="titulo" required maxlength="190" value="<?= e($aviso['titulo'] ?? '') ?>"
                 placeholder="Corte programado de agua">
        </div>
        <div class="campo">
          <label for="cuerpo">Contenido *</label>
          <textarea id="cuerpo" name="cuerpo" rows="8" required minlength="10" maxlength="6000"><?= e($aviso['cuerpo'] ?? '') ?></textarea>
          <span class="ayuda">Escriba con claridad. Los saltos de línea se respetan.</span>
        </div>
        <div class="campos">
          <div class="campo">
            <label for="alcance">¿A quién va dirigido? *</label>
            <select id="alcance" name="alcance" required data-alcance>
              <?php foreach (['todos' => 'Todo el residencial', 'fase' => 'Una fase', 'calle' => 'Una calle', 'casa' => 'Una vivienda'] as $k => $et): ?>
                <option value="<?= e($k) ?>" <?= ($aviso['alcance'] ?? 'todos') === $k ? 'selected' : '' ?>><?= e($et) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo" id="campo-destino" hidden>
            <label for="destino_id">Destino</label>
            <select id="destino_id" name="destino_id">
              <optgroup label="Fases" data-grupo="fase">
                <?php foreach ($fases as $f): ?>
                  <option value="<?= (int) $f['id'] ?>" data-tipo="fase" <?= ($aviso['alcance'] ?? '') === 'fase' && (int) ($aviso['destino_id'] ?? 0) === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['nombre']) ?></option>
                <?php endforeach; ?>
              </optgroup>
              <optgroup label="Calles" data-grupo="calle">
                <?php foreach ($calles as $c): ?>
                  <option value="<?= (int) $c['id'] ?>" data-tipo="calle" <?= ($aviso['alcance'] ?? '') === 'calle' && (int) ($aviso['destino_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['fase'] ?? '') ?> · <?= e($c['nombre']) ?></option>
                <?php endforeach; ?>
              </optgroup>
              <optgroup label="Viviendas" data-grupo="casa">
                <?php foreach ($casas as $c): ?>
                  <option value="<?= (int) $c['id'] ?>" data-tipo="casa" <?= ($aviso['alcance'] ?? '') === 'casa' && (int) ($aviso['destino_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['codigo']) ?></option>
                <?php endforeach; ?>
              </optgroup>
            </select>
          </div>
          <div class="campo">
            <label for="prioridad">Prioridad</label>
            <select id="prioridad" name="prioridad">
              <?php foreach (['normal' => 'Normal', 'importante' => 'Importante', 'urgente' => 'Urgente'] as $k => $et): ?>
                <option value="<?= e($k) ?>" <?= ($aviso['prioridad'] ?? 'normal') === $k ? 'selected' : '' ?>><?= e($et) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="publicar_en">Publicar el</label>
            <input type="datetime-local" id="publicar_en" name="publicar_en"
                   value="<?= e(date('Y-m-d\TH:i', (int) strtotime((string) ($aviso['publicar_en'] ?? 'now')))) ?>">
          </div>
          <div class="campo">
            <label for="vence_en">Dejar de mostrar el</label>
            <input type="datetime-local" id="vence_en" name="vence_en"
                   value="<?= !empty($aviso['vence_en']) ? e(date('Y-m-d\TH:i', (int) strtotime((string) $aviso['vence_en']))) : '' ?>">
          </div>
          <div class="campo">
            <label for="imagen">Imagen</label>
            <input type="file" id="imagen" name="imagen" accept="image/*" data-previa="#previa-aviso">
            <?php if (!empty($aviso['imagen'])): ?>
              <img id="previa-aviso" src="<?= e(subida($aviso['imagen'], 'avisos')) ?>" alt="Imagen del aviso"
                   style="margin-top:10px;border-radius:var(--r-sm);max-height:130px">
            <?php else: ?>
              <img id="previa-aviso" src="<?= e(url('/assets/img/vacio.svg')) ?>" alt="" hidden
                   style="margin-top:10px;border-radius:var(--r-sm);max-height:130px">
            <?php endif; ?>
          </div>
          <div class="campo">
            <label for="archivo">Adjunto (PDF)</label>
            <input type="file" id="archivo" name="archivo" accept="application/pdf,image/*">
          </div>
        </div>
        <label class="marca-check">
          <input type="checkbox" name="confirmar" value="1" <?= (int) ($aviso['confirmar'] ?? 0) === 1 ? 'checked' : '' ?>>
          <span>Solicitar confirmación de lectura</span>
        </label>
      </div>
      <div class="tarjeta-pie fila-fin">
        <a class="btn btn-claro" href="<?= e(url('/admin/avisos')) ?>">Cancelar</a>
        <button class="btn btn-oro" type="submit"><?= ico('megafono', 17) ?> <?= $aviso === null ? 'Publicar aviso' : 'Guardar cambios' ?></button>
      </div>
    </div>
  </form>
</div>

<script<?= nonce() ?>>
(function () {
  var alcance = document.querySelector('[data-alcance]');
  var campo = document.getElementById('campo-destino');
  var select = document.getElementById('destino_id');
  function actualizar() {
    var v = alcance.value;
    campo.hidden = (v === 'todos');
    Array.from(select.querySelectorAll('optgroup')).forEach(function (g) {
      g.hidden = g.dataset.grupo !== v;
      Array.from(g.children).forEach(function (o) { o.disabled = g.hidden; });
    });
    var visible = select.querySelector('optgroup:not([hidden]) option:not([disabled])');
    if (visible && (!select.selectedOptions[0] || select.selectedOptions[0].disabled)) visible.selected = true;
  }
  alcance.addEventListener('change', actualizar);
  actualizar();
})();
</script>
