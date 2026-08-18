<?php
require __DIR__ . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }

// Single-league mode: the homepage is the league's landing page.
$league = the_league();
$isPublic = $league && $league['status'] === 'active' && $league['visibility'] === 'public';

$theme = Theme::resolve($isPublic && $league['theme_id'] ? (int)$league['theme_id'] : (int)Settings::get('default_theme_id', 1));
$pageTitle = ($isPublic ? $league['name'] : (Settings::get('seo_title') ?: Settings::get('site_name', 'Liga de Fútbol')));
$metaDesc  = $isPublic ? ($league['seo_description'] ?: $league['description']) : Settings::get('seo_description', '');
$activeNav = 'inicio';

$tournaments = $isPublic
    ? Database::all("SELECT * FROM tournaments WHERE league_id = ? AND status <> 'draft' ORDER BY created_at ASC", [$league['id']])
    : [];

// Banner overlay for the hero (reuse the league's settings).
if ($isPublic) {
    $ovColor = $league['banner_overlay'] ?: '#000000';
    [$or,$og,$ob] = Color::toRgb($ovColor);
    $ovAlpha = max(0, min(90, (int)$league['overlay_intensity'])) / 100;
    $objPos = ['top'=>'center top','center'=>'center center','bottom'=>'center bottom'][$league['banner_position']] ?? 'center';
}

require __DIR__ . '/public/top.php';
?>
<?php if (!$isPublic): ?>
    <section class="hero"><div class="hero-pattern"></div><div class="hero-inner"><div class="container">
        <div class="eyebrow" style="color:#fff">⚽ <?= e(Settings::get('site_tagline', 'Fútbol')) ?></div>
        <h1><?= e(Settings::get('hero_title', 'Muy pronto')) ?></h1>
        <p class="hero-sub"><?= e(Settings::get('hero_subtitle', 'La liga aún no está publicada. Vuelve pronto.')) ?></p>
    </div></div></section>
<?php else: ?>
    <!-- Hero: league banner + name + short info -->
    <section class="hero">
        <?php if ($league['banner']): ?>
            <img class="hero-bg" src="<?= e(base_url($league['banner'])) ?>" alt="" style="object-position:<?= e($objPos) ?>">
            <div class="hero-overlay" style="background:linear-gradient(to top, rgba(<?= "$or,$og,$ob" ?>,<?= min(0.92, $ovAlpha + 0.28) ?>), rgba(<?= "$or,$og,$ob" ?>,<?= $ovAlpha ?>))"></div>
        <?php else: ?>
            <div class="hero-pattern"></div>
        <?php endif; ?>
        <div class="hero-inner"><div class="container">
            <div class="flex items-center gap-2 wrap">
                <?php if ($league['logo']): ?><img class="hero-league-logo" src="<?= e(base_url($league['logo'])) ?>" alt=""><?php endif; ?>
                <div>
                    <div class="eyebrow" style="color:#fff"><span class="badge badge-accent">DEMO</span> <?= e(Settings::get('site_tagline', 'Fútbol 5')) ?></div>
                    <h1><?= e($league['name']) ?></h1>
                    <?php if ($league['description']): ?><p class="hero-sub"><?= e($league['description']) ?></p><?php endif; ?>
                </div>
            </div>
        </div></div>
    </section>

    <?php if (!empty($league['info'])): ?>
    <section class="section" style="padding-bottom:0">
        <div class="container">
            <div class="card card-pad-lg"><div class="rich"><?= $league['info'] /* admin-managed HTML */ ?></div></div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Tournament cards -->
    <section class="section" id="torneos">
        <div class="container">
            <div class="section-head"><div><div class="eyebrow">Competiciones</div><h2>Nuestros torneos</h2></div></div>
            <?php if (!$tournaments): ?>
                <div class="empty-state card"><div class="es-icon">🎯</div><h3>Muy pronto</h3><p>Aún no hay torneos publicados.</p></div>
            <?php else: ?>
                <div class="tourney-grid">
                    <?php foreach ($tournaments as $t):
                        $url = base_url('liga.php?slug=' . urlencode($league['slug']) . '&t=' . (int)$t['id']);
                        $teams = (int)Database::scalar("SELECT COUNT(*) FROM tournament_teams WHERE tournament_id = ?", [$t['id']]);
                    ?>
                        <article class="card tourney-card">
                            <a class="tc-media" href="<?= e($url) ?>" aria-label="<?= e($t['name']) ?>">
                                <?php if (!empty($t['banner'])): ?>
                                    <img src="<?= e(base_url($t['banner'])) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <div class="hero-pattern" style="position:absolute;inset:0"></div>
                                <?php endif; ?>
                                <span class="tc-badge"><?= $teams ?> equipos</span>
                            </a>
                            <div class="tc-body">
                                <h3 class="tc-title"><?= e($t['name']) ?></h3>
                                <?php if (!empty($t['description'])): ?><p class="tc-desc"><?= e($t['description']) ?></p><?php endif; ?>
                                <a class="btn btn-block mt-2" href="<?= e($url) ?>">Ver torneo →</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/public/bottom.php'; ?>
