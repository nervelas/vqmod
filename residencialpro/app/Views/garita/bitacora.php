<section class="garita-panel">
  <h2><?= ico('libro', 20) ?> Anotar una novedad</h2>
  <?php if ($turno === null): ?>
    <div class="aviso-caja alerta mb-2"><?= ico('alerta', 19) ?>
      <div>No hay un turno abierto. <a href="<?= e(url('/garita/turno')) ?>">Inicie su turno</a> para que la novedad quede asociada.</div>
    </div>
  <?php endif; ?>
  <form method="post">
    <?= csrf() ?>
    <div class="campo">
      <label for="tipo">Tipo de anotación</label>
      <select id="tipo" name="tipo">
        <option value="novedad">Novedad del turno</option>
        <option value="incidente">Incidente (avisa a administración)</option>
        <option value="ronda">Ronda de vigilancia</option>
        <option value="entrega">Entrega o paquete recibido</option>
      </select>
    </div>
    <div class="campo">
      <label for="texto">¿Qué ocurrió? *</label>
      <textarea id="texto" name="texto" rows="5" required minlength="4" maxlength="2000"
                placeholder="Ejemplo: se realizó ronda perimetral a las 02:00, sin novedad."></textarea>
    </div>
    <button class="btn btn-oro btn-lg btn-bloque" type="submit"><?= ico('guardar', 19) ?> Guardar en la bitácora</button>
  </form>
</section>

<section class="garita-panel">
  <h2><?= ico('lista', 20) ?> Últimas anotaciones</h2>
  <?php if ($registros === []): ?>
    <p style="color:rgba(233,238,233,.6)">La bitácora está vacía.</p>
  <?php else: ?>
    <div class="garita-lista" style="max-height:560px">
      <?php foreach ($registros as $r): ?>
        <div class="garita-item" style="align-items:flex-start">
          <span style="color:var(--acento-2);margin-top:2px">
            <?= ico($r['tipo'] === 'incidente' ? 'alerta' : ($r['tipo'] === 'ronda' ? 'escudo' : 'libro'), 20) ?>
          </span>
          <div class="crecer">
            <b style="font-size:.9rem"><?= e(ucfirst((string) $r['tipo'])) ?></b>
            <p style="margin:3px 0;color:#E9EEE9;font-size:.92rem"><?= nl2br(e((string) $r['texto'])) ?></p>
            <small><?= e($r['guardia'] ?? '') ?> · <?= e(fechahora((string) $r['creado_en'])) ?></small>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
