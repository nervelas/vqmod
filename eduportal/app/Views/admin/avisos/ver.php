<div class="pagina-cab">
  <div><h1><?= e($aviso['titulo']) ?></h1>
    <p class="pagina-cab__sub">Por <?= e($aviso['autor'] ?? 'Sistema') ?> ·
      <?= e(fecha_hora($aviso['publicar_en'] ?? $aviso['creado_en'])) ?> ·
      <?= (int)$lecturas ?> lecturas</p></div>
  <div class="acciones"><a href="<?= e(url('avisos')) ?>" class="btn btn--linea"><?= icono('atras', 17) ?> Volver</a></div>
</div>

<article class="tarjeta">
  <?php if (!empty($aviso['imagen'])): ?>
    <img src="<?= e(archivo_url($aviso['imagen'])) ?>" alt="" style="border-radius:var(--r);margin-bottom:var(--s-4)">
  <?php endif; ?>
  <div><?= strip_tags((string)$aviso['contenido'], '<p><br><strong><b><em><i><u><ul><ol><li><h3><h4><blockquote><a>') ?></div>
  <?php if (!empty($aviso['adjunto'])): ?>
    <p class="mt-4"><a class="btn btn--linea" target="_blank" rel="noopener"
       href="<?= e(archivo_url($aviso['adjunto'])) ?>"><?= icono('descargar', 17) ?> Descargar adjunto</a></p>
  <?php endif; ?>
</article>
