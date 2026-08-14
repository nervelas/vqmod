<?php
/**
 * System seed data. Inserts ONLY: roles, permissions, role_permissions,
 * the 10 visual themes and default platform settings.
 * NO sport data (leagues/teams/players/matches) is ever seeded.
 *
 * @param PDO   $pdo
 * @param array $admin  ['name','username','email','password']
 */

function fl_seed(PDO $pdo, array $admin): void
{
    // ---- Themes: the 10 mandatory, visually distinct themes -----------------
    $themes = [
        ['neon-nocturno',  'Neón Nocturno',   '#000000', '#B6FF2E', '#FFFFFF', '#6CFFB8', 0, 'Deportivo, tecnológico, energético y premium.'],
        ['imperial',       'Imperial',        '#111111', '#D4AF37', '#F7F3E8', '#7A0019', 0, 'Lujo, campeonato, prestigio y elegancia.'],
        ['oceano-electrico','Océano Eléctrico','#071A2B', '#00B8FF', '#EAF8FF', '#00E0C6', 0, 'Tecnológico, fresco, moderno y deportivo.'],
        ['fuego',          'Fuego',           '#170806', '#FF4D00', '#FFD6C2', '#FFB000', 0, 'Competitivo, energético, intenso y agresivo.'],
        ['royal',          'Royal',           '#120B25', '#7C3AED', '#F5EFFF', '#FF4FCB', 0, 'Elegante, exclusivo, tecnológico y contemporáneo.'],
        ['artico',         'Ártico',          '#EFF8FF', '#175CD3', '#101828', '#00A6FB', 1, 'Limpio, corporativo, deportivo y moderno.'],
        ['titanio',        'Titanio',         '#161A1D', '#C5CCD3', '#FFFFFF', '#FF5C35', 0, 'Industrial, moderno, masculino y profesional.'],
        ['esmeralda',      'Esmeralda',       '#061A14', '#18C37E', '#E9FFF5', '#D6FF3F', 0, 'Deportivo, natural, moderno y elegante.'],
        ['solar',          'Solar',           '#FFF8E7', '#F79009', '#1D2939', '#D92D20', 1, 'Luminoso, enérgico, premium y llamativo.'],
        ['atlantico',      'Atlántico',       '#FFFFFF', '#003EA8', '#101828', '#FF6B00', 1, 'Elegante, deportivo, profesional y contemporáneo.'],
    ];
    $ts = $pdo->prepare("INSERT INTO themes (slug,name,color_bg,color_primary,color_secondary,color_accent,is_light,style,is_system,sort_order)
                         VALUES (?,?,?,?,?,?,?,?,1,?)");
    foreach ($themes as $i => $t) {
        $ts->execute([$t[0],$t[1],$t[2],$t[3],$t[4],$t[5],$t[6],$t[7],$i+1]);
    }

    // ---- Roles --------------------------------------------------------------
    $pdo->prepare("INSERT INTO roles (slug,name,is_system) VALUES ('super_admin','Super Administrador',1)")->execute();
    $superRoleId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO roles (slug,name,is_system) VALUES ('admin','Administrador',1)")->execute();
    $adminRoleId = (int)$pdo->lastInsertId();

    // ---- Permissions --------------------------------------------------------
    $perms = [
        ['leagues.manage',    'Gestionar ligas',           'leagues'],
        ['tournaments.manage','Gestionar torneos',         'tournaments'],
        ['teams.manage',      'Gestionar equipos',         'teams'],
        ['players.manage',    'Gestionar jugadores',       'players'],
        ['calendar.manage',   'Gestionar calendario',      'calendar'],
        ['results.manage',    'Gestionar resultados',      'results'],
        ['discipline.manage', 'Gestionar disciplina',      'discipline'],
        ['content.manage',    'Gestionar contenido',       'content'],
        ['appearance.manage', 'Gestionar apariencia',      'appearance'],
        ['admins.manage',     'Gestionar administradores', 'admins'],
        ['roles.manage',      'Gestionar roles y permisos','roles'],
        ['settings.manage',   'Gestionar configuración',   'settings'],
        ['security.manage',   'Gestionar seguridad',       'security'],
        ['audit.view',        'Ver auditoría',             'audit'],
        ['backups.manage',    'Gestionar backups',         'backups'],
    ];
    $ps = $pdo->prepare("INSERT INTO permissions (slug,name,module) VALUES (?,?,?)");
    $permIds = [];
    foreach ($perms as $p) {
        $ps->execute($p);
        $permIds[$p[0]] = (int)$pdo->lastInsertId();
    }

    // Super admin: implicit all (handled in code). Still map every permission.
    $rp = $pdo->prepare("INSERT INTO role_permissions (role_id,permission_id) VALUES (?,?)");
    foreach ($permIds as $pid) {
        $rp->execute([$superRoleId, $pid]);
    }
    // Default Administrator: operational permissions, not system-critical ones.
    $adminGrants = ['leagues.manage','tournaments.manage','teams.manage','players.manage',
                    'calendar.manage','results.manage','discipline.manage','content.manage','audit.view'];
    foreach ($adminGrants as $slug) {
        $rp->execute([$adminRoleId, $permIds[$slug]]);
    }

    // ---- Super Admin user ---------------------------------------------------
    $hash = password_hash($admin['password'], PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (name,username,email,password_hash,role_id,status)
                   VALUES (?,?,?,?,?,'active')")
        ->execute([$admin['name'], $admin['username'], $admin['email'], $hash, $superRoleId]);

    // ---- Default settings ---------------------------------------------------
    $settings = [
        ['site_name',        'general', 'Plataforma de Ligas de Fútbol'],
        ['site_tagline',     'general', 'Gestión profesional de ligas de fútbol'],
        ['logo',             'general', ''],
        ['favicon',          'general', ''],
        ['default_theme_id', 'general', '1'],
        ['footer_text',      'content', '© ' . '2026' . ' Plataforma de Ligas de Fútbol. Todos los derechos reservados.'],
        ['institutional',    'content', ''],
        ['hero_title',       'content', 'Vive el fútbol como nunca antes'],
        ['hero_subtitle',    'content', 'Resultados, tablas, goleadores y estadísticas de todas las ligas en un solo lugar.'],
        ['contact_email',    'content', ''],
        ['seo_title',        'seo',     'Plataforma de Ligas de Fútbol'],
        ['seo_description',  'seo',     'Gestión profesional de ligas de fútbol: resultados, tablas y estadísticas.'],
        ['seo_keywords',     'seo',     'fútbol, ligas, torneos, resultados, tabla de posiciones'],
        ['module_news',      'modules', '1'],
        ['module_scorers',   'modules', '1'],
        ['module_discipline','modules', '1'],
        ['module_rules',     'modules', '1'],
        ['module_final',     'modules', '1'],
        ['login_max_attempts','security','8'],
        ['login_lockout_min', 'security','15'],
    ];
    $ss = $pdo->prepare("INSERT INTO settings (setting_key,setting_group,setting_value) VALUES (?,?,?)");
    foreach ($settings as $s) {
        $ss->execute($s);
    }
}
