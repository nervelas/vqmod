<div class="pagina-cab">
  <div><h1>Generar cargos del mes</h1>
    <p class="pagina-cab__sub">Crea los cargos de todos los alumnos activos del ciclo <?= e($ciclo['nombre'] ?? '') ?></p></div>
  <div class="acciones"><a href="<?= e(url('cobranza')) ?>" class="btn btn--linea"><?= icono('atras', 17) ?> Volver</a></div>
</div>

<div class="split">
  <div class="tarjeta">
    <div class="tarjeta__cab"><h2>Parámetros</h2></div>
    <form method="post" action="<?= e(url('cobranza/generar')) ?>"
          data-confirmar="Se generarán los cargos del periodo seleccionado. ¿Continuar?">
      <?= csrf_field() ?>
      <div class="fila fila--3">
        <div class="campo">
          <label for="g-desde">Desde el mes <span class="oro">*</span></label>
          <select id="g-desde" name="mes_desde" required>
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?= $m ?>" <?= $m === (int)date('n') ? 'selected' : '' ?>><?= e(mes_nombre($m)) ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="campo">
          <label for="g-hasta">Hasta el mes <span class="oro">*</span></label>
          <select id="g-hasta" name="mes_hasta" required>
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?= $m ?>" <?= $m === (int)date('n') ? 'selected' : '' ?>><?= e(mes_nombre($m)) ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="campo">
          <label for="g-anio">Año <span class="oro">*</span></label>
          <input type="number" id="g-anio" name="anio" required min="2000" max="2100" value="<?= e(date('Y')) ?>">
        </div>
      </div>
      <p class="ayuda mb-4">La colegiatura es <strong>mensual</strong>: elija un solo mes para el cobro
         corriente, o un rango (por ejemplo <em>Enero a Octubre</em>) para dejar generado todo el ciclo escolar.</p>
      <div class="campo">
        <label for="g-concepto">Concepto</label>
        <select id="g-concepto" name="concepto_id">
          <option value="0">Todos los conceptos mensuales</option>
          <?php foreach ($conceptos as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['nombre']) ?> — <?= e(moneda((float)$c['monto'])) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="ayuda">Elija un concepto puntual (por ejemplo, inscripción) o deje "todos" para la colegiatura mensual.</p>
      </div>
      <button type="submit" class="btn"><?= icono('mas', 17) ?> Generar cargos</button>
    </form>
  </div>
  <div class="tarjeta">
    <div class="tarjeta__cab"><h2>Cómo funciona</h2></div>
    <ul class="sm txt-2">
      <li>Se crea <strong>un cargo por alumno, concepto y mes</strong> del rango elegido.</li>
      <li>Los descuentos por <strong>beca</strong> y por <strong>hermanos</strong> se calculan en el servidor.</li>
      <li>La fecha de vencimiento usa el día configurado en cada concepto, dentro de cada mes.</li>
      <li>Si el cargo ya existe para ese mes, se omite: puede ejecutarlo varias veces sin duplicar.</li>
      <li>La mora se calcula automáticamente por el cron una vez vencido el plazo de gracia.</li>
    </ul>
  </div>
</div>
