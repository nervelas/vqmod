<section class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,320px)">
  <div class="columna">
    <?php if ($avisos === []): ?>
      <div class="tarjeta"><div class="vacio"><?= ico('megafono', 44) ?>
        <h3>No hay avisos publicados</h3><p>Cuando la administración publique algo, lo verá aquí.</p></div></div>
    <?php endif; ?>
    <?php foreach ($avisos as $a): $leido = in_array((int) $a['id'], $leidos, true); ?>
      <article class="tarjeta <?= $leido ? '' : 'tarjeta-flota' ?>"
               <?= $leido ? '' : 'style="border-left:4px solid var(--acento)"' ?>>
        <?php if (!empty($a['imagen'])): ?>
          <img src="<?= e(subida($a['imagen'], 'avisos')) ?>" alt="" style="width:100%;max-height:220px;object-fit:cover">
        <?php endif; ?>
        <div class="tarjeta-cuerpo">
          <div class="fila-entre mb-1">
            <span class="chip <?= $a['prioridad'] === 'urgente' ? 'grave' : ($a['prioridad'] === 'importante' ? 'aviso' : 'neutro') ?>">
              <?= e(ucfirst((string) $a['prioridad'])) ?>
            </span>
            <span class="texto-3" style="font-size:.8rem"><?= e(hace((string) $a['publicar_en'])) ?></span>
          </div>
          <h3><a href="<?= e(url('/portal/avisos/' . (int) $a['id'])) ?>"><?= e($a['titulo']) ?></a></h3>
          <p class="texto-2" style="font-size:.93rem"><?= e(recortar((string) $a['cuerpo'], 240)) ?></p>
          <div class="fila-entre">
            <a class="btn btn-sm btn-claro" href="<?= e(url('/portal/avisos/' . (int) $a['id'])) ?>">Leer completo</a>
            <?php if (!$leido): ?><span class="chip oro">Nuevo</span><?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <article class="tarjeta" style="align-self:start">
    <div class="tarjeta-cab"><h3>Calendario</h3></div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($eventos === []): ?>
        <p class="texto-3 centrado" style="padding:20px 0;margin:0">Sin actividades programadas.</p>
      <?php else: ?>
        <ul class="lista-limpia">
          <?php foreach ($eventos as $ev): ?>
            <li class="item-lista">
              <div style="text-align:center;min-width:44px">
                <div style="font-family:var(--f-titulo);font-size:1.4rem;color:var(--acento-3);line-height:1"><?= e(date('d', (int) strtotime((string) $ev['inicio']))) ?></div>
                <div class="mayus" style="font-size:.62rem"><?= e(mb_substr(mesNombre((int) date('n', (int) strtotime((string) $ev['inicio']))), 0, 3)) ?></div>
              </div>
              <div class="crecer">
                <b><?= e($ev['titulo']) ?></b>
                <div class="meta"><?= e(hora((string) $ev['inicio'])) ?><?= !empty($ev['lugar']) ? ' · ' . e($ev['lugar']) : '' ?></div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </article>
</section>
