<?php
/**
 * Professional round-robin fixture generator (circle method).
 *
 * - Supports any number of teams (odd numbers get a bye each matchday).
 * - One round: each pair meets exactly once.
 * - Two rounds: each pair meets exactly twice, home/away reversed in the second.
 * - Produces a preview structure that can be validated before persisting.
 */
class CalendarGenerator
{
    /**
     * @param int[] $teamIds
     * @param int   $rounds  1 or 2
     * @return array{matchdays: array, stats: array}
     */
    public static function generate(array $teamIds, int $rounds = 2): array
    {
        $teams = array_values(array_unique(array_map('intval', $teamIds)));
        $rounds = $rounds === 1 ? 1 : 2;

        if (count($teams) < 2) {
            throw new InvalidArgumentException('Se requieren al menos 2 equipos.');
        }

        $hasBye = false;
        $work = $teams;
        if (count($work) % 2 !== 0) {
            $work[] = 0; // 0 == bye marker
            $hasBye = true;
        }

        $n = count($work);
        $roundsPerLeg = $n - 1;
        $half = $n / 2;

        $matchdays = [];
        $mdNumber  = 0;

        for ($leg = 1; $leg <= $rounds; $leg++) {
            $arr = $work;
            for ($r = 0; $r < $roundsPerLeg; $r++) {
                $mdNumber++;
                $pairs = [];
                for ($i = 0; $i < $half; $i++) {
                    $a = $arr[$i];
                    $b = $arr[$n - 1 - $i];
                    // Alternate home/away by matchday index for balance.
                    // Also invert the entire leg on the second round.
                    $home = $a; $away = $b;
                    if ($r % 2 === 1) {
                        [$home, $away] = [$b, $a];
                    }
                    if ($leg === 2) {
                        [$home, $away] = [$away, $home];
                    }
                    if ($home === 0 || $away === 0) {
                        // Bye: the non-zero team rests this matchday.
                        $resting = $home === 0 ? $away : $home;
                        $pairs[] = ['home' => null, 'away' => null, 'bye' => $resting];
                    } else {
                        $pairs[] = ['home' => $home, 'away' => $away, 'bye' => null];
                    }
                }
                $matchdays[] = [
                    'number' => $mdNumber,
                    'round'  => $leg,
                    'matches'=> $pairs,
                ];
                // Rotate: keep first element fixed, rotate the rest clockwise.
                $fixed = array_shift($arr);
                $last  = array_pop($arr);
                array_unshift($arr, $last);
                array_unshift($arr, $fixed);
            }
        }

        $stats = self::stats($teams, $matchdays, $rounds, $hasBye);
        return ['matchdays' => $matchdays, 'stats' => $stats];
    }

    private static function stats(array $teams, array $matchdays, int $rounds, bool $hasBye): array
    {
        $totalMatches = 0;
        foreach ($matchdays as $md) {
            foreach ($md['matches'] as $m) {
                if ($m['bye'] === null) {
                    $totalMatches++;
                }
            }
        }
        $t = count($teams);
        $expectedPerLeg = ($t * ($t - 1)) / 2;
        return [
            'teams'            => $t,
            'rounds'           => $rounds,
            'matchdays'        => count($matchdays),
            'total_matches'    => $totalMatches,
            'expected_matches' => $expectedPerLeg * $rounds,
            'has_bye'          => $hasBye,
        ];
    }

    /**
     * Rotate a matchday's matches so that a given pairing does not always sit
     * in the same time slot across matchdays (equitable kickoff-hour spread).
     */
    public static function rotateForEquity(array $matches, int $mdIndex): array
    {
        $n = count($matches);
        if ($n <= 1) {
            return array_values($matches);
        }
        $matches = array_values($matches);
        $r = $mdIndex % $n;
        return array_merge(array_slice($matches, $r), array_slice($matches, 0, $r));
    }

