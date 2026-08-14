<?php
/**
 * Disciplinary rules and automatic suspension handling.
 *
 * Default rules (each admin CONFIRMS/edits them per tournament):
 *   - 4 accumulated yellows        -> 1 match suspension
 *   - double yellow (same match)   -> 1 match suspension
 *   - direct red                   -> 2 matches suspension
 *
 * A double yellow is an expulsion but is NOT treated as a direct red.
 * Suspensions apply to the team's NEXT official match, not merely a date.
 */
class Discipline
{
    public static function defaults(): array
    {
        return [
            'yellow_threshold'     => 4, // yellows to trigger a suspension
            'yellow_matches'       => 1,
            'double_yellow_matches'=> 1,
            'red_matches'          => 2,
        ];
    }

    public static function rules(array $tournament): array
    {
        $d = self::defaults();
        if (!empty($tournament['discipline'])) {
            $j = json_decode($tournament['discipline'], true);
            if (is_array($j)) {
                return array_merge($d, $j);
            }
        }
        return $d;
    }

    /**
     * Regenerate suspension records for a whole tournament from match events,
     * then recompute how many matches have been served.
     * Auto-generated records carry event_type prefixed with 'auto:'.
     */
    public static function recompute(int $tournamentId): void
    {
        $t = Database::one("SELECT * FROM tournaments WHERE id = ?", [$tournamentId]);
        if (!$t) {
            return;
        }
        $rules = self::rules($t);

        // Remove previous auto-generated records (keep manual ones).
        Database::q(
            "DELETE FROM suspensions WHERE tournament_id = ? AND event_type LIKE 'auto:%'",
            [$tournamentId]
        );

        // Gather red + double-yellow events (each triggers one suspension).
        $direct = Database::all(
            "SELECT me.player_id, me.team_id, me.type, m.id AS match_id, m.match_date
             FROM match_events me
             JOIN matches m ON m.id = me.match_id
             WHERE m.tournament_id = ? AND me.type IN ('red','double_yellow')
               AND me.player_id IS NOT NULL
             ORDER BY m.match_date, m.id",
            [$tournamentId]
        );
        foreach ($direct as $ev) {
            $total = $ev['type'] === 'red' ? (int)$rules['red_matches'] : (int)$rules['double_yellow_matches'];
            $reason = $ev['type'] === 'red' ? 'Tarjeta roja directa' : 'Doble amarilla (expulsión)';
            self::createAuto($tournamentId, (int)$ev['player_id'], $ev['team_id'] ? (int)$ev['team_id'] : null,
                'auto:' . $ev['type'], (int)$ev['match_id'], $total, $reason);
        }

        // Yellow-card accumulation: one suspension per threshold crossed.
        $threshold = max(1, (int)$rules['yellow_threshold']);
        $yellowRows = Database::all(
            "SELECT me.player_id, me.team_id, m.id AS match_id, m.match_date
             FROM match_events me
             JOIN matches m ON m.id = me.match_id
             WHERE m.tournament_id = ? AND me.type = 'yellow' AND me.player_id IS NOT NULL
             ORDER BY me.player_id, m.match_date, m.id",
            [$tournamentId]
        );
        $counter = [];
        foreach ($yellowRows as $y) {
            $pid = (int)$y['player_id'];
            $counter[$pid] = ($counter[$pid] ?? 0) + 1;
            if ($counter[$pid] % $threshold === 0) {
                self::createAuto($tournamentId, $pid, $y['team_id'] ? (int)$y['team_id'] : null,
                    'auto:yellow_accumulation', (int)$y['match_id'], (int)$rules['yellow_matches'],
                    "Acumulación de {$threshold} amarillas");
            }
        }

        self::recomputeServed($tournamentId);
    }

    private static function createAuto(int $tournamentId, int $playerId, ?int $teamId,
                                       string $eventType, int $originMatchId, int $total, string $reason): void
    {
        Database::q(
            "INSERT INTO suspensions
             (tournament_id, player_id, team_id, reason, event_type, origin_match_id, total_matches, served_matches, status)
             VALUES (?,?,?,?,?,?,?,0,'active')",
            [$tournamentId, $playerId, $teamId, $reason, $eventType, $originMatchId, max(1, $total)]
        );
    }

    /**
     * Recompute served matches: a suspension is served as the player's team
     * plays subsequent official (finished) matches after the origin match.
     */
    public static function recomputeServed(int $tournamentId): void
    {
        $susps = Database::all(
            "SELECT * FROM suspensions WHERE tournament_id = ? AND status <> 'cancelled'",
            [$tournamentId]
        );
        foreach ($susps as $s) {
            $teamId = $s['team_id'];
            if (!$teamId) {
                continue; // cannot auto-track without a team
            }
            $origin = (int)($s['origin_match_id'] ?? 0);
            // Order key of the origin match (by date then id).
            $originMatch = $origin ? Database::one("SELECT match_date, id FROM matches WHERE id = ?", [$origin]) : null;

            $played = (int)Database::scalar(
                "SELECT COUNT(*) FROM matches
                 WHERE tournament_id = ?
                   AND status = 'finished'
                   AND (home_team_id = ? OR away_team_id = ?)
                   AND (
                        ? IS NULL
                        OR (COALESCE(match_date,'9999-12-31') > ? )
                        OR (COALESCE(match_date,'9999-12-31') = ? AND id > ?)
                   )",
                [
                    $tournamentId, $teamId, $teamId,
                    $origin ?: null,
                    $originMatch['match_date'] ?? '0000-00-00',
                    $originMatch['match_date'] ?? '0000-00-00',
                    $originMatch['id'] ?? 0,
                ]
            );
            $total  = (int)$s['total_matches'];
            $served = min($played, $total);
            $status = $served >= $total ? 'served' : 'active';
            if ($s['status'] !== 'cancelled') {
                Database::q(
                    "UPDATE suspensions SET served_matches = ?, status = ? WHERE id = ?",
                    [$served, $status, $s['id']]
                );
            }
        }
    }

    /** Players currently suspended (pending > 0) for a team in a tournament. */
    public static function activeForTeam(int $tournamentId, int $teamId): array
    {
        return Database::all(
            "SELECT s.*, p.first_name, p.last_name, p.nickname,
                    (s.total_matches - s.served_matches) AS pending
             FROM suspensions s JOIN players p ON p.id = s.player_id
             WHERE s.tournament_id = ? AND s.team_id = ? AND s.status = 'active'
               AND (s.total_matches - s.served_matches) > 0",
            [$tournamentId, $teamId]
        );
    }
}
