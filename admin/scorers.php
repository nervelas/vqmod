<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();
Auth::require('results.manage');

$tournaments = Database::all("SELECT tr.id, tr.name, l.name AS league_name FROM tournaments tr JOIN leagues l ON l.id=tr.league_id ORDER BY tr.created_at DESC");
$tournamentId = int_input('tournament');

$PAGE_TITLE = 'Goleadores';
$ACTIVE = 'scorers';
require 'partials/head.php';
?>
<div class="page-head"><h1>Goleadores</h1><p>Tabla de goleo generada automáticamente desde las actas.</p></div>
<?php if (!$tournaments): ?>
    <div class="empty-state card"><div class="es-icon">🎽</div><h2>Sin torneos</h2><p>Los goleadores se calculan por torneo.</p></div>
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
    $scorers = Standings::scorers($tournamentId);
    if (!$scorers): ?>
        <div class="empty-state card"><div class="es-icon">⚽</div><h2>Aún no hay goles</h2><p>Registra goles en las actas de los partidos.</p></div>
    <?php else: ?>
        <div class="table-wrap"><table class="data">
            <thead><tr><th class="num">Pos</th><th>Jugador</th><th>Equipo</th><th class="num">Goles</th><th class="num">Partidos</th></tr></thead>
            <tbody>
            <?php foreach ($scorers as $i => $s): ?>
                <tr>
                    <td class="num"><span class="pos-badge"><?= $i+1 ?></span></td>
                    <td class="team-cell"><?= media_thumb($s['photo'], player_name($s), 'avatar', true) ?> <?= e(player_name($s)) ?></td>
                    <td><?= e($s['team_short'] ?: $s['team_name'] ?: '—') ?></td>
                    <td class="num pts"><?= (int)$s['goals'] ?></td>
                    <td class="num"><?= (int)$s['matches'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
    <?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php require 'partials/foot.php'; ?>
