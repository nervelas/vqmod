<?php
/**
 * Renderizado de las secciones del sitio público.
 */
if (!defined('TLL_BOOT')) { http_response_code(403); exit; }
require_once __DIR__ . '/map.php';

/* ---------- helpers de color ---------- */
function hex_rgb(string $h): array { $h = ltrim($h, '#'); return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))]; }
function rgb_hex(array $c): string { return sprintf('#%02X%02X%02X', max(0, min(255, round($c[0]))), max(0, min(255, round($c[1]))), max(0, min(255, round($c[2])))); }
function mix_hex(string $a, string $b, float $t): string {
    $x = hex_rgb($a); $y = hex_rgb($b);
    return rgb_hex([$x[0] + ($y[0] - $x[0]) * $t, $x[1] + ($y[1] - $x[1]) * $t, $x[2] + ($y[2] - $x[2]) * $t]);
}
function theme_css(array $t): string {
    $blue = $t['blue']; $navy = $t['navy']; $yellow = $t['yellow'];
    $vars = [
        '--blue' => $blue,
        '--blue-dark' => mix_hex($blue, $navy, .45),
        '--blue-soft' => mix_hex($blue, '#FFFFFF', .92),
        '--blue-tint' => mix_hex($blue, '#FFFFFF', .82),
        '--navy' => $navy,
        '--navy-2' => mix_hex($navy, $blue, .22),
        '--navy-deep' => mix_hex($navy, '#000000', .45),
        '--yellow' => $yellow,
        '--yellow-dark' => mix_hex($yellow, '#000000', .1),
        '--yellow-soft' => mix_hex($yellow, '#FFFFFF', .86),
    ];
    $s = ':root{';
    foreach ($vars as $k => $v) $s .= $k . ':' . $v . ';';
    return $s . '}';
}

/* ---------- enlaces de contacto ---------- */
function tel_link(array $c): string { return 'tel:+' . preg_replace('/\D/', '', $c['phone_digits']); }
function wa_link(array $c, string $msg = ''): string {
    $m = $msg !== '' ? $msg : $c['whatsapp_msg'];
    return 'https://wa.me/' . preg_replace('/\D/', '', $c['whatsapp']) . '?text=' . rawurlencode($m);
}
function lines(string $s): array { return array_values(array_filter(array_map('trim', preg_split('/\R/u', $s)), function ($x) { return $x !== ''; })); }

/* ---------- encabezado de sección ---------- */
function sec_head(array $d, string $cls = ''): string {
    $o = '<div class="sec-head reveal ' . e($cls) . '">';
    if (!empty($d['eyebrow'])) $o .= '<span class="eyebrow">' . e($d['eyebrow']) . '</span>';
    if (!empty($d['title'])) $o .= '<h2>' . fmt($d['title']) . '</h2>';
    if (!empty($d['subtitle'])) $o .= '<p>' . e($d['subtitle']) . '</p>';
    return $o . '</div>';
}

/** Enlace de navegación: las anclas siempre apuntan a la página de inicio. */
function nav_href($u): string {
    $h = href($u);
    return ($h !== '' && $h[0] === '#') ? url() . $h : $h;
}
function arrow(): string { return '<svg class="ic btn-ic" viewBox="0 0 256 256" aria-hidden="true" fill="currentColor"><path d="M221.66,133.66l-72,72a8,8,0,0,1-11.32-11.32L196.69,136H40a8,8,0,0,1,0-16H196.69L138.34,61.66a8,8,0,0,1,11.32-11.32l72,72A8,8,0,0,1,221.66,133.66Z"/></svg>'; }
function btn_a(string $label, string $href, string $cls = 'btn-primary', array $attrs = [], string $svg = null): string {
    if ($label === '') return '';
    $a = '';
    foreach ($attrs as $k => $v) $a .= ' ' . e($k) . '="' . e($v) . '"';
    $h = href($href);
    $ext = is_external($h) ? ' target="_blank" rel="noopener noreferrer"' : '';
    return '<a class="btn ' . e($cls) . '" href="' . e($h) . '"' . $ext . $a . '><span>' . e($label) . '</span>' . ($svg ?? arrow()) . '</a>';
}

