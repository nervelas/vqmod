<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('teams.manage');

/**
 * Ensure the players table has a team_id column (players belong to a team).
 * Runs a one-time, idempotent migration on existing installations.
 */
function fl_ensure_players_team_column(): void
{
    $has = Database::scalar(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'players' AND column_name = 'team_id'"
    );
    if (!$has) {
        Database::q("ALTER TABLE players ADD COLUMN team_id INT UNSIGNED NULL AFTER league_id");
        try {
            Database::q("ALTER TABLE players ADD CONSTRAINT fk_player_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL");
        } catch (Throwable $e) { /* FK optional; column is what matters */ }
    }
}
fl_ensure_players_team_column();

$POSITIONS = ['Portero', 'Defensa', 'Mediocampista', 'Delantero'];

$action = str_input('action', 'list');
$id = int_input('id');

$leagues = Database::all("SELECT id, name FROM leagues ORDER BY name");
$leagueOptions = [];
foreach ($leagues as $l) { $leagueOptions[$l['id']] = $l['name']; }

/* ---- POST handling ------------------------------------------------------ */
if (is_post()) {
    Security::requireCsrf();
    $op = str_input('op', 'save_team');

    /* ---- Save a player that belongs to a team --------------------------- */
    if ($op === 'save_player') {
        $teamId = int_input('team_id');
        $team = $teamId ? Database::one("SELECT * FROM teams WHERE id = ?", [$teamId]) : null;
        if (!$team) { flash('danger', 'Equipo no válido.'); redirect(base_url('admin/teams.php')); }

        $playerId = int_input('player_id');
        $first = str_input('first_name');
        if ($first === '') {
            flash('danger', 'El nombre del jugador es obligatorio.');
            redirect(base_url('admin/teams.php?action=edit&id=' . $teamId . ($playerId ? '&player=' . $playerId : '') . '#jugadores'));
        }
        $birth = str_input('birthdate');
        $birth = preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth) ? $birth : null;
        $position = in_array(str_input('position'), $POSITIONS, true) ? str_input('position') : (str_input('position') ?: null);

        try {
            $photo = Upload::image('photo', 'players');
        } catch (RuntimeException $ex) {
            flash('danger', $ex->getMessage());
            redirect(base_url('admin/teams.php?action=edit&id=' . $teamId . ($playerId ? '&player=' . $playerId : '') . '#jugadores'));
        }

        $data = [
            'league_id' => (int)$team['league_id'],
            'team_id'   => $teamId,
            'first_name'=> $first,
            'last_name' => str_input('last_name') ?: null,
            'position'  => $position,
            'birthdate' => $birth,
            'notes'     => (string)post('notes', ''),   // Historial
            'status'    => 'active',
        ];

        if ($playerId) {
            $before = Database::one("SELECT * FROM players WHERE id = ? AND team_id = ?", [$playerId, $teamId]);
            if (!$before) { redirect(base_url('admin/teams.php?action=edit&id=' . $teamId)); }
            if (post('remove_photo') && $before['photo']) { Upload::delete($before['photo']); $data['photo'] = null; }
            if ($photo) { if ($before['photo']) Upload::delete($before['photo']); $data['photo'] = $photo; }
            $sets = []; $params = [];
            foreach ($data as $k => $v) { $sets[] = "$k = ?"; $params[] = $v; }
            $params[] = $playerId;
            Database::q("UPDATE players SET " . implode(',', $sets) . " WHERE id = ?", $params);
            Audit::log('update', 'players', $playerId, $before, $data);
            flash('success', 'Jugador actualizado.');
        } else {
            $data['photo'] = $photo;
            $cols = implode(',', array_keys($data));
            $ph   = implode(',', array_fill(0, count($data), '?'));
            $newId = Database::insert("INSERT INTO players ($cols) VALUES ($ph)", array_values($data));
            Audit::log('create', 'players', $newId, null, $data);
            flash('success', 'Jugador agregado al equipo.');
        }
        redirect(base_url('admin/teams.php?action=edit&id=' . $teamId . '#jugadores'));
    }

    /* ---- Delete a player ------------------------------------------------ */
    if ($op === 'del_player') {
        $teamId = int_input('team_id');
        $playerId = int_input('player_id');
        $before = Database::one("SELECT * FROM players WHERE id = ? AND team_id = ?", [$playerId, $teamId]);
        if ($before) {
            Upload::delete($before['photo']);
            Database::q("DELETE FROM players WHERE id = ?", [$playerId]);
            Audit::log('delete', 'players', $playerId, $before, null);
            flash('success', 'Jugador eliminado.');
        }
        redirect(base_url('admin/teams.php?action=edit&id=' . $teamId . '#jugadores'));
    }

    /* ---- Save the team (create / update) -------------------------------- */
    $leagueId = int_input('league_id');
    $name = str_input('name');
    if (!$leagueId || $name === '') {
        flash('danger', 'La liga y el nombre del equipo son obligatorios.');
        redirect(base_url('admin/teams.php?action=' . ($id ? 'edit&id=' . $id : 'new')));
    }
    if (!isset($leagueOptions[$leagueId])) {
        flash('danger', 'La liga seleccionada no existe.');
        redirect(base_url('admin/teams.php?action=' . ($id ? 'edit&id=' . $id : 'new')));
    }
    $slug = slugify(str_input('slug') ?: $name);
    $exists = Database::scalar("SELECT id FROM teams WHERE league_id = ? AND slug = ? AND id <> ?", [$leagueId, $slug, $id ?? 0]);
    if ($exists) { $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4); }

    $data = [
        'league_id'   => $leagueId,
        'name'        => $name,
        'short_name'  => str_input('short_name'),
        'slug'        => $slug,
        'color1'      => preg_match('/^#[0-9A-Fa-f]{6}$/', str_input('color1')) ? str_input('color1') : null,
        'color2'      => preg_match('/^#[0-9A-Fa-f]{6}$/', str_input('color2')) ? str_input('color2') : null,
        'description' => str_input('description'),
        'info'        => (string)post('info', ''),
        'status'      => in_array(str_input('status'), ['active','archived'], true) ? str_input('status') : 'active',
    ];

    try {
        $logo = Upload::image('logo', 'teams');
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
        redirect(base_url('admin/teams.php?action=' . ($id ? 'edit&id=' . $id : 'new')));
    }

    if ($id) {
        $before = Database::one("SELECT * FROM teams WHERE id = ?", [$id]);
        if (!$before) { redirect(base_url('admin/teams.php')); }
        if (post('remove_logo') && $before['logo']) { Upload::delete($before['logo']); $data['logo'] = null; }
        if ($logo) { if ($before['logo']) Upload::delete($before['logo']); $data['logo'] = $logo; }
        $sets = []; $params = [];
        foreach ($data as $k => $v) { $sets[] = "$k = ?"; $params[] = $v; }
        $params[] = $id;
        Database::q("UPDATE teams SET " . implode(',', $sets) . " WHERE id = ?", $params);
        Audit::log('update', 'teams', $id, $before, $data);
        flash('success', 'Equipo actualizado correctamente.');
        redirect(base_url('admin/teams.php?action=edit&id=' . $id));
    } else {
        $data['logo'] = $logo;
        $cols = implode(',', array_keys($data));
        $ph   = implode(',', array_fill(0, count($data), '?'));
        $newId = Database::insert("INSERT INTO teams ($cols) VALUES ($ph)", array_values($data));
        Audit::log('create', 'teams', $newId, null, $data);
        flash('success', 'Equipo creado. Ahora puedes agregar sus jugadores.');
        redirect(base_url('admin/teams.php?action=edit&id=' . $newId . '#jugadores'));
    }
}

