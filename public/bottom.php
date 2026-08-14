<?php if (!defined('FL_APP')) { exit; }
$siteName = Settings::get('site_name', 'Ligas de Fútbol');
$footerText = Settings::get('footer_text', '');
$institutional = Settings::get('institutional', '');
$contact = Settings::get('contact_email', '');
?>
</main>
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <div class="brand" style="margin-bottom:.6rem"><span class="brand-mark">⚽</span> <span><?= e($siteName) ?></span></div>
                <?php if ($institutional): ?><p class="muted" style="max-width:38ch"><?= e($institutional) ?></p><?php endif; ?>
            </div>
            <div>
                <h4>Navegación</h4>
                <a href="<?= e(base_url('index.php')) ?>">Inicio</a>
                <a href="<?= e(base_url('index.php#ligas')) ?>">Ligas</a>
                <a href="<?= e(base_url('index.php#noticias')) ?>">Noticias</a>
            </div>
            <div>
                <h4>Contacto</h4>
                <?php if ($contact): ?><a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a><?php endif; ?>
                <a href="<?= e(base_url('admin/login.php')) ?>">Acceso administración</a>
            </div>
        </div>
        <div class="footer-bottom"><?= e($footerText ?: ('© ' . date('Y') . ' ' . $siteName)) ?></div>
    </div>
</footer>
<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
</body>
</html>
