<?php
/**
 * League standings computation with configurable points and tiebreakers.
 */
class Standings
{
    /**
     * Compute the standings table for a tournament's regular (non-final) phase.
     * @return array ordered rows with position + all columns.
     */
    public static function compute(int $tournamentId): array
    {
        $t = Database::one("SELECT * FROM tournaments WHERE id = ?", [$tournamentId]);
        if (!$t) {
            return [];
        }
        $win  = (int)$t['points_win'];
        $draw = (int)$t['points_draw'];
        $loss = (int)$t['points_loss'];
        $tiebreakers = array_filter(array_map('trim', explode(',', $t['tiebreakers'])));
        if (!$tiebreakers) {
            $tiebreakers = ['points', 'goal_diff', 'goals_for', 'wins', 'head_to_head'];
        }

        // Teams registered in the tournament.
        $teams = Database::all(
            "SELECT t.id, t.name, t.short_name, t.logo
             FROM tournament_teams tt JOIN teams t ON t.id = tt.team_id
             WHERE tt.tournament_id = ? ORDER BY t.name",
            [$tournamentId]
        );
        $rows = [];
        foreach ($teams as $tm) {
            $rows[$tm['id']] = [
                'team_id'   => (int)$tm['id'],
                'name'      => $tm['name'],
                'short_name'=> $tm['short_name'],
                'logo'      => $tm['logo'],
                'pj' => 0, 'pg' => 0, 'pe' => 0, 'pp' => 0,
                'gf' => 0, 'gc' => 0, 'dg' => 0, 'pts' => 0,
            ];
        }

        // Finished, non-final-phase matches with both teams present.
        $matches = Database::all(
            "SELECT home_team_id, away_team_id, home_goals, away_goals
             FROM matches
             WHERE tournament_id = ? AND status = 'finished'
               AND is_final_phase = 0
               AND home_team_id IS NOT NULL AND away_team_id IS NOT NULL
               AND home_goals IS NOT NULL AND away_goals IS NOT NULL",
            [$tournamentId]
        );

        // Head-to-head store: [teamA][teamB] => ['pts'=>, 'dg'=>, 'gf'=>]
        $h2h = [];

        foreach ($matches as $m) {
            $h = (int)$m['home_team_id'];
            $a = (int)$m['away_team_id'];
            if (!isset($rows[$h]) || !isset($rows[$a])) {
                continue;
            }
            $hg = (int)$m['home_goals'];
            $ag = (int)$m['away_goals'];

            $rows[$h]['pj']++; $rows[$a]['pj']++;
            $rows[$h]['gf'] += $hg; $rows[$h]['gc'] += $ag;
            $rows[$a]['gf'] += $ag; $rows[$a]['gc'] += $hg;

            if ($hg > $ag) {
                $rows[$h]['pg']++; $rows[$h]['pts'] += $win;
                $rows[$a]['pp']++; $rows[$a]['pts'] += $loss;
                self::h2h($h2h, $h, $a, $win, $hg - $ag, $hg);
                self::h2h($h2h, $a, $h, $loss, $ag - $hg, $ag);
            } elseif ($hg < $ag) {
                $rows[$a]['pg']++; $rows[$a]['pts'] += $win;
                $rows[$h]['pp']++; $rows[$h]['pts'] += $loss;
                self::h2h($h2h, $a, $h, $win, $ag - $hg, $ag);
                self::h2h($h2h, $h, $a, $loss, $hg - $ag, $hg);
            } else {
                $rows[$h]['pe']++; $rows[$h]['pts'] += $draw;
                $rows[$a]['pe']++; $rows[$a]['pts'] += $draw;
                self::h2h($h2h, $h, $a, $draw, 0, $hg);
                self::h2h($h2h, $a, $h, $draw, 0, $ag);
            }
        }

        foreach ($rows as &$r) {
            $r['dg'] = $r['gf'] - $r['gc'];
        }
        unset($r);

        $list = array_values($rows);
        usort($list, function ($x, $y) use ($tiebreakers, $h2h) {
            foreach ($tiebreakers as $crit) {
                $cmp = self::compareBy($crit, $x, $y, $h2h);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }
            // Stable final criterion: alphabetical name.
            return strcmp($x['name'], $y['name']);
        });

        $pos = 1;
        foreach ($list as &$r) {
            $r['pos'] = $pos++;
        }
        unset($r);

        return $list;
    }

