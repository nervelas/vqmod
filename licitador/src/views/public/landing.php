<?php
/** Titular con palabras animadas; *texto* = énfasis dorado en cursiva (si no hay asteriscos, se enfatizan las 2 últimas palabras). */
$ht = (string) S('hero_title'); $words = [];
if (strpos($ht, '*') !== false) { $em = false; foreach (preg_split('/(\*)/', $ht, -1, PREG_SPLIT_DELIM_CAPTURE) as $part) { if ($part === '*') { $em = !$em; continue; } foreach (preg_split('/\s+/', trim($part)) as $w) if ($w !== '') $words[] = [$w, $em]; } }
else { $all = preg_split('/\s+/', trim($ht)); foreach ($all as $i => $w) $words[] = [$w, $i >= count($all) - 2]; }
$d0 = $demo[0] ?? null; $d1 = $demo[1] ?? null; $d2 = $demo[2] ?? null;
?>
<section class="hero">
  <div class="container hero-grid">
    <div class="hero-copy">
      <div class="live-pill reveal"><span class="dot"></span><span><strong class="mono" data-count="<?= (int) $stats['open'] ?>">0</strong> concursos abiertos hoy en Guatemala</span></div>
      <h1><?php foreach ($words as $i => [$w, $em]): ?><span class="wd"><span style="--d:<?= number_format(.1 + $i * .07, 2) ?>s"><?= $em ? '<em>' . e($w) . '</em>' : e($w) ?></span></span> <?php endforeach; ?></h1>
      <p class="lead reveal" style="--d:.5s"><?= e(S('hero_sub')) ?></p>
      <div class="hero-cta reveal" style="--d:.65s">
        <a class="btn btn-gold btn-lg" href="<?= e(url('registro')) ?>"><?= e(S('cta_text', 'Probar 7 días gratis')) ?><?= icon('arrow') ?></a>
        <a class="btn btn-ghost btn-lg" href="#producto"><?= icon('play') ?>Ver cómo se ve</a>
      </div>
      <div class="hero-trust reveal" style="--d:.8s"><span><?= icon('check-circle') ?>Sin tarjeta</span><span><?= icon('check-circle') ?>Cancelas cuando quieras</span><span><?= icon('check-circle') ?>Listo en 2 minutos</span></div>
    </div>
    <div class="radar-stage reveal z" data-tilt style="--d:.3s" aria-hidden="true">
      <div class="radar">
        <div class="radar-ring r3"></div><div class="radar-ring r1"></div><div class="radar-ring r2"></div><div class="radar-ring r4"></div>
        <div class="radar-cross"></div><div class="radar-sweep"></div>
        <?php foreach ([[62, 24, 0], [28, 40, 1.1], [72, 62, 2.0], [42, 72, .6], [24, 22, 2.7], [80, 38, 1.6], [54, 48, 3.2], [36, 58, 1.9]] as [$x, $y, $dl]): ?><span class="blip" style="left:<?= $x ?>%;top:<?= $y ?>%;animation-delay:<?= $dl ?>s"></span><?php endforeach; ?>
        <span class="blip hot" style="left:64%;top:56%;animation-delay:.8s"></span><span class="blip hot" style="left:30%;top:30%;animation-delay:2.4s"></span>
      </div>
      <?php if ($d0): ?><div class="float-card fc1"><span class="ic"><?= icon('spark') ?></span><div><b>Nuevo · <?= e(mb_substr($d0['title'], 0, 30)) ?>…</b><span><?= e($d0['department']) ?> · <?= e(money($d0['amount'])) ?></span></div></div><?php endif; ?>
      <?php if ($d1): ?><div class="float-card fc2"><span class="ic"><?= icon('clock') ?></span><div><b><?= e(days_label($d1['deadline'])) ?></b><span><?= e(mb_substr($d1['entity'], 0, 30)) ?></span></div></div><?php endif; ?>
      <div class="float-card fc3"><span class="ic"><?= icon('chart') ?></span><div><b>Precio de referencia</b><span>mediana de adjudicados parecidos</span></div></div>
    </div>
  </div>
</section>

<div class="marquee" aria-hidden="true"><div class="marquee-track">
  <?php for ($k = 0; $k < 2; $k++): ?><span>Municipalidades</span><span>Ministerio de Educación</span><span>Ministerio de Salud</span><span>Comunicaciones e Infraestructura</span><span>CONRED</span><span>Universidad de San Carlos</span><span>Policía Nacional Civil</span><span>Hospitales nacionales</span><span>Gobernaciones departamentales</span><span>MAGA</span><?php endfor; ?>
</div></div>

