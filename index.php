<?php
require __DIR__ . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }

$theme = Theme::resolve((int)Settings::get('default_theme_id', 1));
$leagues = Database::all("SELECT * FROM leagues WHERE status='active' AND visibility='public' ORDER BY created_at DESC");
$news = Settings::bool('module_news')
    ? Database::all("SELECT n.*, l.name AS league_name, l.slug AS league_slug FROM news n LEFT JOIN leagues l ON l.id=n.league_id WHERE n.status='published' ORDER BY COALESCE(n.published_at, n.created_at) DESC LIMIT 6")
    : [];

$pageTitle = Settings::get('seo_title') ?: Settings::get('site_name', 'Ligas de Fútbol');
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
            <?php if ($leagues): ?>
                <div class="hero-actions"><a class="btn btn-lg" href="#ligas">Explorar ligas</a></div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section" id="ligas">
    <div class="container">
        <div class="section-head">
            <div><div class="eyebrow">Competiciones</div><h2>Ligas</h2></div>
        </div>
        <?php if (!$leagues): ?>
            <div class="empty-state card">
                <div class="es-icon">🏆</div>
                <h2>Muy pronto</h2>
                <p>Aún no hay ligas publicadas. Vuelve pronto para seguir todos los resultados.</p>
            </div>
        <?php else: ?>
            <div class="league-grid">
                <?php foreach ($leagues as $l): ?>
                    <a class="card league-card card-hover reveal" href="<?= e(base_url('liga.php?slug=' . urlencode($l['slug']))) ?>" style="text-decoration:none;color:inherit">
                        <div class="lc-banner">
                            <?php if ($l['banner']): ?><img src="<?= e(base_url($l['banner'])) ?>" alt="">
                            <?php else: ?><div class="hero-pattern" style="position:absolute"></div><?php endif; ?>
                        </div>
                        <div class="lc-body">
                            <?= media_thumb($l['logo'], $l['name'], 'lc-logo') ?>
                            <div>
                                <h4 style="margin:0"><?= e($l['name']) ?></h4>
                                <?php if ($l['description']): ?><div class="muted" style="font-size:.85rem"><?= e($l['description']) ?></div><?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($news): ?>
<section class="section" id="noticias" style="background:var(--c-surface)">
    <div class="container">
        <div class="section-head"><div><div class="eyebrow">Actualidad</div><h2>Noticias</h2></div></div>
        <div class="league-grid">
            <?php foreach ($news as $n): ?>
                <article class="card card-hover reveal" style="padding:0;overflow:hidden">
                    <?php if ($n['image']): ?><div class="lc-banner" style="height:150px"><img src="<?= e(base_url($n['image'])) ?>" alt=""></div><?php endif; ?>
                    <div style="padding:1.25rem">
                        <?php if ($n['league_name']): ?><span class="badge"><?= e($n['league_name']) ?></span><?php endif; ?>
                        <h4 style="margin:.5rem 0 .3rem"><?= e($n['title']) ?></h4>
                        <p class="muted" style="font-size:.9rem"><?= e($n['excerpt'] ?: mb_substr(strip_tags((string)$n['body']), 0, 120)) ?></p>
                        <div class="subtle" style="font-size:.78rem"><?= e(fmt_date($n['published_at'] ?: $n['created_at'])) ?></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/public/bottom.php'; ?>
