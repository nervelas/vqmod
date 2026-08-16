<?php
/**
 * High-level push-notification orchestration:
 *  - schema (subscriptions, log, matchday completion tracking)
 *  - subscription storage
 *  - matchday "results complete" tracking
 *  - composing + sending "results" and "next matchday" notifications
 *  - the 24h-after-results scheduling logic (called by cron)
 */
class Push
{
    /** Create/upgrade the push-related schema (idempotent). */
    public static function ensureSchema(): void
    {
        Database::q("CREATE TABLE IF NOT EXISTS push_subscriptions (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            endpoint   VARCHAR(500) NOT NULL,
            p256dh     VARCHAR(255) NOT NULL,
            auth       VARCHAR(255) NOT NULL,
            league_id  INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_endpoint (endpoint(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        Database::q("CREATE TABLE IF NOT EXISTS notifications_log (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            type        VARCHAR(40) NOT NULL,
            title       VARCHAR(190) NOT NULL,
            body        TEXT NULL,
            matchday_id INT UNSIGNED NULL,
            sent_count  INT NOT NULL DEFAULT 0,
            created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        foreach (['results_completed_at', 'notified_at'] as $col) {
            $has = Database::scalar(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'matchdays' AND column_name = ?",
                [$col]
            );
            if (!$has) {
                Database::q("ALTER TABLE matchdays ADD COLUMN {$col} DATETIME NULL");
            }
        }
    }

    public static function config(): array
    {
        return [
            'public'   => (string)Settings::get('vapid_public', ''),
            'private'  => (string)Settings::get('vapid_private', ''),
            'subject'  => (string)(Settings::get('push_subject') ?: ('mailto:' . (Settings::get('contact_email') ?: 'admin@example.com'))),
            'enabled'  => Settings::bool('push_enabled', false),
            'delay'    => (int)(Settings::get('push_delay_hours', 24)),
        ];
    }

    public static function vapidReady(): bool
    {
        $c = self::config();
        return $c['public'] !== '' && $c['private'] !== '';
    }

    public static function generateAndStoreKeys(): void
    {
        $k = WebPush::generateVapidKeys();
        Settings::set('vapid_public', $k['public'], 'push');
        Settings::set('vapid_private', $k['private_pem'], 'push');
    }

    public static function saveSubscription(string $endpoint, string $p256dh, string $auth, ?int $leagueId = null): void
    {
        Database::q(
            "INSERT INTO push_subscriptions (endpoint, p256dh, auth, league_id) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth)",
            [$endpoint, $p256dh, $auth, $leagueId]
        );
    }

    public static function subscriberCount(): int
    {
        return (int)Database::scalar("SELECT COUNT(*) FROM push_subscriptions");
    }

    /** Send a notification to every subscriber. Prunes dead endpoints. Returns count sent. */
    public static function sendToAll(string $title, string $body, string $url, string $type = 'general', ?int $matchdayId = null): int
    {
        $c = self::config();
        if (!self::vapidReady()) { return 0; }
        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url], JSON_UNESCAPED_UNICODE);
        $sent = 0;
        foreach (Database::all("SELECT * FROM push_subscriptions") as $s) {
            try {
                $r = WebPush::send(
                    ['endpoint' => $s['endpoint'], 'p256dh' => $s['p256dh'], 'auth' => $s['auth']],
                    $payload, $c['public'], $c['private'], $c['subject']
                );
                if ($r['ok']) {
                    $sent++;
                } elseif (in_array($r['status'], [404, 410], true)) {
                    Database::q("DELETE FROM push_subscriptions WHERE id = ?", [$s['id']]);
                }
            } catch (Throwable $e) { /* skip one bad subscription */ }
        }
        Database::q(
            "INSERT INTO notifications_log (type, title, body, matchday_id, sent_count) VALUES (?,?,?,?,?)",
            [$type, $title, $body, $matchdayId, $sent]
        );
        return $sent;
    }

    /** Recompute whether a matchday's results are fully uploaded. Call after saving a result. */
    public static function refreshMatchdayCompletion(int $matchdayId): void
    {
        self::ensureSchema();
        $total   = (int)Database::scalar("SELECT COUNT(*) FROM matches WHERE matchday_id = ?", [$matchdayId]);
        $pending = (int)Database::scalar("SELECT COUNT(*) FROM matches WHERE matchday_id = ? AND status <> 'finished'", [$matchdayId]);
        $row = Database::one("SELECT results_completed_at, notified_at FROM matchdays WHERE id = ?", [$matchdayId]);
        if (!$row) { return; }
        if ($total > 0 && $pending === 0) {
            if (empty($row['results_completed_at'])) {
                Database::q("UPDATE matchdays SET results_completed_at = NOW() WHERE id = ?", [$matchdayId]);
            }
        } else {
            // Results no longer complete: reset the clock and any pending notification.
            if (!empty($row['results_completed_at']) || !empty($row['notified_at'])) {
                Database::q("UPDATE matchdays SET results_completed_at = NULL, notified_at = NULL WHERE id = ?", [$matchdayId]);
            }
        }
    }

    /** Compose the "all results of a matchday" message body. */
    public static function resultsBody(int $matchdayId): array
    {
        $md = Database::one("SELECT md.*, tr.name AS tournament, l.name AS league, l.slug AS league_slug
             FROM matchdays md JOIN tournaments tr ON tr.id = md.tournament_id JOIN leagues l ON l.id = tr.league_id
             WHERE md.id = ?", [$matchdayId]);
        if (!$md) { return ['title' => '', 'body' => '', 'url' => '']; }
        $rows = Database::all("SELECT m.home_goals, m.away_goals, h.short_name hs, h.name hn, a.short_name as_, a.name an
             FROM matches m LEFT JOIN teams h ON h.id = m.home_team_id LEFT JOIN teams a ON a.id = m.away_team_id
             WHERE m.matchday_id = ? ORDER BY COALESCE(m.slot,999999), m.id", [$matchdayId]);
        $parts = [];
        foreach ($rows as $r) {
            $home = $r['hs'] ?: $r['hn'] ?: '?';
            $away = $r['as_'] ?: $r['an'] ?: '?';
            $sc = ($r['home_goals'] === null ? '-' : (int)$r['home_goals']) . '-' . ($r['away_goals'] === null ? '-' : (int)$r['away_goals']);
            $parts[] = "{$home} {$sc} {$away}";
        }
        return [
            'title' => "Resultados · Jornada {$md['number']} ({$md['tournament']})",
            'body'  => implode(' · ', $parts),
            'url'   => base_url('liga.php?slug=' . urlencode($md['league_slug']) . '&t=' . (int)$md['tournament_id']),
        ];
    }

    /** Compose the "next matchday" message, or null when there is none. */
    public static function nextBody(int $matchdayId): ?array
    {
        $md = Database::one("SELECT * FROM matchdays WHERE id = ?", [$matchdayId]);
        if (!$md) { return null; }
        $next = Database::one(
            "SELECT md.*, tr.name AS tournament, l.name AS league, l.slug AS league_slug
             FROM matchdays md JOIN tournaments tr ON tr.id = md.tournament_id JOIN leagues l ON l.id = tr.league_id
             WHERE md.tournament_id = ? AND md.number > ? ORDER BY md.number ASC LIMIT 1",
            [$md['tournament_id'], $md['number']]
        );
        if (!$next) { return null; }
        $rows = Database::all("SELECT h.short_name hs, h.name hn, a.short_name as_, a.name an, m.match_date, m.match_time
             FROM matches m LEFT JOIN teams h ON h.id = m.home_team_id LEFT JOIN teams a ON a.id = m.away_team_id
             WHERE m.matchday_id = ? ORDER BY COALESCE(m.slot,999999), m.id", [$next['id']]);
        $parts = [];
        foreach ($rows as $r) {
            $home = $r['hs'] ?: $r['hn'] ?: '?';
            $away = $r['as_'] ?: $r['an'] ?: '?';
            $t = $r['match_time'] ? ' ' . substr($r['match_time'], 0, 5) : '';
            $parts[] = "{$home} vs {$away}{$t}";
        }
        $when = $next['match_date'] ? fmt_date($next['match_date']) : '';
        return [
            'title' => "Próxima jornada {$next['number']}" . ($when ? " · {$when}" : ''),
            'body'  => implode(' · ', $parts) ?: 'Consulta el calendario.',
            'url'   => base_url('liga.php?slug=' . urlencode($next['league_slug']) . '&t=' . (int)$next['tournament_id']),
            'matchday_id' => (int)$next['id'],
        ];
    }

    /**
     * Cron entry: for each matchday whose results completed >= delay hours ago and
     * not yet notified, send the results notification and the next-matchday one.
     * @return array log of actions.
     */
    public static function processDue(): array
    {
        self::ensureSchema();
        $c = self::config();
        if (!$c['enabled'] || !self::vapidReady()) {
            return ['skipped' => 'push desactivado o sin claves VAPID'];
        }
        $delay = max(0, (int)$c['delay']);
        $due = Database::all(
            "SELECT id FROM matchdays
             WHERE results_completed_at IS NOT NULL AND notified_at IS NULL
               AND results_completed_at <= (NOW() - INTERVAL ? HOUR)",
            [$delay]
        );
        $out = [];
        foreach ($due as $d) {
            $mdId = (int)$d['id'];
            $res = self::resultsBody($mdId);
            $sent1 = $res['title'] ? self::sendToAll($res['title'], $res['body'], $res['url'], 'results', $mdId) : 0;
            $sent2 = 0;
            $next = self::nextBody($mdId);
            if ($next) {
                $sent2 = self::sendToAll($next['title'], $next['body'], $next['url'], 'next_matchday', $next['matchday_id']);
            }
            Database::q("UPDATE matchdays SET notified_at = NOW() WHERE id = ?", [$mdId]);
            $out[] = ['matchday' => $mdId, 'results_sent' => $sent1, 'next_sent' => $sent2, 'has_next' => (bool)$next];
        }
        return ['processed' => count($due), 'details' => $out];
    }
}
