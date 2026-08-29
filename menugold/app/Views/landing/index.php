<?php
/** Sitio de venta. @var \MenuGold\Core\View $view */
use MenuGold\Core\Url;
use MenuGold\Models\Landing;

$view->extend('layouts/site');
$L = function ($k) { return Landing::v($k); };
$wa = mg_wa(Landing::v('whatsapp'), Landing::v('whatsapp_message'));
$heroPhoto = $demo && $demo['cover'] !== '' ? $demo['cover'] : (isset($gallery[0]) ? $gallery[0]['image'] : '');
?>

<!-- ============ I · Hero ============ -->
<section class="hero">
  <div class="hero-bg" data-parallax="0.12">
    <?= mg_img($heroPhoto, array(
        'alt' => 'Platillo servido en un restaurante de alta cocina',
        'sizes' => '100vw', 'loading' => 'eager', 'fetchpriority' => 'high', 'class' => 'zoomer')) ?>
  </div>
  <div class="hero-veil" aria-hidden="true"></div>

  <div class="shell hero-grid">
    <div>
      <p class="eyebrow reveal"><?= e($L('hero_eyebrow')) ?></p>
      <h1 class="display" data-split data-reveal><?= e($L('hero_title')) ?></h1>
      <p class="hero-sub reveal" style="--d:220ms"><?= e($L('hero_subtitle')) ?></p>
      <div class="hero-actions reveal" style="--d:340ms">
        <a class="btn" href="<?= e(mg_url('/demo')) ?>"><?= e($L('hero_cta')) ?></a>
        <a class="btn btn-ghost" href="<?= e($wa) ?>" target="_blank" rel="noopener">Hablar por WhatsApp</a>
      </div>
    </div>

    <div class="qr-card reveal" style="--d:460ms">
      <img src="<?= e(mg_url('/qr/demo?s=7')) ?>" alt="Código QR para abrir el menú de demostración" width="180" height="180" loading="lazy">
      <p><?= e($L('hero_qr_note')) ?></p>
    </div>
  </div>

  <span class="scroll-cue" aria-hidden="true">Desliza</span>
</section>

<!-- ============ Marquesina ============ -->
<div class="marquee" aria-hidden="true">
  <div class="marquee-track">
    <?php foreach (array_filter(array_map('trim', explode('·', $L('marquee')))) as $item): ?>
      <span class="marquee-item"><?= e($item) ?></span>
    <?php endforeach; ?>
  </div>
</div>

<!-- ============ II · El problema ============ -->
<section class="section" id="problema">
  <div class="shell">
    <div class="section-head reveal">
      <span class="numeral">I</span>
      <p class="eyebrow"><?= e($L('problem_eyebrow')) ?></p>
      <h2 class="display">Lo que hoy le cuesta dinero a tu restaurante</h2>
    </div>

    <div class="problem-list">
      <?php
      $problems = array(
          array($L('problem_1'), isset($gallery[1]) ? $gallery[1]['image'] : '', 'Menú impreso sobre una mesa de restaurante'),
          array($L('problem_2'), isset($gallery[2]) ? $gallery[2]['image'] : '', 'Platillo fotografiado con luz cálida'),
          array($L('problem_3'), isset($gallery[3]) ? $gallery[3]['image'] : '', 'Cocina de restaurante en plena operación'),
      );
      foreach ($problems as $i => $p): ?>
        <div class="problem-row reveal">
          <div class="problem-text">
            <span class="numeral"><?= e(mg_roman($i + 1)) ?></span>
            <h3><?= e($p[0]) ?></h3>
          </div>
          <figure class="problem-figure zoomer">
            <?= mg_img($p[1], array('alt' => $p[2], 'sizes' => '(min-width: 860px) 40vw, 100vw')) ?>
          </figure>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============ III · La experiencia ============ -->
<section class="section" id="experiencia">
  <div class="shell experience">
    <div class="phone-stage reveal">
      <div class="phone" data-tilt>
        <div class="phone-screen">
          <div class="phone-scroll">
            <div class="phone-inner">
              <div class="mini-hero">
                <?= mg_img($demo ? $demo['cover'] : '', array('alt' => '', 'sizes' => '300px')) ?>
                <div class="mini-hero-cap">
                  <span>Abierto ahora</span>
                  <strong><?= e($demo ? $demo['name'] : 'Brasa Negra') ?></strong>
                </div>
              </div>
              <div class="mini-cats">
                <span>Cortes</span><span>Entradas</span><span>Del mar</span><span>Postres</span><span>Barra</span>
              </div>
              <?php foreach ($phoneDemo as $p): ?>
                <div class="mini-card">
                  <?= mg_img($p['image'], array('alt' => '', 'sizes' => '280px')) ?>
                  <div class="mini-card-body">
                    <b><?= e($p['name']) ?></b>
                    <small><?= e($p['category_name']) ?></small>
                    <div class="mini-card-row">
                      <span class="mini-price"><?= e(mg_money($p['price'], $demo ? $demo['currency'] : 'Q')) ?></span>
                      <span class="mini-add" aria-hidden="true">+</span>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="phone-glare" aria-hidden="true"></div>
        </div>
      </div>
    </div>

    <div>
      <span class="numeral">II</span>
      <p class="eyebrow reveal"><?= e($L('experience_eyebrow')) ?></p>
      <h2 class="display reveal" style="margin:1rem 0"><?= e($L('experience_title')) ?></h2>
      <p class="lead reveal"><?= e($L('experience_text')) ?></p>

      <div class="benefit-list">
        <?php
        $benefits = array(
            array('Sin aplicaciones', 'El comensal apunta la cámara y ya está adentro. No descarga nada, no crea cuenta, no da su correo.'),
            array('La foto vende', 'Cada platillo se ve a pantalla completa, con su descripción y su precio en claro. El ticket promedio sube solo.'),
            array('Cero errores de suma', 'El total se calcula en el servidor con los precios reales, incluidos extras y descuentos.'),
        );
        foreach ($benefits as $i => $b): ?>
          <div class="benefit reveal">
            <span class="numeral"><?= sprintf('%02d', $i + 1) ?></span>
            <div>
              <h3><?= e($b[0]) ?></h3>
              <p><?= e($b[1]) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ============ IV · Galería viva ============ -->
