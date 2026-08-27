<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,360px)">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Calendario del residencial</h3></div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($eventos === []): ?>
        <p class="texto-3 centrado" style="padding:26px 0;margin:0">Todavía no hay eventos programados.</p>
      <?php else: ?>
        <ul class="lista-limpia">
          <?php foreach ($eventos as $ev):
            $pasado = strtotime((string) $ev['inicio']) < time(); ?>
            <li class="item-lista" style="<?= $pasado ? 'opacity:.6' : '' ?>">
              <div style="text-align:center;min-width:52px">
                <div style="font-family:var(--f-titulo);font-size:1.6rem;color:var(--acento-3);line-height:1"><?= e(date('d', (int) strtotime((string) $ev['inicio']))) ?></div>
                <div class="mayus" style="font-size:.62rem"><?= e(mb_substr(mesNombre((int) date('n', (int) strtotime((string) $ev['inicio']))), 0, 3)) ?> <?= e(date('y', (int) strtotime((string) $ev['inicio']))) ?></div>
              </div>
              <div class="crecer">
                <b><?= e($ev['titulo']) ?></b>
                <div class="meta">
                  <?= e(hora((string) $ev['inicio'])) ?><?= $ev['fin'] ? ' a ' . e(hora((string) $ev['fin'])) : '' ?>
                  <?= !empty($ev['lugar']) ? ' · ' . e($ev['lugar']) : '' ?>
                </div>
                <?php if (!empty($ev['detalle'])): ?>
                  <div class="meta"><?= e(recortar((string) $ev['detalle'], 90)) ?></div>
                <?php endif; ?>
              </div>
              <div class="fila" style="gap:6px">
                <span class="chip <?= $ev['tipo'] === 'asamblea' ? 'oro' : ($ev['tipo'] === 'mantenimiento' ? 'aviso' : 'neutro') ?>"><?= e(ucfirst((string) $ev['tipo'])) ?></span>
                <?php if ((int) $ev['publico'] === 1): ?><span class="chip info">Público</span><?php endif; ?>
                <button class="btn btn-sm btn-fantasma" type="button" aria-label="Editar evento"
                        data-evento="<?= e(json_encode($ev, JSON_UNESCAPED_UNICODE)) ?>"><?= ico('editar', 15) ?></button>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </article>

  <form method="post" id="f-evento" style="align-self:start">
    <?= csrf() ?>
    <input type="hidden" name="id" id="ev-id" value="0">
    <div class="tarjeta">
      <div class="tarjeta-cab"><h3>Agregar al calendario</h3></div>
      <div class="tarjeta-cuerpo">
        <div class="campo"><label for="ev-titulo">Título *</label>
          <input type="text" id="ev-titulo" name="titulo" required maxlength="190" placeholder="Asamblea general ordinaria"></div>
        <div class="campo"><label for="ev-tipo">Tipo</label>
          <select id="ev-tipo" name="tipo">
            <option value="asamblea">Asamblea</option>
            <option value="mantenimiento">Mantenimiento</option>
            <option value="social">Actividad social</option>
            <option value="otro" selected>Otro</option>
          </select></div>
        <div class="campo"><label for="ev-inicio">Inicio *</label>
          <input type="datetime-local" id="ev-inicio" name="inicio" required value="<?= e(date('Y-m-d\T18:00')) ?>"></div>
        <div class="campo"><label for="ev-fin">Fin</label>
          <input type="datetime-local" id="ev-fin" name="fin"></div>
        <div class="campo"><label for="ev-lugar">Lugar</label>
          <input type="text" id="ev-lugar" name="lugar" maxlength="140" placeholder="Salón de eventos"></div>
        <div class="campo"><label for="ev-detalle">Detalle</label>
          <textarea id="ev-detalle" name="detalle" rows="3" maxlength="1000"></textarea></div>
        <label class="marca-check"><input type="checkbox" name="publico" value="1" id="ev-publico"><span>Mostrar también en el sitio público</span></label>
      </div>
      <div class="tarjeta-pie fila-fin">
        <button type="button" class="btn btn-claro btn-sm" data-limpiar-evento>Limpiar</button>
        <button class="btn btn-oro btn-sm" type="submit"><?= ico('guardar', 15) ?> Guardar</button>
      </div>
    </div>
  </form>
</div>
<script<?= nonce() ?>>
document.querySelectorAll('[data-evento]').forEach(function (b) {
  b.addEventListener('click', function () {
    var ev = JSON.parse(b.dataset.evento);
    document.getElementById('ev-id').value = ev.id;
    document.getElementById('ev-titulo').value = ev.titulo || '';
    document.getElementById('ev-tipo').value = ev.tipo || 'otro';
    document.getElementById('ev-inicio').value = (ev.inicio || '').replace(' ', 'T').slice(0, 16);
    document.getElementById('ev-fin').value = ev.fin ? ev.fin.replace(' ', 'T').slice(0, 16) : '';
    document.getElementById('ev-lugar').value = ev.lugar || '';
    document.getElementById('ev-detalle').value = ev.detalle || '';
    document.getElementById('ev-publico').checked = Number(ev.publico) === 1;
    document.getElementById('f-evento').scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
});
document.querySelectorAll('[data-limpiar-evento]').forEach(function (b) {
  b.addEventListener('click', function () {
    document.getElementById('f-evento').reset();
    document.getElementById('ev-id').value = 0;
  });
});
</script>
