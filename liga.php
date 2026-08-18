<?php
// May be included from index.php (single-league home) after bootstrap already
// ran — guard so the app is bootstrapped exactly once.
if (!defined('FL_APP')) { require __DIR__ . '/app/bootstrap.php'; }
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }

$slug = str_input('slug');
$league = $slug ? Database::one("SELECT * FROM leagues WHERE slug = ? AND status='active'", [$slug]) : null;
if (!$league || $league['visibility'] !== 'public') {
    http_response_code(404);
    $theme = Theme::resolve((int)Settings::get('default_theme_id', 1));
    $pageTitle = 'No encontrada';
    require __DIR__ . '/public/top.php';
    echo '<section class="section"><div class="container"><div class="empty-state card"><div class="es-icon">🔍</div><h2>Liga no encontrada</h2><p>La liga solicitada no existe o no está disponible.</p><a class="btn" href="' . e(base_url('index.php')) . '">Volver al inicio</a></div></div></section>';
    require __DIR__ . '/public/bottom.php';
    exit;
}

$theme = Theme::resolve($league['theme_id'] ? (int)$league['theme_id'] : (int)Settings::get('default_theme_id', 1));
$tournaments = Database::all("SELECT * FROM tournaments WHERE league_id = ? ORDER BY created_at DESC", [$league['id']]);
$tournamentId = int_input('t') ?: ($tournaments[0]['id'] ?? null);
$tournament = null;
foreach ($tournaments as $tr) { if ((int)$tr['id'] === (int)$tournamentId) { $tournament = $tr; break; } }

$modScorers = Settings::bool('module_scorers');
$modDisc    = Settings::bool('module_discipline');
$modRules   = Settings::bool('module_rules');
$modNews    = Settings::bool('module_news');

$pageTitle = ($league['seo_title'] ?: $league['name']) . ' · ' . Settings::get('site_name', '');
$metaDesc  = $league['seo_description'] ?: $league['description'];

// Overlay style for the banner
$ovColor = $league['banner_overlay'] ?: '#000000';
[$or,$og,$ob] = Color::toRgb($ovColor);
$ovAlpha = max(0, min(90, (int)$league['overlay_intensity'])) / 100;
$objPos = ['top'=>'center top','center'=>'center center','bottom'=>'center bottom'][$league['banner_position']] ?? 'center';

$activeNav = 'liga';
require __DIR__ . '/public/top.php';
$heroBanner = ($tournament && !empty($tournament['banner'])) ? $tournament['banner'] : $league['banner'];
?>
<!-- Tournament header: ONLY the banner. -->
<section class="banner-head">
    <?php if ($heroBanner): ?>
        <img src="<?= e(base_url($heroBanner)) ?>" alt="" style="object-position:<?= e($objPos) ?>">
    <?php else: ?>
        <div class="hero-pattern"></div>
    <?php endif; ?>
</section>

