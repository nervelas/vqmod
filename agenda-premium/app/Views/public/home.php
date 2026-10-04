<?php
/** @var array $biz @var array $events @var array $hosts @var array $reviews @var string $hero @var bool $heroIsUpload */
$terms = $biz['terms_label'];
?>
<?php partial('public/_header', ['biz' => $biz, 'nav' => 'home']); ?>
<main id="main">
<section class="pub-hero guilloche">
  <div class="pub-hero-in">
    <div class="pub-hero-copy">
      <p class="eyebrow">Reservas en línea</p>
      <h1 class="pub-hero-title serif"><?= e($biz['name']) ?></h1>
      <?php if ($biz['tagline'] !== '') : ?><p class="pub-hero-tag"><?= e($biz['tagline']) ?></p><?php endif; ?>
      <?php if (trim($biz['about']) !== '') : ?><div class="pub-about prose"><?= \App\Core\Str::richText(\App\Core\Str::truncate($biz['about'], 420)) ?></div><?php endif; ?>
      <div class="pub-hero-cta row row-wrap">
        <a class="btn btn-gold btn-lg" href="#eventos">Reservar mi <?= e($terms) ?></a>
        <?php if ($biz['wa_link'] !== '') : ?>
          <a class="btn btn-outline btn-lg" href="<?= e($biz['wa_link']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('whatsapp') ?> Escribir por WhatsApp</a>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($hero !== '') : ?>
    <div class="pub-hero-art" aria-hidden="true">
      <div class="pub-bezel">
        <img src="<?= e($hero) ?>" alt="" width="520" height="520" decoding="async" fetchpriority="high">
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<section class="pub-section" id="eventos" aria-labelledby="h-eventos">
  <div class="container">
    <div class="pub-head">
      <p class="eyebrow">Servicios</p>
      <h2 id="h-eventos" class="serif">Elige cómo quieres que te atendamos</h2>
    </div>
    <?php if (!$events) : ?>
      <div class="empty">
        <?= icon('calendar') ?>
        <p class="empty-title">Aún no hay horarios para reservar en línea</p>
        <p class="empty-text">Estamos preparando la agenda. Mientras tanto puedes escribirnos y con gusto te ayudamos.</p>
        <?php if ($biz['wa_link'] !== '') : ?><a class="btn btn-gold" href="<?= e($biz['wa_link']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('whatsapp') ?> Escribir por WhatsApp</a><?php endif; ?>
      </div>
    <?php else : ?>
    <ul class="pub-events">
      <?php foreach ($events as $ev) : ?>
      <li class="ev-card" <?= vars(['--ev' => $ev['_color']]) ?>>
        <a class="ev-link" href="<?= e(url('/e/' . $ev['slug'])) ?>" aria-label="<?= e('Reservar: ' . $ev['name']) ?>">
          <span class="ev-dial" aria-hidden="true"><svg viewBox="0 0 48 48" width="48" height="48"><circle cx="24" cy="24" r="21" class="ev-d-ring"/><circle cx="24" cy="24" r="16" class="ev-d-in"/><path d="M24 24V12M24 24l8 5" class="ev-d-hand"/><circle cx="24" cy="24" r="2" class="ev-d-hub"/></svg></span>
          <span class="ev-body">
            <span class="ev-name serif"><?= e($ev['name']) ?></span>
            <?php if (trim((string) ($ev['description'] ?? '')) !== '') : ?>
              <span class="ev-desc"><?= e(\App\Core\Str::truncate(trim(strip_tags(str_replace(['**', '*'], '', (string) $ev['description']))), 150)) ?></span>
            <?php endif; ?>
            <span class="ev-meta">
              <span class="ev-chip"><?= icon('clock') ?> <?= e(implode(' · ', array_map(static fn($d) => \App\Core\Fmt::duration((int) $d), $ev['_durations']))) ?></span>
              <span class="ev-chip"><?= icon($ev['_mode_icon']) ?> <?= e($ev['_mode_label']) ?></span>
              <span class="ev-chip ev-price mono"><?= e($ev['_price_text']) ?></span>
              <?php if ((string) $ev['kind'] === 'group') : ?><span class="ev-chip"><?= icon('users') ?> Grupal</span><?php endif; ?>
            </span>
          </span>
          <span class="ev-go btn btn-gold btn-sm">Reservar <?= icon('arrow-right') ?></span>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</section>

<?php if ($hosts) : ?>
<section class="pub-section pub-alt" aria-labelledby="h-equipo">
  <div class="container">
    <div class="pub-head">
      <p class="eyebrow">Equipo</p>
      <h2 id="h-equipo" class="serif">Las personas que te atienden</h2>
    </div>
    <?php partial('public/_hosts', ['hosts' => $hosts]); ?>
  </div>
</section>
<?php endif; ?>

<?php if ($reviews) : ?>
<section class="pub-section" aria-labelledby="h-resenas">
  <div class="container">
    <div class="pub-head">
      <p class="eyebrow">Opiniones</p>
      <h2 id="h-resenas" class="serif">Lo que dicen quienes ya nos visitaron</h2>
    </div>
    <?php partial('public/_reviews', ['reviews' => $reviews]); ?>
  </div>
</section>
<?php endif; ?>

<section class="pub-section pub-alt" id="contacto" aria-labelledby="h-contacto">
  <div class="container pub-contact">
    <div>
      <p class="eyebrow">Contacto</p>
      <h2 id="h-contacto" class="serif">Estamos para ayudarte</h2>
      <p class="muted">Si prefieres hablar con una persona, aquí nos encuentras.</p>
    </div>
    <ul class="pub-contact-list">
      <?php if ($biz['wa_link'] !== '') : ?><li><?= icon('whatsapp') ?><span><strong>WhatsApp</strong><a href="<?= e($biz['wa_link']) ?>" target="_blank" rel="noopener noreferrer"><?= e($biz['wa_display']) ?></a></span></li><?php endif; ?>
      <?php if ($biz['phone_display'] !== '') : ?><li><?= icon('phone') ?><span><strong>Teléfono</strong><a href="tel:<?= e($biz['phone_tel']) ?>"><?= e($biz['phone_display']) ?></a></span></li><?php endif; ?>
      <?php if ($biz['email'] !== '') : ?><li><?= icon('mail') ?><span><strong>Correo</strong><a href="mailto:<?= e($biz['email']) ?>"><?= e($biz['email']) ?></a></span></li><?php endif; ?>
      <?php if ($biz['address'] !== '') : ?><li><?= icon('map-pin') ?><span><strong>Dirección</strong><span><?= nl2br(e($biz['address']), false) ?></span><?php if ($biz['map_url'] !== '') : ?><a href="<?= e($biz['map_url']) ?>" target="_blank" rel="noopener noreferrer">Ver en el mapa</a><?php endif; ?></span></li><?php endif; ?>
      <?php if ($biz['website'] !== '') : ?><li><?= icon('globe') ?><span><strong>Sitio web</strong><a href="<?= e($biz['website']) ?>" target="_blank" rel="noopener noreferrer"><?= e(preg_replace('#^https?://#', '', rtrim($biz['website'], '/'))) ?></a></span></li><?php endif; ?>
    </ul>
    <?php if ($biz['social']) : ?>
    <p class="pub-social"><?php foreach ($biz['social'] as $s) : ?><a class="chip" href="<?= e($s['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($s['label']) ?></a><?php endforeach; ?></p>
    <?php endif; ?>
  </div>
</section>
</main>
<?php partial('public/_footer', ['biz' => $biz]); ?>
