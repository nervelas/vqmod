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

    /**
     * Extract approximate text (with line breaks) from a PDF file.
     * Hardened: never runs the text regex over raw binary (avoids memory blow-ups
     * and 500s). Only decoded content streams that contain text operators are read.
     */
    public static function extractText(string $path): string
    {
        // Cap the read so a huge PDF can never exhaust memory.
        $data = @file_get_contents($path, false, null, 0, 20 * 1024 * 1024);
        if (!is_string($data) || $data === '') { return ''; }

        $text = '';
        $offset = 0; $streams = 0;
        $len = strlen($data);
        while ($streams < 800 && ($sp = strpos($data, 'stream', $offset)) !== false) {
            $streams++;
            $start = $sp + 6;
            if ($start < $len && $data[$start] === "\r") { $start++; }
            if ($start < $len && $data[$start] === "\n") { $start++; }
            $ep = strpos($data, 'endstream', $start);
            if ($ep === false) { break; }
            $raw = substr($data, $start, $ep - $start);
            $offset = $ep + 9;

            $decoded = @gzuncompress($raw);
            if ($decoded === false) { $decoded = @gzinflate($raw); }
            if ($decoded === false) { $decoded = $raw; }
            if (!is_string($decoded) || $decoded === '') { continue; }

            // Only parse streams that actually contain text-showing operators.
            if (strpos($decoded, 'Tj') === false && strpos($decoded, 'TJ') === false) { continue; }
            $text .= self::extractFromContent(substr($decoded, 0, 3 * 1024 * 1024)) . "\n";
        }
        return $text;
    }

    /** Pull visible text out of a decoded PDF content stream (text-ops only). */
    private static function extractFromContent(string $c): string
    {
        $out = '';
        $re = '/\(((?:[^()\\\\]|\\\\.)*)\)|<([0-9A-Fa-f\s]{2,})>|\b(Td|TD|T\*)\b|(\')|(")/';
        $matches = null;
        if (@preg_match_all($re, $c, $matches, PREG_SET_ORDER) === false || !is_array($matches)) {
            return '';
        }
        foreach ($matches as $mt) {
            if (($mt[0][0] ?? '') === '(') {
                $out .= self::decodeLiteral($mt[1] ?? '');
            } elseif (($mt[0][0] ?? '') === '<' && !empty($mt[2])) {
                $out .= self::decodeHex($mt[2]);
            } elseif (!empty($mt[3])) {
                $out .= "\n"; // Td/TD/T* => new text line
            } elseif (($mt[4] ?? '') === "'" || ($mt[5] ?? '') === '"') {
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

    /** Parse a date found anywhere in a string into 'Y-m-d', or null. */
    public static function parseDate(string $s): ?string
    {
        $mk = function ($y, $m, $d) {
            $y = (int)$y; $m = (int)$m; $d = (int)$d;
            return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : null;
        };
        if (preg_match('/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/', $s, $m)) { return $mk($m[1], $m[2], $m[3]); }
        if (preg_match('~\b(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{2,4})\b~', $s, $m)) {
            $y = $m[3]; if (strlen($y) === 2) { $y = '20' . $y; }
            return $mk($y, $m[2], $m[1]);
        }
        $months = ['enero'=>1,'febrero'=>2,'marzo'=>3,'abril'=>4,'mayo'=>5,'junio'=>6,'julio'=>7,
                   'agosto'=>8,'septiembre'=>9,'setiembre'=>9,'octubre'=>10,'noviembre'=>11,'diciembre'=>12];
        if (preg_match('/\b(\d{1,2})\s+(?:de\s+)?([a-zA-Zá-úÁ-Ú]+)\s+(?:de\s+)?(\d{4})\b/u', $s, $m)) {
            $mo = self::norm($m[2]);
            foreach ($months as $name => $num) { if (self::norm($name) === $mo) { return $mk($m[3], $num, $m[1]); } }
        }
        return null;
    }

    /** Parse a time found in a string into 'HH:MM' (24h), or null. */
    public static function parseTime(string $s): ?string
    {
        if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*([ap])\.?\s*m\.?/i', $s, $m)) {
            $h = (int)$m[1] % 12; if (strtolower($m[3]) === 'p') { $h += 12; }
            $min = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 0;
            return sprintf('%02d:%02d', $h, $min);
        }
        // 24h with ':' or 'h' separator (avoid matching dates that use / - .)
        if (preg_match('/\b([01]?\d|2[0-3])[:h](\d{2})\b/', $s, $m)) {
            return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
        }
        return null;
    }

    /**
     * Parse extracted text into jornadas + matches, mapping to team ids.
     * Detects a date per jornada and a time per match when present in the PDF.
     * @param array $teams  id => name (the tournament's league teams)
     * @return array{jornadas: array, detected_matches: int, matched: int}
     */
    public static function parse(string $text, array $teams): array
    {
        $normMap = [];
        foreach ($teams as $id => $name) { $normMap[self::norm($name)] = (int)$id; }

        // Ensure valid UTF-8 so the /u regexes below never fail on binary bytes.
        if (!mb_check_encoding($text, 'UTF-8')) {
            $conv = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            $text = $conv !== false ? $conv : preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', ' ', $text);
        }

        $lines = preg_split('/[\r\n]+/', (string)$text);
        if (!is_array($lines)) { $lines = []; }
        $jornadas = [];
        $current = null;
        $currentDate = null;
        $detected = 0; $matched = 0;
        $sepRe = '/\s+(?:vs\.?|versus|v\.?|x|@|-|–|—)\s+/iu';

        $ensure = function ($num) use (&$jornadas) {
            foreach ($jornadas as $j) { if ($j['number'] === $num) { return; } }
            $jornadas[] = ['number' => $num, 'date' => null, 'matches' => []];
        };
        $setJDate = function ($num, $date) use (&$jornadas) {
            if (!$date) { return; }
            foreach ($jornadas as &$j) { if ($j['number'] === $num && empty($j['date'])) { $j['date'] = $date; } }
            unset($j);
        };

        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line));
            if ($line === '') { continue; }

            $isHeader = false;
            if (preg_match('/\b(?:jornada|fecha|matchday|round|semana)\b\D{0,4}(\d{1,3})/iu', $line, $mm)) {
                $current = (int)$mm[1];
                $ensure($current);
                $isHeader = true;
            }
            // A date on a header or a standalone date line applies to the current jornada.
            $lineDate = self::parseDate($line);
            if ($lineDate) {
                $currentDate = $lineDate;
                if ($current !== null) { $setJDate($current, $currentDate); }
            }

            // Try to split into two team names.
            $parts = preg_split($sepRe, $line, 2);
            if (is_array($parts) && count($parts) === 2) {
                $detected++;
                $homeId = self::matchTeam($parts[0], $teams, $normMap);
                $awayId = self::matchTeam($parts[1], $teams, $normMap);
                if ($homeId && $awayId && $homeId !== $awayId) {
                    $matched++;
                    $num = $current ?? 1;
                    $ensure($num);
                    $mTime = self::parseTime($line);
                    $mDate = self::parseDate($line) ?: $currentDate;
                    if ($mDate) { $setJDate($num, $mDate); }
                    foreach ($jornadas as &$j) {
                        if ($j['number'] === $num) {
                            $j['matches'][] = [
                                'home_id' => $homeId, 'away_id' => $awayId,
                                'home_raw' => trim($parts[0]), 'away_raw' => trim($parts[1]),
                                'date' => $mDate, 'time' => $mTime,
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
