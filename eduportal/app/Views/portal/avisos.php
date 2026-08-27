<div class="pagina-cab">
  <div><h1>Avisos y calendario</h1><p class="pagina-cab__sub">Comunicados del colegio y actividades programadas</p></div>
</div>

<div class="split">
  <div class="col">
    <?php if ($avisos === []): ?>
      <div class="tarjeta vacio"><?= icono('aviso', 44) ?><p>No hay avisos publicados.</p></div>
    <?php else: ?>
      <?php foreach ($avisos as $a): ?>
        <article class="tarjeta tarjeta--hover">
          <div class="flex flex--sep mb-2">
            <span class="xs txt-3"><?= e(fecha_hora($a['publicar_en'] ?? $a['creado_en'])) ?> · <?= e($a['autor'] ?? 'Colegio') ?></span>
            <?php if ((int)$a['leido'] === 0): ?><span class="badge badge--oro">Nuevo</span><?php endif; ?>
          </div>
          <h3 class="mb-2"><a href="<?= e(url('avisos/' . (int)$a['id'])) ?>" style="color:inherit"><?= e($a['titulo']) ?></a></h3>
          <?php if (!empty($a['imagen'])): ?>
            <img src="<?= e(archivo_url($a['imagen'])) ?>" alt="" style="border-radius:var(--r);margin-bottom:12px" loading="lazy">
          <?php endif; ?>
          <p class="txt-2 mb-2"><?= e(recorta($a['contenido'] ?? '', 260)) ?></p>
          <a href="<?= e(url('avisos/' . (int)$a['id'])) ?>" class="btn btn--linea btn--sm">Leer completo <?= icono('flecha', 15) ?></a>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="col">
    <div class="tarjeta">
      <div class="tarjeta__cab"><h2>Calendario</h2></div>
      <?php if ($eventos === []): ?>
        <p class="sm txt-3">Sin actividades programadas.</p>
      <?php else: ?>
        <div class="linea-tiempo">
          <?php foreach ($eventos as $ev): ?>
            <div class="linea-tiempo__item">
              <div class="linea-tiempo__fecha">
                <div class="d"><?= e(date('d', strtotime((string)$ev['fecha_inicio']))) ?></div>
                <div class="m"><?= e(mb_substr(mes_nombre((int)date('n', strtotime((string)$ev['fecha_inicio']))), 0, 3)) ?></div>
              </div>
              <div style="min-width:0">
                <strong class="sm"><?= e($ev['titulo']) ?></strong>
                <div class="xs txt-3"><?= e(ucfirst((string)$ev['tipo'])) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
