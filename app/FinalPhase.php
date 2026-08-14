<?php
/**
 * Final-phase / playoff bracket engine (Top 4 or Top 8).
 *
 * Top 8 quarterfinals: 1v8, 2v7, 3v6, 4v5.
 * Semifinals: best-seeded winner vs worst-seeded winner; the other two meet.
 * Losers of the semifinals play the Third-Place match; winners play the Final.
 * Champion / Runner-up / Third place are stored on the tournament.
 *
 * Penalty shoot-outs are kept separate from the regulation score.
 */
class FinalPhase
{
    public const QF = 'Cuartos de Final';
    public const SF = 'Semifinal';
    public const TP = 'Tercer Lugar';
    public const FN = 'Final';

    /** Seed map: team_id => regular-season position (1 = best). */
    private static function seeds(int $tournamentId): array
    {
        $map = [];
        foreach (Standings::compute($tournamentId) as $r) {
            $map[$r['team_id']] = $r['pos'];
        }
        return $map;
    }

    private static function seedOf(array $seeds, ?int $teamId): int
    {
        return $teamId && isset($seeds[$teamId]) ? $seeds[$teamId] : PHP_INT_MAX;
    }

    /** Winner of a finished final-phase match (regulation, then penalties). */
    public static function winner(array $m): ?int
    {
        if ($m['status'] !== 'finished' || $m['home_team_id'] === null || $m['away_team_id'] === null) {
            return null;
        }
        $hg = (int)$m['home_goals']; $ag = (int)$m['away_goals'];
        if ($hg > $ag) return (int)$m['home_team_id'];
        if ($ag > $hg) return (int)$m['away_team_id'];
        // Regulation draw -> penalties decide.
        if ($m['home_pens'] !== null && $m['away_pens'] !== null) {
            if ((int)$m['home_pens'] > (int)$m['away_pens']) return (int)$m['home_team_id'];
            if ((int)$m['away_pens'] > (int)$m['home_pens']) return (int)$m['away_team_id'];
        }
        return null;
    }

    public static function loser(array $m): ?int
    {
        $w = self::winner($m);
        if ($w === null) return null;
        return $w === (int)$m['home_team_id'] ? (int)$m['away_team_id'] : (int)$m['home_team_id'];
    }

    /** (Re)create the first bracket round from current standings. */
    public static function seedFirstRound(int $tournamentId): array
    {
        $t = Database::one("SELECT * FROM tournaments WHERE id = ?", [$tournamentId]);
        if (!$t || $t['final_phase'] === 'none') {
            throw new RuntimeException('Este torneo no tiene fase final configurada.');
        }
        $need = $t['final_phase'] === 'top8' ? 8 : 4;
        $table = Standings::compute($tournamentId);
        if (count($table) < $need) {
            throw new RuntimeException("Se requieren al menos {$need} equipos clasificados.");
        }
        // Clear previous final-phase matches.
        Database::q("DELETE FROM matches WHERE tournament_id = ? AND is_final_phase = 1", [$tournamentId]);

        $seed = [];
        foreach ($table as $r) {
            $seed[$r['pos']] = $r['team_id'];
        }

        if ($need === 8) {
            $pairs = [[1,8],[2,7],[3,6],[4,5]];
            $label = self::QF;
        } else {
            $pairs = [[1,4],[2,3]];
            $label = self::SF;
        }
        foreach ($pairs as $p) {
            self::createMatch($tournamentId, $seed[$p[0]], $seed[$p[1]], $label);
        }
        Database::q("UPDATE tournaments SET status = 'active' WHERE id = ?", [$tournamentId]);
        return ['created' => count($pairs), 'phase' => $label];
    }

    /** Advance the bracket if the current round is complete. */
    public static function advance(int $tournamentId): string
    {
        $t = Database::one("SELECT * FROM tournaments WHERE id = ?", [$tournamentId]);
        if (!$t || $t['final_phase'] === 'none') {
            return 'Sin fase final.';
        }
        $seeds = self::seeds($tournamentId);

        // Quarterfinals -> Semifinals (top8 only).
        if ($t['final_phase'] === 'top8') {
            $qf = self::round($tournamentId, self::QF);
            $sf = self::round($tournamentId, self::SF);
            if ($qf && self::allFinished($qf) && !$sf) {
                $winners = array_map(fn($m) => self::winner($m), $qf);
                if (in_array(null, $winners, true)) {
                    return 'Hay cuartos empatados sin definir por penales.';
                }
                // Rank winners by seed (best = lowest pos).
                usort($winners, fn($a, $b) => self::seedOf($seeds, $a) <=> self::seedOf($seeds, $b));
                // best vs worst; middle two together.
                self::createMatch($tournamentId, $winners[0], $winners[3], self::SF);
                self::createMatch($tournamentId, $winners[1], $winners[2], self::SF);
                return 'Semifinales generadas.';
            }
        }

        // Semifinals -> Final + Third place.
        $sf = self::round($tournamentId, self::SF);
        $fn = self::round($tournamentId, self::FN);
        if ($sf && count($sf) === 2 && self::allFinished($sf) && !$fn) {
            $w = []; $l = [];
            foreach ($sf as $m) {
                $w[] = self::winner($m);
                $l[] = self::loser($m);
            }
            if (in_array(null, $w, true)) {
                return 'Hay semifinales empatadas sin definir por penales.';
            }
            self::createMatch($tournamentId, $l[0], $l[1], self::TP);
            self::createMatch($tournamentId, $w[0], $w[1], self::FN);
            return 'Final y Tercer Lugar generados.';
        }

        // Final finished -> record champion/runner-up/third.
        if ($fn && self::allFinished($fn)) {
            $final = $fn[0];
            $champion = self::winner($final);
            if ($champion === null) {
                return 'La final está empatada sin definir por penales.';
            }
            $runnerUp = self::loser($final);
            $third = null;
            $tp = self::round($tournamentId, self::TP);
            if ($tp && self::allFinished($tp)) {
                $third = self::winner($tp[0]);
            }
            Database::q(
                "UPDATE tournaments SET champion_team_id = ?, runnerup_team_id = ?, third_team_id = ?, status = 'finished' WHERE id = ?",
                [$champion, $runnerUp, $third, $tournamentId]
            );
            return 'Campeón registrado.';
        }

        return 'Sin cambios: complete los resultados de la ronda actual.';
    }

    private static function createMatch(int $tournamentId, int $home, int $away, string $label): void
    {
        Database::q(
            "INSERT INTO matches (tournament_id, home_team_id, away_team_id, status, is_final_phase, phase_label)
             VALUES (?,?,?,'pending',1,?)",
            [$tournamentId, $home, $away, $label]
        );
    }

    private static function round(int $tournamentId, string $label): array
    {
        return Database::all(
            "SELECT * FROM matches WHERE tournament_id = ? AND is_final_phase = 1 AND phase_label = ? ORDER BY id",
            [$tournamentId, $label]
        );
    }

    private static function allFinished(array $matches): bool
    {
        foreach ($matches as $m) {
            if ($m['status'] !== 'finished') {
                return false;
            }
        }
        return count($matches) > 0;
    }

    /** Full bracket grouped by phase for display. */
    public static function bracket(int $tournamentId): array
    {
        $out = [];
        foreach ([self::QF, self::SF, self::TP, self::FN] as $label) {
            $rows = self::round($tournamentId, $label);
            if ($rows) {
                $out[$label] = $rows;
            }
        }
        return $out;
    }
}
