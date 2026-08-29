<?php
$base = url('/e/' . $company['slug']);
$hero = !empty($company['hero_image']) ? upload($company['hero_image']) : url('/assets/img/industry/hero-taller.jpg');
$heroWebp = !empty($company['hero_image']) ? '' : url('/assets/img/industry/hero-taller.webp');
page('preloadImage', $heroWebp !== ''
    ? ['src' => url('/assets/img/industry/hero-taller-md.webp'),
       'srcset' => url('/assets/img/industry/hero-taller-sm.webp') . ' 640w, ' . url('/assets/img/industry/hero-taller-md.webp') . ' 1200w, ' . $heroWebp . ' 2200w']
    : ['src' => $hero]);
?>
<section class="hero">
  <div class="hero__media">
    <?php if ($heroWebp !== ''): ?>
      <picture>
        <source srcset="<?= e(url('/assets/img/industry/hero-taller-sm.webp')) ?> 640w, <?= e(url('/assets/img/industry/hero-taller-md.webp')) ?> 1200w, <?= e($heroWebp) ?> 2200w" sizes="100vw" type="image/webp">
        <img src="<?= e($hero) ?>" srcset="<?= e(url('/assets/img/industry/hero-taller-sm.jpg')) ?> 640w, <?= e(url('/assets/img/industry/hero-taller-md.jpg')) ?> 1200w, <?= e($hero) ?> 2200w"
             sizes="100vw" alt="Bodega de repuestos de <?= e($company['name']) ?>" width="2200" height="1400" fetchpriority="high" decoding="async">
      </picture>
    <?php else: ?>
      <img src="<?= e($hero) ?>" alt="<?= e($company['name']) ?>" width="2200" height="1400" fetchpriority="high" decoding="async">
    <?php endif; ?>
  </div>
  <div class="hero__scrim" aria-hidden="true"></div>
  <div class="hero__grid" aria-hidden="true"></div>
  <div class="wrap hero__in">
    <?php
    $lugar = array_values(array_unique(array_filter([(string) $company['city'], (string) $company['country']])));
    ?>
    <span class="kicker"><?= e(implode(' · ', $lugar)) ?><?= (int) $company['years_experience'] > 0 ? ' · ' . e($company['years_experience']) . ' años en la industria' : '' ?></span>
    <h1 class="h-hero hero__title hero__title--tagline assemble">
      <?php
      $t = trim((string) ($company['tagline'] ?: $company['name']));
      $words = preg_split('/\s+/', $t) ?: [$t];
      $lines = [];
      $chunk = (int) ceil(count($words) / min(3, max(1, (int) ceil(count($words) / 3))));
      foreach (array_chunk($words, max(2, $chunk)) as $c) { $lines[] = implode(' ', $c); }
      foreach (array_slice($lines, 0, 3) as $i => $line): ?>
        <span style="--d:<?= e(0.12 + $i * 0.13) ?>s"><?= e($line) ?></span>
      <?php endforeach; ?>
    </h1>

    <form class="searchbox" method="get" action="<?= e($base . '/catalogo') ?>" role="search">
      <label class="sr-only" for="q">Buscar en el catálogo</label>
      <input id="q" name="q" type="search" placeholder="Busque por código, nombre o medida…" autocomplete="off"
             role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="sug" aria-haspopup="listbox"
             data-suggest="<?= e($base . '/sugerencias') ?>">
      <button class="btn btn--accent" type="submit">Buscar</button>
      <div class="suggest" id="sug" role="listbox" aria-label="Sugerencias"></div>
    </form>

    <dl class="hero__meta">
      <div><dt>Productos en catálogo</dt><dd><span data-count="<?= e($productTotal) ?>">0</span></dd></div>
      <?php if ((int) $company['years_experience'] > 0): ?>
        <div><dt>Años en la industria</dt><dd><span data-count="<?= e($company['years_experience']) ?>">0</span></dd></div>
      <?php endif; ?>
      <div><dt>Cotizaciones atendidas</dt><dd><span data-count="<?= e($quoteTotal) ?>">0</span></dd></div>
    </dl>
  </div>
</section>