/* ================================================================== */
/*  SECCIONES                                                         */
/* ================================================================== */
function render_section(string $id, array $C): string {
    $fn = 'sec_' . $id;
    return function_exists($fn) ? $fn($C) : '';
}

function sec_hero(array $C): string {
    $h = $C['hero']; $c = $C['contact'];
    $img = media_url($h['image']);
    ob_start(); ?>
<section class="hero" id="inicio">
  <div class="hero-bg" aria-hidden="true">
    <span class="blob b1"></span><span class="blob b2"></span><span class="dots"></span>
    <svg class="route" viewBox="0 0 1200 600" preserveAspectRatio="none"><path d="M-20 520 C 200 420, 260 560, 480 470 S 800 330, 1000 400 S 1180 250, 1240 280" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="3 14" stroke-linecap="round"/></svg>
  </div>
  <div class="container hero-grid">
    <div class="hero-copy">
      <?php if ($h['badge'] !== ''): ?><span class="pill anim-in" style="--d:0"><span class="live-dot"></span><?= e($h['badge']) ?></span><?php endif; ?>
      <h1 class="anim-in" style="--d:1"><?= fmt($h['title']) ?></h1>
      <p class="lead anim-in" style="--d:2"><?= e($h['subtitle']) ?></p>
      <div class="hero-ctas anim-in" style="--d:3">
        <?= btn_a($h['cta1_label'], $h['cta1_href'], 'btn-primary btn-lg') ?>
        <?= btn_a($h['cta2_label'], $h['cta2_href'], 'btn-ghost btn-lg', [], '') ?>
      </div>
      <?php if (!empty($h['trust'])): ?>
      <ul class="trust anim-in" style="--d:4">
        <?php foreach ($h['trust'] as $t): ?><li><?= icon($t['icon'] ?? '', 'trust-ic') ?><span><?= e($t['text']) ?></span></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
    <div class="hero-visual anim-in" style="--d:2">
      <div class="stage">
        <span class="orbit" aria-hidden="true"></span>
        <span class="stage-circle" aria-hidden="true"></span>
        <span class="spark s1" aria-hidden="true"><?= icon('sparkle', '', 'duotone') ?></span>
        <span class="spark s2" aria-hidden="true"><?= icon('sparkle', '', 'duotone') ?></span>
        <span class="spark s3" aria-hidden="true"><?= icon('sparkle', '', 'duotone') ?></span>
        <?php if ($img): ?><img class="stage-img" src="<?= e($img) ?>" alt="<?= e($h['image_alt']) ?>" width="1424" height="967" fetchpriority="high"><?php endif; ?>
        <?php if ($h['card1_title'] !== ''): ?>
        <div class="float-card fc1">
          <span class="fc-ic ok"><?= icon($h['card1_icon'], '') ?></span>
          <div><strong><?= e($h['card1_title']) ?></strong><small><?= e($h['card1_text']) ?></small></div>
        </div>
        <?php endif; ?>
        <?php if ($h['card2_title'] !== ''): ?>
        <div class="float-card fc2">
          <span class="fc-ic"><?= icon($h['card2_icon'], '') ?></span>
          <div><strong><?= e($h['card2_title']) ?></strong><small><?= e($h['card2_text']) ?></small></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php return ob_get_clean();
}

