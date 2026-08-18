<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('tournaments.manage');

$action = str_input('action', 'list');
$id = int_input('id');

/** Idempotent migrations: tournaments.banner + tournaments.description. */
(function () {
    foreach ([
        'banner'      => "ALTER TABLE tournaments ADD COLUMN banner VARCHAR(255) NULL AFTER discipline",
        'description' => "ALTER TABLE tournaments ADD COLUMN description VARCHAR(600) NULL AFTER banner",
    ] as $col => $ddl) {
        $has = Database::scalar(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'tournaments' AND column_name = ?", [$col]
        );
        if (!$has) { Database::q($ddl); }
    }
})();

$TB = [
    'goal_diff' => 'Diferencia de goles',
    'goals_for' => 'Goles a favor',
    'wins'      => 'Victorias',
    'head_to_head' => 'Enfrentamiento directo',
];

if (is_post() && $action !== 'delete') {
    Security::requireCsrf();
    $leagueId = the_league_id();   // single-league mode: always the one league
    $name = str_input('name');
    if (!$leagueId) {
        flash('danger', 'Primero configura la liga.');
        redirect(base_url('admin/leagues.php'));
    }
    if ($name === '') {
        flash('danger', 'El nombre del torneo es obligatorio.');
        redirect(base_url('admin/tournaments.php?action=' . ($id ? 'edit&id=' . $id : 'new')));
    }
    // Season (optional): reuse or create for this league.
    $seasonId = null;
    $seasonName = str_input('season_name');
    if ($seasonName !== '') {
        $seasonId = Database::scalar("SELECT id FROM seasons WHERE league_id = ? AND name = ?", [$leagueId, $seasonName]);
        if (!$seasonId) {
            $seasonId = Database::insert("INSERT INTO seasons (league_id, name) VALUES (?,?)", [$leagueId, $seasonName]);
        }
    }

    // Tiebreakers: points always first, then chosen criteria in canonical order.
    $chosen = (array)post('tiebreakers', []);
    $order = ['points'];
    foreach (array_keys($TB) as $k) { if (in_array($k, $chosen, true)) { $order[] = $k; } }

    $discipline = json_encode([
        'yellow_threshold'      => max(1, (int)int_input('d_yellow_threshold', 4)),
        'yellow_matches'        => max(0, (int)int_input('d_yellow_matches', 1)),
        'double_yellow_matches' => max(0, (int)int_input('d_double_matches', 1)),
        'red_matches'           => max(0, (int)int_input('d_red_matches', 2)),
    ], JSON_UNESCAPED_UNICODE);

    $data = [
        'league_id'   => $leagueId,
        'season_id'   => $seasonId,
        'name'        => $name,
        'slug'        => slugify(str_input('slug') ?: $name),
        'format'      => in_array(str_input('format'), ['league','cup'], true) ? str_input('format') : 'league',
        'rounds'      => int_input('rounds', 2) === 1 ? 1 : 2,
        'points_win'  => max(0, (int)int_input('points_win', 3)),
        'points_draw' => max(0, (int)int_input('points_draw', 1)),
        'points_loss' => max(0, (int)int_input('points_loss', 0)),
        'tiebreakers' => implode(',', $order),
        'discipline'  => $discipline,
        'description' => str_input('description'),
        'final_phase' => 'none',
        'status'      => in_array(str_input('status'), ['draft','active','finished'], true) ? str_input('status') : 'draft',
    ];

    // Per-tournament banner upload.
    try {
        $banner = Upload::image('banner', 'tournaments', [Upload::BANNER_W, Upload::BANNER_H]);
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
        redirect(base_url('admin/tournaments.php?action=' . ($id ? 'edit&id=' . $id : 'new')));
    }
    $curBanner = $id ? Database::scalar("SELECT banner FROM tournaments WHERE id = ?", [$id]) : null;
    if ($id && post('remove_banner') && $curBanner) { Upload::delete($curBanner); $data['banner'] = null; }
    if ($banner) { if ($curBanner) Upload::delete($curBanner); $data['banner'] = $banner; }

    if ($id) {
        $before = Database::one("SELECT * FROM tournaments WHERE id = ?", [$id]);
        $sets = []; $params = [];
        foreach ($data as $k => $v) { $sets[] = "$k = ?"; $params[] = $v; }
        $params[] = $id;
        Database::q("UPDATE tournaments SET " . implode(',', $sets) . " WHERE id = ?", $params);
        Audit::log('update', 'tournaments', $id, $before, $data);
        flash('success', 'Torneo actualizado.');
    } else {
        $cols = implode(',', array_keys($data));
        $ph   = implode(',', array_fill(0, count($data), '?'));
        $newId = Database::insert("INSERT INTO tournaments ($cols) VALUES ($ph)", array_values($data));
        Audit::log('create', 'tournaments', $newId, null, $data);
        flash('success', 'Torneo creado. Añade equipos y genera el calendario.');
    }
    redirect(base_url('admin/tournaments.php'));
}

