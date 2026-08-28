<div class="contenedor-sm" style="margin-left:0">
  <a class="btn btn-claro btn-sm mb-3" href="<?= e(url('/portal/visitas')) ?>"><?= ico('flechaIzq', 16) ?> Volver a mis visitas</a>
  <form method="post">
    <?= csrf() ?>
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3>Autorizar una visita</h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campos">
          <div class="campo campo-ancho">
            <label for="visitante">¿Quién lo visita? *</label>
            <input type="text" id="visitante" name="visitante" required maxlength="140" autofocus
                   placeholder="Nombre completo de su visita">
          </div>
          <div class="campo">
            <label for="placa">Placa del vehículo</label>
            <input type="text" id="placa" name="placa" maxlength="20" style="text-transform:uppercase" placeholder="P123ABC">
          </div>
          <div class="campo">
            <label for="dpi">DPI (opcional)</label>
            <input type="text" id="dpi" name="dpi" maxlength="30" inputmode="numeric">
          </div>
          <div class="campo campo-ancho">
            <label for="motivo">Motivo</label>
            <input type="text" id="motivo" name="motivo" maxlength="140" placeholder="Visita familiar, entrega, servicio…">
          </div>
          <div class="campo">
            <label for="valido_desde">Válido desde *</label>
            <input type="datetime-local" id="valido_desde" name="valido_desde" required value="<?= e(date('Y-m-d\TH:i')) ?>">
          </div>
          <div class="campo">
            <label for="valido_hasta">Válido hasta *</label>
            <input type="datetime-local" id="valido_hasta" name="valido_hasta" required
                   value="<?= e(date('Y-m-d\TH:i', time() + 86400)) ?>">
          </div>
        </div>

        <label class="marca-check mb-2">
          <input type="checkbox" name="recurrente" value="1" id="recurrente">
          <span><strong>Acceso recurrente</strong> — para personal doméstico, jardinero o niñera que entra varias veces.</span>
        </label>

        <fieldset id="bloque-recurrente" hidden>
          <legend>Horario permitido</legend>
          <div class="campo">
            <span class="etiqueta">Días de la semana</span>
            <div class="fila envolver" style="gap:12px">
              <?php foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 0 => 'Dom'] as $n => $et): ?>
                <label class="marca-check" style="margin:0">
                  <input type="checkbox" name="dias[]" value="<?= $n ?>" <?= $n >= 1 && $n <= 5 ? 'checked' : '' ?>>
                  <span><?= e($et) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="campos">
            <div class="campo"><label for="hora_desde">Desde</label><input type="time" id="hora_desde" name="hora_desde" value="07:00"></div>
            <div class="campo"><label for="hora_hasta">Hasta</label><input type="time" id="hora_hasta" name="hora_hasta" value="18:00"></div>
          </div>
        </fieldset>

        <div class="campo" id="bloque-usos">
          <label for="max_usos">¿Cuántas veces puede entrar?</label>
          <select id="max_usos" name="max_usos">
            <option value="1">Una sola vez</option>
            <option value="2">Dos veces</option>
            <option value="5">Hasta 5 veces</option>
          </select>
        </div>
      </div>
      <div class="tarjeta-pie fila-fin">
        <a class="btn btn-claro" href="<?= e(url('/portal/visitas')) ?>">Cancelar</a>
        <button class="btn btn-oro btn-lg" type="submit"><?= ico('qr', 18) ?> Crear el código</button>
      </div>
    </div>
  </form>
</div>

<script<?= nonce() ?>>
(function () {
  var chk = document.getElementById('recurrente');
  var bloque = document.getElementById('bloque-recurrente');
  var usos = document.getElementById('bloque-usos');
  var hasta = document.getElementById('valido_hasta');
  function actualizar() {
    bloque.hidden = !chk.checked;
    usos.hidden = chk.checked;
    if (chk.checked) {
      var d = new Date(); d.setMonth(d.getMonth() + 6);
      hasta.value = d.toISOString().slice(0, 16);
    }
  }
  chk.addEventListener('change', actualizar);
  actualizar();
})();
</script>
