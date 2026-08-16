<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('calendar.manage');

/** Ensure matches.slot exists (order/kickoff-slot within a matchday). */
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

$tournaments = Database::all("SELECT tr.id, tr.name, tr.rounds, l.name AS league_name, l.id AS league_id
    FROM tournaments tr JOIN leagues l ON l.id = tr.league_id ORDER BY tr.created_at DESC");

$stage = str_input('stage', 'config');
$tournamentId = int_input('tournament');
$tournament = $tournamentId ? Database::one("SELECT tr.*, l.name AS league_name FROM tournaments tr JOIN leagues l ON l.id=tr.league_id WHERE tr.id = ?", [$tournamentId]) : null;

$DAYS = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'];

/** Assign a date to each matchday index using allowed weekdays + interval. */
function schedule_dates(int $count, string $start, array $allowedDays, int $intervalDays): array
{
    $dates = [];
    $allowed = array_map('intval', $allowedDays);
    if (!$allowed) { $allowed = [6]; } // default Saturday
    $baseTs = strtotime($start) ?: time();
    for ($i = 0; $i < $count; $i++) {
        $target = $baseTs + ($i * max(1, $intervalDays) * 86400);
        // Snap forward to the next allowed weekday.
        for ($guard = 0; $guard < 14; $guard++) {
            $n = (int)date('N', $target);
            if (in_array($n, $allowed, true)) { break; }
            $target += 86400;
        }
        $dates[$i] = date('Y-m-d', $target);
    }
    return $dates;
}

$preview = null;
$errors = [];
$teamIds = [];
$formParams = [];
$showImport = false;   // render the PDF-import editable preview
$importRows = [];      // flat rows: ['jornada'=>int,'home'=>id,'away'=>id]
$importStats = null;   // detection stats

if (is_post()) {
    Security::requireCsrf();
    $op = str_input('op');
    $stage = str_input('stage');
    $tournamentId = int_input('tournament');
    $tournament = $tournamentId ? Database::one("SELECT * FROM tournaments WHERE id = ?", [$tournamentId]) : null;
    if (!$tournament) { flash('danger', 'Torneo no válido.'); redirect(base_url('admin/calendar.php')); }

    $teamIds  = array_values(array_filter(array_map('intval', (array)post('team_ids', []))));
    $rounds   = int_input('rounds', (int)$tournament['rounds']) === 1 ? 1 : 2;
    $start    = str_input('start_date') ?: date('Y-m-d');
    $days     = (array)post('days', []);
    $time     = str_input('match_time', '15:00');
    $interval = max(1, (int)int_input('interval', 7));
    $intervalMin = max(0, (int)int_input('interval_min', 120));
    $venue    = str_input('venue');

    $formParams = compact('rounds','start','days','time','interval','intervalMin','venue');

    /* ---- Delete the whole calendar of the tournament -------------------- */
    if ($op === 'delete_calendar') {
        Database::q("DELETE FROM matches WHERE tournament_id = ? AND is_final_phase = 0", [$tournamentId]);
        Database::q("DELETE FROM matchdays WHERE tournament_id = ?", [$tournamentId]);
        Database::q("DELETE FROM tournament_teams WHERE tournament_id = ?", [$tournamentId]);
        Audit::log('delete_calendar', 'calendar', $tournamentId);
        flash('success', 'Calendario eliminado por completo.');
        redirect(base_url('admin/calendar.php?tournament=' . $tournamentId));
    }

    /* ---- Import a PDF calendar into an editable preview ------------------ */
    if ($op === 'import_pdf') {
        $file = $_FILES['calendar_pdf'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Selecciona un archivo PDF.';
        } elseif ($file['size'] <= 0 || $file['size'] > 8 * 1024 * 1024) {
            $errors[] = 'El PDF supera el tamaño permitido (8 MB).';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($mime !== 'application/pdf' && $ext !== 'pdf') {
                $errors[] = 'El archivo debe ser un PDF.';
            } else {
                $lt = Database::all("SELECT id, name FROM teams WHERE league_id = ?", [$tournament['league_id']]);
                $teamMap = []; foreach ($lt as $t) { $teamMap[(int)$t['id']] = $t['name']; }
                $parsed = PdfCalendar::parse(PdfCalendar::extractText($file['tmp_name']), $teamMap);
                foreach ($parsed['jornadas'] as $j) {
                    foreach ($j['matches'] as $mm) {
                        $importRows[] = ['jornada' => $j['number'], 'home' => $mm['home_id'], 'away' => $mm['away_id']];
                    }
                }
                $importStats = ['detected' => $parsed['detected_matches'], 'matched' => $parsed['matched'], 'jornadas' => count($parsed['jornadas'])];
                if (!$importRows) {
                    $errors[] = 'No se detectaron partidos. Asegúrate de que el PDF sea de texto (no una imagen escaneada) y que los nombres de los equipos coincidan con los registrados en la liga.';
                } else {
                    $showImport = true;
                }
            }
        }
    }

    /* ---- Confirm & persist an imported / edited calendar ---------------- */
    if ($op === 'import_confirm') {
        $mj = (array)post('mj', []); $mh = (array)post('mh', []); $ma = (array)post('ma', []); $rm = (array)post('rm', []);
        $rowsIn = [];
        foreach ($mj as $i => $jn) {
            if (isset($rm[$i])) { continue; }
            $h = (int)($mh[$i] ?? 0); $a = (int)($ma[$i] ?? 0); $jn = (int)$jn;
            if (!$h || !$a || $jn < 1) { continue; }
            $rowsIn[] = ['jornada' => $jn, 'home' => $h, 'away' => $a];
        }
        $byJ = [];
        foreach ($rowsIn as $r) { $byJ[$r['jornada']][] = $r; }
        $teamsUsed = [];
        foreach ($byJ as $jn => $ms) {
            $seen = [];
            foreach ($ms as $r) {
                if ($r['home'] === $r['away']) { $errors[] = "Jornada {$jn}: un equipo no puede jugar contra sí mismo."; }
                foreach (['home', 'away'] as $side) {
                    $tid = $r[$side];
                    if (isset($seen[$tid])) { $errors[] = "Jornada {$jn}: un equipo aparece dos veces."; }
                    $seen[$tid] = true; $teamsUsed[$tid] = true;
                }
            }
        }
        if ($teamsUsed) {
            $ids = array_keys($teamsUsed);
            $valid = Database::all("SELECT id FROM teams WHERE league_id = ? AND id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")", array_merge([$tournament['league_id']], $ids));
            if (count($valid) !== count($ids)) { $errors[] = 'Hay equipos que no pertenecen a la liga del torneo.'; }
        }
        if (!$rowsIn) { $errors[] = 'No hay partidos válidos para guardar.'; }

        $errors = array_values(array_unique($errors));
        if ($errors) {
            $importRows = $rowsIn; $showImport = true;
        } else {
            $pdo = Database::pdo(); $pdo->beginTransaction();
            try {
                Database::q("DELETE FROM matches WHERE tournament_id = ? AND is_final_phase = 0", [$tournamentId]);
                Database::q("DELETE FROM matchdays WHERE tournament_id = ?", [$tournamentId]);
                Database::q("DELETE FROM tournament_teams WHERE tournament_id = ?", [$tournamentId]);
                foreach (array_keys($teamsUsed) as $tid) {
                    Database::q("INSERT INTO tournament_teams (tournament_id, team_id) VALUES (?,?)", [$tournamentId, $tid]);
                }
                ksort($byJ);
                $numbers = array_keys($byJ);
                $dates = schedule_dates(count($numbers), $start, $days, $interval);
                $idx = 0;
                foreach ($numbers as $jn) {
                    $mdDate = $dates[$idx] ?? null;
                    $mdId = Database::insert("INSERT INTO matchdays (tournament_id, number, round, match_date, status) VALUES (?,?,?,?, 'scheduled')", [$tournamentId, $jn, 1, $mdDate]);
                    $dayMatches = CalendarGenerator::rotateForEquity($byJ[$jn], $idx);
                    foreach ($dayMatches as $slotIdx => $r) {
                        $mt = CalendarGenerator::slotTime($time, $intervalMin, $slotIdx);
                        Database::q("INSERT INTO matches (tournament_id, matchday_id, slot, home_team_id, away_team_id, match_date, match_time, status) VALUES (?,?,?,?,?,?,?, 'pending')", [$tournamentId, $mdId, $slotIdx + 1, $r['home'], $r['away'], $mdDate, $mt]);
                    }
                    $idx++;
                }
                if ($tournament['status'] === 'draft') { Database::q("UPDATE tournaments SET status = 'active' WHERE id = ?", [$tournamentId]); }
                $pdo->commit();
                Audit::log('import_calendar', 'calendar', $tournamentId, null, ['jornadas' => count($numbers)]);
                flash('success', 'Calendario importado desde PDF (' . count($numbers) . ' jornadas).');
                redirect(base_url('admin/matches.php?tournament=' . $tournamentId));
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $errors[] = 'Error al guardar: ' . $ex->getMessage();
                $importRows = $rowsIn; $showImport = true;
            }
        }
    }

    // Standard round-robin generator (only when there is no PDF/delete op).
    if (!$op) {
    // Validate teams belong to the tournament's league.
    if (count($teamIds) < 2) {
        $errors[] = 'Seleccione al menos 2 equipos.';
    } else {
        $valid = Database::all("SELECT id FROM teams WHERE league_id = ? AND id IN (" .
            implode(',', array_fill(0, count($teamIds), '?')) . ")",
            array_merge([$tournament['league_id']], $teamIds));
        $validIds = array_column($valid, 'id');
        if (count($validIds) !== count($teamIds)) {
            $errors[] = 'Algunos equipos no pertenecen a la liga del torneo.';
        }
    }

    if (!$errors) {
        try {
            $preview = CalendarGenerator::generate($teamIds, $rounds);
            $verrors = CalendarGenerator::validate($teamIds, $preview);
            $errors = array_merge($errors, $verrors);
        } catch (Throwable $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    // Confirm & persist
    if ($stage === 'confirm' && !$errors && $preview) {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Reset regular-phase schedule for this tournament.
            Database::q("DELETE FROM matches WHERE tournament_id = ? AND is_final_phase = 0", [$tournamentId]);
            Database::q("DELETE FROM matchdays WHERE tournament_id = ?", [$tournamentId]);
            // Set participating teams.
            Database::q("DELETE FROM tournament_teams WHERE tournament_id = ?", [$tournamentId]);
            foreach ($teamIds as $tid) {
                Database::q("INSERT INTO tournament_teams (tournament_id, team_id) VALUES (?,?)", [$tournamentId, $tid]);
            }
            $dates = schedule_dates(count($preview['matchdays']), $start, $days, $interval);
            foreach ($preview['matchdays'] as $idx => $md) {
                $mdDate = $dates[$idx] ?? null;
                $mdId = Database::insert(
                    "INSERT INTO matchdays (tournament_id, number, round, match_date, status) VALUES (?,?,?,?,'scheduled')",
                    [$tournamentId, $md['number'], $md['round'], $mdDate]
                );
                // Non-bye matches, rotated per matchday for equitable kickoff hours.
                $dayMatches = array_values(array_filter($md['matches'], fn($m) => $m['bye'] === null));
                $dayMatches = CalendarGenerator::rotateForEquity($dayMatches, $idx);
                foreach ($dayMatches as $slotIdx => $m) {
                    $mt = CalendarGenerator::slotTime($time, $intervalMin, $slotIdx);
                    Database::q(
                        "INSERT INTO matches (tournament_id, matchday_id, slot, home_team_id, away_team_id, match_date, match_time, venue, status)
                         VALUES (?,?,?,?,?,?,?,?, 'pending')",
                        [$tournamentId, $mdId, $slotIdx + 1, $m['home'], $m['away'], $mdDate, $mt, $venue ?: null]
                    );
                }
            }
            if ($tournament['status'] === 'draft') {
                Database::q("UPDATE tournaments SET status = 'active' WHERE id = ?", [$tournamentId]);
            }
            $pdo->commit();
            Audit::log('generate_calendar', 'calendar', $tournamentId, null, $preview['stats']);
            flash('success', 'Calendario generado y guardado (' . $preview['stats']['matchdays'] . ' jornadas, ' . $preview['stats']['total_matches'] . ' partidos).');
            redirect(base_url('admin/matches.php?tournament=' . $tournamentId));
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $errors[] = 'Error al guardar: ' . $ex->getMessage();
        }
    }
    } // end standard generator (no PDF/delete op)
}

$PAGE_TITLE = 'Generador de calendario';
$ACTIVE = 'calendar';
require 'partials/head.php';

// Load teams for the selected tournament's league.
$leagueTeams = $tournament ? Database::all("SELECT id, name, short_name, logo FROM teams WHERE league_id = ? AND status = 'active' ORDER BY name", [$tournament['league_id']]) : [];
$assigned = $tournament ? array_column(Database::all("SELECT team_id FROM tournament_teams WHERE tournament_id = ?", [$tournamentId]), 'team_id') : [];
if (!$teamIds && $assigned) { $teamIds = array_map('intval', $assigned); }
?>
<div class="page-head">
    <h1>Generador de calendario</h1>
    <p>Crea automáticamente el fixture (todos contra todos) con validación profesional.</p>
</div>

<?php if (!$tournaments): ?>
    <div class="empty-state card"><div class="es-icon">📅</div><h2>Primero crea un torneo</h2><p>El calendario se genera para un torneo con equipos.</p><a class="btn" href="<?= e(base_url('admin/tournaments.php?action=new')) ?>">+ Nuevo torneo</a></div>
<?php else: ?>

<form method="get" class="card mb-3">
    <div class="field" style="margin-bottom:0">
        <label for="tournament">Torneo</label>
        <select class="select" id="tournament" name="tournament" onchange="this.form.submit()">
            <option value="">Seleccione un torneo…</option>
            <?php foreach ($tournaments as $tr): ?>
                <option value="<?= (int)$tr['id'] ?>"<?= selected($tr['id'], $tournamentId) ?>><?= e($tr['league_name']) ?> — <?= e($tr['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($tournament): ?>
    <?php foreach ($errors as $er): ?><div class="alert alert-danger"><span><?= e($er) ?></span></div><?php endforeach; ?>

    <?php if ($showImport):
        $teamOptsAll = []; foreach ($leagueTeams as $lt) { $teamOptsAll[$lt['id']] = team_display($lt); }
    ?>
        <!-- PDF IMPORT — EDITABLE PREVIEW -->
        <div class="card mb-3">
            <div class="section-head" style="margin-bottom:1rem">
                <div><h2 style="font-size:1.2rem;margin:0">Revisar calendario importado</h2>
                <p class="muted" style="margin:0">Corrige cualquier equipo antes de guardar. El sistema valida que ningún equipo juegue dos veces en la misma jornada.</p></div>
                <?php if ($importStats): ?><div class="flex gap-1 wrap"><span class="badge">Detectados: <?= (int)$importStats['detected'] ?></span><span class="badge badge-success">Reconocidos: <?= (int)$importStats['matched'] ?></span><span class="badge"><?= (int)$importStats['jornadas'] ?> jornadas</span></div><?php endif; ?>
            </div>
            <form method="post">
                <?= Security::csrfField() ?>
                <input type="hidden" name="op" value="import_confirm">
                <input type="hidden" name="tournament" value="<?= (int)$tournamentId ?>">
                <div class="table-wrap mb-2">
                    <table class="data">
                        <thead><tr><th style="width:90px">Jornada</th><th>Local</th><th>Visitante</th><th>Quitar</th></tr></thead>
                        <tbody>
                        <?php foreach ($importRows as $i => $r): ?>
                            <tr>
                                <td><input class="input" type="number" min="1" style="width:80px" name="mj[<?= $i ?>]" value="<?= (int)$r['jornada'] ?>"></td>
                                <td><select class="select" name="mh[<?= $i ?>]"><?= options($teamOptsAll, $r['home'], '—') ?></select></td>
                                <td><select class="select" name="ma[<?= $i ?>]"><?= options($teamOptsAll, $r['away'], '—') ?></select></td>
                                <td><label class="help"><input type="checkbox" name="rm[<?= $i ?>]" value="1"> Quitar</label></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <h3>Fechas y horarios</h3>
                <div class="form-row">
                    <div class="field"><label>Fecha inicial</label><input class="input" type="date" name="start_date" value="<?= e($formParams['start'] ?? date('Y-m-d')) ?>"></div>
                    <div class="field"><label>Intervalo entre jornadas (días)</label><input class="input" type="number" min="1" name="interval" value="<?= e($formParams['interval'] ?? 7) ?>"></div>
                    <div class="field"><label>Hora del primer partido</label><input class="input" type="time" name="match_time" value="<?= e($formParams['time'] ?? '15:00') ?>"></div>
                    <div class="field"><label>Intervalo entre partidos (min)</label><input class="input" type="number" min="0" step="15" name="interval_min" value="<?= e($formParams['intervalMin'] ?? 120) ?>"></div>
                </div>
                <div class="field">
                    <label>Días de juego</label>
                    <div class="check-grid">
                        <?php $selDays = $formParams['days'] ?? [6,7]; foreach ($DAYS as $num => $name): ?>
                            <label class="check-item"><input type="checkbox" name="days[]" value="<?= $num ?>"<?= checked(in_array($num, array_map('intval',$selDays), true)) ?>> <?= e($name) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="page-actions mt-3">
                    <button class="btn" type="submit">✔ Confirmar y guardar calendario</button>
                    <a class="btn btn-ghost" href="<?= e(base_url('admin/calendar.php?tournament=' . $tournamentId)) ?>">Cancelar</a>
                </div>
            </form>
        </div>
    <?php elseif ($preview && !$errors && $stage !== 'confirm'): ?>
        <!-- PREVIEW STAGE -->
        <div class="card mb-3">
            <div class="section-head" style="margin-bottom:1rem">
                <div><h2 style="font-size:1.2rem;margin:0">Previsualización del calendario</h2>
                <p class="muted" style="margin:0">Revisa el fixture antes de confirmar.</p></div>
                <div class="flex gap-1 wrap">
                    <span class="badge badge-success">Válido</span>
                    <span class="badge"><?= (int)$preview['stats']['teams'] ?> equipos</span>
                    <span class="badge"><?= (int)$preview['stats']['matchdays'] ?> jornadas</span>
                    <span class="badge"><?= (int)$preview['stats']['total_matches'] ?> partidos</span>
                    <?php if ($preview['stats']['has_bye']): ?><span class="badge badge-warning">Con descansos</span><?php endif; ?>
                </div>
            </div>
            <?php
            $teamMap = [];
            foreach ($leagueTeams as $lt) { $teamMap[$lt['id']] = $lt; }
            $dates = schedule_dates(count($preview['matchdays']), $formParams['start'], $formParams['days'], $formParams['interval']);
            $intervalMin = (int)($formParams['intervalMin'] ?? 120);
            // Build the display/equity schedule (rotated per matchday, staggered times).
            $equitySchedule = [];
            foreach ($preview['matchdays'] as $idx => $md) {
                $dm = CalendarGenerator::rotateForEquity(array_values(array_filter($md['matches'], fn($m) => $m['bye'] === null)), $idx);
                $rows = [];
                foreach ($dm as $s => $m) { $rows[] = ['home'=>$m['home'],'away'=>$m['away'],'time'=>CalendarGenerator::slotTime($formParams['time'], $intervalMin, $s)]; }
                $equitySchedule[$idx] = $rows;
            }
            $equityWarnings = CalendarGenerator::validateTimeEquity($equitySchedule);
            ?>
            <?php if ($equityWarnings): ?>
                <div class="alert alert-warning"><span>⚠️ Reparto de horarios mejorable: <?= e($equityWarnings[0]) ?> Aún así, puedes ajustar cada horario manualmente después.</span></div>
            <?php else: ?>
                <div class="alert alert-success"><span>✔ Horarios escalonados y repartidos equitativamente: ningún equipo juega siempre a la misma hora.</span></div>
            <?php endif; ?>
            <?php foreach ($preview['matchdays'] as $idx => $md): ?>
                <div class="mb-2">
                    <div class="flex justify-between items-center" style="margin-bottom:.4rem">
                        <strong>Jornada <?= (int)$md['number'] ?></strong>
                        <span class="muted" style="font-size:.82rem">Vuelta <?= (int)$md['round'] ?> · <?= e(fmt_date($dates[$idx] ?? null)) ?></span>
                    </div>
                    <?php foreach (array_filter($md['matches'], fn($m) => $m['bye'] !== null) as $m): ?>
                        <div class="match-card card" style="grid-template-columns:1fr;padding:.6rem 1rem">
                            <span class="muted">Descansa: <?= e(team_display($teamMap[$m['bye']] ?? null)) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach ($equitySchedule[$idx] as $slotIdx => $m): ?>
                        <div class="match-card card" style="padding:.6rem 1rem">
                            <div class="match-team"><?= media_thumb($teamMap[$m['home']]['logo'] ?? null, team_display($teamMap[$m['home']] ?? null)) ?> <span class="name"><?= e(team_display($teamMap[$m['home']] ?? null)) ?></span></div>
                            <div style="text-align:center">
                                <div class="match-score"><span class="vs">vs</span></div>
                                <div class="match-meta"><span class="badge badge-muted">Partido <?= $slotIdx + 1 ?> · <?= e($m['time'] ?? '—') ?></span></div>
                            </div>
                            <div class="match-team away"><span class="name"><?= e(team_display($teamMap[$m['away']] ?? null)) ?></span> <?= media_thumb($teamMap[$m['away']]['logo'] ?? null, team_display($teamMap[$m['away']] ?? null)) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <form method="post" class="page-actions mt-3">
                <?= Security::csrfField() ?>
                <input type="hidden" name="stage" value="confirm">
                <input type="hidden" name="tournament" value="<?= (int)$tournamentId ?>">
                <?php foreach ($teamIds as $tid): ?><input type="hidden" name="team_ids[]" value="<?= (int)$tid ?>"><?php endforeach; ?>
                <input type="hidden" name="rounds" value="<?= (int)$formParams['rounds'] ?>">
                <input type="hidden" name="start_date" value="<?= e($formParams['start']) ?>">
                <?php foreach ((array)$formParams['days'] as $d): ?><input type="hidden" name="days[]" value="<?= (int)$d ?>"><?php endforeach; ?>
                <input type="hidden" name="match_time" value="<?= e($formParams['time']) ?>">
                <input type="hidden" name="interval" value="<?= (int)$formParams['interval'] ?>">
                <input type="hidden" name="interval_min" value="<?= (int)($formParams['intervalMin'] ?? 120) ?>">
                <input type="hidden" name="venue" value="<?= e($formParams['venue']) ?>">
                <button class="btn" type="submit">✔ Confirmar y guardar</button>
                <a class="btn btn-ghost" href="<?= e(base_url('admin/calendar.php?tournament=' . $tournamentId)) ?>">Regenerar / Editar</a>
            </form>
        </div>
    <?php else: ?>
        <!-- CONFIG STAGE -->
        <form method="post" class="card card-pad-lg">
            <?= Security::csrfField() ?>
            <input type="hidden" name="stage" value="preview">
            <input type="hidden" name="tournament" value="<?= (int)$tournamentId ?>">
            <h3 style="margin-top:0">Equipos participantes</h3>
            <?php if (!$leagueTeams): ?>
                <div class="alert alert-warning"><span>La liga de este torneo no tiene equipos. <a href="<?= e(base_url('admin/teams.php?action=new')) ?>">Crea equipos</a> primero.</span></div>
            <?php else: ?>
                <p class="muted" style="font-size:.85rem;margin-top:-.3rem">Selecciona los equipos. Con número impar se generan descansos automáticamente.</p>
                <div class="check-grid mb-2">
                    <?php foreach ($leagueTeams as $lt): ?>
                        <label class="check-item"><input type="checkbox" name="team_ids[]" value="<?= (int)$lt['id'] ?>"<?= checked(in_array((int)$lt['id'], $teamIds, true)) ?>> <?= e(team_display($lt)) ?></label>
                    <?php endforeach; ?>
                </div>

                <h3 class="mt-3">Parámetros</h3>
                <div class="form-row">
                    <div class="field">
                        <label for="rounds">Vueltas</label>
                        <select class="select" id="rounds" name="rounds">
                            <option value="1"<?= selected(1, $formParams['rounds'] ?? $tournament['rounds']) ?>>Una vuelta</option>
                            <option value="2"<?= selected(2, $formParams['rounds'] ?? $tournament['rounds']) ?>>Dos vueltas</option>
                        </select>
                    </div>
                    <div class="field"><label for="start_date">Fecha inicial</label><input class="input" type="date" id="start_date" name="start_date" value="<?= e($formParams['start'] ?? date('Y-m-d')) ?>"></div>
                    <div class="field"><label for="interval">Intervalo entre jornadas (días)</label><input class="input" type="number" min="1" id="interval" name="interval" value="<?= e($formParams['interval'] ?? 7) ?>"></div>
                </div>
                <div class="form-row">
                    <div class="field"><label for="match_time">Hora del primer partido</label><input class="input" type="time" id="match_time" name="match_time" value="<?= e($formParams['time'] ?? '15:00') ?>"></div>
                    <div class="field"><label for="interval_min">Intervalo entre partidos (minutos)</label><input class="input" type="number" min="0" step="15" id="interval_min" name="interval_min" value="<?= e($formParams['intervalMin'] ?? 120) ?>"><div class="help">Cada partido de la jornada empieza a distinta hora. 0 = todos a la misma hora.</div></div>
                    <div class="field"><label for="venue">Sede predeterminada (opcional)</label><input class="input" id="venue" name="venue" value="<?= e($formParams['venue'] ?? '') ?>"></div>
                </div>
                <div class="field">
                    <label>Días de juego</label>
                    <div class="check-grid">
                        <?php $selDays = $formParams['days'] ?? [6,7]; foreach ($DAYS as $num => $name): ?>
                            <label class="check-item"><input type="checkbox" name="days[]" value="<?= $num ?>"<?= checked(in_array($num, array_map('intval',$selDays), true)) ?>> <?= e($name) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="page-actions mt-3">
                    <button class="btn" type="submit">Generar previsualización</button>
                </div>
            <?php endif; ?>
        </form>

        <!-- Importar calendario desde PDF -->
        <div class="card card-pad-lg mt-3">
            <h3 style="margin-top:0">📄 Subir calendario (PDF)</h3>
            <p class="muted" style="margin-top:-.4rem">Sube un PDF con las jornadas y partidos. El sistema los detecta y muestra una previsualización editable; solo se guarda cuando el calendario es válido (sin errores). Funciona con PDF de texto (no imágenes escaneadas) y con los nombres de equipos registrados en la liga.</p>
            <form method="post" enctype="multipart/form-data">
                <?= Security::csrfField() ?>
                <input type="hidden" name="op" value="import_pdf">
                <input type="hidden" name="tournament" value="<?= (int)$tournamentId ?>">
                <div class="field">
                    <label for="calendar_pdf">Archivo PDF</label>
                    <input class="input" type="file" id="calendar_pdf" name="calendar_pdf" accept="application/pdf,.pdf" required>
                </div>
                <button class="btn" type="submit">Subir calendario PDF</button>
            </form>
        </div>

        <!-- Eliminar calendario completo -->
        <div class="card card-pad-lg mt-3">
            <h3 style="margin-top:0">🗑️ Eliminar calendario completo</h3>
            <p class="muted" style="margin-top:-.4rem">Elimina todas las jornadas y partidos de la fase regular de este torneo. Esta acción no se puede deshacer.</p>
            <form method="post" data-confirm="¿Eliminar por completo el calendario de este torneo? Se borrarán todas las jornadas y partidos de la fase regular.">
                <?= Security::csrfField() ?>
                <input type="hidden" name="op" value="delete_calendar">
                <input type="hidden" name="tournament" value="<?= (int)$tournamentId ?>">
                <button class="btn btn-danger" type="submit">Eliminar calendario completo</button>
            </form>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php require 'partials/foot.php'; ?>
