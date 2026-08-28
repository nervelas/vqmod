<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,320px)">
  <div class="columna">
    <form method="post">
      <?= csrf() ?>
      <div class="tarjeta">
        <div class="tarjeta-cab"><h3>Escribir a la administración</h3></div>
        <div class="tarjeta-cuerpo">
          <div class="campo">
            <label for="asunto">Asunto</label>
            <input type="text" id="asunto" name="asunto" maxlength="190" placeholder="Consulta sobre mi estado de cuenta">
          </div>
          <div class="campo">
            <label for="cuerpo">Mensaje *</label>
            <textarea id="cuerpo" name="cuerpo" rows="5" required minlength="5" maxlength="3000"></textarea>
          </div>
        </div>
        <div class="tarjeta-pie fila-fin">
          <button class="btn btn-oro" type="submit"><?= ico('enviar', 17) ?> Enviar mensaje</button>
        </div>
      </div>
    </form>

    <article class="tarjeta">
      <div class="tarjeta-cab"><h3>Conversación</h3></div>
      <div class="tarjeta-cuerpo compacto">
        <?php if ($mensajes === []): ?>
          <p class="texto-3 centrado" style="padding:22px 0;margin:0">Todavía no hay mensajes.</p>
        <?php else: ?>
          <div class="linea-tiempo">
            <?php foreach ($mensajes as $m): ?>
              <div class="lt-item <?= (int) $m['de_usuario'] === (int) (usuarioActual()['id'] ?? 0) ? '' : 'ok' ?>">
                <b><?= e($m['asunto'] ?: 'Mensaje') ?></b>
                <p style="margin:4px 0;font-size:.92rem;color:var(--texto-2)"><?= nl2br(e((string) $m['cuerpo'])) ?></p>
                <small><?= e($m['remitente'] ?? '') ?> · <?= e(fechahora((string) $m['creado_en'])) ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </article>
  </div>

  <article class="tarjeta" style="align-self:start">
    <div class="tarjeta-cab"><h3>Teléfonos útiles</h3></div>
    <div class="tarjeta-cuerpo compacto">
      <ul class="lista-limpia">
        <?php foreach ($contactos as $c): ?>
          <li class="item-lista">
            <span style="color:var(--arcilla)"><?= ico('telefono', 19) ?></span>
            <div class="crecer">
              <b><?= e($c['nombre']) ?></b>
              <div class="meta"><?= e($c['tipo'] ?? '') ?></div>
            </div>
            <a class="btn btn-sm btn-claro" href="tel:<?= e(preg_replace('/\D+/', '', (string) $c['telefono'])) ?>"><?= e($c['telefono']) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </article>
</div>
