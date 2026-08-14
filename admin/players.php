<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('players.manage');

$action = str_input('action', 'list');
$id = int_input('id');

$leagues = Database::all("SELECT id, name FROM leagues ORDER BY name");
$leagueOptions = [];
foreach ($leagues as $l) { $leagueOptions[$l['id']] = $l['name']; }

$POSITIONS = ['Portero', 'Defensa', 'Mediocampista', 'Delantero'];

/* ---- Handle POST (create / update) ------------------------------------- */
if (is_post()) {
    Security::requireCsrf();
    $leagueId = int_input('league_id');
    $firstName = str_input('first_name');
    if (!$leagueId || $firstName === '') {
        flash('danger', 'La liga y el nombre del jugador son obligatorios.');
        redirect(base_url('admin/players.php?action=' . ($id ? 'edit&id=' . $id : 'new')));
    }
    if (!isset($leagueOptions[$leagueId])) {
        flash('danger', 'La liga seleccionada no existe.');
        redirect(base_url('admin/players.php?action=' . ($id ? 'edit&id=' . $id : 'new')));
    }
    $birthdate = str_input('birthdate');
    $position = str_input('position');

    $data = [
        'league_id'  => $leagueId,
        'first_name' => $firstName,
        'last_name'  => str_input('last_name'),
        'nickname'   => str_input('nickname'),
        'birthdate'  => preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate) ? $birthdate : null,
        'position'   => in_array($position, $POSITIONS, true) ? $position : ($position !== '' ? $position : null),
        'status'     => in_array(str_input('status'), ['active','archived'], true) ? str_input('status') : 'active',
        'notes'      => str_input('notes'),
    ];

    try {
        $photo = Upload::image('photo', 'players');
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
        redirect(base_url('admin/players.php?action=' . ($id ? 'edit&id=' . $id : 'new')));
    }

    if ($id) {
        $before = Database::one("SELECT * FROM players WHERE id = ?", [$id]);
        if (!$before) { redirect(base_url('admin/players.php')); }
        if (post('remove_photo') && $before['photo']) { Upload::delete($before['photo']); $data['photo'] = null; }
        if ($photo) { if ($before['photo']) Upload::delete($before['photo']); $data['photo'] = $photo; }

        $sets = []; $params = [];
        foreach ($data as $k => $v) { $sets[] = "$k = ?"; $params[] = $v; }
        $params[] = $id;
        Database::q("UPDATE players SET " . implode(',', $sets) . " WHERE id = ?", $params);
        Audit::log('update', 'players', $id, $before, $data);
        flash('success', 'Jugador actualizado correctamente.');
    } else {
        $data['photo'] = $photo;
        $cols = implode(',', array_keys($data));
        $ph   = implode(',', array_fill(0, count($data), '?'));
        $newId = Database::insert("INSERT INTO players ($cols) VALUES ($ph)", array_values($data));
        Audit::log('create', 'players', $newId, null, $data);
        flash('success', 'Jugador creado correctamente.');
    }
    redirect(base_url('admin/players.php'));
}

/* ---- Delete ------------------------------------------------------------- */
if ($action === 'delete' && $id) {
    Security::requireCsrf();
    $before = Database::one("SELECT * FROM players WHERE id = ?", [$id]);
    if ($before) {
        Upload::delete($before['photo']);
        Database::q("DELETE FROM players WHERE id = ?", [$id]);
        Audit::log('delete', 'players', $id, $before, null);
        flash('success', 'Jugador eliminado.');
    }
    redirect(base_url('admin/players.php'));
}

$PAGE_TITLE = 'Jugadores';
$ACTIVE = 'players';

