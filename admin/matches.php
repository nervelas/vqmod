<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('results.manage');

$tournaments = Database::all("SELECT tr.id, tr.name, l.name AS league_name FROM tournaments tr JOIN leagues l ON l.id=tr.league_id ORDER BY tr.created_at DESC");
$action = str_input('action', 'list');
$tournamentId = int_input('tournament');
$matchId = int_input('id');

$STATUSES = ['pending'=>'Pendiente','in_progress'=>'En juego','finished'=>'Finalizado','suspended'=>'Suspendido','rescheduled'=>'Reprogramado','cancelled'=>'Cancelado'];

/* ---- POST handlers ------------------------------------------------------ */
if (is_post()) {
    Security::requireCsrf();
    $op = str_input('op');

    if ($op === 'save_match') {
        $matchId = int_input('id');
        $m = Database::one("SELECT * FROM matches WHERE id = ?", [$matchId]);
        if (!$m) { flash('danger','Partido no encontrado.'); redirect(base_url('admin/matches.php')); }
        $status = array_key_exists(str_input('status'), $STATUSES) ? str_input('status') : 'pending';
        $hg = post('home_goals'); $ag = post('away_goals');
        $hg = ($hg === '' || $hg === null) ? null : max(0, (int)$hg);
        $ag = ($ag === '' || $ag === null) ? null : max(0, (int)$ag);
        // Pending => goals NULL. 0-0 is a valid finished result.
        if ($status === 'pending') { $hg = null; $ag = null; }
        $hp = post('home_pens'); $ap = post('away_pens');
        $hp = ($hp === '' || $hp === null) ? null : max(0, (int)$hp);
        $ap = ($ap === '' || $ap === null) ? null : max(0, (int)$ap);

        $before = $m;
        Database::q(
            "UPDATE matches SET home_team_id=?, away_team_id=?, match_date=?, match_time=?, venue=?, status=?, home_goals=?, away_goals=?, home_pens=?, away_pens=?, notes=? WHERE id=?",
            [
                int_input('home_team_id') ?: null,
                int_input('away_team_id') ?: null,
                str_input('match_date') ?: null,
                str_input('match_time') ?: null,
                str_input('venue') ?: null,
                $status, $hg, $ag, $hp, $ap,
                str_input('notes') ?: null,
                $matchId,
            ]
        );
        Audit::log('update', 'matches', $matchId, $before, null);
        Discipline::recompute((int)$m['tournament_id']);
        flash('success', 'Partido actualizado.');
        redirect(base_url('admin/matches.php?action=edit&id=' . $matchId));
    }

    if ($op === 'add_event') {
        $matchId = int_input('match_id');
        $m = Database::one("SELECT * FROM matches WHERE id = ?", [$matchId]);
        if ($m) {
            $type = str_input('type');
            $allowed = ['goal','own_goal','yellow','red','double_yellow','sub_in','sub_out'];
            $teamId = int_input('team_id') ?: null;
            $playerId = int_input('player_id') ?: null;
            if (in_array($type, $allowed, true) && $playerId) {
                Database::q(
                    "INSERT INTO match_events (match_id, team_id, player_id, type, minute, notes) VALUES (?,?,?,?,?,?)",
                    [$matchId, $teamId, $playerId, $type, int_input('minute'), str_input('notes') ?: null]
                );
                // Ensure a tournament registration exists (historial por torneo).
                if ($teamId) {
                    $exists = Database::scalar("SELECT id FROM registrations WHERE tournament_id=? AND player_id=?", [$m['tournament_id'], $playerId]);
                    if (!$exists) {
                        Database::q("INSERT INTO registrations (tournament_id, team_id, player_id) VALUES (?,?,?)", [$m['tournament_id'], $teamId, $playerId]);
                    }
                }
                Discipline::recompute((int)$m['tournament_id']);
                flash('success', 'Evento agregado al acta.');
            } else {
                flash('danger', 'Selecciona tipo, equipo y jugador.');
            }
        }
        redirect(base_url('admin/matches.php?action=edit&id=' . $matchId));
    }

    if ($op === 'del_event') {
        $eventId = int_input('event_id');
        $matchId = int_input('match_id');
        $ev = Database::one("SELECT me.*, m.tournament_id FROM match_events me JOIN matches m ON m.id=me.match_id WHERE me.id=?", [$eventId]);
        if ($ev) {
            Database::q("DELETE FROM match_events WHERE id = ?", [$eventId]);
            Discipline::recompute((int)$ev['tournament_id']);
            flash('success', 'Evento eliminado.');
        }
        redirect(base_url('admin/matches.php?action=edit&id=' . $matchId));
    }
}

$PAGE_TITLE = 'Partidos';
$ACTIVE = 'matches';