<section class="section" id="menu">
  <div class="shell">
    <div class="section-head reveal">
      <span class="numeral">III</span>
      <p class="eyebrow"><?= e($L('gallery_eyebrow')) ?></p>
      <h2 class="display"><?= e($L('gallery_title')) ?></h2>
    </div>

    <div class="gallery">
      <?php foreach ($gallery as $i => $g): ?>
        <a class="g-item zoomer reveal" style="--d:<?= ($i % 4) * 70 ?>ms"
           href="<?= e(mg_url('/r/' . ($demo ? $demo['slug'] : '') . '/producto/' . (int)$g['id'])) ?>">
          <span data-parallax="<?= $i % 2 ? '0.06' : '-0.05' ?>" style="display:block;height:100%">
            <?= mg_img($g['image'], array('alt' => $g['name'], 'sizes' => '(min-width: 820px) 40vw, 50vw')) ?>
          </span>
          <span class="g-cap">
            <span class="g-title"><?= e($g['name']) ?></span>
            <span class="g-meta">
              <span class="g-price"><?= e(mg_money($g['price'], $demo ? $demo['currency'] : 'Q')) ?></span>
              <span class="g-add">Agregar</span>
            </span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============ V · Cómo funciona ============ -->
<section class="section" id="pasos">
  <div class="shell">
    <div class="section-head is-centered reveal">
      <span class="numeral">IV</span>
      <p class="eyebrow is-centered"><?= e($L('steps_eyebrow')) ?></p>
      <h2 class="display"><?= e($L('steps_title')) ?></h2>
    </div>

    <div class="steps" data-steps>
      <span class="steps-line" aria-hidden="true"></span>
      <?php for ($i = 1; $i <= 3; $i++): ?>
        <div class="step reveal" style="--d:<?= ($i - 1) * 110 ?>ms">
          <div class="step-num"><?= e(mg_roman($i)) ?></div>
          <h3><?= e($L('step_' . $i . '_title')) ?></h3>
          <p><?= e($L('step_' . $i . '_text')) ?></p>
        </div>
      <?php endfor; ?>
    </div>
  </div>
</section>

<!-- ============ VI · Para el dueño ============ -->
<section class="section" id="dueno" style="background:linear-gradient(180deg,var(--ink),var(--carbon) 50%,var(--ink))">
  <div class="shell owner">
    <div class="reveal">
      <div class="laptop">
        <div class="laptop-lid">
          <div class="laptop-screen">
            <div class="kds-mock">
              <div class="kds-mock-top">
                <b>Cocina en vivo</b>
                <span class="chip chip-green"><span class="dot-live"></span> 4 activos</span>
              </div>
              <div class="kds-cols">
                <div class="kds-col">
                  <b>Nuevo</b>
                  <div class="kds-ticket"><b>KX41 · Mesa 7</b><span>2 min · 3 platillos</span></div>
                  <div class="kds-ticket"><b>KX42 · Llevar</b><span>1 min · 2 platillos</span></div>
                </div>
                <div class="kds-col">
                  <b>Preparando</b>
                  <div class="kds-ticket is-late"><b>KX38 · Mesa 2</b><span>21 min · retrasado</span></div>
                </div>
                <div class="kds-col">
                  <b>Listo</b>
                  <div class="kds-ticket"><b>KX36 · Mesa 11</b><span>Esperando mesero</span></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="laptop-base"></div>
      </div>

      <div class="tablet reveal" style="--d:180ms">
        <div class="tablet-screen">
          <p class="eyebrow is-plain" style="font-size:8px">Ventas del mes</p>
          <b class="display" style="font-size:26px;color:var(--gold);display:block;margin-top:6px">Q184,320</b>
          <span style="font-size:9px;color:var(--green)">+31 % vs. mes anterior</span>
          <div class="chart-mock">
            <?php foreach (array(38,52,44,68,58,74,62,88,71,95,80,100) as $k => $h): ?>
              <i style="--h:<?= $h ?>%;--d:<?= $k * 0.05 ?>s"></i>
            <?php endforeach; ?>
          </div>
          <p style="font-size:8.5px;color:var(--text-faint);margin-top:10px">Hora pico: 20:00 – 21:30</p>
        </div>
      </div>
    </div>

    <div>
      <span class="numeral">V</span>
      <p class="eyebrow reveal"><?= e($L('owner_eyebrow')) ?></p>
      <h2 class="display reveal" style="margin:1rem 0"><?= e($L('owner_title')) ?></h2>
      <p class="lead reveal"><?= e($L('owner_text')) ?></p>

      <div class="stats">
        <?php for ($i = 1; $i <= 4; $i++):
          $val = (float)preg_replace('/[^0-9.]/', '', $L('stat_' . $i . '_value'));
          $suffix = $i === 3 ? '' : '';
        ?>
          <div class="stat reveal" style="--d:<?= ($i - 1) * 80 ?>ms">
            <b data-count="<?= e($val) ?>"<?= $i === 3 ? ' data-suffix="%"' : '' ?>>0</b>
            <span><?= e($L('stat_' . $i . '_label')) ?></span>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>
