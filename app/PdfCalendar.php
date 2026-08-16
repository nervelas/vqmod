<?php
/**
 * Best-effort PDF calendar importer.
 *
 * Extracts text from a (text-based) PDF, detects "Jornada N" headers and
 * "TeamA vs TeamB" fixtures, and matches team names against the tournament's
 * existing teams. Results are always shown in an editable, validated preview
 * before saving — so an imperfect detection never produces a broken calendar.
 *
 * Note: works with text PDFs, not scanned images.
 */
class PdfCalendar
{
    /** Normalize a name for fuzzy matching (lowercase, no accents, alnum only). */
    public static function norm(string $s): string
    {
        $s = trim($s);
        if (function_exists('iconv')) {
            $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($c !== false) { $s = $c; }
        }
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', '', $s);
        return $s;
    }

    /** Extract approximate text (with line breaks) from a PDF file. */
    public static function extractText(string $path): string
    {
        $data = @file_get_contents($path);
        if ($data === false || $data === '') { return ''; }
        $text = '';
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $data, $m)) {
            foreach ($m[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false) { $decoded = @gzinflate($stream); }
                if ($decoded === false) { $decoded = $stream; }
                if (is_string($decoded) && $decoded !== '') {
                    $text .= self::extractFromContent($decoded) . "\n";
                }
            }
        }
        if (trim($text) === '') {
            $text = self::extractFromContent($data);
        }
        return $text;
    }

    /** Pull visible text out of a decoded PDF content stream. */
    private static function extractFromContent(string $c): string
    {
        $out = '';
        $re = '/\(((?:[^()\\\\]|\\\\.)*)\)|<([0-9A-Fa-f\s]+)>|\b(Td|TD|T\*)\b|(\')|(")/';
        if (!preg_match_all($re, $c, $matches, PREG_SET_ORDER)) {
            return '';
        }
        foreach ($matches as $mt) {
            if (isset($mt[1]) && $mt[1] !== '' || (isset($mt[0]) && $mt[0][0] === '(')) {
                $out .= self::decodeLiteral($mt[1] ?? '');
            } elseif (!empty($mt[2])) {
                $out .= self::decodeHex($mt[2]);
            } elseif (!empty($mt[3])) {
                $out .= "\n"; // Td/TD/T* => new text line
            } elseif (isset($mt[4]) && $mt[4] === "'") {
                $out .= "\n";
            } elseif (isset($mt[5]) && $mt[5] === '"') {
                $out .= "\n";
            }
        }
        return $out;
    }

    private static function decodeLiteral(string $s): string
    {
        $map = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C", '(' => '(', ')' => ')', '\\' => '\\'];
        $out = ''; $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
            if ($ch === '\\' && $i + 1 < $len) {
                $n = $s[$i + 1];
                if (isset($map[$n])) { $out .= $map[$n]; $i++; }
                elseif ($n >= '0' && $n <= '7') {
                    $oct = $n; $i++;
                    for ($k = 0; $k < 2 && $i + 1 < $len && $s[$i + 1] >= '0' && $s[$i + 1] <= '7'; $k++) { $oct .= $s[++$i]; }
                    $out .= chr(octdec($oct) & 0xFF);
                } else { $out .= $n; $i++; }
            } else {
                $out .= $ch;
            }
        }
        return $out;
    }

    private static function decodeHex(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        if (strlen($s) % 2 !== 0) { $s .= '0'; }
        $out = '';
        for ($i = 0; $i < strlen($s); $i += 2) {
            $out .= chr(hexdec(substr($s, $i, 2)));
        }
        return $out;
    }

    /**
     * Match a raw name to a team id.
     * @param array $teams id => name
     */
    public static function matchTeam(string $raw, array $teams, array $normMap): ?int
    {
        $n = self::norm($raw);
        if ($n === '') { return null; }
        if (isset($normMap[$n])) { return $normMap[$n]; }
        // substring either way
        foreach ($normMap as $tn => $tid) {
            if ($tn !== '' && (strpos($n, $tn) !== false || strpos($tn, $n) !== false)) {
                return $tid;
            }
        }
        // closest by levenshtein (short strings only)
        $best = null; $bestD = PHP_INT_MAX;
        foreach ($normMap as $tn => $tid) {
            if ($tn === '') { continue; }
            $d = levenshtein(substr($n, 0, 40), substr($tn, 0, 40));
            $tol = (int)floor(max(strlen($tn), 1) * 0.34);
            if ($d <= $tol && $d < $bestD) { $bestD = $d; $best = $tid; }
        }
        return $best;
    }

    /**
     * Parse extracted text into jornadas + matches, mapping to team ids.
     * @param array $teams  id => name (the tournament's league teams)
     * @return array{jornadas: array, detected_matches: int, matched: int}
     */
    public static function parse(string $text, array $teams): array
    {
        $normMap = [];
        foreach ($teams as $id => $name) { $normMap[self::norm($name)] = (int)$id; }

        $lines = preg_split('/[\r\n]+/', $text);
        $jornadas = [];
        $current = null;
        $detected = 0; $matched = 0;
        $sepRe = '/\s+(?:vs\.?|versus|v\.?|x|@|-|–|—)\s+/iu';

        $ensure = function ($num) use (&$jornadas) {
            foreach ($jornadas as &$j) { if ($j['number'] === $num) { return $j['number']; } }
            unset($j);
            $jornadas[] = ['number' => $num, 'matches' => []];
            return $num;
        };

        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line));
            if ($line === '') { continue; }

            if (preg_match('/\b(?:jornada|fecha|matchday|round|semana)\b\D{0,4}(\d{1,3})/iu', $line, $mm)) {
                $current = (int)$mm[1];
                $ensure($current);
                // A header line may also contain a match after it; fall through to try.
            }

            // Try to split into two team names.
            $parts = preg_split($sepRe, $line, 2);
            if (count($parts) === 2) {
                $detected++;
                $homeId = self::matchTeam($parts[0], $teams, $normMap);
                $awayId = self::matchTeam($parts[1], $teams, $normMap);
                if ($homeId && $awayId && $homeId !== $awayId) {
                    $matched++;
                    $num = $current ?? 1;
                    $ensure($num);
                    foreach ($jornadas as &$j) {
                        if ($j['number'] === $num) {
                            $j['matches'][] = [
                                'home_id' => $homeId, 'away_id' => $awayId,
                                'home_raw' => trim($parts[0]), 'away_raw' => trim($parts[1]),
                            ];
                            break;
                        }
                    }
                    unset($j);
                }
            }
        }

        // Drop empty jornadas, renumber sequentially by first appearance order of number.
        $jornadas = array_values(array_filter($jornadas, fn($j) => !empty($j['matches'])));
        usort($jornadas, fn($a, $b) => $a['number'] <=> $b['number']);

        return ['jornadas' => $jornadas, 'detected_matches' => $detected, 'matched' => $matched];
    }
}
