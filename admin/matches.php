<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('results.manage');

/** Ensure matches.slot exists (kickoff order within a matchday). */
if (!function_exists('fl_ensure_match_slot_column')) {
    function fl_ensure_match_slot_column(): void
    {
        $has = Database::scalar(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'matches' AND column_name = 'slot'"
        );
        if (!$has) {
            Database::q("ALTER TABLE matches ADD COLUMN slot SMALLINT UNSIGNED NULL AFTER matchday_id");
        }
    }
}
fl_ensure_match_slot_column();

/** Ensure matches.referee_report exists (photo of the referee's match report). */
if (!function_exists('fl_ensure_match_report_column')) {
    function fl_ensure_match_report_column(): void
    {
        $has = Database::scalar(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'matches' AND column_name = 'referee_report'"
        );
        if (!$has) {
            Database::q("ALTER TABLE matches ADD COLUMN referee_report VARCHAR(255) NULL");
        }
        $hasPub = Database::scalar(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'matches' AND column_name = 'referee_report_public'"
        );
        if (!$hasPub) {
            Database::q("ALTER TABLE matches ADD COLUMN referee_report_public TINYINT(1) NOT NULL DEFAULT 0");
        }
    }
}
fl_ensure_match_report_column();

$tournaments = Database::all("SELECT tr.id, tr.name, l.name AS league_name FROM tournaments tr JOIN leagues l ON l.id=tr.league_id ORDER BY tr.created_at DESC");
$action = str_input('action', 'list');
$tournamentId = int_input('tournament');
$matchId = int_input('id');

$STATUSES = ['pending'=>'Pendiente','in_progress'=>'En juego','finished'=>'Finalizado','suspended'=>'Suspendido','rescheduled'=>'Reprogramado','cancelled'=>'Cancelado'];