/* ---- Edit / Acta view --------------------------------------------------- */
if ($action === 'edit' && $matchId) {
    $m = Database::one("SELECT m.*, tr.league_id, tr.name AS tournament_name FROM matches m JOIN tournaments tr ON tr.id=m.tournament_id WHERE m.id=?", [$matchId]);
    if (!$m) { redirect(base_url('admin/matches.php')); }
    $teams = Database::all("SELECT id, name, short_name FROM teams WHERE league_id = ? ORDER BY name", [$m['league_id']]);
    $teamOpts = []; foreach ($teams as $t) { $teamOpts[$t['id']] = $t['name']; }
    $players = Database::all("SELECT id, first_name, last_name, nickname FROM players WHERE league_id = ? AND status='active' ORDER BY first_name", [$m['league_id']]);
    $playerOpts = []; foreach ($players as $p) { $playerOpts[$p['id']] = player_name($p); }
    $events = Database::all("SELECT me.*, p.first_name, p.last_name, p.nickname, t.short_name, t.name AS team_name FROM match_events me LEFT JOIN players p ON p.id=me.player_id LEFT JOIN teams t ON t.id=me.team_id WHERE me.match_id=? ORDER BY me.minute IS NULL, me.minute, me.id", [$matchId]);
    $home = $m['home_team_id'] ? Database::one("SELECT * FROM teams WHERE id=?", [$m['home_team_id']]) : null;
    $away = $m['away_team_id'] ? Database::one("SELECT * FROM teams WHERE id=?", [$m['away_team_id']]) : null;
    $evLabels = ['goal'=>'⚽ Gol','own_goal'=>'⚽ Autogol','yellow'=>'🟨 Amarilla','double_yellow'=>'🟨🟨 Doble amarilla','red'=>'🟥 Roja','sub_in'=>'↑ Entra','sub_out'=>'↓ Sale'];
    require 'partials/head.php';
    ?>
    <div class="page-head flex justify-between items-center wrap">
        <div><h1>Acta del partido</h1><p><?= e($m['tournament_name']) ?><?= $m['is_final_phase'] ? ' · ' . e($m['phase_label']) : '' ?></p></div>
        <a class="btn btn-ghost" href="<?= e(base_url('admin/matches.php?tournament=' . $m['tournament_id'])) ?>">← Volver</a>
    </div>

    <div class="grid" style="grid-template-columns:1fr 1fr;gap:1.5rem">
        <form method="post" class="card card-pad-lg">
            <?= Security::csrfField() ?>
            <input type="hidden" name="op" value="save_match">
            <input type="hidden" name="id" value="<?= (int)$matchId ?>">
            <h3 style="margin-top:0">Datos y resultado</h3>
            <div class="form-row">
                <div class="field"><label>Local</label><select class="select" name="home_team_id"><?= options($teamOpts, $m['home_team_id'], '—') ?></select></div>
                <div class="field"><label>Visitante</label><select class="select" name="away_team_id"><?= options($teamOpts, $m['away_team_id'], '—') ?></select></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Goles local</label><input class="input" type="number" min="0" name="home_goals" value="<?= $m['home_goals'] === null ? '' : (int)$m['home_goals'] ?>"></div>
                <div class="field"><label>Goles visitante</label><input class="input" type="number" min="0" name="away_goals" value="<?= $m['away_goals'] === null ? '' : (int)$m['away_goals'] ?>"></div>
            </div>
            <?php if ($m['is_final_phase']): ?>
            <p class="help" style="margin-top:-.4rem">Penales (solo fase final, separados del marcador reglamentario).</p>
            <div class="form-row">
                <div class="field"><label>Penales local</label><input class="input" type="number" min="0" name="home_pens" value="<?= $m['home_pens'] === null ? '' : (int)$m['home_pens'] ?>"></div>
                <div class="field"><label>Penales visitante</label><input class="input" type="number" min="0" name="away_pens" value="<?= $m['away_pens'] === null ? '' : (int)$m['away_pens'] ?>"></div>
            </div>
            <?php endif; ?>
            <div class="form-row">
                <div class="field"><label>Fecha</label><input class="input" type="date" name="match_date" value="<?= e($m['match_date']) ?>"></div>
                <div class="field"><label>Hora</label><input class="input" type="time" name="match_time" value="<?= e($m['match_time']) ?>"></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Sede</label><input class="input" name="venue" value="<?= e($m['venue']) ?>"></div>
                <div class="field"><label>Estado</label><select class="select" name="status"><?= options($STATUSES, $m['status']) ?></select></div>
            </div>
            <div class="field"><label>Observaciones</label><textarea class="textarea" name="notes"><?= e($m['notes']) ?></textarea></div>
            <button class="btn" type="submit">Guardar partido</button>
        </form>

        <div class="card card-pad-lg">
            <h3 style="margin-top:0">Eventos del acta</h3>
            <form method="post" class="mb-3">
                <?= Security::csrfField() ?>
                <input type="hidden" name="op" value="add_event">
                <input type="hidden" name="match_id" value="<?= (int)$matchId ?>">
                <div class="form-row">
                    <div class="field" style="margin-bottom:.6rem"><label>Tipo</label><select class="select" name="type"><?= options($evLabels) ?></select></div>
                    <div class="field" style="margin-bottom:.6rem"><label>Equipo</label>
                        <select class="select" name="team_id">
                            <?php if ($home): ?><option value="<?= (int)$home['id'] ?>"><?= e(team_display($home)) ?> (L)</option><?php endif; ?>
                            <?php if ($away): ?><option value="<?= (int)$away['id'] ?>"><?= e(team_display($away)) ?> (V)</option><?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="field" style="margin-bottom:.6rem"><label>Jugador</label><select class="select" name="player_id"><?= options($playerOpts, null, '—') ?></select></div>
                    <div class="field" style="margin-bottom:.6rem"><label>Minuto</label><input class="input" type="number" min="0" max="130" name="minute"></div>
                </div>
                <button class="btn btn-sm" type="submit">+ Agregar evento</button>
            </form>

            <?php if (!$players): ?>
                <div class="alert alert-warning"><span>No hay jugadores en la liga. <a href="<?= e(base_url('admin/players.php?action=new')) ?>">Crea jugadores</a>.</span></div>
            <?php endif; ?>

            <?php if ($events): ?>
                <div class="table-wrap">
                    <table class="data" style="min-width:auto">
                        <thead><tr><th>Min</th><th>Evento</th><th>Jugador</th><th>Equipo</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($events as $ev): ?>
                            <tr>
                                <td class="num"><?= $ev['minute'] !== null ? (int)$ev['minute'] . "'" : '—' ?></td>
                                <td><?= e($evLabels[$ev['type']] ?? $ev['type']) ?></td>
                                <td><?= e(player_name($ev)) ?></td>
                                <td><?= e($ev['short_name'] ?: $ev['team_name'] ?: '—') ?></td>
                                <td>
                                    <form method="post" data-confirm="¿Eliminar evento?">
                                        <?= Security::csrfField() ?>
                                        <input type="hidden" name="op" value="del_event">
                                        <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
                                        <input type="hidden" name="match_id" value="<?= (int)$matchId ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">×</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="muted">Sin eventos registrados.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php require 'partials/foot.php'; exit;
}