    private static function h2h(array &$store, int $team, int $opp, int $pts, int $dg, int $gf): void
    {
        if (!isset($store[$team][$opp])) {
            $store[$team][$opp] = ['pts' => 0, 'dg' => 0, 'gf' => 0];
        }
        $store[$team][$opp]['pts'] += $pts;
        $store[$team][$opp]['dg']  += $dg;
        $store[$team][$opp]['gf']  += $gf;
    }

    private static function compareBy(string $crit, array $x, array $y, array $h2h): int
    {
        switch ($crit) {
            case 'points':    return $y['pts'] <=> $x['pts'];
            case 'goal_diff': return $y['dg']  <=> $x['dg'];
            case 'goals_for': return $y['gf']  <=> $x['gf'];
            case 'wins':      return $y['pg']  <=> $x['pg'];
            case 'head_to_head':
                $xa = $h2h[$x['team_id']][$y['team_id']] ?? null;
                $ya = $h2h[$y['team_id']][$x['team_id']] ?? null;
                if ($xa === null || $ya === null) {
                    return 0;
                }
                if ($xa['pts'] !== $ya['pts']) return $ya['pts'] <=> $xa['pts'];
                if ($xa['dg']  !== $ya['dg'])  return $ya['dg']  <=> $xa['dg'];
                return $ya['gf'] <=> $xa['gf'];
            default:
                return 0;
        }
    }

    /** Top scorers for a tournament. */
    public static function scorers(int $tournamentId, int $limit = 100): array
    {
        return Database::all(
            "SELECT p.id, p.first_name, p.last_name, p.nickname, p.photo,
                    tm.name AS team_name, tm.short_name AS team_short, tm.logo AS team_logo,
                    COUNT(*) AS goals,
                    COUNT(DISTINCT me.match_id) AS matches
             FROM match_events me
             JOIN matches m ON m.id = me.match_id
             JOIN players p ON p.id = me.player_id
             LEFT JOIN teams tm ON tm.id = me.team_id
             WHERE m.tournament_id = ? AND me.type = 'goal'
             GROUP BY p.id, p.first_name, p.last_name, p.nickname, p.photo,
                      tm.name, tm.short_name, tm.logo
             ORDER BY goals DESC, matches ASC, p.first_name ASC
             LIMIT " . (int)$limit,
            [$tournamentId]
        );
    }

    /** Disciplinary tallies (yellow/red) per player for a tournament. */
    public static function discipline(int $tournamentId, int $limit = 200): array
    {
        return Database::all(
            "SELECT p.id, p.first_name, p.last_name, p.nickname, p.photo,
                    tm.name AS team_name, tm.short_name AS team_short,
                    SUM(me.type = 'yellow') AS yellows,
                    SUM(me.type = 'double_yellow') AS double_yellows,
                    SUM(me.type = 'red') AS reds
             FROM match_events me
             JOIN matches m ON m.id = me.match_id
             JOIN players p ON p.id = me.player_id
             LEFT JOIN teams tm ON tm.id = me.team_id
             WHERE m.tournament_id = ? AND me.type IN ('yellow','double_yellow','red')
             GROUP BY p.id, p.first_name, p.last_name, p.nickname, p.photo, tm.name, tm.short_name
             HAVING yellows > 0 OR double_yellows > 0 OR reds > 0
             ORDER BY reds DESC, double_yellows DESC, yellows DESC
             LIMIT " . (int)$limit,
            [$tournamentId]
        );
    }
}
