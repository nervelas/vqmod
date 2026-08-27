<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,380px)">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Conversaciones</h3></div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($mensajes === []): ?>
        <p class="texto-3 centrado" style="padding:26px 0;margin:0">Todavía no hay mensajes.</p>
      <?php else: ?>
        <ul class="lista-limpia">
          <?php foreach ($mensajes as $m): ?>
            <li class="item-lista">
              <span class="avatar sm"><?= e(iniciales((string) ($m['remitente'] ?? '?'))) ?></span>
              <div class="crecer">
                <div class="fila-entre">
                  <b><?= e($m['asunto'] ?: 'Mensaje') ?></b>
                  <small class="texto-3"><?= e(hace((string) $m['creado_en'])) ?></small>
                </div>
                <p style="margin:4px 0;font-size:.92rem;color:var(--texto-2)"><?= nl2br(e((string) $m['cuerpo'])) ?></p>
                <div class="meta">
                  <?= e($m['remitente'] ?? '') ?>
                  <?php if (!empty($m['casa'])): ?> · casa <?= e($m['casa']) ?><?php endif; ?>
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </article>

  <form method="post" style="align-self:start">
    <?= csrf() ?>
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3>Escribir a un residente</h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campo">
          <label for="para_usuario">Destinatario</label>
          <select id="para_usuario" name="para_usuario">
            <option value="">Seleccione…</option>
            <?php foreach ($residentes as $r): ?>
              <option value="<?= (int) $r['id'] ?>"><?= e($r['casa']) ?> · <?= e(recortar((string) $r['nombre'], 28)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="asunto">Asunto</label>
          <input type="text" id="asunto" name="asunto" maxlength="190" value="Mensaje de la administración">
        </div>
        <div class="campo">
          <label for="cuerpo">Mensaje *</label>
          <textarea id="cuerpo" name="cuerpo" rows="6" required minlength="3" maxlength="3000"></textarea>
        </div>
      </div>
      <div class="tarjeta-pie fila-fin">
        <button class="btn btn-oro btn-bloque" type="submit"><?= ico('enviar', 17) ?> Enviar mensaje</button>
      </div>
    </div>
  </form>
</div>
