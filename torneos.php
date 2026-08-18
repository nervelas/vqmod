<?php
require __DIR__ . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }

$league = the_league();
$isPublic = $league && $league['status'] === 'active' && $league['visibility'] === 'public';

$theme = Theme::resolve($isPublic && $league['theme_id'] ? (int)$league['theme_id'] : (int)Settings::get('default_theme_id', 1));
$pageTitle = 'Torneos · ' . ($league['name'] ?? Settings::get('site_name', 'Liga de Fútbol'));
$metaDesc  = 'Todos los torneos de ' . ($league['name'] ?? '') . '.';
$activeNav = 'torneos';

$tournaments = $isPublic
    ? Database::all("SELECT * FROM tournaments WHERE league_id = ? AND status <> 'draft' ORDER BY created_at ASC", [$league['id']])
    : [];

require __DIR__ . '/public/top.php';
?>
<section class="section">
    <div class="container">
        <nav class="crumbs"><a href="<?= e(base_url('index.php')) ?>">La Liga</a><span>›</span><span>Torneos</span></nav>
        <div class="section-head"><div><div class="eyebrow">Competiciones</div><h1>Torneos</h1></div></div>

        <?php if (!$tournaments): ?>
            <div class="empty-state card"><div class="es-icon">🎯</div><h3>Muy pronto</h3><p>Aún no hay torneos publicados.</p></div>
        <?php else: ?>
            <div class="tourney-grid">
                <?php foreach ($tournaments as $t):
                    $url = base_url('liga.php?slug=' . urlencode($league['slug']) . '&t=' . (int)$t['id']);
                    $teams = (int)Database::scalar("SELECT COUNT(*) FROM tournament_teams WHERE tournament_id = ?", [$t['id']]);
                    $season = $t['season_id'] ? Database::scalar("SELECT name FROM seasons WHERE id = ?", [$t['season_id']]) : null;
                    $statusLabel = ['draft'=>'Borrador','active'=>'En curso','finished'=>'Finalizado'][$t['status']] ?? '';
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
                            <div class="tc-meta">
                                <?php if ($season): ?><span class="badge badge-muted"><?= e($season) ?></span><?php endif; ?>
                                <?php if ($statusLabel): ?><span class="badge <?= $t['status']==='active'?'badge-success':($t['status']==='finished'?'badge-accent':'badge-muted') ?>"><?= e($statusLabel) ?></span><?php endif; ?>
                            </div>
                            <?php if (!empty($t['description'])): ?><p class="tc-desc"><?= e($t['description']) ?></p><?php endif; ?>
                            <a class="btn btn-block mt-2" href="<?= e($url) ?>">Ver torneo →</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/public/bottom.php'; ?>
