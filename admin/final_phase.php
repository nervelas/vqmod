<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('tournaments.manage');

$tournaments = Database::all("SELECT tr.id, tr.name, tr.final_phase, l.name AS league_name FROM tournaments tr JOIN leagues l ON l.id=tr.league_id WHERE tr.final_phase <> 'none' ORDER BY tr.created_at DESC");
$tournamentId = int_input('tournament');

if (is_post()) {
    Security::requireCsrf();
    $op = str_input('op');
    $tournamentId = int_input('tournament');
    try {
        if ($op === 'seed') {
            $res = FinalPhase::seedFirstRound($tournamentId);
            Audit::log('seed_final', 'final', $tournamentId, null, $res);
            flash('success', 'Fase final generada: ' . $res['phase'] . '.');
        } elseif ($op === 'advance') {
            $msg = FinalPhase::advance($tournamentId);
            Audit::log('advance_final', 'final', $tournamentId);
            flash('info', $msg);
        }
    } catch (Throwable $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect(base_url('admin/final_phase.php?tournament=' . $tournamentId));
}

$PAGE_TITLE = 'Fase final';
$ACTIVE = 'final';
require 'partials/head.php';
?>
<div class="page-head"><h1>Fase final</h1><p>Genera y administra los playoffs (Top 4 / Top 8).</p></div>
<?php if (!$tournaments): ?>
    <div class="empty-state card"><div class="es-icon">🥇</div><h2>Ningún torneo con fase final</h2><p>Activa Top 4 o Top 8 al editar un torneo.</p><a class="btn" href="<?= e(base_url('admin/tournaments.php')) ?>">Ir a torneos</a></div>
<?php else: ?>
<form method="get" class="card mb-3">
    <div class="field" style="margin-bottom:0">
        <label for="tournament">Torneo</label>
        <select class="select" id="tournament" name="tournament" onchange="this.form.submit()">
            <option value="">Seleccione…</option>
            <?php foreach ($tournaments as $tr): ?><option value="<?= (int)$tr['id'] ?>"<?= selected($tr['id'], $tournamentId) ?>><?= e($tr['league_name']) ?> — <?= e($tr['name']) ?> (<?= strtoupper($tr['final_phase']) ?>)</option><?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($tournamentId):
    $t = Database::one("SELECT * FROM tournaments WHERE id=?", [$tournamentId]);
    $bracket = FinalPhase::bracket($tournamentId);
    $teamName = function ($id) { if (!$id) return '—'; $r = Database::one("SELECT name, short_name FROM teams WHERE id=?", [$id]); return $r ? team_display($r) : '—'; };
    ?>
    <div class="toolbar">
        <div class="flex gap-1 wrap">
            <span class="badge badge-accent"><?= strtoupper($t['final_phase']) ?></span>
            <?php if ($t['champion_team_id']): ?><span class="badge badge-success">🏆 Campeón: <?= e($teamName($t['champion_team_id'])) ?></span><?php endif; ?>
        </div>
        <div class="flex gap-1 wrap">
            <form method="post" data-confirm="Esto (re)generará la primera ronda desde la tabla actual. ¿Continuar?">
                <?= Security::csrfField() ?><input type="hidden" name="op" value="seed"><input type="hidden" name="tournament" value="<?= (int)$tournamentId ?>">
                <button class="btn btn-sm" type="submit">Generar / Reiniciar bracket</button>
            </form>
            <form method="post">
                <?= Security::csrfField() ?><input type="hidden" name="op" value="advance"><input type="hidden" name="tournament" value="<?= (int)$tournamentId ?>">
                <button class="btn btn-sm btn-accent" type="submit">Avanzar ronda →</button>
            </form>
        </div>
    </div>

    <?php if (!$bracket): ?>
        <div class="empty-state card"><div class="es-icon">🥇</div><h2>Bracket no generado</h2><p>Pulsa "Generar bracket" cuando la fase regular haya definido la tabla.</p></div>
    <?php else: ?>
        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.25rem">
            <?php foreach ($bracket as $phase => $matches): ?>
                <div class="card">
                    <h3 style="margin-top:0"><?= e($phase) ?></h3>
                    <?php foreach ($matches as $mt): $w = FinalPhase::winner($mt); ?>
                        <div class="card mb-2" style="padding:.75rem">
                            <div class="flex justify-between items-center">
                                <span style="font-weight:<?= $w==$mt['home_team_id']?800:600 ?>"><?= e($teamName($mt['home_team_id'])) ?></span>
                                <strong><?= $mt['home_goals']!==null ? (int)$mt['home_goals'] : '-' ?></strong>
                            </div>
                            <div class="flex justify-between items-center">
                                <span style="font-weight:<?= $w==$mt['away_team_id']?800:600 ?>"><?= e($teamName($mt['away_team_id'])) ?></span>
                                <strong><?= $mt['away_goals']!==null ? (int)$mt['away_goals'] : '-' ?></strong>
                            </div>
                            <?php if ($mt['home_pens']!==null || $mt['away_pens']!==null): ?>
                                <div class="muted" style="font-size:.78rem;margin-top:.3rem">Penales: <?= (int)$mt['home_pens'] ?> - <?= (int)$mt['away_pens'] ?></div>
                            <?php endif; ?>
                            <div class="mt-1"><a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/matches.php?action=edit&id=' . $mt['id'])) ?>">Cargar resultado</a></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php require 'partials/foot.php'; ?>