<section class="container" style="margin-top:34px">
  <div class="stats-grid">
    <div class="stat reveal"><strong class="mono" data-count="<?= (int) $stats['open'] ?>">0</strong><span>concursos abiertos hoy</span></div>
    <div class="stat reveal"><strong class="mono" data-count="<?= (int) round($stats['sum'] / 1000000) ?>" data-prefix="Q " data-suffix=" M">0</strong><span>en montos estimados</span></div>
    <div class="stat reveal"><strong class="mono" data-count="<?= (int) $stats['awarded'] ?>">0</strong><span>adjudicados como referencia de precios</span></div>
    <div class="stat reveal"><strong class="mono" data-count="22">0</strong><span>departamentos cubiertos</span></div>
  </div>
</section>

<section class="section" id="problema">
  <div class="container problem">
    <div>
      <p class="eyebrow reveal">El problema</p>
      <h2 class="reveal"><?= e(S('problem_t')) ?></h2>
      <p class="lead reveal"><?= e(S('problem_d')) ?></p>
    </div>
    <div class="problem-art">
      <div class="pcard reveal r"><span class="ic"><?= icon('clock') ?></span><div><b>Te enteras tarde</b><span>Cuando ves el concurso, el plazo ya venció.</span></div></div>
      <div class="pcard reveal r" style="--d:.1s"><span class="ic"><?= icon('search') ?></span><div><b>Buscar a mano cansa</b><span>Miles de publicaciones al mes y ninguna hecha a tu medida.</span></div></div>
      <div class="pcard ok reveal r" style="--d:.2s"><span class="ic"><?= icon('radar') ?></span><div><b>Con <?= e(S('site_name', 'Licitador')) ?></b><span>Recibes solo lo que vendes, con el motivo y el plazo.</span></div></div>
    </div>
  </div>
</section>

<section class="section alt" id="como">
  <div class="container">
    <div class="sec-head"><p class="eyebrow center reveal">Cómo funciona</p><h2 class="reveal">Tres pasos, <em>cero</em> complicaciones</h2></div>
    <div class="steps">
      <?php foreach ([[1, 'sliders'], [2, 'radar'], [3, 'award']] as [$i, $ic]): ?>
      <div class="step reveal" style="--d:<?= ($i - 1) * .12 ?>s"><span class="step-n">0<?= $i ?></span><span class="ic"><?= icon($ic, 'i-lg') ?></span><h3><?= e(S("how{$i}_t")) ?></h3><p><?= e(S("how{$i}_d")) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="producto">
  <div class="container">
    <div class="sec-head"><p class="eyebrow center reveal">El producto</p><h2 class="reveal">Así te llegan las <em>oportunidades</em></h2><p class="lead reveal">Cada tarjeta te dice qué compran, cuánto, hasta cuándo y <strong>por qué</strong> te conviene. Ejemplos con concursos DEMO.</p></div>
    <div class="showcase reveal z" data-tilt>
      <div class="win">
        <div class="win-bar"><i></i><i></i><i></i><span><?= e(parse_url(site_url(''), PHP_URL_HOST)) ?>/app</span></div>
        <div class="win-body">
          <?php foreach ($demo as $k => $t): $sc = 72 + ($t['id'] * 7) % 24; $u = urgency_class($t['deadline']); ?>
          <div class="mini">
            <?= score_ring($sc, '46px') ?>
            <div><h4><?= e(mb_substr($t['title'], 0, 74)) ?></h4><p><?= e($t['entity']) ?> · <?= e($t['department']) ?></p>
              <div class="tags"><span class="badge b-new">Nuevo</span><span class="badge"><?= e(money($t['amount'])) ?></span><span class="badge <?= $u === 'urgent' ? 'b-urgent' : '' ?>"><?= e(days_label($t['deadline'])) ?></span><span class="badge b-demo">DEMO</span></div></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section alt">
  <div class="container">
    <div class="sec-head"><p class="eyebrow center reveal">Todo en un solo lugar</p><h2 class="reveal">Hecho para <em>ganar</em> tiempo</h2></div>
    <div class="bento">
      <div class="bcell reveal"><span class="ic"><?= icon('radar', 'i-lg') ?></span><h3>Coincidencias que se explican</h3><p>Nada de cajas negras: ves por qué cada concurso te sirve.</p><div class="viz chipline"><span class="g">coincide por: tuberías PVC</span><span>departamento: Escuintla</span><span>monto en tu rango</span></div></div>
      <div class="bcell reveal" style="--d:.1s"><span class="ic"><?= icon('chart', 'i-lg') ?></span><h3>Precios de referencia</h3><p>Mínimo, mediana y máximo de adjudicados parecidos, para cotizar con criterio.</p><div class="viz"><div class="bars"><?php foreach ([34, 52, 40, 68, 58, 82, 62, 46, 74, 56] as $i => $h): ?><i style="--h:<?= $h ?>%;animation-delay:<?= $i * 70 ?>ms"></i><?php endforeach; ?></div></div></div>
      <div class="bcell reveal"><span class="ic"><?= icon('clock', 'i-lg') ?></span><h3>Plazos sin sorpresas</h3><p>Calendario de vencimientos y alertas antes de que se acabe el tiempo.</p></div>
      <div class="bcell reveal" style="--d:.1s"><span class="ic"><?= icon('file', 'i-lg') ?></span><h3>Borrador de oferta</h3><p>Carta, lista de documentos y cuadro de precios en DOCX y PDF.</p></div>
      <div class="bcell reveal" style="--d:.2s"><span class="ic"><?= icon('chat', 'i-lg') ?></span><h3>WhatsApp y correo</h3><p>Resumen diario o aviso inmediato, como prefieras.</p></div>
      <div class="bcell reveal"><span class="ic"><?= icon('shield', 'i-lg') ?></span><div><h3>Datos oficiales, sin atajos</h3><p>Trabajamos con los datos abiertos que publica GUATECOMPRAS y siempre te damos el enlace oficial para verificar cada concurso.</p></div></div>
    </div>
  </div>
