<div class="contenedor-sm" style="margin-left:0">
  <a class="btn btn-claro btn-sm mb-3" href="<?= e(url('/portal/avisos')) ?>"><?= ico('flechaIzq', 16) ?> Volver a los avisos</a>
  <article class="tarjeta">
    <?php if (!empty($aviso['imagen'])): ?>
      <img src="<?= e(subida($aviso['imagen'], 'avisos')) ?>" alt="" style="width:100%;max-height:320px;object-fit:cover">
    <?php endif; ?>
    <div class="tarjeta-cuerpo">
      <div class="fila-entre mb-2">
        <span class="chip <?= $aviso['prioridad'] === 'urgente' ? 'grave' : ($aviso['prioridad'] === 'importante' ? 'aviso' : 'neutro') ?>">
          <?= e(ucfirst((string) $aviso['prioridad'])) ?>
        </span>
        <span class="texto-3" style="font-size:.82rem">
          Publicado <?= e(fechahora((string) $aviso['publicar_en'])) ?>
          <?= !empty($aviso['autor']) ? ' · ' . e($aviso['autor']) : '' ?>
        </span>
      </div>
      <h1 style="font-size:1.75rem"><?= e($aviso['titulo']) ?></h1>
      <div style="font-size:1rem;line-height:1.75;color:var(--texto-2)"><?= nl2br(e((string) $aviso['cuerpo'])) ?></div>
      <?php if (!empty($aviso['archivo'])): ?>
        <a class="btn btn-claro mt-3" href="<?= e(url('/archivo/avisos/' . $aviso['archivo'])) ?>" target="_blank" rel="noopener">
          <?= ico('archivo', 17) ?> Descargar el adjunto
        </a>
      <?php endif; ?>
      <?php if ((int) $aviso['confirmar'] === 1): ?>
        <div class="aviso-caja ok mt-3"><?= ico('checkCirculo', 20) ?>
          <div>Su lectura quedó registrada. Gracias por mantenerse informado.</div>
        </div>
      <?php endif; ?>
    </div>
  </article>
</div>
