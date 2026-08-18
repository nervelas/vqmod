<?php
require __DIR__ . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }

$slug = str_input('slug');
$n = $slug ? Database::one("SELECT * FROM news WHERE slug = ? AND status = 'published'", [$slug]) : null;

$league = the_league();
$theme = Theme::resolve($league && $league['theme_id'] ? (int)$league['theme_id'] : (int)Settings::get('default_theme_id', 1));
$activeNav = 'noticias';

if (!$n) {
    http_response_code(404);
    $pageTitle = 'Noticia no encontrada';
    require __DIR__ . '/public/top.php';
    echo '<section class="section"><div class="container"><div class="empty-state card"><div class="es-icon">🔍</div><h2>Noticia no encontrada</h2><a class="btn" href="' . e(base_url('noticias.php')) . '">Ver noticias</a></div></div></section>';
    require __DIR__ . '/public/bottom.php';
    exit;
}

$pageTitle = $n['title'];
$metaDesc  = $n['excerpt'] ?: mb_substr(strip_tags((string)$n['body']), 0, 155);
require __DIR__ . '/public/top.php';
?>
<section class="section">
    <div class="container" style="max-width:820px">
        <nav class="crumbs"><a href="<?= e(base_url('index.php')) ?>">La Liga</a><span>›</span><a href="<?= e(base_url('noticias.php')) ?>">Noticias</a><span>›</span><span><?= e($n['title']) ?></span></nav>
        <div class="subtle" style="font-size:.82rem"><?= e(fmt_date($n['published_at'] ?: $n['created_at'], 'd/m/Y')) ?></div>
        <h1 style="margin:.2rem 0 1rem"><?= e($n['title']) ?></h1>
        <?php if (!empty($n['image'])): ?>
            <img src="<?= e(base_url($n['image'])) ?>" alt="" style="width:100%;border-radius:16px;margin-bottom:1.5rem">
        <?php endif; ?>
        <div class="rich"><?= nl2br(e($n['body'])) ?></div>
    </div>
</section>
<?php require __DIR__ . '/public/bottom.php'; ?>
