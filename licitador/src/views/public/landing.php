<?php $blips = [[62, 28, 0], [30, 44, 1.2], [70, 62, 2.1], [44, 70, 0.6], [24, 24, 2.8], [78, 40, 1.7], [52, 50, 3.3]]; ?>
<section class="hero">
  <div class="container hero-grid">
    <div class="hero-copy">
      <p class="eyebrow reveal">Radar de compras del Estado de Guatemala</p>
      <h1 class="reveal"><?= e(S('hero_title')) ?></h1>
      <p class="lead reveal"><?= e(S('hero_sub')) ?></p>
      <div class="hero-cta reveal">
        <a class="btn btn-gold btn-lg" href="<?= e(url('registro')) ?>"><?= e(S('cta_text', 'Probar 7 días gratis')) ?></a>
        <a class="btn btn-ghost btn-lg" href="#demo">Ver cómo se ve</a>
      </div>
      <p class="fine reveal">Sin tarjeta · Cancelas cuando quieras · Listo en 2 minutos</p>
    </div>
    <div class="radar-wrap reveal" aria-hidden="true">
      <div class="radar">
        <div class="radar-ring r1"></div><div class="radar-ring r2"></div><div class="radar-ring r3"></div>
        <div class="radar-cross"></div>
        <div class="radar-sweep"></div>
        <?php foreach ($blips as [$x, $y, $d]): ?><span class="blip" style="left:<?= $x ?>%;top:<?= $y ?>%;animation-delay:<?= $d ?>s"></span><?php endforeach; ?>
        <span class="blip blip-hot" style="left:64%;top:56%"></span>
      </div>
      <div class="radar-chip c1"><span class="badge b-new">Nuevo</span> Tuberías PVC · Escuintla</div>
      <div class="radar-chip c2"><span class="badge b-urgent">Vence en 2 días</span> Útiles escolares</div>
    </div>
  </div>
</section>

<section class="stats-strip">
  <div class="container stats-grid">
    <div class="stat reveal"><strong class="mono" data-count="<?= (int) $stats['open'] ?>">0</strong><span>concursos abiertos hoy</span></div>
    <div class="stat reveal"><strong class="mono" data-count="<?= (int) round($stats['sum'] / 1000000) ?>" data-prefix="Q " data-suffix=" M">0</strong><span>en montos estimados</span></div>
    <div class="stat reveal"><strong class="mono" data-count="<?= (int) $stats['awarded'] ?>">0</strong><span>adjudicados para precios de referencia</span></div>
    <div class="stat reveal"><strong class="mono" data-count="3">0</strong><span>pasos para empezar</span></div>
  </div>
</section>

<section class="section" id="problema">
  <div class="container narrow">
    <h2 class="reveal"><?= e(S('problem_t')) ?></h2>
    <p class="lead reveal"><?= e(S('problem_d')) ?></p>
  </div>
</section>

<section class="section alt" id="como">
  <div class="container">
    <p class="eyebrow center reveal">Cómo funciona</p>
    <h2 class="center reveal">Tres pasos, cero complicaciones</h2>
    <div class="steps">
      <?php foreach ([1, 2, 3] as $i): ?>
      <div class="step card reveal"><span class="step-n mono">0<?= $i ?></span><h3><?= e(S("how{$i}_t")) ?></h3><p><?= e(S("how{$i}_d")) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="demo">
  <div class="container">
    <p class="eyebrow center reveal">Demostración en vivo</p>
    <h2 class="center reveal">Así te llegan las oportunidades</h2>
    <p class="lead center reveal">Ejemplos reales de la plataforma (concursos DEMO). Cada tarjeta te explica <em>por qué</em> te sirve.</p>
    <div class="cards-grid">
      <?php foreach ($demo as $t): $t['reasons'] = 'coincide por: ' . (categorias()[$t['category']] ?? 'tu rubro') . '; departamento: ' . $t['department']; $t['mstatus'] = 'nuevo'; $t['score'] = 70 + ($t['id'] % 25); echo tender_card($t, ['noactions' => true]); endforeach; ?>
    </div>
    <p class="center reveal"><a class="btn btn-gold" href="<?= e(url('registro')) ?>">Quiero las mías</a></p>
  </div>
</section>

<section class="section alt" id="sectores">
  <div class="container">
    <p class="eyebrow center reveal">Sectores</p>
    <h2 class="center reveal">Para quienes le venden al Estado</h2>
    <div class="sector-grid">
      <?php foreach ($sectors as $s): ?>
      <article class="sector reveal">
        <img src="<?= e(asset($s['image'] ?: 'img/sector-servicios.svg')) ?>" alt="<?= e($s['name']) ?>" width="480" height="300" loading="lazy">
        <div class="sector-body"><h3><?= e($s['name']) ?></h3><p><?= e($s['tagline']) ?></p></div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="planes">
  <div class="container">
    <p class="eyebrow center reveal">Planes</p>
    <h2 class="center reveal">Suscripción mensual, sin letra chiquita</h2>
    <div class="plans">
      <?php foreach ($plans as $p): ?>
      <article class="plan card reveal <?= $p['highlight'] ? 'plan-hl' : '' ?>">
        <?php if ($p['highlight']): ?><span class="ribbon">Más elegido</span><?php endif; ?>
        <h3><?= e($p['name']) ?></h3>
        <p class="price"><span class="mono"><?= e(money($p['price'])) ?></span><small>/ <?= (int) $p['period_days'] === 30 ? 'mes' : e($p['period_days']) . ' días' ?></small></p>
        <ul class="ticks"><?php foreach (array_filter(array_map('trim', explode("\n", (string) $p['features']))) as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul>
        <a class="btn <?= $p['highlight'] ? 'btn-gold' : 'btn-ghost' ?>" href="<?= e(url('registro')) ?>">Empezar prueba gratis</a>
      </article>
      <?php endforeach; ?>
    </div>
    <p class="fine center">Todos los planes inician con <?= (int) S('trial_days', 7) ?> días de prueba gratis.</p>
  </div>
</section>

<section class="section alt" id="preguntas">
  <div class="container narrow">
    <p class="eyebrow center reveal">Preguntas frecuentes</p>
    <h2 class="center reveal">Lo que todos preguntan</h2>
    <div class="faq">
      <?php foreach ($faqs as $f): ?><details class="reveal"><summary><?= e($f['question']) ?></summary><p><?= nl2br(e($f['answer'])) ?></p></details><?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section cta-final">
  <div class="container narrow center">
    <h2 class="reveal">La próxima oportunidad ya está publicada</h2>
    <p class="lead reveal">Que no la encuentre primero tu competencia.</p>
    <a class="btn btn-gold btn-lg reveal" href="<?= e(url('registro')) ?>"><?= e(S('cta_text', 'Probar 7 días gratis')) ?></a>
    <p class="fine reveal"><?= e(S('footer_text')) ?></p>
  </div>
</section>
