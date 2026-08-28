<div class="contenedor-sm" style="margin-left:0">
  <a class="btn btn-claro btn-sm mb-3" href="<?= e(url('/admin/votaciones')) ?>"><?= ico('flechaIzq', 16) ?> Volver</a>
  <form method="post">
    <?= csrf() ?>
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3>Nueva votación</h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campo">
          <label for="titulo">Pregunta o tema *</label>
          <input type="text" id="titulo" name="titulo" required maxlength="190"
                 placeholder="Instalación de cámaras en el perímetro">
        </div>
        <div class="campo">
          <label for="detalle">Explicación para los propietarios</label>
          <textarea id="detalle" name="detalle" rows="5" maxlength="4000"
                    placeholder="Describa la propuesta, el costo estimado y de dónde saldrían los fondos."></textarea>
        </div>
        <div class="campo">
          <span class="etiqueta">Opciones de respuesta *</span>
          <div id="opciones" class="columna" style="gap:8px">
            <input type="text" name="opciones[]" maxlength="190" placeholder="A favor" required>
            <input type="text" name="opciones[]" maxlength="190" placeholder="En contra" required>
          </div>
          <button type="button" class="btn btn-claro btn-sm mt-1" data-agregar-opcion><?= ico('mas', 15) ?> Agregar opción</button>
        </div>
        <div class="campos">
          <div class="campo">
            <label for="modo">Forma de contar los votos</label>
            <select id="modo" name="modo">
              <option value="casa">Una vivienda, un voto</option>
              <option value="coeficiente">Ponderado por coeficiente de participación</option>
            </select>
          </div>
          <div class="campo">
            <label for="quorum">Quórum requerido (%)</label>
            <input type="number" id="quorum" name="quorum" min="0" max="100" step="0.01" value="50">
          </div>
          <div class="campo">
            <label for="inicio">Abre el</label>
            <input type="datetime-local" id="inicio" name="inicio" value="<?= e(date('Y-m-d\TH:i')) ?>">
          </div>
          <div class="campo">
            <label for="fin">Cierra el</label>
            <input type="datetime-local" id="fin" name="fin" value="<?= e(date('Y-m-d\TH:i', strtotime('+7 days'))) ?>">
          </div>
        </div>
        <label class="marca-check">
          <input type="checkbox" name="abrir" value="1" checked>
          <span>Abrir de inmediato y notificar a los residentes</span>
        </label>
      </div>
      <div class="tarjeta-pie fila-fin">
        <a class="btn btn-claro" href="<?= e(url('/admin/votaciones')) ?>">Cancelar</a>
        <button class="btn btn-oro" type="submit"><?= ico('voto', 17) ?> Crear votación</button>
      </div>
    </div>
  </form>
</div>
<script<?= nonce() ?>>
document.querySelectorAll('[data-agregar-opcion]').forEach(function (b) {
  b.addEventListener('click', function () {
    var cont = document.getElementById('opciones');
    if (cont.children.length >= 8) { RP.aviso('Máximo 8 opciones.', 'info'); return; }
    var i = document.createElement('input');
    i.type = 'text'; i.name = 'opciones[]'; i.maxLength = 190;
    i.placeholder = 'Opción ' + (cont.children.length + 1);
    cont.appendChild(i); i.focus();
  });
});
</script>