if ($action === 'delete' && $id) {
    Security::requireCsrf();
    $before = Database::one("SELECT * FROM tournaments WHERE id = ?", [$id]);
    if ($before) {
        Database::q("DELETE FROM tournaments WHERE id = ?", [$id]);
        Audit::log('delete', 'tournaments', $id, $before, null);
        flash('success', 'Torneo eliminado.');
    }
    redirect(base_url('admin/tournaments.php'));
}

$PAGE_TITLE = 'Torneos';
$ACTIVE = 'tournaments';

if ($action === 'new' || $action === 'edit') {
    if (!the_league()) {
        require 'partials/head.php';
        echo '<div class="empty-state card"><div class="es-icon">🏆</div><h2>Primero configura la liga</h2><p>Los torneos pertenecen a la liga. Configúrala para empezar.</p><a class="btn" href="' . e(base_url('admin/leagues.php')) . '">Configurar la liga</a></div>';
        require 'partials/foot.php'; exit;
    }
    $t = $action === 'edit' && $id ? Database::one("SELECT * FROM tournaments WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$t) { redirect(base_url('admin/tournaments.php')); }
    $d = $t ? Discipline::rules($t) : Discipline::defaults();
    $tbActive = $t ? array_filter(array_map('trim', explode(',', $t['tiebreakers']))) : ['goal_diff','goals_for','wins','head_to_head'];
    $season = $t && $t['season_id'] ? Database::one("SELECT name FROM seasons WHERE id = ?", [$t['season_id']]) : null;
    $v = fn($k, $def = '') => e($t[$k] ?? $def);
    require 'partials/head.php';
    ?>
    <div class="page-head"><h1><?= $t ? 'Editar torneo' : 'Nuevo torneo' ?></h1><p>Formato, puntuación, desempates y reglas disciplinarias.</p></div>
    <form method="post" enctype="multipart/form-data" class="card card-pad-lg">
        <?= Security::csrfField() ?>
        <div class="field">
            <label for="name">Nombre del torneo *</label>
            <input class="input" id="name" name="name" required value="<?= $v('name') ?>" data-slug-source="#slug" placeholder="Ej: Torneo Masculino, Torneo Femenino…">
        </div>
        <div class="field">
            <label for="banner">Banner del torneo (JPG, PNG, WEBP)</label>
            <input class="input" type="file" id="banner" name="banner" accept=".jpg,.jpeg,.png,.webp">
            <div class="help">Imagen de cabecera de este torneo. Se muestra en la página pública. Puedes cambiarla cuando quieras.</div>
            <?php if (!empty($t['banner'])): ?>
                <div class="mt-1"><img src="<?= e(base_url($t['banner'])) ?>" alt="" style="max-height:90px;border-radius:10px">
                <label class="help"><input type="checkbox" name="remove_banner" value="1"> Eliminar banner</label></div>
            <?php endif; ?>
        </div>
        <div class="field">
            <label for="description">Descripción del torneo</label>
            <textarea class="textarea" id="description" name="description" rows="2" placeholder="Breve descripción que se muestra en la portada (Inicio) y en la tarjeta del torneo."><?= $v('description') ?></textarea>
        </div>
        <div class="form-row">
            <div class="field"><label for="slug">Slug</label><input class="input" id="slug" name="slug" value="<?= $v('slug') ?>"></div>
            <div class="field"><label for="season_name">Temporada (opcional)</label><input class="input" id="season_name" name="season_name" value="<?= e($season['name'] ?? '') ?>" placeholder="Ej: Apertura 2026"></div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="format">Formato</label>
                <select class="select" id="format" name="format">
                    <option value="league"<?= selected('league', $t['format'] ?? 'league') ?>>Liga (todos contra todos)</option>
                    <option value="cup"<?= selected('cup', $t['format'] ?? '') ?>>Copa / Eliminatoria</option>
                </select>
            </div>
            <div class="field">
                <label for="rounds">Vueltas</label>
                <select class="select" id="rounds" name="rounds">
                    <option value="1"<?= selected('1', $t['rounds'] ?? 2) ?>>Una vuelta</option>
                    <option value="2"<?= selected('2', $t['rounds'] ?? 2) ?>>Dos vueltas</option>
                </select>
            </div>
        </div>

        <h3 class="mt-3">Puntuación</h3>
        <div class="form-row">
            <div class="field"><label for="points_win">Victoria</label><input class="input" type="number" min="0" id="points_win" name="points_win" value="<?= e($t['points_win'] ?? 3) ?>"></div>
            <div class="field"><label for="points_draw">Empate</label><input class="input" type="number" min="0" id="points_draw" name="points_draw" value="<?= e($t['points_draw'] ?? 1) ?>"></div>
            <div class="field"><label for="points_loss">Derrota</label><input class="input" type="number" min="0" id="points_loss" name="points_loss" value="<?= e($t['points_loss'] ?? 0) ?>"></div>
        </div>

        <h3 class="mt-3">Criterios de desempate</h3>
        <p class="muted" style="font-size:.85rem;margin-top:-.5rem">Los puntos siempre son el primer criterio. Marque los siguientes en orden de prioridad.</p>
        <div class="check-grid">
            <?php foreach ($TB as $k => $label): ?>
                <label class="check-item"><input type="checkbox" name="tiebreakers[]" value="<?= e($k) ?>"<?= checked(in_array($k, $tbActive, true)) ?>> <?= e($label) ?></label>
            <?php endforeach; ?>
        </div>

        <h3 class="mt-3">Reglas disciplinarias <span class="badge badge-warning">Confirme o modifique</span></h3>
        <p class="muted" style="font-size:.85rem;margin-top:-.5rem">Valores sugeridos, no universales. Ajústelos según el reglamento de este torneo.</p>
        <div class="form-row">
            <div class="field"><label for="d_yellow_threshold">Amarillas acumuladas para suspensión</label><input class="input" type="number" min="1" id="d_yellow_threshold" name="d_yellow_threshold" value="<?= (int)$d['yellow_threshold'] ?>"></div>
            <div class="field"><label for="d_yellow_matches">Partidos por acumulación</label><input class="input" type="number" min="0" id="d_yellow_matches" name="d_yellow_matches" value="<?= (int)$d['yellow_matches'] ?>"></div>
            <div class="field"><label for="d_double_matches">Partidos por doble amarilla</label><input class="input" type="number" min="0" id="d_double_matches" name="d_double_matches" value="<?= (int)$d['double_yellow_matches'] ?>"></div>
            <div class="field"><label for="d_red_matches">Partidos por roja directa</label><input class="input" type="number" min="0" id="d_red_matches" name="d_red_matches" value="<?= (int)$d['red_matches'] ?>"></div>
        </div>

        <div class="field mt-2">
            <label for="status">Estado</label>
            <select class="select" id="status" name="status">
                <option value="draft"<?= selected('draft', $t['status'] ?? 'draft') ?>>Borrador</option>
                <option value="active"<?= selected('active', $t['status'] ?? '') ?>>Activo</option>
                <option value="finished"<?= selected('finished', $t['status'] ?? '') ?>>Finalizado</option>
            </select>
        </div>

        <div class="page-actions mt-3">
            <button class="btn" type="submit">Guardar torneo</button>
            <a class="btn btn-ghost" href="<?= e(base_url('admin/tournaments.php')) ?>">Cancelar</a>
        </div>
    </form>
    <?php require 'partials/foot.php'; exit;
}

/* ---- List --------------------------------------------------------------- */
$tournaments = Database::all("SELECT tr.*,
    (SELECT COUNT(*) FROM tournament_teams WHERE tournament_id = tr.id) AS teams,
    (SELECT COUNT(*) FROM matches WHERE tournament_id = tr.id) AS matches
    FROM tournaments tr ORDER BY tr.created_at DESC");
require 'partials/head.php';
?>
<div class="page-head flex justify-between items-center wrap">
    <div><h1>Torneos</h1><p>Competiciones dentro de la liga.</p></div>
    <div class="page-actions"><a class="btn" href="<?= e(base_url('admin/tournaments.php?action=new')) ?>">+ Nuevo torneo</a></div>
</div>
<?php if (!$tournaments): ?>
    <div class="empty-state card"><div class="es-icon">🎯</div><h2>Sin torneos</h2><p>Crea el primer torneo de la liga.</p><a class="btn" href="<?= e(base_url('admin/tournaments.php?action=new')) ?>">+ Nuevo torneo</a></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Torneo</th><th>Formato</th><th class="num">Equipos</th><th class="num">Partidos</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tournaments as $t): ?>
                <tr>
                    <td><strong><?= e($t['name']) ?></strong><br><span class="muted" style="font-size:.8rem"><?= $t['rounds']==2?'Dos vueltas':'Una vuelta' ?></span></td>
                    <td><?= $t['format']==='league'?'Liga':'Copa' ?></td>
                    <td class="num"><?= (int)$t['teams'] ?></td>
                    <td class="num"><?= (int)$t['matches'] ?></td>
                    <td><span class="badge <?= $t['status']==='active'?'badge-success':($t['status']==='finished'?'badge-accent':'badge-muted') ?>"><?= e(ucfirst($t['status'])) ?></span></td>
                    <td>
                        <div class="flex gap-1 wrap">
                            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/tournaments.php?action=edit&id=' . $t['id'])) ?>">Editar</a>
                            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/calendar.php?tournament=' . $t['id'])) ?>">Calendario</a>
                            <form method="post" action="<?= e(base_url('admin/tournaments.php?action=delete&id=' . $t['id'])) ?>" data-confirm="¿Eliminar el torneo y sus partidos?">
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
<?php require 'partials/foot.php'; ?>