/* ---- List by tournament ------------------------------------------------- */
require 'partials/head.php';
?>
<div class="page-head"><h1>Partidos y resultados</h1><p>Carga resultados y actas por jornada.</p></div>

<?php if (!$tournaments): ?>
    <div class="empty-state card"><div class="es-icon">⚽</div><h2>Sin torneos</h2><p>Crea un torneo y genera su calendario.</p><a class="btn" href="<?= e(base_url('admin/tournaments.php?action=new')) ?>">+ Nuevo torneo</a></div>
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
        $matchdays = Database::all("SELECT * FROM matchdays WHERE tournament_id = ? ORDER BY number", [$tournamentId]);
        if (!$matchdays):
            echo '<div class="empty-state card"><div class="es-icon">📅</div><h2>Sin calendario</h2><p>Genera el calendario de este torneo.</p><a class="btn" href="' . e(base_url('admin/calendar.php?tournament=' . $tournamentId)) . '">Generar calendario</a></div>';
        else:
            foreach ($matchdays as $md):
                $matches = Database::all("SELECT m.*, h.name AS home_name, h.short_name AS home_short, h.logo AS home_logo, a.name AS away_name, a.short_name AS away_short, a.logo AS away_logo FROM matches m LEFT JOIN teams h ON h.id=m.home_team_id LEFT JOIN teams a ON a.id=m.away_team_id WHERE m.matchday_id = ? ORDER BY m.match_date, m.match_time, m.id", [$md['id']]); ?>
                <div class="card mb-3">
                    <div class="flex justify-between items-center wrap" style="margin-bottom:.75rem">
                        <h3 style="margin:0">Jornada <?= (int)$md['number'] ?> <span class="muted" style="font-weight:400;font-size:.85rem">· Vuelta <?= (int)$md['round'] ?> · <?= e(fmt_date($md['match_date'])) ?></span></h3>
                    </div>
                    <?php foreach ($matches as $mt): ?>
                        <div class="match-card card" style="padding:.7rem 1rem;margin-bottom:.5rem">
                            <div class="match-team"><?= media_thumb($mt['home_logo'], $mt['home_name'] ?? '') ?> <span class="name"><?= e($mt['home_name'] ?? '—') ?></span></div>
                            <div style="text-align:center">
                                <div class="match-score">
                                    <?php if ($mt['status']==='finished' || $mt['home_goals']!==null): ?>
                                        <?= (int)$mt['home_goals'] ?> - <?= (int)$mt['away_goals'] ?>
                                    <?php else: ?><span class="vs">vs</span><?php endif; ?>
                                </div>
                                <div class="match-meta"><span class="badge badge-muted"><?= e($STATUSES[$mt['status']]) ?></span></div>
                            </div>
                            <div class="match-team away"><span class="name"><?= e($mt['away_name'] ?? '—') ?></span> <?= media_thumb($mt['away_logo'], $mt['away_name'] ?? '') ?></div>
                        </div>
                        <div style="text-align:right;margin-bottom:.75rem"><a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/matches.php?action=edit&id=' . $mt['id'])) ?>">Editar / Acta</a></div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach;
        endif;
    endif; ?>
<?php endif; ?>
<?php require 'partials/foot.php'; ?>
