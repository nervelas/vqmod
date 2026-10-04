<?php /** @var array $biz @var array $host @var string $photo @var array $events @var array $reviews */ ?>
<?php partial('public/_header', ['biz' => $biz, 'nav' => 'team']); ?>
<main id="main" class="pub-page-main">
  <div class="container">
    <section class="host-hero">
      <span class="host-photo host-photo-lg">
        <?php if ($photo !== '') : ?><img src="<?= e($photo) ?>" alt="<?= e('Foto de ' . $host['name']) ?>" width="160" height="160" decoding="async"><?php else : ?><span class="host-initial serif" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $host['name'], 0, 1))) ?></span><?php endif; ?>
      </span>
      <div>
        <p class="eyebrow"><?= e($biz['host_label']) ?></p>
        <h1 class="serif"><?= e($host['name']) ?></h1>
        <?php if (!empty($host['title'])) : ?><p class="host-title-lg"><?= e($host['title']) ?></p><?php endif; ?>
        <?php if (trim((string) ($host['bio'] ?? '')) !== '') : ?><div class="prose"><?= \App\Core\Str::richText($host['bio']) ?></div><?php endif; ?>
      </div>
    </section>
    <section class="pub-section-in" aria-labelledby="h-ev">
      <h2 id="h-ev" class="serif">Reserva con <?= e(explode(' ', (string) $host['name'])[0]) ?></h2>
      <?php if (!$events) : ?>
        <div class="empty"><?= icon('calendar') ?><p class="empty-title">Por ahora no hay horarios disponibles para reservar en línea</p><a class="btn btn-gold" href="<?= e(url('/')) ?>">Ver otros servicios</a></div>
      <?php else : ?>
      <ul class="pub-events">
        <?php foreach ($events as $ev) : ?>
        <li class="ev-card" <?= vars(['--ev' => $ev['_color']]) ?>>
          <a class="ev-link" href="<?= e(url('/e/' . $ev['slug'], ['host' => $host['slug']])) ?>">
            <span class="ev-dial" aria-hidden="true"><svg viewBox="0 0 48 48" width="48" height="48"><circle cx="24" cy="24" r="21" class="ev-d-ring"/><circle cx="24" cy="24" r="16" class="ev-d-in"/><path d="M24 24V12M24 24l8 5" class="ev-d-hand"/><circle cx="24" cy="24" r="2" class="ev-d-hub"/></svg></span>
            <span class="ev-body">
              <span class="ev-name serif"><?= e($ev['name']) ?></span>
              <span class="ev-meta">
                <span class="ev-chip"><?= icon('clock') ?> <?= e(implode(' · ', array_map(static fn($d) => \App\Core\Fmt::duration((int) $d), $ev['_durations']))) ?></span>
                <span class="ev-chip"><?= icon($ev['_mode_icon']) ?> <?= e($ev['_mode_label']) ?></span>
                <span class="ev-chip ev-price mono"><?= e($ev['_price_text']) ?></span>
              </span>
            </span>
            <span class="ev-go btn btn-gold btn-sm">Reservar <?= icon('arrow-right') ?></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
    <?php if ($reviews) : ?>
    <section class="pub-section-in" aria-labelledby="h-rv"><h2 id="h-rv" class="serif">Opiniones</h2><?php partial('public/_reviews', ['reviews' => $reviews]); ?></section>
    <?php endif; ?>
  </div>
</main>
<?php partial('public/_footer', ['biz' => $biz]); ?>
