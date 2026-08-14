<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('discipline.manage');

$tournaments = Database::all("SELECT tr.id, tr.name, l.name AS league_name FROM tournaments tr JOIN leagues l ON l.id=tr.league_id ORDER BY tr.created_at DESC");
$tournamentId = int_input('tournament');

if (is_post()) {
    Security::requireCsrf();
    $op = str_input('op');
    $tournamentId = int_input('tournament');
    if ($op === 'recompute' && $tournamentId) {
        Discipline::recompute($tournamentId);
        Audit::log('recompute', 'discipline', $tournamentId);
        flash('success', 'Sanciones recalculadas automáticamente desde las actas.');
    }
    if ($op === 'add' && $tournamentId) {
        $playerId = int_input('player_id');
        $teamId = int_input('team_id') ?: null;
        $total = max(1, (int)int_input('total_matches', 1));
        if ($playerId) {
            Database::q(
                "INSERT INTO suspensions (tournament_id, player_id, team_id, reason, event_type, total_matches, served_matches, status)
                 VALUES (?,?,?,?,?,?,0,'active')",
                [$tournamentId, $playerId, $teamId, str_input('reason') ?: 'Sanción manual', 'manual', $total]
            );
            Discipline::recomputeServed($tournamentId);
            Audit::log('create', 'discipline', $playerId);
            flash('success', 'Suspensión registrada.');
        }
    }
    if ($op === 'cancel' && $tournamentId) {
        $sid = int_input('suspension_id');
        Database::q("UPDATE suspensions SET status='cancelled' WHERE id=?", [$sid]);
        Audit::log('cancel', 'discipline', $sid);
        flash('success', 'Suspensión cancelada.');
    }
    redirect(base_url('admin/discipline.php?tournament=' . $tournamentId));
}

$PAGE_TITLE = 'Disciplina y suspensiones';
$ACTIVE = 'discipline';
require 'partials/head.php';
?>
<div class="page-head"><h1>Disciplina y suspensiones</h1><p>Tarjetas, expulsiones y cumplimiento automático de sanciones.</p></div>

<?php if (!$tournaments): ?>
    <div class="empty-state card"><div class="es-icon">🟨</div><h2>Sin torneos</h2><p>La disciplina se gestiona por torneo.</p></div>
