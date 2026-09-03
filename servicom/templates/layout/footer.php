<?php
declare(strict_types=1);

$footerMenu = array_values(array_filter(Content::menu(), static fn($m) => $m['location'] === 'footer'));
$services   = Content::services(6);
$siteName   = Settings::get('site_name', 'Servicom');
$phone      = Settings::get('phone');
$mail       = Settings::get('email');
$waLink     = whatsapp_link(Settings::get('whatsapp', $phone), Settings::get('whatsapp_message'));
$socials    = [
    'facebook'  => Settings::get('social_facebook'),
    'instagram' => Settings::get('social_instagram'),
    'linkedin'  => Settings::get('social_linkedin'),
    'youtube'   => Settings::get('social_youtube'),
    'tiktok'    => Settings::get('social_tiktok'),
    'x-social'  => Settings::get('social_x'),
];
$logoFoot = Theme::mode() === 'dark'
    ? Settings::get('logo_light', Settings::get('logo'))
    : Settings::get('logo', Settings::get('logo_light'));
$themes = Theme::all();
?>
</main>

<footer class="footer">
  <div class="wrap wrap-wide">
    <div class="footer__grid">
      <div>
        <a class="brand" href="<?= e(base('')) ?>" aria-label="<?= e($siteName) ?> — inicio">
          <?php if ($logoFoot !== ''): ?>
            <img src="<?= e(asset_url($logoFoot)) ?>" alt="<?= e($siteName) ?>" width="220" height="44" loading="lazy">
          <?php else: ?>
            <span class="brand__dot"></span><?= e($siteName) ?>
          <?php endif; ?>
        </a>
        <p class="footer__about"><?= e(Settings::get('footer_text')) ?></p>
        <div class="socials">
          <?php foreach ($socials as $key => $link):
              if (trim($link) === '') { continue; }
              $names = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'x-social' => 'X'];
          ?>
            <a class="social" href="<?= e($link) ?>" target="_blank" rel="noopener me" aria-label="<?= e($names[$key] ?? $key) ?>">
              <?= icon($key, 19) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <div>
        <h3 class="footer__title">Servicios</h3>
        <ul class="footer__list">
          <?php foreach ($services as $s): ?>
            <li><a href="<?= e(base('servicios/' . $s['slug'] . '/')) ?>"><?= icon((string) $s['icon'], 16) ?><?= e($s['short_title'] ?: $s['title']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div>
        <h3 class="footer__title">Enlaces</h3>
        <ul class="footer__list">
          <?php foreach ($footerMenu as $item): ?>
            <li><a href="<?= e(preg_match('#^https?://#i', (string) $item['url']) ? $item['url'] : base(ltrim((string) $item['url'], '/'))) ?>">
              <?= icon((string) $item['icon'], 16) ?><?= e($item['label']) ?></a></li>
          <?php endforeach; ?>
          <li><a href="<?= e(base('aviso-legal/')) ?>"><?= icon('escudo', 16) ?>Aviso legal y privacidad</a></li>
        </ul>
      </div>

      <div>
        <h3 class="footer__title">Contacto</h3>
        <ul class="footer__list">
          <?php if ($phone !== ''): ?>
            <li><a href="tel:+<?= e(digits($phone)) ?>"><?= icon('telefono', 16) ?><?= e($phone) ?></a></li>
          <?php endif; ?>
          <?php if (Settings::get('whatsapp') !== ''): ?>
            <li><a href="<?= e($waLink) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 16) ?>WhatsApp</a></li>
          <?php endif; ?>
          <?php if ($mail !== ''): ?>
            <li><a href="mailto:<?= e($mail) ?>"><?= icon('contacto', 16) ?><?= e($mail) ?></a></li>
          <?php endif; ?>
          <?php if (($addr = Settings::get('address_line')) !== ''): ?>
            <li><span class="footer__list-item" style="display:inline-flex;gap:.6rem;color:var(--muted);font-size:.94rem"><?= icon('ubicacion', 16) ?><?= e($addr) ?></span></li>
          <?php endif; ?>
          <?php if (($sched = Settings::get('schedule')) !== ''): ?>
            <li><span style="display:inline-flex;gap:.6rem;color:var(--muted);font-size:.94rem"><?= icon('reloj', 16) ?><?= e($sched) ?></span></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>

    <div class="footer__word" aria-hidden="true"><?= e(mb_strtoupper($siteName)) ?></div>

    <div class="footer__bar">
      <p>&copy; <span data-year><?= date('Y') ?></span> <?= e(Settings::get('copyright')) ?></p>
      <p><a href="<?= e(base('aviso-legal/')) ?>">Aviso legal</a> · <a href="<?= e(base('contacto/')) ?>">Contacto</a></p>
    </div>
  </div>
</footer>

<div class="dock">
  <?php if (Settings::bool('theme_allow_visitor_switch', true) && $themes !== []): ?>
  <div class="themer">
    <div class="themer__panel" role="dialog" aria-label="Elegir tema visual">
      <div class="themer__head"><strong>Tema visual</strong><span>8 estilos</span></div>
      <div class="themer__grid">
        <?php foreach ($themes as $t):
            $p = is_array($t['palette']) ? $t['palette'] : []; ?>
          <button class="themer__opt<?= ($t['theme_key'] === (Theme::active()['theme_key'] ?? '')) ? ' is-active' : '' ?>"
                  type="button" data-theme-key="<?= e($t['theme_key']) ?>" title="<?= e($t['description']) ?>">
            <span class="themer__swatch" style="background:<?= e($p['bg'] ?? '#000') ?>">
              <i style="background:<?= e($p['accent'] ?? '#fff') ?>"></i>
            </span>
            <span><b><?= e($t['name']) ?></b><small><?= $t['mode'] === 'dark' ? 'Oscuro' : 'Claro' ?></small></span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <button class="dock__btn" type="button" data-themer-toggle aria-expanded="false" aria-label="Cambiar tema visual" data-cursor="Temas">
      <?= icon('diseno', 22) ?>
    </button>
  </div>
  <?php endif; ?>

  <?php if (Settings::get('whatsapp') !== ''): ?>
    <a class="dock__btn dock__btn--wa" href="<?= e($waLink) ?>" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp" data-cursor="Escribir">
      <?= icon('whatsapp', 23) ?>
    </a>
  <?php endif; ?>

  <a class="dock__btn dock__btn--top" href="#inicio-header" aria-label="Volver arriba">
    <?= icon('flecha-arriba', 21) ?>
  </a>
</div>

<script>
window.SERVICOM = {
  cursor: <?= Settings::bool('fx_cursor', true) ? 'true' : 'false' ?>,
  parallax: <?= Settings::bool('fx_parallax', true) ? 'true' : 'false' ?>,
  base: <?= jsvalue(base('')) ?>
};
</script>
<script src="<?= e(base('assets/js/app.js?v=1.0.0')) ?>" defer></script>
<?php if (($ga = trim(Settings::get('google_analytics'))) !== '' && preg_match('/^(G-|UA-|GTM-)[A-Za-z0-9\-]+$/', $ga) === 1): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}
gtag('js',new Date());gtag('config','<?= e($ga) ?>');
</script>
<?php endif; ?>
</body>
</html>