</section>

<section class="section" id="sectores">
  <div class="container">
    <div class="sec-head"><p class="eyebrow center reveal">Sectores</p><h2 class="reveal">Para quienes le <em>venden</em> al Estado</h2></div>
    <div class="sector-grid">
      <?php foreach ($sectors as $i => $s): $img = preg_replace('/\.svg$/', '.webp', $s['image'] ?: 'img/sector-servicios.webp'); ?>
      <article class="sector reveal" style="--d:<?= ($i % 3) * .1 ?>s">
        <img src="<?= e(asset($img)) ?>" alt="<?= e($s['name']) ?>" width="1200" height="800" loading="lazy" decoding="async">
        <span class="tag"><?= icon('tag') ?><?= e(explode(',', (string) $s['keywords'])[0] ?: $s['name']) ?></span>
        <div class="sector-body"><h3><?= e($s['name']) ?></h3><p><?= e($s['tagline']) ?></p></div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section alt" id="planes">
  <div class="container">
    <div class="sec-head"><p class="eyebrow center reveal">Planes</p><h2 class="reveal">Suscripción mensual, <em>sin letra chiquita</em></h2></div>
    <div class="plans">
      <?php foreach ($plans as $i => $p): ?>
      <article class="plan reveal <?= $p['highlight'] ? 'plan-hl' : '' ?>" style="--d:<?= $i * .1 ?>s">
        <?php if ($p['highlight']): ?><span class="ribbon">Más elegido</span><?php endif; ?>
        <h3><?= e($p['name']) ?></h3>
        <p class="price"><span><?= e(money($p['price'])) ?></span><small>/ <?= (int) $p['period_days'] === 30 ? 'mes' : e($p['period_days']) . ' días' ?></small></p>
        <ul class="ticks"><?php foreach (array_filter(array_map('trim', explode("\n", (string) $p['features']))) as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul>
        <a class="btn <?= $p['highlight'] ? 'btn-gold' : 'btn-ghost' ?> btn-block" href="<?= e(url('registro')) ?>">Empezar prueba gratis</a>
      </article>
      <?php endforeach; ?>
    </div>
    <p class="fine center">Todos los planes inician con <?= (int) S('trial_days', 7) ?> días de prueba gratis.</p>
  </div>
</section>

<section class="section" id="preguntas">
  <div class="container narrow">
    <div class="sec-head"><p class="eyebrow center reveal">Preguntas frecuentes</p><h2 class="reveal">Lo que todos <em>preguntan</em></h2></div>
    <div class="faq"><?php foreach ($faqs as $f): ?><details class="reveal"><summary><?= e($f['question']) ?></summary><p><?= nl2br(e($f['answer'])) ?></p></details><?php endforeach; ?></div>
  </div>
</section>

<section class="section cta-final alt">
  <div class="ringbg" aria-hidden="true"><i></i><i></i><i></i></div>
  <div class="container narrow">
    <h2 class="reveal">La próxima oportunidad <em>ya está publicada</em></h2>
    <p class="lead center reveal">Que no la encuentre primero tu competencia.</p>
    <p class="reveal"><a class="btn btn-gold btn-lg" href="<?= e(url('registro')) ?>"><?= e(S('cta_text', 'Probar 7 días gratis')) ?><?= icon('arrow') ?></a></p>
    <p class="fine reveal"><?= e(S('footer_text')) ?></p>
  </div>
</section>
