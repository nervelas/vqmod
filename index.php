<?php
require __DIR__ . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }

// Single-league mode: the homepage IS the league. If the (only) league is
// published, send visitors straight to it. Otherwise show a "coming soon" page.
$league = the_league();
if ($league && $league['status'] === 'active' && $league['visibility'] === 'public') {
    redirect(base_url('liga.php?slug=' . urlencode($league['slug'])));
}

$theme = Theme::resolve((int)Settings::get('default_theme_id', 1));
$pageTitle = Settings::get('seo_title') ?: Settings::get('site_name', 'Liga de Fútbol');
$metaDesc  = Settings::get('seo_description', '');
$activeNav = 'inicio';
require __DIR__ . '/public/top.php';
?>
<section class="hero">
    <div class="hero-pattern"></div>
    <div class="hero-inner">
        <div class="container">
            <div class="eyebrow" style="color:#fff">⚽ <?= e(Settings::get('site_tagline', 'Fútbol')) ?></div>
            <h1><?= e(Settings::get('hero_title', 'Vive el fútbol')) ?></h1>
            <p class="hero-sub"><?= e(Settings::get('hero_subtitle', '')) ?></p>
        </div>
    </div>
</section>

<section class="section" id="ligas">
    <div class="container">
        <div class="empty-state card">
            <div class="es-icon">🏆</div>
            <h2>Muy pronto</h2>
            <p>La liga aún no está publicada. Vuelve pronto para seguir todos los resultados.</p>
        </div>
    </div>
</section>
<?php require __DIR__ . '/public/bottom.php'; ?>
