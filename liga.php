<?php
require __DIR__ . '/app/bootstrap.php';
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
$modFinal   = Settings::bool('module_final');
$modNews    = Settings::bool('module_news');

$pageTitle = ($league['seo_title'] ?: $league['name']) . ' · ' . Settings::get('site_name', '');
$metaDesc  = $league['seo_description'] ?: $league['description'];

// Overlay style for the banner
$ovColor = $league['banner_overlay'] ?: '#000000';
[$or,$og,$ob] = Color::toRgb($ovColor);
$ovAlpha = max(0, min(90, (int)$league['overlay_intensity'])) / 100;
$objPos = ['top'=>'center top','center'=>'center center','bottom'=>'center bottom'][$league['banner_position']] ?? 'center';

require __DIR__ . '/public/top.php';
?>
<section class="hero">
    <?php if ($league['banner']): ?>
        <img class="hero-bg" src="<?= e(base_url($league['banner'])) ?>" alt="" style="object-position:<?= e($objPos) ?>">
        <div class="hero-overlay" style="background:linear-gradient(to top, rgba(<?= "$or,$og,$ob" ?>,<?= min(0.92, $ovAlpha + 0.25) ?>), rgba(<?= "$or,$og,$ob" ?>,<?= $ovAlpha ?>))"></div>
    <?php else: ?>
        <div class="hero-pattern"></div>
    <?php endif; ?>
    <div class="hero-inner">
        <div class="container">
            <div class="flex items-center gap-2 wrap">
                <?php if ($league['logo']): ?><img class="hero-league-logo" src="<?= e(base_url($league['logo'])) ?>" alt=""><?php endif; ?>
                <div>
                    <h1><?= e($league['name']) ?></h1>
                    <?php if ($tournament): ?><p class="hero-sub"><?= e($tournament['name']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
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
        <?php else:
            $hasFinal = $modFinal && $tournament['final_phase'] !== 'none';
        ?>
        <div data-tabs="#liga-panels">
            <div class="tabs">
                <button class="tab active" data-target="#p-tabla">Tabla</button>
                <button class="tab" data-target="#p-calendario">Calendario</button>
                <?php if ($modScorers): ?><button class="tab" data-target="#p-goleadores">Goleadores</button><?php endif; ?>
                <?php if ($modDisc): ?><button class="tab" data-target="#p-disciplina">Disciplina</button><?php endif; ?>
                <button class="tab" data-target="#p-equipos">Equipos</button>
                <?php if ($modRules): ?><button class="tab" data-target="#p-reglamento">Reglamento</button><?php endif; ?>
                <?php if ($hasFinal): ?><button class="tab" data-target="#p-final">Fase Final</button><?php endif; ?>
            </div>
            <div id="liga-panels">
                <!-- Standings -->
                <div class="tab-panel" id="p-tabla">
                    <?php
                    $table = Standings::compute($tournamentId);
                    $cut = $tournament['final_phase'] === 'top8' ? 8 : ($tournament['final_phase'] === 'top4' ? 4 : 0);
                    if (!$table): ?>
                        <div class="empty-state card"><div class="es-icon">📊</div><h3>Sin datos</h3><p>La tabla se genera al cargar resultados.</p></div>
                    <?php else: ?>
                        <div class="table-wrap"><table class="data standings">
                            <thead><tr><th class="num">#</th><th>Equipo</th><th class="num">PJ</th><th class="num">PG</th><th class="num">PE</th><th class="num">PP</th><th class="num">GF</th><th class="num">GC</th><th class="num">DG</th><th class="num">PTS</th></tr></thead>
                            <tbody>
                            <?php foreach ($table as $r): ?>
                                <tr class="<?= $cut && $r['pos'] <= $cut ? 'zone-final' : '' ?>">
                                    <td class="num"><span class="pos-badge"><?= (int)$r['pos'] ?></span></td>
                                    <td class="team-cell"><?= media_thumb($r['logo'], $r['name']) ?> <?= e($r['name']) ?></td>
                                    <td class="num"><?= (int)$r['pj'] ?></td><td class="num"><?= (int)$r['pg'] ?></td><td class="num"><?= (int)$r['pe'] ?></td><td class="num"><?= (int)$r['pp'] ?></td>
                                    <td class="num"><?= (int)$r['gf'] ?></td><td class="num"><?= (int)$r['gc'] ?></td><td class="num"><?= (int)$r['dg'] ?></td><td class="num pts"><?= (int)$r['pts'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table></div>
                        <?php if ($cut): ?><p class="subtle mt-1" style="font-size:.8rem">Los primeros <?= $cut ?> clasifican a la fase final.</p><?php endif; ?>
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
                        <div class="card mb-2">
                            <div class="flex justify-between items-center wrap" style="margin-bottom:.5rem"><strong>Jornada <?= (int)$md['number'] ?></strong><span class="muted" style="font-size:.82rem"><?= e(fmt_date($md['match_date'])) ?></span></div>
                            <?php foreach ($ms as $mt): ?>
                                <div class="match-card" style="padding:.55rem .4rem;border-bottom:1px solid var(--c-border)">
                                    <div class="match-team"><?= media_thumb($mt['hl'], $mt['hn'] ?? '') ?> <span class="name"><?= e($mt['hn'] ?? '—') ?></span></div>
                                    <div style="text-align:center">
                                        <div class="match-score"><?php if ($mt['home_goals']!==null): ?><?= (int)$mt['home_goals'] ?> - <?= (int)$mt['away_goals'] ?><?php else: ?><span class="vs"><?= e(fmt_time($mt['match_time'])) ?></span><?php endif; ?></div>
                                    </div>
                                    <div class="match-team away"><span class="name"><?= e($mt['an'] ?? '—') ?></span> <?= media_thumb($mt['al'], $mt['an'] ?? '') ?></div>
                                </div>
                                <?php if (!empty($mt['referee_report']) && !empty($mt['referee_report_public'])): ?>
                                    <div style="text-align:center;padding:.1rem 0 .55rem">
                                        <img class="liga-acta-thumb" src="<?= e(base_url($mt['referee_report'])) ?>" data-full="<?= e(base_url($mt['referee_report'])) ?>" alt="Acta arbitral" title="Ver acta arbitral" style="width:52px;height:52px;object-fit:cover;border-radius:8px;border:1px solid var(--c-border);cursor:zoom-in;display:inline-block">
                                        <div class="subtle" style="font-size:.7rem">📄 Acta arbitral</div>
                                    </div>
                                <?php endif; ?>
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

                <?php if ($hasFinal): ?>
                <div class="tab-panel hidden" id="p-final">
                    <?php $bracket = FinalPhase::bracket($tournamentId);
                    $tn = function($id){ if(!$id) return '—'; $r=Database::one("SELECT name FROM teams WHERE id=?",[$id]); return $r?$r['name']:'—'; };
                    if (!$bracket): ?><div class="empty-state card"><div class="es-icon">🥇</div><h3>Fase final por definir</h3></div>
                    <?php else: ?>
                        <?php if ($tournament['champion_team_id']): ?>
                            <div class="card card-pad-lg text-center mb-3"><div style="font-size:2.5rem">🏆</div><h3 style="margin:.3rem 0">Campeón: <?= e($tn($tournament['champion_team_id'])) ?></h3>
                            <p class="muted">Subcampeón: <?= e($tn($tournament['runnerup_team_id'])) ?><?php if ($tournament['third_team_id']): ?> · Tercer lugar: <?= e($tn($tournament['third_team_id'])) ?><?php endif; ?></p></div>
                        <?php endif; ?>
                        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
                        <?php foreach ($bracket as $phase=>$matches): ?>
                            <div class="card"><h4 style="margin-top:0"><?= e($phase) ?></h4>
                            <?php foreach ($matches as $mt): $w=FinalPhase::winner($mt); ?>
                                <div style="padding:.5rem 0;border-bottom:1px solid var(--c-border)">
                                    <div class="flex justify-between"><span style="font-weight:<?= $w==$mt['home_team_id']?800:500 ?>"><?= e($tn($mt['home_team_id'])) ?></span><strong><?= $mt['home_goals']!==null?(int)$mt['home_goals']:'-' ?></strong></div>
                                    <div class="flex justify-between"><span style="font-weight:<?= $w==$mt['away_team_id']?800:500 ?>"><?= e($tn($mt['away_team_id'])) ?></span><strong><?= $mt['away_goals']!==null?(int)$mt['away_goals']:'-' ?></strong></div>
                                    <?php if($mt['home_pens']!==null): ?><div class="subtle" style="font-size:.75rem">Pen: <?= (int)$mt['home_pens'] ?>-<?= (int)$mt['away_pens'] ?></div><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
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
