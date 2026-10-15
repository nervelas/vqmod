<?php $d = days_left($t['deadline']); $u2 = urgency_class($t['deadline']); $st = $m['status'] ?? null; ?>
<p><a href="<?= e(url('app')) ?>" class="back"><?= icon('arrow') ?>Mis oportunidades</a></p>
<article class="detail">
  <header class="detail-head card <?= $u2 ?>">
    <div class="tc-tags" style="display:flex;flex-wrap:wrap;gap:6px">
      <?php if ($t['is_demo']): ?><span class="badge b-demo">DEMO</span><?php endif; ?>
      <span class="badge b-mod"><?= e(modalidades()[$t['modality']] ?? $t['modality']) ?></span>
      <span class="badge"><?= e(categorias()[$t['category']] ?? 'Otros') ?></span>
      <?php if ($st && $st !== 'nuevo'): ?><span class="badge b-new"><?= e(ucfirst($st)) ?></span><?php endif; ?>
      <?php if ($t['status'] !== 'abierto'): ?><span class="badge b-urgent"><?= e(ucfirst($t['status'])) ?></span><?php endif; ?>
    </div>
    <h1><?= e($t['title']) ?></h1>
    <p class="tcard-entity"><?= icon('building') ?><?= e($t['entity']) ?> <span class="muted">·</span> <?= icon('pin') ?><?= e($t['department']) ?></p>
    <?php if ($m && $m['reasons'] && $m['score']): ?><p class="why" style="margin:0"><?= icon('radar') ?><span><?= e(ucfirst($m['reasons'])) ?> <span class="score inline"><span class="mono"><?= (int) $m['score'] ?></span>% de coincidencia</span></span></p><?php endif; ?>
    <div class="facts">
      <div><small>Monto estimado</small><strong class="mono"><?= e(money($t['amount'])) ?></strong></div>
      <div><small>Publicado</small><strong class="mono"><?= e(fdate($t['publish_date'])) ?></strong></div>
      <div><small>Fecha límite</small><strong class="mono"><?= e(fdate($t['deadline'], true)) ?></strong></div>
      <div><small>Plazo</small><div class="countdown"><?php if ($d !== null && strtotime($t['deadline']) >= time()): ?><b class="mono"><?= (int) $d ?></b><span class="muted">días</span><?php else: ?><strong><?= e(days_label($t['deadline'])) ?></strong><?php endif; ?></div></div>
    </div>
    <form method="post" action="<?= e(url('app/accion/' . $t['id'])) ?>" class="detail-actions" style="padding:0"><?= csrf_field() ?><input type="hidden" name="volver" value="detalle">
      <button name="accion" value="participar" class="btn btn-gold <?= $st === 'participando' ? 'is-on' : '' ?>"><?= icon('award') ?>Voy a participar</button>
      <button name="accion" value="guardar" class="btn btn-ghost <?= $st === 'guardado' ? 'is-on' : '' ?>"><?= icon('bookmark') ?>Guardar</button>
      <?php if ($st === 'participando' || $st === 'ganado' || $st === 'perdido'): ?>
        <button name="accion" value="ganado" class="btn btn-ghost"><?= icon('check-circle') ?>Ganado</button><button name="accion" value="perdido" class="btn btn-ghost">Perdido</button>
      <?php endif; ?>
      <button name="accion" value="descartar" class="btn btn-quiet"><?= icon('x') ?>Descartar</button>
    </form>
  </header>

  <div class="detail-grid">
    <div class="detail-main">
      <section class="card"><h2><?= icon("search") ?> ¿Qué <em>compran</em>?</h2><p><?= nl2br(e(preg_replace('/\s*Concurso de ejemplo \(DEMO\).*$/s', '', (string) $t['description']) ?: 'La entidad no publicó una descripción adicional.')) ?></p>
        <?php if ($t['url']): ?><p><a class="btn btn-ghost btn-sm" href="<?= e($t['url']) ?>" target="_blank" rel="noopener noreferrer">Ver en GUATECOMPRAS (enlace oficial) <?= icon("external") ?></a></p><?php endif; ?>
        <p class="fine">Verifica siempre las bases oficiales antes de ofertar.<?= $t['is_demo'] ? ' Este es un concurso de ejemplo (DEMO).' : '' ?></p></section>

      <section class="card"><h2><?= icon("file") ?> Documentos y <em>requisitos</em></h2>
        <?php if ($items): ?>
        <p class="muted small">Marca lo que ya tienes listo. <span data-check-progress class="mono"></span></p>
        <ul class="checklist" data-checklist data-url="<?= e(url('app/check/' . $t['id'])) ?>" data-token="<?= e(csrf_token()) ?>">
          <?php foreach ($items as $i => $it): ?><li><label class="check"><input type="checkbox" data-idx="<?= $i ?>" <?= in_array($i, $done) ? 'checked' : '' ?>> <span><?= e($it) ?></span></label></li><?php endforeach; ?>
        </ul>
        <?php else: ?><p class="muted">Este concurso no trae lista de requisitos. Revisa las bases en GUATECOMPRAS.</p><?php endif; ?>
      </section>
    </div>

    <aside class="detail-side">
      <section class="card price-card"><h2><?= icon("chart") ?> Precios de <em>referencia</em></h2>
        <?php if ($price['n'] > 0): ?>
          <p class="muted small">Basado en <?= (int) $price['n'] ?> concursos parecidos ya adjudicados.</p>
          <div class="range" data-range="<?= (float) $price['min'] ?>,<?= (float) $price['median'] ?>,<?= (float) $price['max'] ?>">
            <div class="range-bar"><span class="range-med"></span></div>
            <div class="range-vals"><div><small>Mínimo</small><strong class="mono"><?= e(money($price['min'])) ?></strong></div><div><small>Mediana</small><strong class="mono gold"><?= e(money($price['median'])) ?></strong></div><div><small>Máximo</small><strong class="mono"><?= e(money($price['max'])) ?></strong></div></div>
          </div>
          <details><summary>Ver los concursos parecidos</summary><ul class="similar"><?php foreach ($price['items'] as $s): ?><li><span><?= e($s['title']) ?></span><strong class="mono"><?= e(money($s['awarded_amount'])) ?></strong></li><?php endforeach; ?></ul></details>
        <?php else: ?><p class="muted">Todavía no hay adjudicados parecidos para comparar.</p><?php endif; ?>
      </section>
      <section class="card"><h2><?= icon("file") ?> Borrador de <em>oferta</em></h2>
        <p class="muted small">Carta de oferta, lista de documentos y cuadro de precios con tus datos. Siempre es un <strong>borrador a revisar</strong>.</p>
        <form method="post" action="<?= e(url('app/generar/' . $t['id'])) ?>"><?= csrf_field() ?><button class="btn btn-gold btn-block"><?= icon("spark") ?>Generar borrador de oferta</button></form>
        <?php if ($drafts): ?><ul class="similar"><?php foreach ($drafts as $dr): ?><li><a href="<?= e(url('app/borrador/' . $dr['id'])) ?>">Borrador del <?= e(fdate($dr['created_at'], true)) ?></a><small class="muted"><?= $dr['source'] === 'ia' ? 'IA' : 'plantilla' ?></small></li><?php endforeach; ?></ul><?php endif; ?>
      </section>
    </aside>
  </div>
</article>
