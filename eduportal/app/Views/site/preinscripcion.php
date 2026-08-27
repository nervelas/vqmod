<section class="seccion">
  <div class="seccion__int" style="max-width:760px">
    <div class="seccion__cab">
      <span class="etq">Admisiones</span>
      <h2>Pre-inscripción en línea</h2>
      <p>Complete el formulario y nuestro equipo se comunicará con usted para continuar el proceso.</p>
    </div>
    <div class="tarjeta">
      <form method="post" action="<?= e(url('inscripcion')) ?>">
        <?= csrf_field() ?>
        <fieldset>
          <legend>Datos del alumno</legend>
          <div class="campo">
            <label for="p-alumno">Nombre completo del alumno</label>
            <input type="text" id="p-alumno" name="alumno_nombre" required maxlength="160">
          </div>
          <div class="fila">
            <div class="campo">
              <label for="p-nac">Fecha de nacimiento</label>
              <input type="date" id="p-nac" name="fecha_nacimiento" max="<?= e(date('Y-m-d')) ?>">
            </div>
            <div class="campo">
              <label for="p-grado">Grado al que aplica</label>
              <select id="p-grado" name="grado_id">
                <option value="">Seleccione…</option>
                <?php foreach ($grados as $g): ?>
                  <option value="<?= (int)$g['id'] ?>"><?= e(($g['nivel'] ?? '') . ' · ' . $g['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </fieldset>
        <fieldset>
          <legend>Datos del encargado</legend>
          <div class="campo">
            <label for="p-enc">Nombre del padre, madre o encargado</label>
            <input type="text" id="p-enc" name="encargado" required maxlength="160">
          </div>
          <div class="fila">
            <div class="campo">
              <label for="p-tel">Teléfono de contacto</label>
              <input type="tel" id="p-tel" name="telefono" required maxlength="40" inputmode="tel">
            </div>
            <div class="campo">
              <label for="p-mail">Correo electrónico</label>
              <input type="email" id="p-mail" name="email" maxlength="160">
            </div>
          </div>
        </fieldset>
        <div class="campo">
          <label for="p-msg">Comentarios (opcional)</label>
          <textarea id="p-msg" name="mensaje" maxlength="2000"></textarea>
        </div>
        <div class="campo">
          <label for="p-captcha">Verificación: ¿cuánto es <?= e($captcha) ?>?</label>
          <input type="text" id="p-captcha" name="captcha" required inputmode="numeric" maxlength="4" style="max-width:130px">
        </div>
        <button type="submit" class="btn">Enviar solicitud</button>
      </form>
    </div>
  </div>
</section>