/* ---- Delete team -------------------------------------------------------- */
if ($action === 'delete' && $id) {
    Security::requireCsrf();
    $before = Database::one("SELECT * FROM teams WHERE id = ?", [$id]);
    if ($before) {
        Upload::delete($before['logo']);
        // Remove photos of the team's players too.
        foreach (Database::all("SELECT photo FROM players WHERE team_id = ?", [$id]) as $pl) { Upload::delete($pl['photo']); }
        Database::q("DELETE FROM teams WHERE id = ?", [$id]);
        Audit::log('delete', 'teams', $id, $before, null);
        flash('success', 'Equipo eliminado.');
    }
    redirect(base_url('admin/teams.php'));
}

$PAGE_TITLE = 'Equipos';
$ACTIVE = 'teams';

/* ---- Form view (new/edit) ---------------------------------------------- */
if ($action === 'new' || $action === 'edit') {
    if (!$leagues) {
        require 'partials/head.php';
        echo '<div class="empty-state card"><div class="es-icon">🛡️</div><h2>Primero crea una liga</h2><p>Los equipos pertenecen a una liga.</p><a class="btn" href="' . e(base_url('admin/leagues.php?action=new')) . '">+ Crear Liga</a></div>';
        require 'partials/foot.php'; exit;
    }
    $team = $action === 'edit' && $id ? Database::one("SELECT * FROM teams WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$team) { redirect(base_url('admin/teams.php')); }
    $v = fn($k, $d = '') => e($team[$k] ?? $d);

    // Player being edited (if any)
    $editPlayerId = int_input('player');
    $editPlayer = ($team && $editPlayerId) ? Database::one("SELECT * FROM players WHERE id = ? AND team_id = ?", [$editPlayerId, $team['id']]) : null;
    $players = $team ? Database::all("SELECT * FROM players WHERE team_id = ? ORDER BY first_name, last_name", [$team['id']]) : [];

    require 'partials/head.php';
    ?>
    <div class="page-head">
        <h1><?= $action === 'edit' ? 'Editar equipo' : 'Nuevo equipo' ?></h1>
        <p>Identidad, datos y jugadores del equipo. Cada equipo pertenece a una sola liga.</p>
    </div>
    <form method="post" enctype="multipart/form-data" class="card card-pad-lg">
        <?= Security::csrfField() ?>
        <input type="hidden" name="op" value="save_team">
        <div class="form-row">
            <div class="field">
                <label for="league_id">Liga *</label>
                <select class="select" id="league_id" name="league_id" required><?= options($leagueOptions, $team['league_id'] ?? null, 'Seleccione…') ?></select>
            </div>
            <div class="field">
                <label for="name">Nombre del equipo *</label>
                <input class="input" id="name" name="name" required value="<?= $v('name') ?>" data-slug-source="#slug">
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="short_name">Nombre corto</label>
                <input class="input" id="short_name" name="short_name" value="<?= $v('short_name') ?>" placeholder="Ej: BAR">
            </div>
            <div class="field">
                <label for="slug">Slug (URL)</label>
                <input class="input" id="slug" name="slug" value="<?= $v('slug') ?>" placeholder="se-genera-automaticamente">
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="color1">Color primario</label>
                <input class="input" type="color" id="color1" name="color1" value="<?= $v('color1', '#1e293b') ?>">
            </div>
            <div class="field">
                <label for="color2">Color secundario</label>
                <input class="input" type="color" id="color2" name="color2" value="<?= $v('color2', '#f8fafc') ?>">
            </div>
            <div class="field">
                <label for="status">Estado</label>
                <select class="select" id="status" name="status">
                    <option value="active"<?= selected('active', $team['status'] ?? 'active') ?>>Activo</option>
                    <option value="archived"<?= selected('archived', $team['status'] ?? '') ?>>Archivado</option>
                </select>
            </div>
        </div>
        <div class="field">
            <label for="description">Descripción corta</label>
            <input class="input" id="description" name="description" value="<?= $v('description') ?>">
        </div>
        <div class="field">
            <label for="info">Información del equipo</label>
            <textarea class="textarea" id="info" name="info"><?= $v('info') ?></textarea>
        </div>

        <h3 class="mt-3">Escudo</h3>
        <div class="field">
            <label for="logo">Escudo / Logo (JPG, PNG, WEBP)</label>
            <input class="input" type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.webp">
            <?php if (!empty($team['logo'])): ?>
                <div class="mt-1 flex items-center gap-1">
                    <?= media_thumb($team['logo'], $team['name'], 'team-logo avatar-lg') ?>
                    <label class="help"><input type="checkbox" name="remove_logo" value="1"> Eliminar</label>
                </div>
            <?php endif; ?>
        </div>

        <div class="page-actions mt-3">
            <button class="btn" type="submit">Guardar equipo</button>
            <a class="btn btn-ghost" href="<?= e(base_url('admin/teams.php')) ?>">Volver a equipos</a>
        </div>
    </form>

    <?php if ($action === 'edit' && $team): ?>
        <!-- ================= Jugadores del equipo ================= -->
        <div id="jugadores" class="mt-4"></div>
        <div class="cols-2 ratio">
            <div class="card">
                <h3 style="margin-top:0">Jugadores de <?= e(team_display($team)) ?> <span class="badge badge-muted"><?= count($players) ?></span></h3>
                <?php if (!$players): ?>
                    <p class="muted">Este equipo aún no tiene jugadores. Agrégalos con el formulario de la derecha.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data" style="min-width:auto">
                            <thead><tr><th>Jugador</th><th>Posición</th><th>Nac.</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($players as $p): ?>
                                <tr>
                                    <td class="team-cell"><?= media_thumb($p['photo'], player_name($p), 'avatar', true) ?> <strong><?= e(player_name($p)) ?></strong></td>
                                    <td><?= e($p['position'] ?: '—') ?></td>
                                    <td><?= e(fmt_date($p['birthdate'])) ?></td>
                                    <td>
                                        <div class="flex gap-1 wrap">
                                            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/teams.php?action=edit&id=' . $team['id'] . '&player=' . $p['id'] . '#jugadores')) ?>">Editar</a>
                                            <form method="post" data-confirm="¿Eliminar a este jugador?">
                                                <?= Security::csrfField() ?>
                                                <input type="hidden" name="op" value="del_player">
                                                <input type="hidden" name="team_id" value="<?= (int)$team['id'] ?>">
                                                <input type="hidden" name="player_id" value="<?= (int)$p['id'] ?>">
                                                <button class="btn btn-sm btn-danger" type="submit">×</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3 style="margin-top:0"><?= $editPlayer ? 'Editar jugador' : 'Agregar jugador' ?></h3>
                <form method="post" enctype="multipart/form-data">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="op" value="save_player">
                    <input type="hidden" name="team_id" value="<?= (int)$team['id'] ?>">
                    <?php if ($editPlayer): ?><input type="hidden" name="player_id" value="<?= (int)$editPlayer['id'] ?>"><?php endif; ?>
                    <div class="form-row">
                        <div class="field"><label for="first_name">Nombre *</label><input class="input" id="first_name" name="first_name" required value="<?= e($editPlayer['first_name'] ?? '') ?>"></div>
                        <div class="field"><label for="last_name">Apellido</label><input class="input" id="last_name" name="last_name" value="<?= e($editPlayer['last_name'] ?? '') ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="position">Posición</label>
                            <select class="select" id="position" name="position">
                                <option value="">—</option>
                                <?php foreach ($POSITIONS as $pos): ?><option value="<?= e($pos) ?>"<?= selected($pos, $editPlayer['position'] ?? '') ?>><?= e($pos) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field"><label for="birthdate">Fecha de nacimiento</label><input class="input" type="date" id="birthdate" name="birthdate" value="<?= e($editPlayer['birthdate'] ?? '') ?>"></div>
                    </div>
                    <div class="field">
                        <label for="photo">Foto (JPG, PNG, WEBP)</label>
                        <input class="input" type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp">
                        <?php if (!empty($editPlayer['photo'])): ?>
                            <div class="mt-1 flex items-center gap-1">
                                <?= media_thumb($editPlayer['photo'], player_name($editPlayer), 'avatar avatar-lg', true) ?>
                                <label class="help"><input type="checkbox" name="remove_photo" value="1"> Eliminar</label>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="notes">Historial</label>
                        <textarea class="textarea" id="notes" name="notes" placeholder="Trayectoria, observaciones, historial deportivo…"><?= e($editPlayer['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="page-actions">
                        <button class="btn" type="submit"><?= $editPlayer ? 'Guardar cambios' : '+ Agregar jugador' ?></button>
                        <?php if ($editPlayer): ?><a class="btn btn-ghost" href="<?= e(base_url('admin/teams.php?action=edit&id=' . $team['id'] . '#jugadores')) ?>">Cancelar</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    <?php
    require 'partials/foot.php';
    exit;
}

/* ---- List view ---------------------------------------------------------- */
$filterLeague = int_input('league');
if ($filterLeague && !isset($leagueOptions[$filterLeague])) { $filterLeague = null; }

$sql = "SELECT t.*, l.name AS league_name,
        (SELECT COUNT(*) FROM players WHERE team_id = t.id) AS players
        FROM teams t JOIN leagues l ON l.id = t.league_id";
$params = [];
if ($filterLeague) { $sql .= " WHERE t.league_id = ?"; $params[] = $filterLeague; $sql .= " ORDER BY t.name"; }
else { $sql .= " ORDER BY l.name, t.name"; }
$teams = Database::all($sql, $params);
require 'partials/head.php';
?>
<div class="page-head flex justify-between items-center wrap">
    <div>
        <h1>Equipos</h1>
        <p>Gestiona los equipos y sus jugadores. Cada equipo pertenece a una sola liga.</p>
    </div>
    <div class="page-actions"><a class="btn" href="<?= e(base_url('admin/teams.php?action=new')) ?>">+ Nuevo equipo</a></div>
</div>

<?php if (!$leagues): ?>
    <div class="empty-state card">
        <div class="es-icon">🛡️</div>
        <h2>Primero crea una liga</h2>
        <p>Los equipos pertenecen a una liga. Crea una para comenzar.</p>
        <a class="btn" href="<?= e(base_url('admin/leagues.php?action=new')) ?>">+ Crear Liga</a>
    </div>
<?php else: ?>
    <div class="card card-pad-lg" style="margin-bottom:1rem">
        <div class="field" style="margin:0;max-width:340px">
            <label for="league">Filtrar por liga</label>
            <select class="select" id="league" name="league" onchange="location.href='<?= e(base_url('admin/teams.php')) ?>' + (this.value ? '?league=' + this.value : '')">
                <?= options($leagueOptions, $filterLeague, 'Todas las ligas') ?>
            </select>
        </div>
    </div>

    <?php if (!$teams): ?>
        <div class="empty-state card">
            <div class="es-icon">🛡️</div>
            <h2>Sin equipos</h2>
            <p>No hay equipos <?= $filterLeague ? 'en esta liga' : 'todavía' ?>. Crea el primero.</p>
            <a class="btn" href="<?= e(base_url('admin/teams.php?action=new')) ?>">+ Nuevo equipo</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Equipo</th><th>Nombre corto</th><th>Liga</th><th class="num">Jugadores</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($teams as $t): ?>
                    <tr>
                        <td>
                            <div class="team-cell flex items-center gap-1">
                                <?= media_thumb($t['logo'], team_display($t), 'team-logo') ?>
                                <strong><?= e(team_display($t)) ?></strong>
                            </div>
                        </td>
                        <td><?= $t['short_name'] !== null && $t['short_name'] !== '' ? e($t['short_name']) : '—' ?></td>
                        <td><?= e($t['league_name']) ?></td>
                        <td class="num"><?= (int)$t['players'] ?></td>
                        <td><span class="badge <?= $t['status']==='active'?'badge-success':'badge-muted' ?>"><?= $t['status']==='active'?'Activo':'Archivado' ?></span></td>
                        <td>
                            <div class="flex gap-1 wrap">
                                <a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/teams.php?action=edit&id=' . $t['id'])) ?>">Editar</a>
                                <a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/teams.php?action=edit&id=' . $t['id'] . '#jugadores')) ?>">Jugadores</a>
                                <form method="post" action="<?= e(base_url('admin/teams.php?action=delete&id=' . $t['id'])) ?>" data-confirm="¿Eliminar este equipo y sus jugadores? Esta acción no se puede deshacer.">
                                    <?= Security::csrfField() ?><button class="btn btn-sm btn-danger" type="submit">×</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php require 'partials/foot.php'; ?>
