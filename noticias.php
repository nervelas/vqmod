<?php
require __DIR__ . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }

$league = the_league();
$theme = Theme::resolve($league && $league['theme_id'] ? (int)$league['theme_id'] : (int)Settings::get('default_theme_id', 1));
$pageTitle = 'Noticias · ' . ($league['name'] ?? Settings::get('site_name', 'Liga de Fútbol'));
$metaDesc  = 'Noticias de ' . ($league['name'] ?? '') . '.';
$activeNav = 'noticias';

$news = Settings::bool('module_news', true)
    ? Database::all("SELECT * FROM news WHERE status = 'published' ORDER BY COALESCE(published_at, created_at) DESC, id DESC")
    : [];

require __DIR__ . '/public/top.php';
?>
<section class="section">
    <div class="container">
        <nav class="crumbs"><a href="<?= e(base_url('index.php')) ?>">La Liga</a><span>›</span><span>Noticias</span></nav>
        <div class="section-head"><div><div class="eyebrow">Actualidad</div><h1>Noticias</h1></div></div>

        <?php if (!$news): ?>
            <div class="empty-state card"><div class="es-icon">📰</div><h3>Sin noticias</h3><p>Aún no hay noticias publicadas.</p></div>
        <?php else: ?>
            <div class="tourney-grid">
                <?php foreach ($news as $n):
                    $url = base_url('noticia.php?slug=' . urlencode($n['slug']));
                ?>
                    <article class="card tourney-card">
                        <?php if (!empty($n['image'])): ?>
                            <a class="tc-media" href="<?= e($url) ?>"><img src="<?= e(base_url($n['image'])) ?>" alt="" loading="lazy"></a>
                        <?php endif; ?>
                        <div class="tc-body">
                            <div class="subtle" style="font-size:.78rem"><?= e(fmt_date($n['published_at'] ?: $n['created_at'], 'd/m/Y')) ?></div>
                            <h3 class="tc-title" style="margin-top:.2rem"><?= e($n['title']) ?></h3>
                            <?php if (!empty($n['excerpt']) || !empty($n['body'])): ?>
                                <p class="tc-desc"><?= e($n['excerpt'] ?: mb_substr(strip_tags((string)$n['body']), 0, 130)) ?></p>
                            <?php endif; ?>
                            <a class="btn btn-ghost btn-block mt-2" href="<?= e($url) ?>">Leer más →</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/public/bottom.php'; ?>