</section>

<!-- ============ VII · Precios ============ -->
<section class="section" id="planes">
  <div class="shell">
    <div class="section-head is-centered reveal">
      <span class="numeral">VI</span>
      <p class="eyebrow is-centered"><?= e($L('pricing_eyebrow')) ?></p>
      <h2 class="display"><?= e($L('pricing_title')) ?></h2>
      <p class="lead" style="text-align:center"><?= e($L('pricing_note')) ?></p>
    </div>

    <div class="plans">
      <?php foreach ($plans as $i => $p):
        $msg = $p['wa_message'] !== '' ? $p['wa_message'] : 'Hola, me interesa el plan ' . $p['name'] . ' de MenúGold.';
      ?>
        <article class="plan reveal<?= (int)$p['is_featured'] === 1 ? ' is-featured' : '' ?>" style="--d:<?= $i * 90 ?>ms">
          <?php if ((int)$p['is_featured'] === 1): ?><span class="plan-flag">El más elegido</span><?php endif; ?>
          <h3><?= e($p['name']) ?></h3>
          <p class="plan-pitch"><?= e($p['pitch']) ?></p>
          <p class="plan-price"><b><?= e($p['price']) ?></b><span><?= e($p['period']) ?></span></p>
          <ul class="plan-features">
            <?php foreach ($p['features_list'] as $f): ?>
              <li>
                <svg width="13" height="13" viewBox="0 0 13 13" fill="none" aria-hidden="true"><path d="M2 6.8 5 9.8l6-7" stroke="#D8B26E" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span><?= e($f) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <a class="btn <?= (int)$p['is_featured'] === 1 ? '' : 'btn-ghost' ?> btn-block"
             href="<?= e(mg_wa(Landing::v('whatsapp'), $msg)) ?>" target="_blank" rel="noopener"><?= e($p['cta_text']) ?></a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============ VIII · Testimonios ============ -->
<?php if ($quotes): ?>
<section class="section" id="testimonios">
  <div class="shell">
    <div class="section-head reveal">
      <span class="numeral">VII</span>
      <p class="eyebrow"><?= e($L('testimonials_eyebrow')) ?></p>
      <h2 class="display"><?= e($L('testimonials_title')) ?></h2>
    </div>
    <div class="quotes">
      <?php foreach ($quotes as $i => $q): ?>
        <figure class="quote reveal" style="--d:<?= $i * 100 ?>ms">
          <blockquote><?= e($q['quote']) ?></blockquote>
          <figcaption>
            <span class="quote-avatar" aria-hidden="true"><?= e(\MenuGold\Core\Str::initials($q['name'])) ?></span>
            <span>
              <b><?= e($q['name']) ?></b>
              <span><?= e(trim($q['role'] . ($q['place'] !== '' ? ' · ' . $q['place'] : ''), ' ·')) ?></span>
            </span>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============ IX · Cierre ============ -->
<section class="closer">
  <div class="closer-bg" data-parallax="0.08">
    <?= mg_img(isset($gallery[4]) ? $gallery[4]['image'] : $heroPhoto, array('alt' => '', 'sizes' => '100vw')) ?>
  </div>
  <div class="closer-veil" aria-hidden="true"></div>
  <div class="shell-narrow">
    <p class="eyebrow is-centered reveal">Empieza hoy</p>
    <h2 class="display reveal" style="margin-top:1.4rem"><?= e($L('cta_title')) ?></h2>
    <p class="lead reveal"><?= e($L('cta_text')) ?></p>
    <div class="closer-actions reveal">
      <a class="btn" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= e($L('cta_button')) ?></a>
      <a class="btn btn-ghost" href="<?= e(mg_url('/demo')) ?>">Ver el demo</a>
      <div class="qr-card">
        <img src="<?= e(mg_url('/qr/demo?s=6')) ?>" alt="Código QR del menú de demostración" width="150" height="150" loading="lazy">
        <p>Escanea y míralo en tu celular</p>
      </div>
    </div>
  </div>
</section>
