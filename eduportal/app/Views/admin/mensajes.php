<div class="pagina-cab">
  <div><h1>Mensajes</h1><p class="pagina-cab__sub">Comunicación directa entre docentes y encargados</p></div>
</div>

<div class="chat">
  <aside class="chat__lista">
    <?php foreach ($hilos as $h): ?>
      <a class="chat__hilo <?= (int)($actual['id'] ?? 0) === (int)$h['user_id'] ? 'activo' : '' ?>"
         href="<?= e(url('mensajes/' . (int)$h['user_id'])) ?>">
        <span class="avatar iniciales"><?= e(mb_strtoupper(mb_substr((string)$h['nombre'], 0, 2))) ?></span>
        <span style="min-width:0;flex:1">
          <strong class="sm truncar" style="display:block"><?= e($h['nombre']) ?></strong>
          <span class="xs txt-3"><?= e(rol_nombre((string)$h['rol'])) ?></span>
        </span>
        <?php if ((int)$h['no_leidos'] > 0): ?><span class="badge badge--bad"><?= (int)$h['no_leidos'] ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>

    <div style="padding:12px;border-top:1px solid var(--borde)">
      <label class="etq" for="nuevo-contacto">Iniciar conversación</label>
      <select id="nuevo-contacto" data-ir-a>
        <option value="">Seleccione un contacto…</option>
        <?php foreach ($contactos as $c): ?>
          <option value="<?= e(url('mensajes/' . (int)$c['id'])) ?>"><?= e($c['nombre']) ?> — <?= e(rol_nombre((string)$c['rol'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </aside>

  <section class="chat__panel">
    <?php if ($actual === null): ?>
      <div class="vacio" style="margin:auto"><?= icono('mensaje', 44) ?>
        <p>Seleccione una conversación o inicie una nueva.</p></div>
    <?php else: ?>
      <div class="chat__cab">
        <span class="avatar iniciales"><?= e(mb_strtoupper(mb_substr((string)$actual['nombre'], 0, 2))) ?></span>
        <div><strong><?= e($actual['nombre']) ?></strong>
          <div class="xs txt-3"><?= e(rol_nombre((string)$actual['rol'])) ?></div></div>
      </div>
      <div class="chat__mensajes">
        <?php foreach ($mensajes as $m): ?>
          <div class="burbuja <?= (int)$m['de_id'] === (int)App\Core\Auth::id() ? 'mia' : '' ?>">
            <?= nl2br(e($m['cuerpo'])) ?>
            <span class="hora"><?= e(fecha_hora((string)$m['creado_en'])) ?></span>
          </div>
        <?php endforeach; ?>
        <?php if ($mensajes === []): ?>
          <p class="txt-3 sm cen">Aún no hay mensajes en esta conversación.</p>
        <?php endif; ?>
      </div>
      <form class="chat__form" method="post" action="<?= e(url('mensajes/' . (int)$actual['id'])) ?>">
        <?= csrf_field() ?>
        <textarea name="cuerpo" required maxlength="4000" placeholder="Escriba su mensaje…" aria-label="Mensaje"></textarea>
        <button type="submit" class="btn"><?= icono('flecha', 17) ?><span class="solo-lectores">Enviar</span></button>
      </form>
    <?php endif; ?>
  </section>
</div>