/* ---- Form view (new/edit) ---------------------------------------------- */
if ($action === 'new' || $action === 'edit') {
    if (!$leagues) {
        require 'partials/head.php';
        echo '<div class="empty-state card"><div class="es-icon">👤</div><h2>Primero crea una liga</h2><p>Los jugadores pertenecen a una liga.</p><a class="btn" href="' . e(base_url('admin/leagues.php?action=new')) . '">+ Crear Liga</a></div>';
        require 'partials/foot.php'; exit;
    }
    $player = $action === 'edit' && $id ? Database::one("SELECT * FROM players WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$player) { redirect(base_url('admin/players.php')); }
    $v = fn($k, $d = '') => e($player[$k] ?? $d);
    $positionOptions = [];
    foreach ($POSITIONS as $p) { $positionOptions[$p] = $p; }

    // Tournament registration history for this player (read-only).
    $registrations = $player ? Database::all(
        "SELECT r.dorsal, tr.name AS tournament_name, te.name AS team_name, te.short_name AS team_short
         FROM registrations r
         JOIN tournaments tr ON tr.id = r.tournament_id
         JOIN teams te ON te.id = r.team_id
         WHERE r.player_id = ?
         ORDER BY tr.created_at DESC", [$player['id']]
    ) : [];

    require 'partials/head.php';
    ?>
    <div class="page-head">
        <h1><?= $action === 'edit' ? 'Editar jugador' : 'Nuevo jugador' ?></h1>
        <p>Datos del jugador. Cada jugador pertenece a una sola liga.</p>
    </div>
    <form method="post" enctype="multipart/form-data" class="card card-pad-lg">
        <?= Security::csrfField() ?>
        <div class="form-row">
            <div class="field">
                <label for="league_id">Liga *</label>
                <select class="select" id="league_id" name="league_id" required><?= options($leagueOptions, $player['league_id'] ?? null, 'Seleccione…') ?></select>
            </div>
            <div class="field">
                <label for="first_name">Nombre *</label>
                <input class="input" id="first_name" name="first_name" required value="<?= $v('first_name') ?>">
            </div>
            <div class="field">
                <label for="last_name">Apellido</label>
                <input class="input" id="last_name" name="last_name" value="<?= $v('last_name') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="nickname">Apodo</label>
                <input class="input" id="nickname" name="nickname" value="<?= $v('nickname') ?>" placeholder="Nombre para mostrar">
            </div>
            <div class="field">
                <label for="position">Posición</label>
                <select class="select" id="position" name="position">
                    <?= options($positionOptions, $player['position'] ?? null, 'Sin definir') ?>
                </select>
            </div>
            <div class="field">
                <label for="birthdate">Fecha de nacimiento</label>
                <input class="input" type="date" id="birthdate" name="birthdate" value="<?= $v('birthdate') ?>">
            </div>
        </div>
        <div class="field">
            <label for="status">Estado</label>
            <select class="select" id="status" name="status">
                <option value="active"<?= selected('active', $player['status'] ?? 'active') ?>>Activo</option>
                <option value="archived"<?= selected('archived', $player['status'] ?? '') ?>>Archivado</option>
            </select>
        </div>
        <div class="field">
            <label for="notes">Notas</label>
            <textarea class="textarea" id="notes" name="notes"><?= $v('notes') ?></textarea>
        </div>

        <h3 class="mt-3">Foto</h3>
        <div class="field">
            <label for="photo">Foto del jugador (JPG, PNG, WEBP)</label>
            <input class="input" type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp">
            <?php if (!empty($player['photo'])): ?>
                <div class="mt-1 flex items-center gap-1">
                    <?= media_thumb($player['photo'], player_name($player), 'team-logo avatar-lg', true) ?>
                    <label class="help"><input type="checkbox" name="remove_photo" value="1"> Eliminar</label>
                </div>
            <?php endif; ?>
        </div>

        <div class="page-actions mt-3">
            <button class="btn" type="submit">Guardar jugador</button>
            <a class="btn btn-ghost" href="<?= e(base_url('admin/players.php')) ?>">Cancelar</a>
        </div>
    </form>

    <?php if ($player): ?>
        <div class="card card-pad-lg mt-3">
            <h3>Historial por torneo</h3>
            <p class="muted" style="font-size:.85rem;margin-top:-.5rem">Registros del jugador en cada torneo. Se gestionan desde el plantel de cada partido/torneo.</p>
            <?php if (!$registrations): ?>
                <div class="empty-state">
                    <div class="es-icon">📋</div>
                    <p>Este jugador aún no está inscrito en ningún torneo.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Torneo</th><th>Equipo</th><th class="num">Dorsal</th></tr></thead>
                        <tbody>
                        <?php foreach ($registrations as $r): ?>
                            <tr>
                                <td><?= e($r['tournament_name']) ?></td>
                                <td><?= e(team_display(['name' => $r['team_name'], 'short_name' => $r['team_short']])) ?></td>
                                <td class="num"><?= $r['dorsal'] !== null ? (int)$r['dorsal'] : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php
    require 'partials/foot.php';
    exit;
}

/* ---- List view ---------------------------------------------------------- */
$filterLeague = int_input('league');
if ($filterLeague && !isset($leagueOptions[$filterLeague])) { $filterLeague = null; }

if ($filterLeague) {
    $players = Database::all("SELECT p.*, l.name AS league_name FROM players p
        JOIN leagues l ON l.id = p.league_id
        WHERE p.league_id = ? ORDER BY p.first_name, p.last_name", [$filterLeague]);
} else {
    $players = Database::all("SELECT p.*, l.name AS league_name FROM players p
        JOIN leagues l ON l.id = p.league_id ORDER BY l.name, p.first_name, p.last_name");
}
require 'partials/head.php';
?>
<div class="page-head flex justify-between items-center wrap">
    <div>
        <h1>Jugadores</h1>
        <p>Gestiona los jugadores. Cada jugador pertenece a una sola liga.</p>
    </div>
    <div class="page-actions"><a class="btn" href="<?= e(base_url('admin/players.php?action=new')) ?>">+ Nuevo jugador</a></div>
</div>

<?php if (!$leagues): ?>
    <div class="empty-state card">
        <div class="es-icon">👤</div>
        <h2>Primero crea una liga</h2>
        <p>Los jugadores pertenecen a una liga. Crea una para comenzar.</p>
        <a class="btn" href="<?= e(base_url('admin/leagues.php?action=new')) ?>">+ Crear Liga</a>
    </div>
<?php else: ?>
    <div class="card card-pad-lg" style="margin-bottom:1rem">
        <div class="field" style="margin:0;max-width:340px">
            <label for="league">Filtrar por liga</label>
            <select class="select" id="league" name="league" onchange="location.href='<?= e(base_url('admin/players.php')) ?>' + (this.value ? '?league=' + this.value : '')">
                <?= options($leagueOptions, $filterLeague, 'Todas las ligas') ?>
            </select>
        </div>
    </div>

    <?php if (!$players): ?>
        <div class="empty-state card">
            <div class="es-icon">👤</div>
            <h2>Sin jugadores</h2>
            <p>No hay jugadores <?= $filterLeague ? 'en esta liga' : 'todavía' ?>. Crea el primero.</p>
            <a class="btn" href="<?= e(base_url('admin/players.php?action=new')) ?>">+ Nuevo jugador</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Jugador</th><th>Posición</th><th>Liga</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($players as $p): ?>
                    <tr>
                        <td>
                            <div class="team-cell flex items-center gap-1">
                                <?= media_thumb($p['photo'], player_name($p), 'team-logo', true) ?>
                                <strong><?= e(player_name($p)) ?></strong>
                            </div>
                        </td>
                        <td><?= $p['position'] !== null && $p['position'] !== '' ? e($p['position']) : '—' ?></td>
                        <td><?= e($p['league_name']) ?></td>
                        <td><span class="badge <?= $p['status']==='active'?'badge-success':'badge-muted' ?>"><?= $p['status']==='active'?'Activo':'Archivado' ?></span></td>
                        <td>
                            <div class="flex gap-1 wrap">
                                <a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/players.php?action=edit&id=' . $p['id'])) ?>">Editar</a>
                                <form method="post" action="<?= e(base_url('admin/players.php?action=delete&id=' . $p['id'])) ?>" data-confirm="¿Eliminar este jugador? Esta acción no se puede deshacer.">
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
