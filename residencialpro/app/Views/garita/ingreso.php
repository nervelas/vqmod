<section class="garita-panel">
  <h2><?= ico('escanear', 20) ?> Escanear código QR</h2>
  <p style="color:color-mix(in srgb, #fff 80%, transparent);font-size:.9rem">
    Apunte la cámara al código que le muestra el visitante. También puede escribir el código de 6 dígitos.
  </p>

  <div class="lector mb-2" id="lector" hidden>
    <video id="video" playsinline muted></video>
    <div class="lector-marco"></div>
  </div>
  <canvas id="lienzo" hidden></canvas>

  <div class="garita-botones mb-2">
    <button class="btn-garita oro" type="button" id="btn-camara"><?= ico('camara', 34) ?> Abrir la cámara</button>
  </div>

  <form method="get" action="<?= e(url('/garita/ingreso')) ?>" id="f-codigo">
    <div class="campo">
      <label for="codigo">Código de 6 dígitos</label>
      <input type="text" id="codigo" name="codigo" inputmode="numeric" maxlength="6" autocomplete="off"
             value="<?= e($codigo) ?>" style="font-size:2rem;letter-spacing:.4em;text-align:center">
    </div>
    <div class="rejilla" data-teclado="#codigo" style="grid-template-columns:repeat(3,1fr);gap:8px">
      <?php foreach (['1','2','3','4','5','6','7','8','9'] as $n): ?>
        <button type="button" class="btn btn-fantasma btn-lg" data-tecla="<?= $n ?>"
                style="color:#EFF3EF;border-color:rgba(255,255,255,.2);font-size:1.4rem"><?= $n ?></button>
      <?php endforeach; ?>
      <button type="button" class="btn btn-fantasma btn-lg" data-tecla="borrar" style="color:#EFF3EF;border-color:rgba(255,255,255,.2)">←</button>
      <button type="button" class="btn btn-fantasma btn-lg" data-tecla="0" style="color:#EFF3EF;border-color:rgba(255,255,255,.2);font-size:1.4rem">0</button>
      <button type="submit" class="btn btn-oro btn-lg" aria-label="Buscar el código"><?= ico('buscar', 20) ?></button>
    </div>
  </form>

  <?php if ($mensaje !== ''): ?>
    <div class="resultado-scan <?= $prereg !== null ? 'ok' : 'no' ?> mt-3">
      <?= ico($prereg !== null ? 'checkCirculo' : 'equisCirculo', 40) ?>
      <h3><?= $prereg !== null ? 'Autorización válida' : 'No autorizado' ?></h3>
      <p style="color:rgba(233,238,233,.8);margin:0"><?= e($mensaje) ?></p>
      <?php if ($prereg !== null): ?>
        <p style="color:#fff;font-size:1.15rem;margin:12px 0 0">
          <b><?= e($prereg['visitante']) ?></b><br>
          <span style="color:var(--arcilla-3)">Casa <?= e($casaPre['codigo'] ?? '') ?></span>
        </p>
        <?php if (!empty($casaPre) && (int) $casaPre['restringida'] === 1): ?>
          <p class="chip grave mt-2">Vivienda con restricción de servicios por mora</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<section class="garita-panel">
  <h2><?= ico('puerta', 20) ?> Registrar el ingreso</h2>
  <form method="post" action="<?= e(url('/garita/ingreso')) ?>" id="f-ingreso">
    <?= csrf() ?>
    <input type="hidden" name="codigo" value="<?= e($codigo) ?>">
    <input type="hidden" name="foto_data" id="foto_data">

    <div class="campo">
      <label for="visitante">Nombre del visitante *</label>
      <input type="text" id="visitante" name="visitante" required maxlength="140" autocomplete="off"
             value="<?= e($prereg['visitante'] ?? '') ?>">
    </div>
    <div class="campos">
      <div class="campo">
        <label for="dpi">DPI</label>
        <input type="text" id="dpi" name="dpi" maxlength="30" inputmode="numeric" autocomplete="off"
               value="<?= e($prereg['dpi'] ?? '') ?>">
      </div>
      <div class="campo">
        <label for="placa">Placa del vehículo</label>
        <input type="text" id="placa" name="placa" maxlength="20" data-placa autocomplete="off"
               style="text-transform:uppercase" value="<?= e($prereg['placa'] ?? '') ?>">
      </div>
      <div class="campo">
        <label for="casa_id">Casa de destino *</label>
        <select id="casa_id" name="casa_id" required>
          <option value="">Seleccione…</option>
          <?php foreach ($casas as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) ($casaPre['id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
              <?= e($c['codigo']) ?> · <?= e($c['fase'] ?? '') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="tipo">Tipo de ingreso</label>
        <select id="tipo" name="tipo">
          <?php foreach (['visita' => 'Visita', 'proveedor' => 'Proveedor', 'delivery' => 'Delivery',
                          'servicio' => 'Servicio (agua, gas)', 'empleado' => 'Personal doméstico', 'mudanza' => 'Mudanza'] as $k => $et): ?>
            <option value="<?= e($k) ?>"><?= e($et) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="vehiculo">Vehículo (marca y color)</label>
        <input type="text" id="vehiculo" name="vehiculo" maxlength="90" autocomplete="off">
      </div>
      <div class="campo">
        <label for="personas">Cuántas personas</label>
        <input type="number" id="personas" name="personas" min="1" max="30" value="1">
      </div>
      <div class="campo campo-ancho">
        <label for="motivo">Motivo</label>
        <input type="text" id="motivo" name="motivo" maxlength="190" autocomplete="off"
               value="<?= e($prereg['motivo'] ?? '') ?>">
      </div>
    </div>

    <div class="fila envolver mb-2" style="gap:10px">
      <button class="btn btn-fantasma" type="button" id="btn-foto"
              style="color:#EFF3EF;border-color:rgba(255,255,255,.2)"><?= ico('camara', 18) ?> Tomar fotografía</button>
      <img id="previa-foto" src="<?= e(url('/assets/img/vacio.svg')) ?>" alt="Fotografía capturada del visitante" hidden
           style="height:70px;border-radius:10px">
    </div>

    <button class="btn btn-oro btn-lg btn-bloque" type="submit"><?= ico('checkCirculo', 20) ?> Registrar ingreso</button>
  </form>
</section>

<script<?= nonce() ?>>
(function () {
  var video = document.getElementById('video');
  var lector = document.getElementById('lector');
  var lienzo = document.getElementById('lienzo');
  var abierta = false;

  document.getElementById('btn-camara').addEventListener('click', async function () {
    if (abierta) { Garita.cerrarCamara(); lector.hidden = true; abierta = false; this.innerHTML = '<?= ico('camara', 34) ?> Abrir la cámara'; return; }
    lector.hidden = false;
    abierta = await Garita.abrirCamara(video, function (valor) {
      document.getElementById('codigo').value = valor.replace(/[^0-9A-Za-z.]/g, '').slice(0, 60);
      document.getElementById('f-codigo').submit();
    });
    if (!abierta) { lector.hidden = true; }
  });

  // La fotografía abre su propia cámara. Antes exigía tener abierto el lector
  // de QR, pero esta pantalla aparece tras recargar con el código validado, es
  // decir con el lector ya cerrado: el botón sólo respondía «Abra primero la
  // cámara» y no había forma de tomar la foto.
  var btnFoto  = document.getElementById('btn-foto');
  var enFoto   = false;
  var ICO_CAM  = <?= json_encode(ico('camara', 18)) ?>;
  var ICO_TIRO = <?= json_encode(ico('checkCirculo', 18)) ?>;

  btnFoto.addEventListener('click', async function () {
    if (!enFoto && !abierta) {
      lector.hidden = false;
      btnFoto.disabled = true;
      enFoto = await Garita.abrirCamaraFoto(video);
      btnFoto.disabled = false;
      if (!enFoto) { lector.hidden = true; return; }
      btnFoto.innerHTML = ICO_TIRO + ' Capturar';
      RP.aviso('Encuadre al visitante y toque «Capturar».', 'info');
      return;
    }
    var dato = await Garita.tomarFoto(video, lienzo);
    if (!dato) { RP.aviso('La cámara todavía no da imagen. Intente otra vez.', 'error'); return; }
    document.getElementById('foto_data').value = dato;
    var img = document.getElementById('previa-foto');
    img.src = dato; img.hidden = false;
    if (enFoto) { Garita.cerrarCamara(); lector.hidden = true; enFoto = false; }
    btnFoto.innerHTML = ICO_CAM + ' Repetir fotografía';
    RP.aviso('Fotografía capturada.');
  });

  // Sin conexión: guardar el ingreso en el dispositivo.
  document.getElementById('f-ingreso').addEventListener('submit', function (ev) {
    if (navigator.onLine) return;
    ev.preventDefault();
    var f = ev.target;
    Garita.encolar({
      visitante: f.visitante.value, dpi: f.dpi.value, placa: f.placa.value,
      casa_id: f.casa_id.value, tipo: f.tipo.value, vehiculo: f.vehiculo.value,
      personas: f.personas.value, motivo: f.motivo.value, entrada: new Date().toISOString(),
    });
    f.reset();
  });

  window.addEventListener('beforeunload', function () { Garita.cerrarCamara(); });
})();
</script>
