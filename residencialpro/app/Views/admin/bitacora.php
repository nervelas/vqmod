<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,380px)">
  <article class="tarjeta">
    <div class="tarjeta-cab"><h3>Bitácora de novedades</h3></div>
    <div class="tarjeta-cuerpo compacto">
      <?php if ($registros === []): ?>
        <p class="texto-3 centrado" style="padding:26px 0;margin:0">La bitácora está vacía.</p>
      <?php else: ?>
        <div class="linea-tiempo">
          <?php foreach ($registros as $r): ?>
            <div class="lt-item <?= $r['tipo'] === 'incidente' ? '' : 'ok' ?>">
              <div class="fila-entre">
                <b><?= e(ucfirst((string) $r['tipo'])) ?></b>
                <small class="texto-3"><?= e(fechahora((string) $r['creado_en'])) ?></small>
              </div>
              <p style="margin:4px 0;font-size:.92rem;color:var(--texto-2)"><?= nl2br(e((string) $r['texto'])) ?></p>
              <small><?= e($r['guardia'] ?? '') ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </article>

  <div class="columna">
    <?php if ($emergencias !== []): ?>
      <article class="tarjeta" style="border-color:var(--grave)">
        <div class="tarjeta-cab"><h3 style="color:var(--grave)"><?= ico('sirena', 18) ?> Alertas de emergencia</h3></div>
        <div class="tarjeta-cuerpo compacto">
          <ul class="lista-limpia">
            <?php foreach ($emergencias as $em): ?>
              <li class="item-lista">
                <span style="color:var(--grave)"><?= ico('sirena', 20) ?></span>
                <div class="crecer">
                  <b><?= e(ucfirst((string) $em['tipo'])) ?><?= !empty($em['casa']) ? ' · casa ' . e($em['casa']) : '' ?></b>
                  <div class="meta"><?= e($em['usuario'] ?? '') ?> · <?= e(fechahora((string) $em['creado_en'])) ?></div>
                  <?php if (!empty($em['detalle'])): ?><div class="meta"><?= e($em['detalle']) ?></div><?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </article>
    <?php endif; ?>

    <article class="tarjeta">
      <div class="tarjeta-cab"><h3>Turnos de garita</h3></div>
      <div class="tarjeta-cuerpo compacto">
        <ul class="lista-limpia">
          <?php foreach ($turnos as $t): ?>
            <li class="item-lista">
              <span class="avatar sm"><?= e(iniciales((string) ($t['guardia'] ?? '?'))) ?></span>
              <div class="crecer">
                <b><?= e($t['guardia'] ?? '') ?></b>
                <div class="meta"><?= e(fechahora((string) $t['inicio'])) ?> — <?= $t['fin'] ? e(fechahora((string) $t['fin'])) : 'en curso' ?></div>
                <?php if (!empty($t['novedades'])): ?>
                  <div class="meta"><?= e(recortar((string) $t['novedades'], 80)) ?></div>
                <?php endif; ?>
              </div>
              <?php if ($t['fin'] === null): ?><span class="chip ok">Abierto</span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </article>
  </div>
</div>