<section class="section" style="padding-top:1.5rem">
    <div class="container">
        <div class="page-head" style="margin-bottom:1.25rem">
            <h1><?= e($tournament ? $tournament['name'] : $league['name']) ?></h1>
        </div>
        <?php if (count($tournaments) > 1): ?>
            <form method="get" class="mb-3" style="max-width:420px">
                <input type="hidden" name="slug" value="<?= e($league['slug']) ?>">
                <div class="field" style="margin:0">
                    <label for="t">Torneo</label>
                    <select class="select" id="t" name="t" onchange="this.form.submit()">
                        <?php foreach ($tournaments as $tr): ?><option value="<?= (int)$tr['id'] ?>"<?= selected($tr['id'], $tournamentId) ?>><?= e($tr['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </form>
        <?php endif; ?>

        <?php if (!$tournament): ?>
            <div class="empty-state card"><div class="es-icon">🎯</div><h2>Muy pronto</h2><p>Esta liga aún no tiene torneos publicados.</p></div>
        <?php else: ?>
        <div data-tabs="#liga-panels">
            <div class="tabs">
                <button class="tab active" data-target="#p-tabla">Tabla</button>
                <button class="tab" data-target="#p-calendario">Calendario</button>
                <?php if ($modScorers): ?><button class="tab" data-target="#p-goleadores">Goleadores</button><?php endif; ?>
                <?php if ($modDisc): ?><button class="tab" data-target="#p-disciplina">Disciplina</button><?php endif; ?>
                <button class="tab" data-target="#p-equipos">Equipos</button>
                <?php if ($modRules): ?><button class="tab" data-target="#p-reglamento">Reglamento</button><?php endif; ?>
            </div>
            <div id="liga-panels">
                <!-- Standings -->
                <div class="tab-panel" id="p-tabla">
                    <?php
                    $table = Standings::compute($tournamentId);
                    if (!$table): ?>
                        <div class="empty-state card"><div class="es-icon">📊</div><h3>Sin datos</h3><p>La tabla se genera al cargar resultados.</p></div>
                    <?php else: ?>
                        <div class="table-wrap"><table class="data standings">
                            <thead><tr><th class="num">#</th><th>Equipo</th><th class="num">PJ</th><th class="num">PG</th><th class="num">PE</th><th class="num">PP</th><th class="num">GF</th><th class="num">GC</th><th class="num">DG</th><th class="num">PTS</th></tr></thead>
                            <tbody>
                            <?php foreach ($table as $r): ?>
                                <tr>
                                    <td class="num"><span class="pos-badge"><?= (int)$r['pos'] ?></span></td>
                                    <td class="team-cell"><?= media_thumb($r['logo'], $r['name']) ?> <?= e($r['name']) ?></td>
                                    <td class="num"><?= (int)$r['pj'] ?></td><td class="num"><?= (int)$r['pg'] ?></td><td class="num"><?= (int)$r['pe'] ?></td><td class="num"><?= (int)$r['pp'] ?></td>
                                    <td class="num"><?= (int)$r['gf'] ?></td><td class="num"><?= (int)$r['gc'] ?></td><td class="num"><?= (int)$r['dg'] ?></td><td class="num pts"><?= (int)$r['pts'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table></div>
                    <?php endif; ?>
                </div>

                <!-- Calendar / results -->
                <div class="tab-panel hidden" id="p-calendario">
                    <?php
                    $mds = Database::all("SELECT * FROM matchdays WHERE tournament_id=? ORDER BY number", [$tournamentId]);
                    if (!$mds): ?>
                        <div class="empty-state card"><div class="es-icon">📅</div><h3>Sin calendario</h3><p>Aún no se ha publicado el calendario.</p></div>
                    <?php else: foreach ($mds as $md):
                        $ms = Database::all("SELECT m.*, h.name hn, h.logo hl, a.name an, a.logo al FROM matches m LEFT JOIN teams h ON h.id=m.home_team_id LEFT JOIN teams a ON a.id=m.away_team_id WHERE m.matchday_id=? ORDER BY m.match_date, m.match_time, m.id", [$md['id']]); ?>
                        <div class="card mb-2 jornada">
                            <div class="jornada-head"><strong>Jornada <?= (int)$md['number'] ?></strong><span class="muted"><?= e(fmt_date($md['match_date'])) ?></span></div>
                            <?php foreach ($ms as $mt):
                                $played = $mt['home_goals'] !== null && $mt['away_goals'] !== null;
                                $hg = (int)$mt['home_goals']; $ag = (int)$mt['away_goals'];
                            ?>
                                <div class="fixture">
                                    <div class="fx-row<?= $played && $hg > $ag ? ' fx-win' : '' ?>">
                                        <span class="fx-team"><?= media_thumb($mt['hl'], $mt['hn'] ?? '') ?><span class="fx-name"><?= e($mt['hn'] ?? '—') ?></span></span>
                                        <span class="fx-goals"><?= $played ? $hg : '<span class="fx-dash">–</span>' ?></span>
                                    </div>
                                    <div class="fx-row<?= $played && $ag > $hg ? ' fx-win' : '' ?>">
                                        <span class="fx-team"><?= media_thumb($mt['al'], $mt['an'] ?? '') ?><span class="fx-name"><?= e($mt['an'] ?? '—') ?></span></span>
                                        <span class="fx-goals"><?= $played ? $ag : '<span class="fx-dash">–</span>' ?></span>
                                    </div>
                                    <div class="fx-meta">
                                        <span><?= $played ? 'Final' : ('🕒 ' . e(fmt_time($mt['match_time']))) ?><?php if ($mt['venue']): ?> · <?= e($mt['venue']) ?><?php endif; ?></span>
                                        <?php if (!empty($mt['referee_report']) && !empty($mt['referee_report_public'])): ?>
                                            <img class="liga-acta-thumb" src="<?= e(base_url($mt['referee_report'])) ?>" data-full="<?= e(base_url($mt['referee_report'])) ?>" alt="Acta arbitral" title="Ver acta arbitral">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <?php if ($modScorers): ?>
                <div class="tab-panel hidden" id="p-goleadores">
                    <?php $sc = Standings::scorers($tournamentId, 50); if (!$sc): ?>
                        <div class="empty-state card"><div class="es-icon">⚽</div><h3>Sin goles</h3></div>
                    <?php else: ?>
                        <div class="table-wrap"><table class="data"><thead><tr><th class="num">#</th><th>Jugador</th><th>Equipo</th><th class="num">Goles</th></tr></thead><tbody>
                        <?php foreach ($sc as $i=>$s): ?><tr><td class="num"><span class="pos-badge"><?= $i+1 ?></span></td><td class="team-cell"><?= media_thumb($s['photo'], player_name($s), 'avatar', true) ?> <?= e(player_name($s)) ?></td><td><?= e($s['team_short'] ?: $s['team_name'] ?: '—') ?></td><td class="num pts"><?= (int)$s['goals'] ?></td></tr><?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($modDisc): ?>
                <div class="tab-panel hidden" id="p-disciplina">
                    <?php $tl = Standings::discipline($tournamentId, 100); if (!$tl): ?>
                        <div class="empty-state card"><div class="es-icon">🟨</div><h3>Sin tarjetas</h3></div>
                    <?php else: ?>
                        <div class="table-wrap"><table class="data"><thead><tr><th>Jugador</th><th>Equipo</th><th class="num">🟨</th><th class="num">🟥</th></tr></thead><tbody>
                        <?php foreach ($tl as $r): ?><tr><td><?= e(player_name($r)) ?></td><td><?= e($r['team_short'] ?: $r['team_name'] ?: '—') ?></td><td class="num"><?= (int)$r['yellows'] + (int)$r['double_yellows']*2 ?></td><td class="num"><?= (int)$r['reds'] + (int)$r['double_yellows'] ?></td></tr><?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="tab-panel hidden" id="p-equipos">
                    <?php $tt = Database::all("SELECT t.* FROM tournament_teams tt JOIN teams t ON t.id=tt.team_id WHERE tt.tournament_id=? ORDER BY t.name", [$tournamentId]);
                    if (!$tt) { $tt = Database::all("SELECT * FROM teams WHERE league_id=? AND status='active' ORDER BY name", [$league['id']]); }
                    if (!$tt): ?><div class="empty-state card"><div class="es-icon">🛡️</div><h3>Sin equipos</h3></div>
                    <?php else: ?>
                        <div class="league-grid">
                        <?php foreach ($tt as $t): ?>
                            <div class="card card-hover flex items-center gap-2 reveal"><?= media_thumb($t['logo'], $t['name'], 'team-logo avatar-lg') ?><div><strong><?= e($t['name']) ?></strong><?php if ($t['short_name']): ?><div class="muted" style="font-size:.82rem"><?= e($t['short_name']) ?></div><?php endif; ?></div></div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($modRules): ?>
                <div class="tab-panel hidden" id="p-reglamento">
                    <?php $rules = Database::all("SELECT * FROM rules WHERE (league_id=? OR league_id IS NULL) AND (tournament_id=? OR tournament_id IS NULL) ORDER BY category, sort_order, title", [$league['id'], $tournamentId]);
                    if (!$rules): ?><div class="empty-state card"><div class="es-icon">📋</div><h3>Sin reglamento</h3></div>
                    <?php else: $lastCat=null; foreach ($rules as $ru): if ($ru['category']!==$lastCat){ if($lastCat!==null) echo '</div>'; echo '<h3 class="mt-3">'.e($ru['category'] ?: 'General').'</h3><div class="card">'; $lastCat=$ru['category']; } ?>
                        <div style="padding:.6rem 0;border-bottom:1px solid var(--c-border)"><strong><?= e($ru['title']) ?></strong><?php if ($ru['body']): ?><p class="muted" style="margin:.3rem 0 0"><?= nl2br(e($ru['body'])) ?></p><?php endif; ?></div>
                    <?php endforeach; echo '</div>'; endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php
// ---- News for this league (published) ----------------------------------
if ($modNews):
    $ligaNews = Database::all(
        "SELECT * FROM news
         WHERE status = 'published' AND (league_id = ? OR league_id IS NULL)
         ORDER BY COALESCE(published_at, created_at) DESC, id DESC LIMIT 6",
        [$league['id']]
    );
    if ($ligaNews):
?>
<section class="section" id="noticias" style="background:var(--c-surface)">
    <div class="container">
        <div class="section-head"><div><div class="eyebrow">Actualidad</div><h2>Noticias</h2></div></div>
        <div class="league-grid">
            <?php foreach ($ligaNews as $n): ?>
                <article class="card card-hover reveal" style="padding:0;overflow:hidden">
                    <?php if (!empty($n['image'])): ?><div class="lc-banner" style="height:150px"><img src="<?= e(base_url($n['image'])) ?>" alt=""></div><?php endif; ?>
                    <div style="padding:1.25rem">
                        <h4 style="margin:.2rem 0 .3rem"><?= e($n['title']) ?></h4>
                        <p class="muted" style="font-size:.9rem"><?= e($n['excerpt'] ?: mb_substr(strip_tags((string)$n['body']), 0, 120)) ?></p>
                        <div class="subtle" style="font-size:.78rem"><?= e(fmt_date($n['published_at'] ?: $n['created_at'])) ?></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; endif; ?>

<div id="acta-lightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:200;align-items:center;justify-content:center;padding:1.5rem;cursor:zoom-out">
    <img id="acta-lightbox-img" src="" alt="Acta arbitral" style="max-width:100%;max-height:100%;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,.6)">
</div>
<script>
(function () {
    var lb = document.getElementById('acta-lightbox'), img = document.getElementById('acta-lightbox-img');
    document.querySelectorAll('.liga-acta-thumb').forEach(function (t) {
        t.addEventListener('click', function () { img.src = t.getAttribute('data-full'); lb.style.display = 'flex'; });
    });
    if (lb) {
        lb.addEventListener('click', function () { lb.style.display = 'none'; img.src = ''; });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { lb.style.display = 'none'; img.src = ''; } });
    }
})();
</script>
<?php require __DIR__ . '/public/bottom.php'; ?>