    /**
     * Kickoff time for a given slot within a matchday: base time staggered by
     * $intervalMin minutes per slot. Returns "HH:MM" or null when no base time.
     */
    public static function slotTime(?string $base, int $intervalMin, int $slotIndex): ?string
    {
        if (empty($base)) {
            return null;
        }
        $ts = strtotime($base);
        if ($ts === false) {
            return null;
        }
        $mins = ((int)date('H', $ts)) * 60 + (int)date('i', $ts) + $slotIndex * max(0, $intervalMin);
        $mins %= 1440;
        return sprintf('%02d:%02d', intdiv($mins, 60), $mins % 60);
    }

    /**
     * Verify kickoff-hour equity across the whole tournament: no team should
     * play more than $maxShare of its matches at a single hour (soft check).
     * $schedule: array of matchdays, each an ordered list of ['home','away','time'].
     * Returns a list of warnings (empty = balanced).
     */
    public static function validateTimeEquity(array $schedule, float $maxShare = 0.75): array
    {
        $byTeamHour = [];
        $byTeamTotal = [];
        foreach ($schedule as $day) {
            foreach ($day as $m) {
                $hour = $m['time'] !== null ? substr((string)$m['time'], 0, 2) : '--';
                foreach ([$m['home'], $m['away']] as $t) {
                    if (!$t) { continue; }
                    $byTeamHour[$t][$hour] = ($byTeamHour[$t][$hour] ?? 0) + 1;
                    $byTeamTotal[$t] = ($byTeamTotal[$t] ?? 0) + 1;
                }
            }
        }
        $warnings = [];
        foreach ($byTeamHour as $team => $hours) {
            $total = $byTeamTotal[$team] ?? 0;
            if ($total < 4) { continue; } // too few games to judge
            $max = max($hours);
            if ($max / $total > $maxShare) {
                $warnings[] = "Un equipo juega demasiados partidos a la misma hora ({$max} de {$total}).";
            }
        }
        return $warnings;
    }

    /**
     * Validate a generated schedule. Returns a list of error strings (empty = OK).
     */
    public static function validate(array $teams, array $result): array
    {
        $errors = [];
        $teams  = array_values(array_unique(array_map('intval', $teams)));
        $rounds = (int)($result['stats']['rounds'] ?? 1);
        $matchdays = $result['matchdays'];

        // Count meetings between each unordered pair.
        $meetings = [];
        foreach ($matchdays as $md) {
            $seenThisMd = [];
            foreach ($md['matches'] as $m) {
                if ($m['bye'] !== null) {
                    continue;
                }
                $h = $m['home']; $a = $m['away'];
                if ($h === $a) {
                    $errors[] = "Jornada {$md['number']}: un equipo no puede jugar contra sí mismo.";
                }
                foreach ([$h, $a] as $tid) {
                    if (isset($seenThisMd[$tid])) {
                        $errors[] = "Jornada {$md['number']}: un equipo juega dos veces en la misma jornada.";
                    }
                    $seenThisMd[$tid] = true;
                }
                $key = min($h, $a) . '-' . max($h, $a);
                $meetings[$key] = ($meetings[$key] ?? 0) + 1;
            }
        }

        // Every unordered pair must meet exactly $rounds times.
        for ($i = 0; $i < count($teams); $i++) {
            for ($j = $i + 1; $j < count($teams); $j++) {
                $key = min($teams[$i], $teams[$j]) . '-' . max($teams[$i], $teams[$j]);
                $got = $meetings[$key] ?? 0;
                if ($got !== $rounds) {
                    $errors[] = "El emparejamiento entre dos equipos ocurre {$got} vez/veces (esperado {$rounds}).";
                }
            }
        }

        $stats = $result['stats'];
        if ($stats['total_matches'] !== $stats['expected_matches']) {
            $errors[] = "Cantidad de partidos incorrecta ({$stats['total_matches']} de {$stats['expected_matches']}).";
        }

        return array_values(array_unique($errors));
    }
}