function sec_stats(array $C): string {
    $items = $C['stats']['items'];
    if (!$items) return '';
    ob_start(); ?>
<section class="stats" aria-label="Cifras destacadas">
  <div class="container">
    <div class="stats-bar reveal" data-n="<?= count($items) ?>">
      <?php foreach ($items as $i => $s): ?>
      <div class="stat">
        <span class="stat-ic"><?= icon($s['icon'], '') ?></span>
        <div>
          <div class="stat-num<?= is_numeric($s['number']) ? '' : ' is-text' ?>"><?= e($s['prefix']) ?><?php if (is_numeric($s['number'])): ?><span data-count="<?= e($s['number']) ?>"><?= e($s['number']) ?></span><?php else: ?><?= e($s['number']) ?><?php endif; ?><?= e($s['suffix']) ?></div>
          <div class="stat-label"><?= e($s['label']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php return ob_get_clean();
}

function sec_services(array $C): string {
    $d = $C['services'];
    ob_start(); ?>
<section class="section" id="servicios">
  <div class="container">
    <?= sec_head($d) ?>
    <div class="grid services-grid" data-n="<?= count($d['items']) ?>">
      <?php foreach ($d['items'] as $i => $s): ?>
      <article class="card service reveal" style="--i:<?= $i % 3 ?>">
        <div class="service-top">
          <span class="ic-box"><?= icon($s['icon'], '') ?></span>
          <?php if ($s['tag'] !== ''): ?><span class="tag"><?= e($s['tag']) ?></span><?php endif; ?>
        </div>
        <h3><?= e($s['title']) ?></h3>
        <p><?= e($s['text']) ?></p>
        <?php if ($d['cta_label'] !== ''): ?>
        <a class="link-arrow" href="#contacto" data-interest="<?= e($s['title']) ?>"><span><?= e($d['cta_label']) ?></span><?= arrow() ?></a>
        <?php endif; ?>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php return ob_get_clean();
}

function sec_about(array $C): string {
    $d = $C['about'];
    $img = media_url($d['image']); $ban = media_url($d['banner']);
    ob_start(); ?>
<section class="section about" id="mision">
  <div class="container">
    <?= sec_head($d) ?>
    <div class="about-grid">
      <?php if ($img): ?>
      <div class="about-photo reveal"><img src="<?= e($img) ?>" alt="<?= e($d['image_alt']) ?>" loading="lazy" width="860" height="1290"></div>
      <?php endif; ?>
      <div class="about-cards">
        <?php foreach ($d['items'] as $i => $it): ?>
        <article class="about-card reveal" style="--i:<?= $i ?>">
          <span class="ic-box"><?= icon($it['icon'], '') ?></span>
          <div><h3><?= e($it['title']) ?></h3><p><?= e($it['text']) ?></p></div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ($ban): ?>
    <div class="about-banner reveal">
      <img src="<?= e($ban) ?>" alt="<?= e($d['banner_alt']) ?>" loading="lazy" width="1500" height="1000">
      <?php if (!empty($d['values'])): ?>
      <ul class="values"><?php foreach ($d['values'] as $v): ?><li><?= e($v['name']) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
    <?php elseif (!empty($d['values'])): ?>
    <ul class="values solo reveal"><?php foreach ($d['values'] as $v): ?><li><?= e($v['name']) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
</section>
<?php return ob_get_clean();
}

function sec_how(array $C): string {
    $d = $C['how'];
    ob_start(); ?>
<section class="section dark how" id="como-funciona">
  <div class="how-bg" aria-hidden="true"></div>
  <div class="container">
    <?= sec_head($d, 'on-dark') ?>
    <ol class="steps">
      <?php foreach ($d['items'] as $i => $s): ?>
      <li class="step reveal" style="--i:<?= $i ?>">
        <span class="step-num"><?= $i + 1 ?></span>
        <span class="step-ic"><?= icon($s['icon'], '') ?></span>
        <h3><?= e($s['title']) ?></h3>
        <p><?= e($s['text']) ?></p>
      </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php return ob_get_clean();
}

function sec_rates(array $C): string {
    $d = $C['rates'];
    if (!$d['distances'] || !$d['sizes'] || !$d['speeds']) return '';
    ob_start(); ?>
<section class="section tint" id="tarifas">
  <div class="container">
    <?= sec_head($d) ?>
    <div class="calc reveal" id="calc" data-currency="<?= e($d['currency']) ?>">
      <div class="calc-form">
        <fieldset class="calc-group">
          <legend><span class="num">1</span> ¿Qué distancia hay?</legend>
          <div class="chips">
            <?php foreach ($d['distances'] as $i => $o): ?>
            <label class="chip"><input type="radio" name="calc_d" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?>><span><?= e($o['label']) ?><em><?= e($d['currency']) ?><?= e($o['price']) ?></em></span></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <fieldset class="calc-group">
          <legend><span class="num">2</span> ¿Qué vas a enviar?</legend>
          <div class="chips">
            <?php foreach ($d['sizes'] as $i => $o): ?>
            <label class="chip"><input type="radio" name="calc_s" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?>><span><?= e($o['label']) ?><?php if ($o['extra'] > 0): ?><em>+<?= e($d['currency']) ?><?= e($o['extra']) ?></em><?php endif; ?></span></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <fieldset class="calc-group">
          <legend><span class="num">3</span> ¿Qué tan urgente es?</legend>
          <div class="chips">
            <?php foreach ($d['speeds'] as $i => $o): ?>
            <label class="chip"><input type="radio" name="calc_u" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?>><span><?= e($o['label']) ?><?php if ($o['extra'] > 0): ?><em>+<?= e($d['currency']) ?><?= e($o['extra']) ?></em><?php endif; ?></span></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      </div>
      <aside class="calc-result" aria-live="polite">
        <span class="calc-eyebrow">Precio estimado</span>
        <div class="calc-total"><small><?= e($d['currency']) ?></small><span id="calcTotal">0</span></div>
        <ul class="calc-lines" id="calcLines"></ul>
        <a class="btn btn-wa btn-block" id="calcBtn" href="#" target="_blank" rel="noopener noreferrer"><span><?= e($d['cta_label']) ?></span><?= icon('whatsapp-logo', 'btn-ic', 'fill') ?></a>
        <p class="calc-note"><?= icon('info', '') ?><span><?= e($d['note']) ?></span></p>
      </aside>
    </div>
  </div>
</section>
<?php return ob_get_clean();
}

function sec_why(array $C): string {
    $d = $C['why'];
    $img = media_url($d['image']);
    ob_start(); ?>
<section class="section" id="porque">
  <div class="container why-grid">
    <div class="why-visual reveal">
      <div class="why-card">
        <span class="why-ring" aria-hidden="true"></span>
        <?php if ($img): ?><img src="<?= e($img) ?>" alt="<?= e($d['image_alt']) ?>" loading="lazy" width="960" height="1440"><?php endif; ?>
      </div>
      <?php if ($d['badge_title'] !== ''): ?>
      <div class="float-card fcw">
        <span class="fc-ic ok"><?= icon('identification-card', '') ?></span>
        <div><strong><?= e($d['badge_title']) ?></strong><small><?= e($d['badge_text']) ?></small></div>
      </div>
      <?php endif; ?>
    </div>
    <div class="why-body">
      <?= sec_head($d, 'left') ?>
      <div class="why-list">
        <?php foreach ($d['items'] as $i => $s): ?>
        <div class="why-item reveal" style="--i:<?= $i % 2 ?>">
          <span class="ic-box sm"><?= icon($s['icon'], '') ?></span>
          <div><h3><?= e($s['title']) ?></h3><p><?= e($s['text']) ?></p></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php return ob_get_clean();
}

function sec_plans(array $C): string {
    $d = $C['plans'];
    if (!$d['items']) return '';
    ob_start(); ?>
<section class="section tint" id="planes">
  <div class="container">
    <?= sec_head($d) ?>
    <div class="grid plans-grid">
      <?php foreach ($d['items'] as $i => $p): $feat = !empty($p['featured']); ?>
      <article class="plan reveal <?= $feat ? 'featured' : '' ?>" style="--i:<?= $i ?>">
        <?php if ($feat): ?><span class="plan-flag">Recomendado</span><?php endif; ?>
        <span class="ic-box"><?= icon($p['icon'], '') ?></span>
        <h3><?= e($p['name']) ?></h3>
        <p class="plan-desc"><?= e($p['desc']) ?></p>
        <div class="plan-price"><strong><?= e($p['price']) ?></strong><small><?= e($p['period']) ?></small></div>
        <ul class="plan-feats">
          <?php foreach (lines($p['features']) as $f): ?><li><?= icon('check-circle', 'chk') ?><span><?= e($f) ?></span></li><?php endforeach; ?>
        </ul>
        <?= btn_a($p['cta_label'], $p['cta_href'], $feat ? 'btn-primary btn-block' : 'btn-outline btn-block') ?>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php return ob_get_clean();
}

function sec_coverage(array $C): string {
    $d = $C['coverage']; $c = $C['contact'];
    ob_start(); ?>
<section class="section" id="cobertura">
  <div class="container cov-grid">
    <div class="cov-body">
      <?= sec_head($d, 'left') ?>
      <ul class="zones reveal">
        <?php foreach ($d['zones'] as $z): ?><li><?= icon('map-pin', '') ?><span><?= e($z['name']) ?></span></li><?php endforeach; ?>
      </ul>
      <?php if ($d['note'] !== ''): ?><p class="cov-note reveal"><?= icon('clock', '') ?><span><?= nl2br(e($d['note']), false) ?></span></p><?php endif; ?>
      <?php if ($d['cta_label'] !== ''): ?><div class="reveal"><?= btn_a($d['cta_label'], wa_link($c, 'Hola, quisiera cotizar el servicio de motorista por jornada para mi empresa.'), 'btn-primary', [], icon('whatsapp-logo', 'btn-ic', 'fill')) ?></div><?php endif; ?>
    </div>
    <div class="cov-visual reveal" aria-hidden="true">
      <div class="radar">
        <?= render_gt_map_bg() ?>
        <span class="ring r1"></span><span class="ring r2"></span><span class="ring r3"></span>
        <span class="sweep"></span>
        <span class="pin center"><?= icon('map-pin', '', 'fill') ?: icon('map-pin', '') ?></span>
        <span class="pin p1"><?= icon('package', '') ?></span>
        <span class="pin p2"><?= icon('house-line', '') ?></span>
        <span class="pin p3"><?= icon('storefront', '') ?></span>
        <span class="pin p4"><?= icon('buildings', '') ?></span>
        <span class="pin p5"><?= icon('moped', '') ?></span>
      </div>
    </div>
  </div>
</section>
<?php return ob_get_clean();
}

function sec_testimonials(array $C): string {
    $d = $C['testimonials'];
    if (!$d['items']) return '';
    ob_start(); ?>
<section class="section tint" id="opiniones">
  <div class="container">
    <?= sec_head($d) ?>
    <div class="grid t-grid">
      <?php foreach ($d['items'] as $i => $t): $r = max(1, min(5, (int)$t['rating'])); ?>
      <figure class="card testi reveal" style="--i:<?= $i % 3 ?>">
        <div class="stars" aria-label="<?= $r ?> de 5 estrellas"><?php for ($k = 0; $k < $r; $k++) echo icon('star', '', 'fill') ?: icon('star', ''); ?></div>
        <blockquote><?= e($t['text']) ?></blockquote>
        <figcaption><span class="avatar"><?= e(mb_strtoupper(mb_substr($t['name'], 0, 1))) ?></span><div><strong><?= e($t['name']) ?></strong><small><?= e($t['role']) ?></small></div></figcaption>
      </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php return ob_get_clean();
}

function sec_faq(array $C): string {
    $d = $C['faq'];
    if (!$d['items']) return '';
    ob_start(); ?>
<section class="section" id="preguntas">
  <div class="container faq-grid">
    <div class="faq-intro">
      <?= sec_head($d, 'left') ?>
      <div class="faq-help reveal">
        <?= icon('headset', '') ?>
        <div><strong>¿Aún tienes dudas?</strong><span>Escríbenos y te respondemos de inmediato.</span></div>
        <a class="btn btn-wa btn-sm" href="<?= e(wa_link($C['contact'])) ?>" target="_blank" rel="noopener noreferrer"><span>WhatsApp</span><?= icon('whatsapp-logo', 'btn-ic', 'fill') ?></a>
      </div>
    </div>
    <div class="faq-list">
      <?php foreach ($d['items'] as $i => $q): ?>
      <div class="faq-item reveal">
        <h3><button type="button" class="faq-q" aria-expanded="false" aria-controls="faq-a<?= $i ?>" id="faq-q<?= $i ?>"><span><?= e($q['q']) ?></span><?= icon('caret-down', 'faq-ic') ?></button></h3>
        <div class="faq-a" id="faq-a<?= $i ?>" role="region" aria-labelledby="faq-q<?= $i ?>"><div><p><?= e($q['a']) ?></p></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php return ob_get_clean();
}

function sec_cta(array $C): string {
    $d = $C['cta']; $c = $C['contact']; $logo = media_url($C['site']['logo']);
    ob_start(); ?>
<section class="cta-band" id="cta">
  <div class="container cta-inner reveal">
    <div class="cta-copy">
      <h2><?= fmt($d['title']) ?></h2>
      <p><?= e($d['text']) ?></p>
      <div class="cta-btns">
        <?= btn_a($d['whatsapp_label'], wa_link($c), 'btn-wa btn-lg', [], icon('whatsapp-logo', 'btn-ic', 'fill')) ?>
        <?= btn_a($d['call_label'], tel_link($c), 'btn-navy btn-lg', [], icon('phone-call', 'btn-ic', 'fill') ?: icon('phone-call', 'btn-ic')) ?>
      </div>
    </div>
    <?php if ($logo): ?><div class="cta-logo" aria-hidden="true"><img src="<?= e($logo) ?>" alt="" loading="lazy" width="220" height="220"></div><?php endif; ?>
  </div>
</section>
<?php return ob_get_clean();
}

function sec_contact(array $C): string {
    $d = $C['form']; $c = $C['contact'];
    $services = lines($d['services']); $sizes = lines($d['sizes']); $sends = lines($d['sends']);
    $sent = isset($_GET['enviado']); $err = isset($_GET['error']);
    ob_start(); ?>
<section class="section tint contact" id="contacto">
  <div class="container">
    <?= sec_head($d) ?>
    <div class="contact-grid">
      <div class="contact-info reveal">
        <a class="info-card" href="<?= e(wa_link($c)) ?>" target="_blank" rel="noopener noreferrer">
          <span class="ic-box wa"><?= icon('whatsapp-logo', '', 'fill') ?></span>
          <div><small>WhatsApp</small><strong><?= e($c['phone_display']) ?></strong></div>
        </a>
        <a class="info-card" href="<?= e(tel_link($c)) ?>">
          <span class="ic-box"><?= icon('phone-call', '') ?></span>
          <div><small>Llámanos</small><strong><?= e($c['phone_display']) ?></strong></div>
        </a>
        <?php if ($c['email'] !== ''): ?>
        <a class="info-card" href="mailto:<?= e($c['email']) ?>">
          <span class="ic-box"><?= icon('envelope-simple', '') ?></span>
          <div><small>Correo</small><strong><?= e($c['email']) ?></strong></div>
        </a>
        <?php endif; ?>
        <?php if ($c['address'] !== ''): ?>
        <div class="info-card">
          <span class="ic-box"><?= icon('map-pin-area', '') ?></span>
          <div><small>Cobertura</small><strong><?= e($c['address']) ?></strong></div>
        </div>
        <?php endif; ?>
        <?php if ($c['hours'] !== ''): ?>
        <div class="info-card">
          <span class="ic-box"><?= icon('clock', '') ?></span>
          <div><small>Horario</small><strong><?php foreach (lines($c['hours']) as $l): ?><span class="hrs"><?= e($l) ?></span><?php endforeach; ?></strong></div>
        </div>
        <?php endif; ?>
      </div>

      <div class="form-card reveal">
        <?php if ($sent): ?><div class="alert ok" role="status"><?= icon('check-circle', '') ?><span><?= e($d['success']) ?></span></div><?php endif; ?>
        <?php if ($err): ?><div class="alert bad" role="alert"><?= icon('warning-circle', '') ?><span>No pudimos enviar tu solicitud. Revisa los datos e inténtalo de nuevo o escríbenos por WhatsApp.</span></div><?php endif; ?>
        <form id="orderForm" action="<?= e(url('contact.php')) ?>" method="post" novalidate>
          <input type="hidden" name="token" id="fToken" value="<?= e(form_token()) ?>">
          <input type="hidden" name="interes" id="fInteres" value="">
          <div class="hp" aria-hidden="true"><label>No llenar este campo<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
          <div class="interest" id="interestChip" hidden><?= icon('sparkle', '') ?><span>Servicio de interés: <strong id="interestText"></strong></span><button type="button" id="interestClear" aria-label="Quitar">×</button></div>
          <div class="form-grid">
            <div class="field"><label for="f_nombre">Nombre completo *</label><input id="f_nombre" name="nombre" type="text" maxlength="80" autocomplete="name" required placeholder="Tu nombre"></div>
            <div class="field"><label for="f_tel">Teléfono / WhatsApp *</label><input id="f_tel" name="telefono" type="tel" maxlength="25" inputmode="tel" autocomplete="tel" required placeholder="Ej. 5555 5555"></div>
            <div class="field"><label for="f_email">Correo electrónico</label><input id="f_email" name="email" type="email" maxlength="120" autocomplete="email" placeholder="opcional"></div>
            <div class="field"><label for="f_serv">Tipo de servicio *</label>
              <select id="f_serv" name="servicio" required><option value="">Selecciona una opción</option><?php foreach ($services as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select></div>
            <div class="field full"><label for="f_rec">Dirección de recogida *</label><input id="f_rec" name="recogida" type="text" maxlength="200" required placeholder="Zona, colonia, calle y referencias"></div>
            <div class="field full"><label for="f_ent">Dirección de entrega *</label><input id="f_ent" name="entrega" type="text" maxlength="200" required placeholder="Zona, colonia, calle y referencias"></div>
            <div class="field"><label for="f_desc"><?= e($d['sends_label']) ?> *</label><input id="f_desc" name="descripcion" type="text" maxlength="160" required list="sendsList" autocomplete="off" placeholder="<?= e($sends ? 'Ej. ' . $sends[0] : '') ?>"><datalist id="sendsList"><?php foreach ($sends as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist></div>
            <div class="field"><label for="f_size"><?= e($d['size_label']) ?></label>
              <select id="f_size" name="tamano"><option value="">Selecciona una opción</option><?php foreach ($sizes as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="f_when">¿Cuándo lo necesitas?</label>
              <select id="f_when" name="cuando"><option>Lo antes posible</option><option>Programar para otra fecha</option></select></div>
            <div class="field sched" id="schedBox" hidden><label for="f_fecha">Fecha y hora</label><div class="two"><input id="f_fecha" name="fecha" type="date" min="<?= date('Y-m-d') ?>"><input id="f_hora" name="hora" type="time" aria-label="Hora"></div></div>
            <div class="field full"><label for="f_msg">Comentarios adicionales</label><textarea id="f_msg" name="comentarios" rows="3" maxlength="600" placeholder="Indicaciones especiales, nombre de quien recibe, etc."></textarea></div>
          </div>
          <label class="check"><input type="checkbox" name="acepto" value="1" required><span>Acepto la <a href="<?= e(url('legal.php?p=privacidad')) ?>" target="_blank">política de privacidad</a> y que me contacten para atender mi solicitud.</span></label>
          <div class="form-actions">
            <button class="btn btn-primary btn-lg" type="submit" id="fSubmit"><span><?= e($d['button']) ?></span><?= icon('paper-plane-tilt', 'btn-ic', 'fill') ?: icon('paper-plane-tilt', 'btn-ic') ?></button>
            <button class="btn btn-wa-outline btn-lg" type="button" id="fWhats"><span>Enviar por WhatsApp</span><?= icon('whatsapp-logo', 'btn-ic', 'fill') ?></button>
          </div>
          <p class="form-note"><?= icon('lock-key', '') ?><span><?= e($d['privacy']) ?></span></p>
          <div class="form-msg" id="formMsg" role="status" aria-live="polite" hidden></div>
        </form>
      </div>
    </div>
  </div>
</section>
<?php return ob_get_clean();
}

/* ---------- Encabezado / pie / flotantes ---------- */
function render_header(array $C): string {
    $s = $C['site']; $n = $C['nav']; $logo = media_url($s['logo']);
    ob_start(); ?>
<header class="site-header" id="top">
  <div class="container nav-wrap">
    <a class="brand" href="<?= e(url()) ?>#inicio" aria-label="<?= e($s['brand']) ?> - inicio">
      <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="" width="76" height="76"><?php endif; ?>
      <span class="brand-text"><strong><?= e($s['brand']) ?></strong><small><?= e($s['tagline']) ?></small></span>
    </a>
    <nav class="nav" id="nav" aria-label="Principal">
      <ul>
        <?php foreach ($n['items'] as $it): ?><li><a href="<?= e(nav_href($it['href'])) ?>" data-nav="<?= e(ltrim($it['href'], '#')) ?>"><?= e($it['label']) ?></a></li><?php endforeach; ?>
      </ul>
      <?php if ($n['cta_label'] !== ''): ?><a class="btn btn-primary btn-sm nav-cta" href="<?= e(nav_href($n['cta_href'])) ?>"><span><?= e($n['cta_label']) ?></span><?= arrow() ?></a><?php endif; ?>
    </nav>
    <button class="burger" id="burger" aria-label="Abrir menú" aria-expanded="false" aria-controls="nav"><span></span><span></span><span></span></button>
  </div>
</header>
<?php return ob_get_clean();
}

function render_footer(array $C): string {
    $s = $C['site']; $c = $C['contact']; $f = $C['footer']; $n = $C['nav']; $logo = media_url($s['logo']);
    $soc = [['facebook', 'facebook-logo', 'Facebook'], ['instagram', 'instagram-logo', 'Instagram'], ['tiktok', 'tiktok-logo', 'TikTok'], ['youtube', 'youtube-logo', 'YouTube']];
    ob_start(); ?>
<footer class="footer">
  <div class="container footer-grid">
    <div class="f-brand">
      <a class="brand on-dark" href="<?= e(url()) ?>#inicio">
        <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="" width="84" height="84" loading="lazy"><?php endif; ?>
        <span class="brand-text"><strong><?= e($s['brand']) ?></strong><small><?= e($s['tagline']) ?></small></span>
      </a>
      <p><?= e($f['about']) ?></p>
      <div class="social">
        <?php foreach ($soc as $so): $u = safe_url($c[$so[0]] ?? ''); if ($u === '') continue; ?>
        <a href="<?= e($u) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($so[2]) ?>"><?= icon($so[1], '', 'fill') ?></a>
        <?php endforeach; ?>
        <a href="<?= e(wa_link($c)) ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><?= icon('whatsapp-logo', '', 'fill') ?></a>
      </div>
    </div>
    <div>
      <h4>Navegación</h4>
      <ul class="f-links">
        <?php foreach ($n['items'] as $it): ?><li><a href="<?= e(nav_href($it['href'])) ?>"><?= e($it['label']) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h4>Contacto</h4>
      <ul class="f-contact">
        <li><?= icon('phone-call', '') ?><a href="<?= e(tel_link($c)) ?>"><?= e($c['phone_display']) ?></a></li>
        <li><?= icon('whatsapp-logo', '', 'fill') ?><a href="<?= e(wa_link($c)) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a></li>
        <?php if ($c['email'] !== ''): ?><li><?= icon('envelope-simple', '') ?><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></li><?php endif; ?>
        <?php if ($c['address'] !== ''): ?><li><?= icon('map-pin', '') ?><span><?= e($c['address']) ?></span></li><?php endif; ?>
      </ul>
    </div>
    <div>
      <h4>Horario</h4>
      <ul class="f-contact">
        <?php foreach (lines($c['hours']) as $l): ?><li><?= icon('clock', '') ?><span><?= e($l) ?></span></li><?php endforeach; ?>
      </ul>
    </div>
  </div>
  <div class="container footer-bottom">
    <span>© <?= date('Y') ?> <?= e($f['copyright']) ?></span>
    <span class="legal-links"><a href="<?= e(url('legal.php?p=privacidad')) ?>">Privacidad</a><a href="<?= e(url('legal.php?p=terminos')) ?>">Términos</a></span>
  </div>
</footer>
<?php return ob_get_clean();
}

function render_floating(array $C): string {
    if (empty($C['site']['floating'])) return '';
    $c = $C['contact'];
    ob_start(); ?>
<div class="fab-wrap">
  <a class="fab fab-call" href="<?= e(tel_link($c)) ?>" aria-label="Llamar al <?= e($c['phone_display']) ?>"><span class="fab-label">Llamar <?= e($c['phone_display']) ?></span><?= icon('phone-call', '', 'fill') ?: icon('phone-call', '') ?></a>
  <a class="fab fab-wa" href="<?= e(wa_link($c)) ?>" target="_blank" rel="noopener noreferrer" aria-label="Escribir por WhatsApp"><span class="fab-label">Escríbenos por WhatsApp</span><?= icon('whatsapp-logo', '', 'fill') ?></a>
</div>
<?php return ob_get_clean();
}