<?php else: ?>
<form method="get" class="card mb-3">
    <div class="field" style="margin-bottom:0">
        <label for="tournament">Torneo</label>
        <select class="select" id="tournament" name="tournament" onchange="this.form.submit()">
            <option value="">Seleccione…</option>
            <?php foreach ($tournaments as $tr): ?><option value="<?= (int)$tr['id'] ?>"<?= selected($tr['id'], $tournamentId) ?>><?= e($tr['league_name']) ?> — <?= e($tr['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($tournamentId):
    $tournament = Database::one("SELECT tr.*, l.id AS lid FROM tournaments tr JOIN leagues l ON l.id=tr.league_id WHERE tr.id=?", [$tournamentId]);
    $tally = Standings::discipline($tournamentId);
    $suspensions = Database::all("SELECT s.*, p.first_name, p.last_name, p.nickname, t.name AS team_name, t.short_name FROM suspensions s JOIN players p ON p.id=s.player_id LEFT JOIN teams t ON t.id=s.team_id WHERE s.tournament_id=? ORDER BY s.status='active' DESC, s.created_at DESC", [$tournamentId]);
    $players = Database::all("SELECT id, first_name, last_name, nickname FROM players WHERE league_id=? AND status='active' ORDER BY first_name", [$tournament['lid']]);
    $playerOpts = []; foreach ($players as $p) { $playerOpts[$p['id']] = player_name($p); }
    $teams = Database::all("SELECT id, name FROM teams WHERE league_id=? ORDER BY name", [$tournament['lid']]);
    $teamOpts = []; foreach ($teams as $t) { $teamOpts[$t['id']] = $t['name']; }
    ?>
    <div class="toolbar">
        <div class="flex gap-1 wrap">
            <span class="badge badge-warning">Doble amarilla = expulsión (no roja directa)</span>
        </div>
        <form method="post">
            <?= Security::csrfField() ?>
            <input type="hidden" name="op" value="recompute">
            <input type="hidden" name="tournament" value="<?= (int)$tournamentId ?>">
            <button class="btn btn-sm" type="submit">↻ Recalcular sanciones automáticas</button>
        </form>
    </div>

    <div class="grid" style="grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">
        <div class="card">
            <h3 style="margin-top:0">Tarjetas por jugador</h3>
            <?php if (!$tally): ?><p class="muted">Sin tarjetas registradas.</p><?php else: ?>
            <div class="table-wrap"><table class="data" style="min-width:auto">
                <thead><tr><th>Jugador</th><th>Equipo</th><th class="num">🟨</th><th class="num">🟨🟨</th><th class="num">🟥</th></tr></thead>
                <tbody>
                <?php foreach ($tally as $r): ?>
                    <tr><td><?= e(player_name($r)) ?></td><td><?= e($r['team_short'] ?: $r['team_name'] ?: '—') ?></td>
                    <td class="num"><?= (int)$r['yellows'] ?></td><td class="num"><?= (int)$r['double_yellows'] ?></td><td class="num"><?= (int)$r['reds'] ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 style="margin-top:0">Registrar suspensión manual</h3>
            <form method="post">
                <?= Security::csrfField() ?>
                <input type="hidden" name="op" value="add">
                <input type="hidden" name="tournament" value="<?= (int)$tournamentId ?>">
                <div class="field"><label>Jugador</label><select class="select" name="player_id" required><?= options($playerOpts, null, '—') ?></select></div>
                <div class="form-row">
                    <div class="field"><label>Equipo</label><select class="select" name="team_id"><?= options($teamOpts, null, '—') ?></select></div>
                    <div class="field"><label>Partidos</label><input class="input" type="number" min="1" name="total_matches" value="1"></div>
                </div>
                <div class="field"><label>Motivo</label><input class="input" name="reason" placeholder="Ej: conducta antideportiva"></div>
                <button class="btn btn-sm" type="submit">Registrar</button>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <h3 style="margin-top:0">Suspensiones</h3>
        <p class="muted" style="margin-top:-.4rem;font-size:.85rem">El cumplimiento se aplica al siguiente partido oficial del equipo. Recalcula tras cargar resultados.</p>
        <?php if (!$suspensions): ?><p class="muted">Sin suspensiones.</p><?php else: ?>
        <div class="table-wrap"><table class="data">
            <thead><tr><th>Jugador</th><th>Equipo</th><th>Motivo</th><th class="num">Total</th><th class="num">Cumplidos</th><th class="num">Pendientes</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($suspensions as $s): $pending = max(0, (int)$s['total_matches'] - (int)$s['served_matches']); ?>
                <tr>
                    <td><?= e(player_name($s)) ?></td>
                    <td><?= e($s['short_name'] ?: $s['team_name'] ?: '—') ?></td>
                    <td><?= e($s['reason']) ?></td>
                    <td class="num"><?= (int)$s['total_matches'] ?></td>
                    <td class="num"><?= (int)$s['served_matches'] ?></td>
                    <td class="num"><strong><?= $pending ?></strong></td>
                    <td><span class="badge <?= $s['status']==='active'?'badge-warning':($s['status']==='served'?'badge-success':'badge-muted') ?>"><?= $s['status']==='active'?'Activa':($s['status']==='served'?'Cumplida':'Cancelada') ?></span></td>
                    <td>
                        <?php if ($s['status']!=='cancelled'): ?>
                        <form method="post" data-confirm="¿Cancelar suspensión?">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="op" value="cancel"><input type="hidden" name="tournament" value="<?= (int)$tournamentId ?>"><input type="hidden" name="suspension_id" value="<?= (int)$s['id'] ?>">
                            <button class="btn btn-sm btn-ghost" type="submit">Cancelar</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php endif; ?>
<?php require 'partials/foot.php'; ?>