<?php if ($categories): ?>
<section class="section blueprint">
  <svg class="tracer" viewBox="0 0 1200 500" preserveAspectRatio="none" aria-hidden="true"><path d="M0 30 H300 V240 H900 V470 H1200"/></svg>
  <div class="wrap">
    <div class="section__head reveal">
      <div><span class="secnum">01/</span><h2 class="h2" style="margin-top:12px">Líneas que distribuimos</h2></div>
      <a class="linkarrow" href="<?= e($base . '/catalogo') ?>">Ver el catálogo completo <span aria-hidden="true">&rarr;</span></a>
    </div>
    <div class="grid12 reveal" data-d="1">
      <?php foreach (array_slice($categories, 0, 6) as $i => $c): ?>
        <?php $tex = ['acero-cepillado', 'pieza-torneada', 'placa-remachada', 'concreto', 'plano-tecnico', 'hero-taller'][$i % 6]; ?>
        <a class="cattile" style="grid-column:span <?= $i < 2 ? 6 : 4 ?>" href="<?= e($base . '/categoria/' . $c['slug']) ?>">
          <?php if ($c['image']): ?>
            <img src="<?= e(upload($c['image'])) ?>" alt="" aria-hidden="true" loading="lazy" decoding="async" width="1200" height="764">
          <?php else: ?>
            <picture>
              <source type="image/webp" sizes="(max-width:640px) 100vw, 50vw"
                      srcset="<?= e(url('/assets/img/industry/' . $tex . '-sm.webp')) ?> 640w, <?= e(url('/assets/img/industry/' . $tex . '-md.webp')) ?> 1200w">
              <img src="<?= e(url('/assets/img/industry/' . $tex . '-sm.jpg')) ?>"
                   srcset="<?= e(url('/assets/img/industry/' . $tex . '-sm.jpg')) ?> 640w, <?= e(url('/assets/img/industry/' . $tex . '-md.jpg')) ?> 1200w"
                   sizes="(max-width:640px) 100vw, 50vw" alt="" aria-hidden="true" loading="lazy" decoding="async" width="640" height="408">
            </picture>
          <?php endif; ?>
          <span class="cattile__n"><?= e(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) ?>/</span>
          <span class="cattile__name"><?= e($c['name']) ?></span>
          <span class="cattile__count"><?= e((int) $c['product_count']) ?> productos</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($featured): ?>
<section class="section section--tight" style="background:var(--paper-2)">
  <div class="wrap">
    <div class="section__head reveal">
      <div><span class="secnum">02/</span><h2 class="h2" style="margin-top:12px">Los más cotizados</h2></div>
      <p class="lead" style="max-width:34ch;margin:0">Agréguelos a su solicitud y reciba precio y tiempo de entrega el mismo día.</p>
    </div>
    <div class="pgrid reveal" data-d="1">
      <?php foreach (array_slice($featured, 0, 8) as $i => $p): ?>
        <?= \App\Core\View::partial('partials/pcard', ['p' => $p, 'company' => $company, 'eager' => $i < 3]) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section blueprint">
  <div class="wrap">
    <div class="grid12" style="align-items:center">
      <div style="grid-column:span 6" class="reveal">
        <span class="secnum">03/</span>
        <h2 class="h2" style="margin:12px 0 18px">Cotice en línea, sin llamadas ni cadenas de correo.</h2>
        <p class="lead"><?= e(str_limit((string) ($company['about'] ?: 'Arme su lista desde el catálogo, envíela con sus datos y siga el avance de su cotización desde un enlace propio.'), 320)) ?></p>
        <div class="flex flex-wrap" style="margin-top:26px">
          <a class="btn btn--accent" href="<?= e($base . '/catalogo') ?>">Explorar el catálogo <span class="arw" aria-hidden="true">&rarr;</span></a>
          <?php if ($company['whatsapp']): ?>
            <a class="btn btn--ghost" href="https://wa.me/<?= e($company['whatsapp']) ?>?text=<?= e(rawurlencode('Hola, necesito una cotización de ' . $company['name'] . '.')) ?>" target="_blank" rel="noopener">Escribir por WhatsApp</a>
          <?php endif; ?>
        </div>
      </div>
      <div style="grid-column:span 5/-1" class="reveal" data-d="2">
        <div class="brackets" style="padding:26px">
          <ol style="list-style:none;padding:0;margin:0;display:grid;gap:0">
            <?php foreach ([
              ['Busque por código o medida', 'El buscador entiende códigos parciales y descripciones.'],
              ['Agregue a su cotización', 'Indique cantidades y notas por producto.'],
              ['Envíe sus datos', 'Nombre, empresa, NIT y teléfono. Sin crear cuenta.'],
              ['Siga su cotización', 'Recibirá un enlace para ver el estado y aprobarla.'],
            ] as $i => $row): ?>
              <li style="display:flex;gap:16px;padding:16px 0;border-bottom:1px solid var(--line)">
                <span class="secnum" style="flex:none;padding-top:3px"><?= e(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) ?>/</span>
                <span><b style="font-family:var(--f-display);letter-spacing:-.01em"><?= e($row[0]) ?></b><br><span class="small muted"><?= e($row[1]) ?></span></span>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if ($brands): ?>
<section class="section section--tight">
  <div class="wrap">
    <div class="cota" style="margin-bottom:26px">Marcas que distribuimos</div>
    <div class="brandwall reveal">
      <?php foreach ($brands as $b): ?>
        <div>
          <?php if ($b['logo']): ?>
            <img src="<?= e(upload($b['logo'])) ?>" alt="<?= e($b['name']) ?>" loading="lazy" decoding="async" width="150" height="44">
          <?php else: ?>
            <span><?= e($b['name']) ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
