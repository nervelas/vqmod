<?php
/**
 * Admin layout — top portion (html head, sidebar, topbar).
 * Expects: $PAGE_TITLE (string), $ACTIVE (nav key), optional $PAGE_SUBTITLE.
 */
if (!defined('FL_APP')) { exit; }
$__theme = Theme::resolve((int)Settings::get('default_theme_id', 1));
$__u = Auth::user();
$__site = Settings::get('site_name', 'Ligas de Fútbol');

$nav = [
    'Panel' => [
        ['dashboard', '📊', 'Dashboard', 'index.php', null],
    ],
    'Competiciones' => [
        ['leagues',     '🏆', 'Ligas',        'leagues.php',     'leagues.manage'],
        ['tournaments', '🎯', 'Torneos',      'tournaments.php', 'tournaments.manage'],
        ['calendar',    '📅', 'Calendario',   'calendar.php',    'calendar.manage'],
        ['matches',     '⚽', 'Partidos',     'matches.php',     'results.manage'],
        ['final',       '🥇', 'Fase Final',   'final_phase.php', 'tournaments.manage'],
    ],
    'Participantes' => [
        ['teams',   '🛡️', 'Equipos', 'teams.php', 'teams.manage'],
    ],
    'Estadísticas' => [
        ['scorers',    '🎽', 'Goleadores',  'scorers.php',     'results.manage'],
        ['discipline', '🟨', 'Disciplina',  'discipline.php',  'discipline.manage'],
    ],
    'Contenido' => [
        ['news',    '📰', 'Noticias',        'news.php',    'content.manage'],
        ['rules',   '📋', 'Reglamento',      'rules.php',   'content.manage'],
        ['content', '✏️', 'Contenido y SEO', 'content.php', 'content.manage'],
    ],
    'Configuración' => [
        ['appearance', '🎨', 'Apariencia',        'appearance.php', 'appearance.manage'],
        ['admins',     '👥', 'Administradores',   'admins.php',     'admins.manage'],
        ['roles',      '🔐', 'Roles y permisos',  'roles.php',      'roles.manage'],
        ['settings',   '⚙️', 'Configuración',     'settings.php',   'settings.manage'],
        ['notifications','🔔','Notificaciones',    'notifications.php','settings.manage'],
        ['security',   '🛡️', 'Seguridad',         'security.php',   'security.manage'],
        ['audit',      '🧾', 'Auditoría',         'audit.php',      'audit.view'],
        ['backups',    '💾', 'Backups',           'backups.php',    'backups.manage'],
    ],
];
?><!DOCTYPE html>
<html lang="es" data-theme-slug="<?= e($__theme['slug']) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($PAGE_TITLE ?? 'Panel') ?> · <?= e($__site) ?></title>
<?php $__fav = Settings::get('favicon'); if ($__fav): ?>
<link rel="icon" href="<?= e(base_url($__fav)) ?>">
<?php else: ?>
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="46" fill="#111"/><text x="50" y="68" font-size="54" text-anchor="middle">⚽</text></svg>') ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/admin.css')) ?>">
<style><?= Theme::styleBlock($__theme) ?></style>
</head>
<body>
<div class="drawer-backdrop"></div>
<div class="admin-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-mark">⚽</span>
            <span><?= e($__site) ?></span>
        </div>
        <nav class="sidebar-nav">
            <?php foreach ($nav as $group => $items):
                $visible = array_filter($items, fn($it) => $it[4] === null || Auth::can($it[4]));
                if (!$visible) continue; ?>
                <div class="nav-group-title"><?= e($group) ?></div>
                <?php foreach ($visible as $it): ?>
                    <a class="nav-item <?= ($ACTIVE ?? '') === $it[0] ? 'active' : '' ?>" href="<?= e(base_url('admin/' . $it[3])) ?>">
                        <span class="ni-icon"><?= $it[1] ?></span><span><?= e($it[2]) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <a class="nav-item" href="<?= e(base_url('index.php')) ?>" target="_blank"><span class="ni-icon">🌐</span> Ver sitio público</a>
        </div>
    </aside>
    <div class="admin-main">
        <header class="topbar">
            <div class="flex items-center gap-2">
                <button class="drawer-toggle" aria-label="Menú">☰</button>
                <h1><?= e($PAGE_TITLE ?? 'Panel') ?></h1>
            </div>
            <div class="topbar-actions">
                <span class="user-chip">
                    <span class="avatar"><?= e(mb_strtoupper(mb_substr($__u['name'] ?? '?', 0, 1))) ?></span>
                    <span class="u-name"><?= e($__u['name'] ?? '') ?></span>
                </span>
                <a class="btn btn-ghost btn-sm" href="<?= e(base_url('admin/logout.php')) ?>">Salir</a>
            </div>
        </header>
        <main class="admin-content">
            <?php foreach (get_flashes() as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?>"><span><?= e($f['message']) ?></span></div>
            <?php endforeach; ?>
