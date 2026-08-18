<?php
/**
 * Demo data seeder (optional, chosen at install time or re-runnable).
 *
 * Creates ONE league ("LIGA DE FUTBOL PLANES", fútbol 5) with 4 tournaments,
 * fictional teams (each with a pre-generated crest shipped in assets/demo/),
 * example players, full round-robin calendars and partial results so the tables,
 * goleadores and disciplina look alive. Everything is deletable from the admin.
 *
 * Requires the sport tables to exist and be empty (no leagues yet).
 */

function fl_seed_demo(PDO $pdo): array
{
    if ((int)$pdo->query("SELECT COUNT(*) FROM leagues")->fetchColumn() > 0) {
        return ['skipped' => true];
    }

    mt_srand(20260101); // deterministic demo

    $root = defined('FL_ROOT') ? FL_ROOT : dirname(__DIR__);
    $teamSets = require __DIR__ . '/demo_data.php';
    $crestMap = [];
    $mf = $root . '/assets/demo/crest_manifest.json';
    if (is_file($mf)) { $crestMap = json_decode((string)file_get_contents($mf), true) ?: []; }

    $hsl = function (float $h, float $s, float $l): string {
        $c = (1 - abs(2*$l - 1)) * $s; $x = $c * (1 - abs(fmod($h/60, 2) - 1)); $m = $l - $c/2;
        if ($h < 60) [$r,$g,$b]=[$c,$x,0]; elseif ($h<120) [$r,$g,$b]=[$x,$c,0];
        elseif ($h<180) [$r,$g,$b]=[0,$c,$x]; elseif ($h<240) [$r,$g,$b]=[0,$x,$c];
        elseif ($h<300) [$r,$g,$b]=[$x,0,$c]; else [$r,$g,$b]=[$c,0,$x];
        return sprintf('#%02X%02X%02X', (int)round(($r+$m)*255), (int)round(($g+$m)*255), (int)round(($b+$m)*255));
    };

    $slugify = function (string $t): string {
        if (function_exists('iconv')) { $c = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$t); if ($c !== false) $t = $c; }
        $t = strtolower($t); $t = preg_replace('/[^a-z0-9]+/', '-', $t); return trim($t, '-') ?: 'item';
    };

    // ---- League -------------------------------------------------------------
    $pdo->prepare(
        "INSERT INTO leagues (name, slug, logo, banner, banner_position, banner_overlay, overlay_intensity,
            description, info, location, theme_id, status, visibility, seo_title, seo_description)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        'LIGA DE FUTBOL PLANES', 'liga-de-futbol-planes',
        'assets/demo/liga-logo.png', 'assets/demo/liga-banner.jpg', 'center', '#04121F', 60,
        'Liga de fútbol 5 con torneos en todas las categorías.',
        '<p>La <strong>LIGA DE FUTBOL PLANES</strong> organiza torneos de <strong>fútbol 5</strong> en las categorías masculina, femenina, juvenil e infantil. Estos son datos de demostración: puedes eliminarlos desde el panel y crear tus propios torneos cuando quieras.</p>',
        'Cancha Los Planes', 1, 'active', 'public',
        'Liga de Fútbol Planes', 'Resultados, tablas y goleadores de la Liga de Fútbol Planes (fútbol 5).',
    ]);
    $leagueId = (int)$pdo->lastInsertId();

    // ---- Cohesive site branding (so the whole site matches the demo league) --
    $setSetting = $pdo->prepare(
        "INSERT INTO settings (setting_key, setting_group, setting_value) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    foreach ([
        ['site_name',       'general', 'Liga de Fútbol Planes'],
        ['site_tagline',    'general', 'Fútbol 5'],
        ['logo',            'general', 'assets/demo/liga-logo.png'],
        ['favicon',         'general', 'assets/demo/liga-logo.png'],
        ['hero_title',      'content', 'Vive la Liga de Fútbol Planes'],
        ['hero_subtitle',   'content', 'Resultados, tablas, goleadores y disciplina de todos los torneos, en un solo lugar.'],
        ['seo_title',       'seo',     'Liga de Fútbol Planes'],
        ['seo_description', 'seo',     'Resultados, tablas de posiciones y goleadores de la Liga de Fútbol Planes (fútbol 5).'],
        ['footer_text',     'content', '© 2026 Liga de Fútbol Planes. Todos los derechos reservados.'],
    ] as $s) { $setSetting->execute($s); }

    // ---- Season -------------------------------------------------------------
    $pdo->prepare("INSERT INTO seasons (league_id, name) VALUES (?, ?)")->execute([$leagueId, 'Temporada 2026']);
    $seasonId = (int)$pdo->lastInsertId();

    $discipline = json_encode([
        'yellow_threshold' => 4, 'yellow_matches' => 1, 'double_yellow_matches' => 1, 'red_matches' => 2,
    ], JSON_UNESCAPED_UNICODE);

    $maleFirst = ['Carlos','Luis','José','Juan','Diego','Marco','Andrés','Pedro','Fernando','Ricardo','Javier','Sergio','Óscar','Raúl','Mateo','Bryan','Kevin','Álex','Daniel','Emilio','Gustavo','Rodrigo','Iván','Néstor','Hugo','Pablo','Cristian','Manuel','Adrián','Tomás'];
    $femaleFirst = ['María','Ana','Laura','Sofía','Gabriela','Andrea','Paola','Daniela','Carla','Valeria','Fernanda','Lucía','Camila','Diana','Rocío','Karla','Mónica','Alejandra','Isabel','Patricia','Renata','Ximena','Natalia','Elena','Julia'];
    $last = ['García','Martínez','López','González','Rodríguez','Pérez','Sánchez','Ramírez','Torres','Flores','Rivera','Gómez','Díaz','Cruz','Morales','Ortiz','Castillo','Vargas','Ramos','Herrera','Mendoza','Aguilar','Núñez','Rojas','Reyes','Guzmán','Molina','Silva','Cabrera','Fuentes'];
    $positions = ['Portero','Cierre','Cierre','Ala','Ala','Ala','Pívot','Pívot'];

    $insTeam   = $pdo->prepare("INSERT INTO teams (league_id,name,short_name,slug,logo,color1,color2,description,status) VALUES (?,?,?,?,?,?,?,?, 'active')");
    $insPlayer = $pdo->prepare("INSERT INTO players (league_id,team_id,first_name,last_name,position,status) VALUES (?,?,?,?,?, 'active')");
    $insTourn  = $pdo->prepare("INSERT INTO tournaments (league_id,season_id,name,slug,format,rounds,points_win,points_draw,points_loss,tiebreakers,discipline,final_phase,status) VALUES (?,?,?,?, 'league',1,3,1,0,'points,goal_diff,goals_for,wins,head_to_head',?, ?, 'active')");
    $insTT     = $pdo->prepare("INSERT INTO tournament_teams (tournament_id, team_id) VALUES (?, ?)");
    $insMd     = $pdo->prepare("INSERT INTO matchdays (tournament_id,number,round,match_date,status) VALUES (?,?,?,?,?)");
    $insMatch  = $pdo->prepare("INSERT INTO matches (tournament_id,matchday_id,slot,home_team_id,away_team_id,match_date,match_time,venue,status,home_goals,away_goals) VALUES (?,?,?,?,?,?,?,?,?,?,?)");

    $insGoal = $pdo->prepare("INSERT INTO match_events (match_id,team_id,player_id,type,minute) VALUES (?,?,?, 'goal', ?)");
    $insCard = $pdo->prepare("INSERT INTO match_events (match_id,team_id,player_id,type,minute) VALUES (?,?,?, ?, ?)");

    require_once __DIR__ . '/CalendarGenerator.php';

    $tournaments = [
        ['masculino', 'Torneo Masculino Libre', 'none', $maleFirst,   'Categoría libre masculina de fútbol 5. 14 equipos compiten todos contra todos por el título.'],
        ['femenino',  'Torneo Femenino Libre',  'none', $femaleFirst, 'Categoría libre femenina de fútbol 5, con 10 equipos y mucha emoción cada jornada.'],
        ['juvenil',   'Torneo Juvenil',         'none', $maleFirst,   'Torneo juvenil de fútbol 5 para las nuevas promesas de la liga.'],
        ['infantil',  'Torneo Infantil',        'none', $maleFirst,   'Torneo infantil de fútbol 5: diversión, aprendizaje y compañerismo.'],
    ];

    // Base kickoff Saturday (first Saturday at least 7 days out from a fixed anchor).
    $baseTs = strtotime('2026-08-22'); // a Saturday
    $globalTeamIndex = 0;
    $summary = ['tournaments' => 0, 'teams' => 0, 'players' => 0, 'matches' => 0, 'goals' => 0];

    $setMeta = $pdo->prepare("UPDATE tournaments SET banner = ?, description = ? WHERE id = ?");
    foreach ($tournaments as [$key, $tname, $finalPhase, $firstNames, $tdesc]) {
        $insTourn->execute([$leagueId, $seasonId, $tname, $slugify($tname), $discipline, $finalPhase]);
        $tournamentId = (int)$pdo->lastInsertId();
        $court = is_file($root . "/assets/demo/court-$key.jpg") ? "assets/demo/court-$key.jpg" : null;
        $setMeta->execute([$court, $tdesc, $tournamentId]);
        $summary['tournaments']++;

        $teamIds = [];
        foreach ($teamSets[$key] as $teamName) {
            $hue   = fmod($globalTeamIndex * 137.508, 360);
            $c1    = $hsl($hue, 0.62, 0.42);
            $c2    = $hsl($hue, 0.55, 0.24);
            $logo  = $crestMap[$teamName] ?? null;
            $short = mb_strtoupper(mb_substr(preg_replace('/\s+(FC|Jr)$/u', '', $teamName), 0, 3));
            $insTeam->execute([$leagueId, $teamName, $short, $slugify($teamName), $logo, $c1, $c2, 'Equipo de demostración.']);
            $teamId = (int)$pdo->lastInsertId();
            $teamIds[] = $teamId;
            $insTT->execute([$tournamentId, $teamId]);   // register team in the tournament
            $summary['teams']++;

            // players
            $used = [];
            foreach ($positions as $pos) {
                do { $fn = $firstNames[array_rand($firstNames)]; $ln = $last[array_rand($last)]; $full = "$fn $ln"; } while (isset($used[$full]));
                $used[$full] = true;
                $insPlayer->execute([$leagueId, $teamId, $fn, $ln, $pos]);
                $summary['players']++;
            }
            $globalTeamIndex++;
        }

        // players per team map (for goal/card attribution)
        $teamPlayers = [];
        foreach ($teamIds as $tid) {
            $teamPlayers[$tid] = array_column(
                $pdo->query("SELECT id FROM players WHERE team_id = $tid")->fetchAll(PDO::FETCH_ASSOC), 'id'
            );
        }

        // ---- Calendar (single round-robin) ---------------------------------
        $cal = CalendarGenerator::generate($teamIds, 1);
        $mdCount = count($cal['matchdays']);
        $playedThrough = (int)ceil($mdCount * 0.55); // first ~55% finished

        foreach ($cal['matchdays'] as $idx => $md) {
            $mdDate = date('Y-m-d', $baseTs + $idx * 7 * 86400);
            $isPlayed = ($idx + 1) <= $playedThrough;
            $insMd->execute([$tournamentId, $md['number'], $md['round'], $mdDate, $isPlayed ? 'finished' : 'scheduled']);
            $mdId = (int)$pdo->lastInsertId();

            $dayMatches = array_values(array_filter($md['matches'], fn($m) => $m['bye'] === null));
            $dayMatches = CalendarGenerator::rotateForEquity($dayMatches, $idx);
            foreach ($dayMatches as $slotIdx => $m) {
                $mt = CalendarGenerator::slotTime('09:00', 75, $slotIdx);
                $hg = $ag = null; $status = 'pending';
                if ($isPlayed) {
                    $status = 'finished';
                    $hg = mt_rand(0, 5); $ag = mt_rand(0, 5);
                }
                $insMatch->execute([$tournamentId, $mdId, $slotIdx + 1, $m['home'], $m['away'], $mdDate, $mt, 'Cancha Los Planes', $status, $hg, $ag]);
                $matchId = (int)$pdo->lastInsertId();
                $summary['matches']++;

                if ($isPlayed) {
                    // goal events matching the score
                    foreach ([[$m['home'], $hg], [$m['away'], $ag]] as [$scTeam, $goals]) {
                        $pool = $teamPlayers[$scTeam] ?? [];
                        for ($g = 0; $g < (int)$goals && $pool; $g++) {
                            $insGoal->execute([$matchId, $scTeam, $pool[array_rand($pool)], mt_rand(1, 40)]);
                            $summary['goals']++;
                        }
                    }
                    // occasional cards
                    if (mt_rand(1, 100) <= 55) {
                        $scTeam = mt_rand(0, 1) ? $m['home'] : $m['away'];
                        $pool = $teamPlayers[$scTeam] ?? [];
                        if ($pool) { $insCard->execute([$matchId, $scTeam, $pool[array_rand($pool)], 'yellow', mt_rand(1, 40)]); }
                    }
                    if (mt_rand(1, 100) <= 12) {
                        $scTeam = mt_rand(0, 1) ? $m['home'] : $m['away'];
                        $pool = $teamPlayers[$scTeam] ?? [];
                        if ($pool) { $insCard->execute([$matchId, $scTeam, $pool[array_rand($pool)], 'red', mt_rand(20, 40)]); }
                    }
                }
            }
        }
    }

    return ['skipped' => false] + $summary;
}