/* ---- POST handlers ------------------------------------------------------ */
if (is_post()) {
    Security::requireCsrf();
    $op = str_input('op');

    /* ---- Drag & drop: reorder matches within a matchday (AJAX) ---------- */
    if ($op === 'reorder') {
        $mdId  = int_input('matchday_id');
        $order = array_values(array_filter(array_map('intval', explode(',', (string)post('order', '')))));
        $md = $mdId ? Database::one("SELECT * FROM matchdays WHERE id = ?", [$mdId]) : null;
        if (!$md || !$order) { json_response(['ok' => false, 'error' => 'Datos inválidos.'], 400); }
        // All current matches of this matchday.
        $rows = Database::all("SELECT id, match_time FROM matches WHERE matchday_id = ?", [$mdId]);
        $existing = array_map('intval', array_column($rows, 'id'));
        sort($existing);
        $sortedOrder = $order; sort($sortedOrder);
        if ($existing !== $sortedOrder) { json_response(['ok' => false, 'error' => 'El orden no coincide con la jornada.'], 400); }
        // Keep the same set of kickoff times; reassign them by the new order.
        $times = array_column($rows, 'match_time');
        usort($times, fn($a, $b) => (strtotime($a ?? '') ?: 0) <=> (strtotime($b ?? '') ?: 0));
        $resp = [];
        foreach ($order as $i => $mid) {
            $mt = $times[$i] ?? null;
            Database::q("UPDATE matches SET slot = ?, match_time = ? WHERE id = ? AND matchday_id = ?", [$i + 1, $mt, $mid, $mdId]);
            $resp[$mid] = ['slot' => $i + 1, 'time' => $mt ? substr($mt, 0, 5) : null];
        }
        Audit::log('reorder', 'calendar', $mdId, null, ['order' => $order]);
        json_response(['ok' => true, 'matches' => $resp]);
    }

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
            "UPDATE matches SET home_team_id=?, away_team_id=?, match_date=?, match_time=?, status=?, home_goals=?, away_goals=?, home_pens=?, away_pens=?, notes=? WHERE id=?",
            [
                int_input('home_team_id') ?: null,
                int_input('away_team_id') ?: null,
                str_input('match_date') ?: null,
                str_input('match_time') ?: null,
                $status, $hg, $ag, $hp, $ap,
                str_input('notes') ?: null,
                $matchId,
            ]
        );
        Audit::log('update', 'matches', $matchId, $before, null);
        Discipline::recompute((int)$m['tournament_id']);
        if (!empty($m['matchday_id'])) { Push::refreshMatchdayCompletion((int)$m['matchday_id']); }
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

    /* ---- Upload / replace / remove the referee report photo (Acta Arbitral) */
    if ($op === 'save_acta') {
        $matchId = int_input('match_id');
        $m = Database::one("SELECT * FROM matches WHERE id = ?", [$matchId]);
        if ($m) {
            try {
                $img = Upload::image('referee_report', 'acta');
            } catch (RuntimeException $ex) {
                flash('danger', $ex->getMessage());
                redirect(base_url('admin/matches.php?action=edit&id=' . $matchId . '#acta-arbitral'));
            }
            $pub = post('report_public') ? 1 : 0;
            if (post('remove_report') && !empty($m['referee_report'])) {
                Upload::delete($m['referee_report']);
                Database::q("UPDATE matches SET referee_report = NULL, referee_report_public = 0 WHERE id = ?", [$matchId]);
                flash('success', 'Acta arbitral eliminada.');
            } elseif ($img) {
                if (!empty($m['referee_report'])) { Upload::delete($m['referee_report']); }
                Database::q("UPDATE matches SET referee_report = ?, referee_report_public = ? WHERE id = ?", [$img, $pub, $matchId]);
                flash('success', 'Acta arbitral subida correctamente.');
            } elseif (!empty($m['referee_report'])) {
                // No new file: just update the public/private visibility.
                Database::q("UPDATE matches SET referee_report_public = ? WHERE id = ?", [$pub, $matchId]);
                flash('success', 'Visibilidad del acta actualizada.');
            } else {
                flash('warning', 'Selecciona una imagen (JPG, PNG o WEBP).');
            }
            Audit::log('update', 'matches', $matchId, null, ['referee_report' => 'changed', 'public' => $pub]);
        }
        redirect(base_url('admin/matches.php?action=edit&id=' . $matchId . '#acta-arbitral'));
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

    // Players grouped by team (team_id) so the acta lists only that team's squad.
    $teamPlayers = [];
    foreach ([$home, $away] as $tm) {
        if (!$tm) { continue; }
        $tid = (int)$tm['id'];
        $teamPlayers[$tid] = [];
        foreach (Database::all("SELECT id, first_name, last_name, nickname FROM players WHERE team_id = ? AND status='active' ORDER BY first_name, last_name", [$tid]) as $p) {
            $teamPlayers[$tid][] = ['id' => (int)$p['id'], 'name' => player_name($p)];
        }
    }
    $firstTeamId = $home ? (int)$home['id'] : ($away ? (int)$away['id'] : 0);

    $evLabels = ['goal'=>'⚽ Gol','own_goal'=>'⚽ Autogol','yellow'=>'🟨 Amarilla','double_yellow'=>'🟨🟨 Doble amarilla','red'=>'🟥 Roja','sub_in'=>'↑ Entra','sub_out'=>'↓ Sale'];
    require 'partials/head.php';
    ?>
    <div class="page-head flex justify-between items-center wrap">
        <div><h1>Acta del partido</h1><p><?= e($m['tournament_name']) ?><?= $m['is_final_phase'] ? ' · ' . e($m['phase_label']) : '' ?></p></div>
        <a class="btn btn-ghost" href="<?= e(base_url('admin/matches.php?tournament=' . $m['tournament_id'])) ?>">← Volver</a>
    </div>

    <div class="cols-2">
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
            <div class="field"><label>Estado</label><select class="select" name="status"><?= options($STATUSES, $m['status']) ?></select></div>
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
                        <select class="select" name="team_id" id="ev-team">
                            <?php if ($home): ?><option value="<?= (int)$home['id'] ?>"><?= e(team_display($home)) ?> (L)</option><?php endif; ?>
                            <?php if ($away): ?><option value="<?= (int)$away['id'] ?>"><?= e(team_display($away)) ?> (V)</option><?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="field" style="margin-bottom:.6rem"><label>Jugador</label>
                        <select class="select" name="player_id" id="ev-player">
                            <option value="">—</option>
                            <?php foreach (($teamPlayers[$firstTeamId] ?? []) as $pp): ?><option value="<?= (int)$pp['id'] ?>"><?= e($pp['name']) ?></option><?php endforeach; ?>
                        </select>
                        <div class="help">Elige primero el equipo; se listan solo sus jugadores.</div>
                    </div>
                    <div class="field" style="margin-bottom:.6rem"><label>Minuto</label><input class="input" type="number" min="0" max="130" name="minute"></div>
                </div>
                <button class="btn btn-sm" type="submit">+ Agregar evento</button>
            </form>
            <script>
            (function () {
                var TP = <?= json_encode($teamPlayers, JSON_UNESCAPED_UNICODE) ?>;
                var teamSel = document.getElementById('ev-team'), playerSel = document.getElementById('ev-player');
                if (!teamSel || !playerSel) { return; }
                function fill() {
                    var list = TP[teamSel.value] || [];
                    playerSel.innerHTML = '<option value="">—</option>';
                    list.forEach(function (p) { var o = document.createElement('option'); o.value = p.id; o.textContent = p.name; playerSel.appendChild(o); });
                }
                teamSel.addEventListener('change', fill);
                fill();
            })();
            </script>

            <?php if (!$players): ?>
                <div class="alert alert-warning"><span>No hay jugadores en la liga. <a href="<?= e(base_url('admin/teams.php')) ?>">Agrégalos dentro de cada equipo</a>.</span></div>
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

    <!-- ================= Acta Arbitral (foto) ================= -->
    <div id="acta-arbitral" class="card card-pad-lg mt-3">
        <h3 style="margin-top:0">Acta arbitral</h3>
        <p class="muted" style="margin-top:-.4rem">Sube una foto del acta firmada por el árbitro. Se muestra en miniatura; haz clic para verla a tamaño real.</p>
        <form method="post" enctype="multipart/form-data">
            <?= Security::csrfField() ?>
            <input type="hidden" name="op" value="save_acta">
            <input type="hidden" name="match_id" value="<?= (int)$matchId ?>">
            <?php if (!empty($m['referee_report'])): ?>
                <div class="flex items-center gap-2 wrap mb-2">
                    <img class="acta-thumb" src="<?= e(base_url($m['referee_report'])) ?>" alt="Acta arbitral"
                         data-full="<?= e(base_url($m['referee_report'])) ?>"
                         style="width:130px;height:130px;object-fit:cover;border-radius:12px;border:1px solid var(--c-border);cursor:zoom-in">
                    <label class="help"><input type="checkbox" name="remove_report" value="1"> Eliminar acta actual</label>
                </div>
                <div class="field">
                    <label>Visibilidad del acta</label>
                    <label class="check-item" style="max-width:360px"><input type="checkbox" name="report_public" value="1"<?= checked(!empty($m['referee_report_public'])) ?>> Mostrar el acta en la página pública de la liga</label>
                    <div class="help">Sin marcar, el acta queda privada (solo visible en el panel).</div>
                </div>
                <div class="field">
                    <label for="referee_report">Reemplazar acta (JPG, PNG, WEBP)</label>
                    <input class="input" type="file" id="referee_report" name="referee_report" accept=".jpg,.jpeg,.png,.webp">
                </div>
                <button class="btn" type="submit">Guardar cambios del acta</button>
            <?php else: ?>
                <div class="field">
                    <label for="referee_report">Imagen del acta (JPG, PNG, WEBP)</label>
                    <input class="input" type="file" id="referee_report" name="referee_report" accept=".jpg,.jpeg,.png,.webp" required>
                </div>
                <div class="field">
                    <label class="check-item" style="max-width:360px"><input type="checkbox" name="report_public" value="1"> Mostrar el acta en la página pública de la liga</label>
                    <div class="help">Sin marcar, el acta queda privada (solo visible en el panel).</div>
                </div>
                <button class="btn" type="submit">📄 Subir Acta Arbitral</button>
            <?php endif; ?>
        </form>
    </div>

    <!-- Lightbox -->
    <div id="acta-lightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:200;align-items:center;justify-content:center;padding:1.5rem;cursor:zoom-out">
        <img id="acta-lightbox-img" src="" alt="Acta arbitral" style="max-width:100%;max-height:100%;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,.6)">
    </div>
    <script>
    (function () {
        var lb = document.getElementById('acta-lightbox');
        var lbImg = document.getElementById('acta-lightbox-img');
        document.querySelectorAll('.acta-thumb').forEach(function (t) {
            t.addEventListener('click', function () {
                lbImg.src = t.getAttribute('data-full');
                lb.style.display = 'flex';
            });
        });
        if (lb) {
            lb.addEventListener('click', function () { lb.style.display = 'none'; lbImg.src = ''; });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { lb.style.display = 'none'; lbImg.src = ''; } });
        }
    })();
    </script>
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
                <?php foreach ($tournaments as $tr): ?><option value="<?= (int)$tr['id'] ?>"<?= selected($tr['id'], $tournamentId) ?>><?= e($tr['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($tournamentId):
        $matchdays = Database::all("SELECT * FROM matchdays WHERE tournament_id = ? ORDER BY number", [$tournamentId]);
        if (!$matchdays):
            echo '<div class="empty-state card"><div class="es-icon">📅</div><h2>Sin calendario</h2><p>Genera el calendario de este torneo.</p><a class="btn" href="' . e(base_url('admin/calendar.php?tournament=' . $tournamentId)) . '">Generar calendario</a></div>';
        else:
            foreach ($matchdays as $md):
                $matches = Database::all("SELECT m.*, h.name AS home_name, h.short_name AS home_short, h.logo AS home_logo, a.name AS away_name, a.short_name AS away_short, a.logo AS away_logo FROM matches m LEFT JOIN teams h ON h.id=m.home_team_id LEFT JOIN teams a ON a.id=m.away_team_id WHERE m.matchday_id = ? ORDER BY COALESCE(m.slot, 999999), m.match_time, m.id", [$md['id']]); ?>
                <div class="card mb-3">
                    <div class="flex justify-between items-center wrap" style="margin-bottom:.4rem">
                        <h3 style="margin:0">Jornada <?= (int)$md['number'] ?> <span class="muted" style="font-weight:400;font-size:.85rem">· Vuelta <?= (int)$md['round'] ?> · <?= e(fmt_date($md['match_date'])) ?></span></h3>
                        <span class="muted" style="font-size:.8rem">↕ Arrastra los partidos para reordenarlos y cambiar su horario</span>
                    </div>
                    <div class="md-matches" data-matchday="<?= (int)$md['id'] ?>" data-csrf="<?= e(Security::csrfToken()) ?>">
                    <?php foreach ($matches as $i => $mt): $slotNum = $mt['slot'] !== null ? (int)$mt['slot'] : ($i + 1); ?>
                        <div class="match-row card" draggable="true" data-id="<?= (int)$mt['id'] ?>">
                            <span class="drag-handle" title="Arrastrar">⠿</span>
                            <span class="badge badge-muted slot-badge">Partido <span class="slot-num"><?= $slotNum ?></span> · <span class="slot-time"><?= e($mt['match_time'] ? substr($mt['match_time'],0,5) : '—') ?></span></span>
                            <div class="mr-body">
                                <div class="match-team"><?= media_thumb($mt['home_logo'], $mt['home_name'] ?? '') ?> <span class="name"><?= e($mt['home_name'] ?? '—') ?></span></div>
                                <div style="text-align:center">
                                    <div class="match-score">
                                        <?php if ($mt['status']==='finished' || $mt['home_goals']!==null): ?><?= (int)$mt['home_goals'] ?> - <?= (int)$mt['away_goals'] ?><?php else: ?><span class="vs">vs</span><?php endif; ?>
                                    </div>
                                    <div class="match-meta"><span class="badge badge-muted"><?= e($STATUSES[$mt['status']]) ?></span></div>
                                </div>
                                <div class="match-team away"><span class="name"><?= e($mt['away_name'] ?? '—') ?></span> <?= media_thumb($mt['away_logo'], $mt['away_name'] ?? '') ?></div>
                            </div>
                            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/matches.php?action=edit&id=' . $mt['id'])) ?>">Editar / Acta</a>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach;
        endif;
    endif; ?>

    <style>
    .md-matches .match-row{display:flex;align-items:center;gap:.7rem;padding:.6rem .8rem;margin-bottom:.5rem}
    .md-matches .match-row .mr-body{flex:1;display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:.6rem;min-width:0}
    .md-matches .drag-handle{cursor:grab;color:var(--c-text-subtle);font-size:1.2rem;user-select:none;line-height:1}
    .md-matches .match-row.dragging{opacity:.45}
    .md-matches .match-row.drop-target{outline:2px dashed var(--c-accent);outline-offset:2px}
    .slot-badge{white-space:nowrap}
    @media(max-width:620px){.md-matches .match-row{flex-wrap:wrap}.md-matches .match-row .mr-body{flex-basis:100%;order:3}}
    </style>
    <script>
    (function(){
        var dragEl=null;
        function rowAfter(list,y){
            var rows=[].slice.call(list.querySelectorAll('.match-row'));
            for(var i=0;i<rows.length;i++){ if(rows[i]===dragEl) continue; var b=rows[i].getBoundingClientRect(); if(y < b.top + b.height/2) return rows[i]; }
            return null;
        }
        function save(list){
            var ids=[].slice.call(list.querySelectorAll('.match-row')).map(function(r){return r.getAttribute('data-id');});
            var body='op=reorder&matchday_id='+encodeURIComponent(list.getAttribute('data-matchday'))+'&order='+encodeURIComponent(ids.join(','))+'&_csrf='+encodeURIComponent(list.getAttribute('data-csrf'));
            fetch(location.pathname,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':list.getAttribute('data-csrf')},body:body})
            .then(function(r){return r.json();})
            .then(function(d){
                if(d&&d.ok){ Object.keys(d.matches).forEach(function(id){ var row=list.querySelector('.match-row[data-id="'+id+'"]'); if(!row)return; var sn=row.querySelector('.slot-num'),st=row.querySelector('.slot-time'); if(sn)sn.textContent=d.matches[id].slot; if(st)st.textContent=d.matches[id].time||'—'; }); toast('Orden y horarios actualizados'); }
                else { toast((d&&d.error)||'No se pudo reordenar'); setTimeout(function(){location.reload();},600); }
            }).catch(function(){ location.reload(); });
        }
        function toast(msg){
            var t=document.createElement('div'); t.textContent=msg;
            t.style.cssText='position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:var(--c-elevated);color:var(--c-text);border:1px solid var(--c-border);padding:.6rem 1rem;border-radius:10px;z-index:200;box-shadow:0 8px 24px rgba(0,0,0,.3);font-weight:600';
            document.body.appendChild(t); setTimeout(function(){t.remove();},1800);
        }
        document.querySelectorAll('.md-matches').forEach(function(list){
            list.querySelectorAll('.match-row').forEach(function(row){
                row.addEventListener('dragstart',function(e){ dragEl=row; row.classList.add('dragging'); if(e.dataTransfer){e.dataTransfer.effectAllowed='move';try{e.dataTransfer.setData('text/plain','');}catch(_){}} });
                row.addEventListener('dragend',function(){ row.classList.remove('dragging'); dragEl=null; });
            });
            list.addEventListener('dragover',function(e){ e.preventDefault(); if(!dragEl||!list.contains(dragEl))return; var after=rowAfter(list,e.clientY); if(after==null){ if(list.lastElementChild!==dragEl) list.appendChild(dragEl); } else if(after!==dragEl){ list.insertBefore(dragEl,after); } });
            list.addEventListener('drop',function(e){ e.preventDefault(); if(dragEl){ save(list); } });
        });
    })();
    </script>
<?php endif; ?>
<?php require 'partials/foot.php'; ?>
