<div class="fila-entre mb-3">
  <a class="btn btn-claro btn-sm" href="<?= e(url('/admin/incidencias')) ?>"><?= ico('flechaIzq', 16) ?> Volver a incidencias</a>
  <span class="chip <?= e(estadoBadge((string) $incidencia['estado'])) ?>"><?= e(ucfirst((string) $incidencia['estado'])) ?></span>
</div>

<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,380px)">
  <article class="tarjeta">
    <div class="tarjeta-cab">
      <div>
        <h3 style="margin:0"><?= e($incidencia['titulo']) ?></h3>
        <div class="texto-3" style="font-size:.8rem">
          <?= e(ucfirst((string) $incidencia['categoria'])) ?> ·
          reportado por <?= e($incidencia['reporta'] ?? 'un residente') ?> ·
          <?= e(fechahora((string) $incidencia['creado_en'])) ?>
        </div>
      </div>
      <span class="chip <?= $incidencia['prioridad'] === 'alta' ? 'grave' : ($incidencia['prioridad'] === 'media' ? 'aviso' : 'neutro') ?>">
        <?= e(ucfirst((string) $incidencia['prioridad'])) ?>
      </span>
    </div>
    <div class="tarjeta-cuerpo">
      <p style="font-size:1rem;line-height:1.7"><?= nl2br(e((string) $incidencia['descripcion'])) ?></p>
      <?php if (!empty($incidencia['ubicacion'])): ?>
        <div class="chip neutro"><?= ico('pin', 14) ?> <?= e($incidencia['ubicacion']) ?></div>
      <?php endif; ?>
      <?php if (!empty($incidencia['casa'])): ?>
        <div class="chip oro"><?= ico('casa', 14) ?> <?= e($incidencia['casa']) ?></div>
      <?php endif; ?>
      <?php if (!empty($incidencia['foto'])): ?>
        <div class="mt-2">
          <a href="<?= e(url('/archivo/incidencias/' . $incidencia['foto'])) ?>" target="_blank" rel="noopener">
            <img src="<?= e(url('/archivo/incidencias/' . $incidencia['foto'])) ?>" alt="Fotografía del reporte"
                 style="border-radius:var(--r-md);max-height:330px">
          </a>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($seguimiento !== []): ?>
      <div class="tarjeta-cuerpo" style="border-top:1px solid var(--borde)">
        <h4>Seguimiento</h4>
        <div class="linea-tiempo">
          <?php foreach ($seguimiento as $s): ?>
            <div class="lt-item <?= in_array($s['estado'], ['resuelta', 'cerrada'], true) ? 'ok' : '' ?>">
              <b><?= e(ucfirst((string) ($s['estado'] ?? 'Actualización'))) ?></b>
              <p style="margin:3px 0;font-size:.92rem;color:var(--texto-2)"><?= nl2br(e((string) $s['texto'])) ?></p>
              <small><?= e($s['autor'] ?? '') ?> · <?= e(fechahora((string) $s['creado_en'])) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </article>

  <form method="post" style="align-self:start">
    <?= csrf() ?>
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3>Actualizar el estado</h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campo">
          <label for="estado">Nuevo estado</label>
          <select id="estado" name="estado">
            <?php foreach (['recibida' => 'Recibida', 'proceso' => 'En proceso', 'resuelta' => 'Resuelta', 'cerrada' => 'Cerrada'] as $k => $et): ?>
              <option value="<?= e($k) ?>" <?= $incidencia['estado'] === $k ? 'selected' : '' ?>><?= e($et) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="comentario">Comentario para el residente</label>
          <textarea id="comentario" name="comentario" rows="4" maxlength="1500"
                    placeholder="Se reportó al proveedor. Programado para esta semana."></textarea>
          <span class="ayuda">El residente recibirá una notificación con este mensaje.</span>
        </div>
      </div>
      <div class="tarjeta-pie fila-fin">
        <button class="btn btn-oro btn-bloque" type="submit"><?= ico('guardar', 17) ?> Guardar y notificar</button>
      </div>
    </div>
  </form>
</div>
