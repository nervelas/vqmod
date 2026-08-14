<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (defined('FL_NOT_INSTALLED')) { redirect(base_url('install/')); }
Auth::requireLogin();

$counts = [
    'leagues'     => (int)Database::scalar("SELECT COUNT(*) FROM leagues"),
    'tournaments' => (int)Database::scalar("SELECT COUNT(*) FROM tournaments"),
    'teams'       => (int)Database::scalar("SELECT COUNT(*) FROM teams"),
    'players'     => (int)Database::scalar("SELECT COUNT(*) FROM players"),
    'matches'     => (int)Database::scalar("SELECT COUNT(*) FROM matches"),
    'finished'    => (int)Database::scalar("SELECT COUNT(*) FROM matches WHERE status='finished'"),
];
$hasData = $counts['leagues'] > 0;

$PAGE_TITLE = 'Dashboard';
$ACTIVE = 'dashboard';
require 'partials/head.php';
?>

<?php if (!$hasData): ?>
    <div class="empty-state reveal">
        <div class="es-icon">⚽</div>
        <div class="eyebrow">Bienvenido</div>
        <h2>Crea tu primera liga</h2>
        <p>Aún no hay competiciones registradas. Comienza creando una liga; luego podrás añadir torneos, equipos, jugadores, generar el calendario y publicar.</p>
        <a class="btn btn-lg" href="<?= e(base_url('admin/leagues.php?action=new')) ?>">+ Crear Liga</a>

        <div class="wizard-steps">
            <?php
            $steps = [
                ['1', 'Crear Liga', 'Nombre, escudo, banner y tema visual.'],
                ['2', 'Crear Torneo', 'Formato, vueltas y reglas de puntos.'],
                ['3', 'Agregar Equipos', 'Registra los equipos participantes.'],
                ['4', 'Generar Calendario', 'Round robin automático y validado.'],
                ['5', 'Publicar', 'Resultados, tablas y estadísticas en vivo.'],
            ];
            foreach ($steps as $s): ?>
                <div class="wizard-step reveal">
                    <div class="ws-num"><?= e($s[0]) ?></div>
                    <h4><?= e($s[1]) ?></h4>
                    <p><?= e($s[2]) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php else: ?>
    <div class="page-head">
        <h1>Dashboard</h1>
        <p>Resumen general de la plataforma.</p>
    </div>
    <div class="stat-grid mb-3">
        <?php
        $tiles = [
            ['Ligas', $counts['leagues'], 'leagues.php'],
            ['Torneos', $counts['tournaments'], 'tournaments.php'],
            ['Equipos', $counts['teams'], 'teams.php'],
            ['Jugadores', $counts['players'], 'players.php'],
            ['Partidos', $counts['matches'], 'matches.php'],
            ['Finalizados', $counts['finished'], 'matches.php'],
        ];
        foreach ($tiles as $t): ?>
            <a class="stat card-hover reveal" href="<?= e(base_url('admin/' . $t[2])) ?>" style="text-decoration:none;display:block">
                <div class="stat-num" data-count="<?= (int)$t[1] ?>">0</div>
                <div class="stat-label"><?= e($t[0]) ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="card reveal">
        <div class="section-head" style="margin-bottom:1rem">
            <h2 style="font-size:1.2rem">Accesos rápidos</h2>
        </div>
        <div class="page-actions">
            <a class="btn btn-sm" href="<?= e(base_url('admin/leagues.php?action=new')) ?>">+ Nueva liga</a>
            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/tournaments.php')) ?>">Torneos</a>
            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/calendar.php')) ?>">Generar calendario</a>
            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/matches.php')) ?>">Cargar resultados</a>
            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('admin/appearance.php')) ?>">Cambiar tema</a>
        </div>
    </div>
<?php endif; ?>

<?php require 'partials/foot.php'; ?>
