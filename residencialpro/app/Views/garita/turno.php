<section class="garita-panel">
  <h2><?= ico('reloj', 20) ?> Turno actual</h2>
  <?php if ($turno === null): ?>
    <p style="color:rgba(233,238,233,.7)">No hay un turno abierto en este momento.</p>
    <form method="post">
      <?= csrf() ?>
      <input type="hidden" name="accion" value="iniciar">
      <button class="btn btn-oro btn-lg btn-bloque" type="submit"><?= ico('entrar', 20) ?> Iniciar mi turno</button>
    </form>
  <?php else: ?>
    <div class="garita-item mb-2">
      <span class="avatar"><?= e(iniciales((string) (usuarioActual()['nombre'] ?? ''))) ?></span>
      <div class="crecer">
        <b><?= e(usuarioActual()['nombre'] ?? '') ?></b>
        <small>Turno iniciado el <?= e(fechahora((string) $turno['inicio'])) ?></small>
      </div>
      <span class="chip ok">Abierto</span>
    </div>
    <p style="color:rgba(233,238,233,.7);font-size:.9rem">
      Anote a continuación las novedades que debe conocer el siguiente guardia. Quedarán registradas de forma permanente.
    </p>
    <form method="post">
      <?= csrf() ?>
      <input type="hidden" name="accion" value="cerrar">
      <div class="campo">
        <label for="novedades">Novedades para el relevo</label>
        <textarea id="novedades" name="novedades" rows="5" maxlength="2000"
                  placeholder="Ejemplo: sin novedad. Se reportó lámpara fundida en la calle de los Cipreses."></textarea>
      </div>
      <button class="btn btn-oro btn-lg btn-bloque" type="submit"><?= ico('salir', 20) ?> Cerrar turno y entregar</button>
    </form>
  <?php endif; ?>
</section>

<section class="garita-panel">
  <h2><?= ico('lista', 20) ?> Turnos anteriores</h2>
  <div class="garita-lista">
    <?php foreach ($anteriores as $t): ?>
      <div class="garita-item" style="align-items:flex-start">
        <span style="color:var(--acento-2);margin-top:2px"><?= ico('usuario', 20) ?></span>
        <div class="crecer">
          <b><?= e($t['guardia'] ?? '') ?></b>
          <small><?= e(fechahora((string) $t['inicio'])) ?> — <?= $t['fin'] ? e(fechahora((string) $t['fin'])) : 'en curso' ?></small>
          <?php if (!empty($t['novedades'])): ?>
            <p style="margin:6px 0 0;color:rgba(233,238,233,.82);font-size:.88rem"><?= nl2br(e((string) $t['novedades'])) ?></p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
