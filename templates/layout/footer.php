<?php
/** Public layout footer. */
if (!defined('BASE_PATH')) { exit; }
$menu = $menu ?? Content::menu();
$waEnabled = Settings::bool('whatsapp_enabled', true);
$waNumber  = Settings::get('whatsapp_number', '50222775656');
$waMsg     = Settings::get('whatsapp_message', 'Hola, deseo más información.');
$waText    = Settings::get('whatsapp_button_text', 'WhatsApp');
$year      = date('Y');
?>
</main>

<footer class="site-footer">
  <div class="container site-footer__grid">
    <div class="site-footer__col">
      <img src="<?= e(asset_url(Settings::get('logo_light', Settings::get('logo')))) ?>" alt="<?= e(Settings::get('site_name')) ?>" class="site-footer__logo">
      <p><?= e(Settings::get('footer_about')) ?></p>
      <div class="site-footer__social">
        <?php foreach (['facebook'=>'Facebook','instagram'=>'Instagram','tiktok'=>'TikTok','youtube'=>'YouTube'] as $net=>$label):
          $u = Settings::get($net); if ($u==='') continue; ?>
          <a href="<?= e($u) ?>" target="_blank" rel="noopener" aria-label="<?= e($label) ?>" class="i i-<?= e($net) ?>"></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="site-footer__col">
      <h4>Enlaces</h4>
      <ul>
        <?php foreach ($menu as $m): ?>
          <li><a href="<?= e(Content::url($m['url'])) ?>" <?= $m['target']==='_blank'?'target="_blank" rel="noopener"':'' ?>><?= e($m['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="site-footer__col">
      <h4>Plataformas</h4>
      <ul>
        <?php foreach (Content::platforms() as $pl): ?>
          <li><a href="<?= e(Content::url($pl['url'])) ?>" <?= $pl['target']==='_blank'?'target="_blank" rel="noopener"':'' ?>><?= e($pl['name']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="site-footer__col">
      <h4>Contacto</h4>
      <address>
        <p><span class="i i-pin"></span> <?= e(Settings::get('address')) ?></p>
        <p><a href="tel:+502<?= e(Settings::get('phone_link','50222775656')) ?>"><span class="i i-phone"></span> <?= e(Settings::get('phone')) ?></a></p>
        <p><a href="mailto:<?= e(Settings::get('email')) ?>"><span class="i i-mail"></span> <?= e(Settings::get('email')) ?></a></p>
      </address>
    </div>
  </div>
  <div class="site-footer__bottom">
    <div class="container">
      <p>&copy; <?= e($year) ?> <?= e(Settings::get('copyright')) ?></p>
    </div>
  </div>
</footer>

<?php if ($waEnabled && $waNumber !== ''): ?>
<a class="whatsapp-float" href="<?= e(whatsapp_link($waNumber, $waMsg)) ?>" target="_blank" rel="noopener" aria-label="<?= e($waText) ?>">
  <svg viewBox="0 0 32 32" width="30" height="30" aria-hidden="true"><path fill="currentColor" d="M16 .5C7.5.5.6 7.4.6 15.9c0 2.8.7 5.4 2 7.7L.5 31.5l8.1-2.1c2.2 1.2 4.8 1.9 7.4 1.9 8.5 0 15.4-6.9 15.4-15.4S24.5.5 16 .5zm0 28c-2.4 0-4.6-.6-6.5-1.8l-.5-.3-4.8 1.3 1.3-4.7-.3-.5c-1.3-2-2-4.3-2-6.8C2.9 8.7 8.7 2.9 16 2.9S29.1 8.7 29.1 16 23.3 28.5 16 28.5zm7.4-9.4c-.4-.2-2.4-1.2-2.7-1.3-.4-.1-.6-.2-.9.2s-1 1.3-1.3 1.5c-.2.2-.5.3-.9.1-.4-.2-1.7-.6-3.2-2-1.2-1-2-2.4-2.2-2.8-.2-.4 0-.6.2-.8l.6-.7c.2-.2.3-.4.4-.6.1-.2 0-.5 0-.7-.1-.2-.9-2.1-1.2-2.9-.3-.8-.6-.7-.9-.7h-.7c-.2 0-.6.1-1 .5-.3.4-1.3 1.3-1.3 3.1s1.3 3.6 1.5 3.9c.2.2 2.6 4 6.4 5.6.9.4 1.6.6 2.1.8.9.3 1.7.2 2.3.1.7-.1 2.4-1 2.7-1.9.3-.9.3-1.7.2-1.9-.1-.2-.3-.3-.7-.5z"/></svg>
</a>
<?php endif; ?>

<script src="<?= e(asset_url('assets/js/main.js')) ?>?v=1" defer></script>
</body>
</html>
