<?php
/**
 * Database schema for the Football Leagues Platform.
 * Returns an ordered list of DDL statements. Executed by the installer.
 *
 * IMPORTANT: This schema seeds ONLY system data (super admin, roles,
 * permissions, settings, 10 visual themes). No leagues, tournaments,
 * teams, players, matches or results are ever created here.
 */

return [

// ---------------------------------------------------------------------------
// Core / system tables
// ---------------------------------------------------------------------------

"CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_group VARCHAR(50)  NOT NULL DEFAULT 'general',
    setting_value MEDIUMTEXT   NULL,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS themes (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug             VARCHAR(60)  NOT NULL UNIQUE,
    name             VARCHAR(120) NOT NULL,
    color_bg         VARCHAR(9)   NOT NULL,
    color_primary    VARCHAR(9)   NOT NULL,
    color_secondary  VARCHAR(9)   NOT NULL,
    color_accent     VARCHAR(9)   NOT NULL,
    is_light         TINYINT(1)   NOT NULL DEFAULT 0,
    style            VARCHAR(255) NULL,
    is_system        TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order       INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS roles (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug       VARCHAR(60)  NOT NULL UNIQUE,
    name       VARCHAR(120) NOT NULL,
    is_system  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS permissions (
    id     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug   VARCHAR(80)  NOT NULL UNIQUE,
    name   VARCHAR(160) NOT NULL,
    module VARCHAR(60)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(160) NOT NULL,
    username      VARCHAR(80)  NOT NULL UNIQUE,
    email         VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id       INT UNSIGNED NOT NULL,
    status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
    last_login    DATETIME     NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS login_attempts (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(45)  NOT NULL,
    username   VARCHAR(120) NULL,
    success    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_la_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    user_name   VARCHAR(160) NULL,
    action      VARCHAR(60)  NOT NULL,
    module      VARCHAR(60)  NOT NULL,
    record_id   VARCHAR(60)  NULL,
    before_json MEDIUMTEXT   NULL,
    after_json  MEDIUMTEXT   NULL,
    ip          VARCHAR(45)  NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_time (created_at),
    INDEX idx_audit_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

// ---------------------------------------------------------------------------
// Sport structure (created EMPTY — filled by the admin later)
// ---------------------------------------------------------------------------

"CREATE TABLE IF NOT EXISTS leagues (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name              VARCHAR(160) NOT NULL,
    slug              VARCHAR(180) NOT NULL UNIQUE,
    logo              VARCHAR(255) NULL,
    banner            VARCHAR(255) NULL,
    banner_position   VARCHAR(20)  NOT NULL DEFAULT 'center',
    banner_overlay    VARCHAR(9)   NOT NULL DEFAULT '#000000',
    overlay_intensity TINYINT UNSIGNED NOT NULL DEFAULT 55,
    description       TEXT         NULL,
    info              MEDIUMTEXT   NULL,
    location          VARCHAR(180) NULL,
    theme_id          INT UNSIGNED NULL,
    status            ENUM('active','archived') NOT NULL DEFAULT 'active',
    visibility        ENUM('public','private') NOT NULL DEFAULT 'public',
    seo_title         VARCHAR(190) NULL,
    seo_description   VARCHAR(255) NULL,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_league_theme FOREIGN KEY (theme_id) REFERENCES themes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS seasons (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    league_id  INT UNSIGNED NOT NULL,
    name       VARCHAR(160) NOT NULL,
    start_date DATE NULL,
    end_date   DATE NULL,
    status     ENUM('active','closed') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_season_league (league_id),
    CONSTRAINT fk_season_league FOREIGN KEY (league_id) REFERENCES leagues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS tournaments (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    league_id     INT UNSIGNED NOT NULL,
    season_id     INT UNSIGNED NULL,
    name          VARCHAR(160) NOT NULL,
    slug          VARCHAR(180) NOT NULL,
    format        ENUM('league','cup') NOT NULL DEFAULT 'league',
    rounds        TINYINT UNSIGNED NOT NULL DEFAULT 2,
    points_win    TINYINT UNSIGNED NOT NULL DEFAULT 3,
    points_draw   TINYINT UNSIGNED NOT NULL DEFAULT 1,
    points_loss   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    tiebreakers   VARCHAR(255) NOT NULL DEFAULT 'points,goal_diff,goals_for,wins,head_to_head',
    discipline    MEDIUMTEXT   NULL,
    final_phase   ENUM('none','top4','top8') NOT NULL DEFAULT 'none',
    champion_team_id INT UNSIGNED NULL,
    runnerup_team_id INT UNSIGNED NULL,
    third_team_id    INT UNSIGNED NULL,
    status        ENUM('draft','active','finished') NOT NULL DEFAULT 'draft',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tournament_league (league_id),
    CONSTRAINT fk_tournament_league FOREIGN KEY (league_id) REFERENCES leagues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS teams (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    league_id   INT UNSIGNED NOT NULL,
    name        VARCHAR(160) NOT NULL,
    short_name  VARCHAR(40)  NULL,
    slug        VARCHAR(180) NOT NULL,
    logo        VARCHAR(255) NULL,
    color1      VARCHAR(9)   NULL,
    color2      VARCHAR(9)   NULL,
    description TEXT         NULL,
    info        MEDIUMTEXT   NULL,
    status      ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_team_league (league_id),
    CONSTRAINT fk_team_league FOREIGN KEY (league_id) REFERENCES leagues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS players (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    league_id  INT UNSIGNED NOT NULL,
    team_id    INT UNSIGNED NULL,
    first_name VARCHAR(120) NOT NULL,
    last_name  VARCHAR(120) NULL,
    nickname   VARCHAR(120) NULL,
    photo      VARCHAR(255) NULL,
    birthdate  DATE NULL,
    position   VARCHAR(60) NULL,
    status     ENUM('active','archived') NOT NULL DEFAULT 'active',
    notes      TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_player_league (league_id),
    INDEX idx_player_team (team_id),
    CONSTRAINT fk_player_league FOREIGN KEY (league_id) REFERENCES leagues(id) ON DELETE CASCADE,
    CONSTRAINT fk_player_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS tournament_teams (
    tournament_id INT UNSIGNED NOT NULL,
    team_id       INT UNSIGNED NOT NULL,
    PRIMARY KEY (tournament_id, team_id),
    CONSTRAINT fk_tt_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    CONSTRAINT fk_tt_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS registrations (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT UNSIGNED NOT NULL,
    team_id       INT UNSIGNED NOT NULL,
    player_id     INT UNSIGNED NOT NULL,
    dorsal        SMALLINT UNSIGNED NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reg (tournament_id, player_id),
    INDEX idx_reg_team (tournament_id, team_id),
    CONSTRAINT fk_reg_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    CONSTRAINT fk_reg_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_reg_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS matchdays (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT UNSIGNED NOT NULL,
    number        SMALLINT UNSIGNED NOT NULL,
    round         TINYINT UNSIGNED NOT NULL DEFAULT 1,
    match_date    DATE NULL,
    status        ENUM('scheduled','in_progress','finished') NOT NULL DEFAULT 'scheduled',
    notes         VARCHAR(255) NULL,
    INDEX idx_md_tournament (tournament_id),
    CONSTRAINT fk_md_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS matches (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tournament_id  INT UNSIGNED NOT NULL,
    matchday_id    INT UNSIGNED NULL,
    slot           SMALLINT UNSIGNED NULL,
    home_team_id   INT UNSIGNED NULL,
    away_team_id   INT UNSIGNED NULL,
    match_date     DATE NULL,
    match_time     TIME NULL,
    venue          VARCHAR(160) NULL,
    status         ENUM('pending','in_progress','finished','suspended','rescheduled','cancelled') NOT NULL DEFAULT 'pending',
    home_goals     SMALLINT UNSIGNED NULL,
    away_goals     SMALLINT UNSIGNED NULL,
    home_pens      SMALLINT UNSIGNED NULL,
    away_pens      SMALLINT UNSIGNED NULL,
    is_final_phase TINYINT(1) NOT NULL DEFAULT 0,
    phase_label    VARCHAR(60) NULL,
    notes          TEXT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_match_tournament (tournament_id),
    INDEX idx_match_matchday (matchday_id),
    CONSTRAINT fk_match_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    CONSTRAINT fk_match_matchday FOREIGN KEY (matchday_id) REFERENCES matchdays(id) ON DELETE SET NULL,
    CONSTRAINT fk_match_home FOREIGN KEY (home_team_id) REFERENCES teams(id) ON DELETE SET NULL,
    CONSTRAINT fk_match_away FOREIGN KEY (away_team_id) REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS match_events (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    match_id   INT UNSIGNED NOT NULL,
    team_id    INT UNSIGNED NULL,
    player_id  INT UNSIGNED NULL,
    type       ENUM('goal','own_goal','yellow','red','double_yellow','sub_in','sub_out') NOT NULL,
    minute     SMALLINT UNSIGNED NULL,
    notes      VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_match (match_id),
    INDEX idx_event_player (player_id),
    CONSTRAINT fk_event_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
    CONSTRAINT fk_event_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS suspensions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tournament_id   INT UNSIGNED NOT NULL,
    player_id       INT UNSIGNED NOT NULL,
    team_id         INT UNSIGNED NULL,
    reason          VARCHAR(190) NULL,
    event_type      VARCHAR(40) NULL,
    origin_match_id INT UNSIGNED NULL,
    total_matches   SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    served_matches  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status          ENUM('active','served','cancelled') NOT NULL DEFAULT 'active',
    notes           VARCHAR(255) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_susp_tournament (tournament_id),
    INDEX idx_susp_player (player_id),
    CONSTRAINT fk_susp_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    CONSTRAINT fk_susp_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

// ---------------------------------------------------------------------------
// Content
// ---------------------------------------------------------------------------

"CREATE TABLE IF NOT EXISTS news (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    league_id    INT UNSIGNED NULL,
    title        VARCHAR(190) NOT NULL,
    slug         VARCHAR(200) NOT NULL,
    excerpt      VARCHAR(300) NULL,
    body         MEDIUMTEXT NULL,
    image        VARCHAR(255) NULL,
    status       ENUM('draft','published') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_news_league (league_id),
    CONSTRAINT fk_news_league FOREIGN KEY (league_id) REFERENCES leagues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS rules (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    league_id     INT UNSIGNED NULL,
    tournament_id INT UNSIGNED NULL,
    category      VARCHAR(120) NULL,
    title         VARCHAR(190) NOT NULL,
    body          MEDIUMTEXT NULL,
    sort_order    INT NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rule_league (league_id),
    CONSTRAINT fk_rule_league FOREIGN KEY (league_id) REFERENCES leagues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS media (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    league_id  INT UNSIGNED NULL,
    title      VARCHAR(190) NULL,
    file       VARCHAR(255) NOT NULL,
    type       VARCHAR(20) NOT NULL DEFAULT 'image',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_media_league (league_id),
    CONSTRAINT fk_media_league FOREIGN KEY (league_id) REFERENCES leagues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

];
