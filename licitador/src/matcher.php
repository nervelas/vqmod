<?php
/**
 * Motor de coincidencias: determinista, explicable y rápido.
 * Puntaje = palabras clave (hasta 70) + bono de título + departamento (15) + monto (8) + modalidad (7).
 */
function synonym_map(): array {
    static $m = null;
    if ($m !== null) return $m;
    $m = [];
    try {
        foreach (rows("SELECT term, synonyms FROM synonyms") as $r) {
            $group = array_filter(array_map('trim', array_merge([$r['term']], explode(',', $r['synonyms']))));
            foreach ($group as $g) { $m[norm($g)] = array_unique(array_merge($m[norm($g)] ?? [], $group)); }
        }
    } catch (Throwable $e) {}
    return $m;
}
/** Prepara el perfil: expande palabras clave con sinónimos. */
function profile_terms(array $profile): array {
    $kws = array_filter(array_map('trim', preg_split('/[,\n;]+/', (string) $profile['keywords'])));
    $syn = synonym_map(); $out = [];
    foreach ($kws as $kw) {
        $alts = [$kw];
        if (isset($syn[norm($kw)])) $alts = array_merge($alts, $syn[norm($kw)]);
        $variants = [];
        foreach (array_unique($alts) as $a) {
            $t = array_values(array_diff(tokens($a), array_map('stem', stop_words())));
            if ($t) $variants[] = ['label' => $a, 'tokens' => $t];
        }
        if ($variants) $out[] = ['kw' => $kw, 'variants' => $variants];
    }
    return $out;
}
function tender_norm_text(array $t): string {
    return norm(($t['title'] ?? '') . ' | ' . ($t['description'] ?? '') . ' | ' . ($t['entity'] ?? ''));
}
/** Puntúa un concurso frente al perfil. Devuelve null si no coincide. */
function score_tender(array $t, array $profile, array $terms): ?array {
    $titleTok = array_flip(tokens($t['title']));
    $allTok = array_flip(tokens($t['norm_text'] ?: tender_norm_text($t)));
    $depts = array_filter(array_map('trim', explode(',', (string) $profile['departments'])));
    $mods = array_filter(array_map('trim', explode(',', (string) $profile['modalities'])));
    // Filtros duros: lo que el cliente indicó en el asistente
    if ($depts && $t['department'] && $t['department'] !== 'Nacional' && !in_array($t['department'], $depts, true)) return null;
    if ($mods && $t['modality'] && !in_array($t['modality'], $mods, true)) return null;
    $amt = $t['amount'] !== null ? (float) $t['amount'] : null;
    if ($amt !== null && $amt > 0) {
        if ($profile['min_amount'] !== null && $profile['min_amount'] !== '' && $amt < (float) $profile['min_amount']) return null;
        if ($profile['max_amount'] !== null && $profile['max_amount'] !== '' && $amt > (float) $profile['max_amount']) return null;
    }
    $hits = []; $kwScore = 0.0; $titleBonus = 0.0;
    foreach ($terms as $term) {
        $best = 0.0; $label = null; $inTitle = false;
        foreach ($term['variants'] as $v) {
            $n = count($v['tokens']); $inT = 0; $inA = 0;
            foreach ($v['tokens'] as $tk) { if (isset($allTok[$tk])) $inA++; if (isset($titleTok[$tk])) $inT++; }
            $ratio = $inA / $n;
            if ($n > 1 && $ratio < 1) $ratio = $ratio >= 0.5 ? $ratio * 0.5 : 0; // coincidencia parcial de frases
            if ($ratio > $best) { $best = $ratio; $label = $v['label']; $inTitle = $inT === $n; }
        }
        if ($best > 0) { $hits[] = $label; $kwScore += $best; if ($inTitle) $titleBonus += 1; }
    }
    if (!$hits) return null;
    $nk = max(1, count($terms));
    $score = 40 + min(30.0, 30.0 * ($kwScore / min($nk, 3)));      // base por coincidencia + cobertura
    $score += min(10.0, 10.0 * $titleBonus / min($nk, 2));          // aparece en el título
    $reasons = ['coincide por: ' . implode(', ', array_unique($hits))];
    if ($depts) { $score += 8; $reasons[] = 'departamento: ' . ($t['department'] ?: 'Nacional'); }
    else $score += 4;
    if ($amt !== null && ($profile['min_amount'] !== null && $profile['min_amount'] !== '' || $profile['max_amount'] !== null && $profile['max_amount'] !== '')) { $score += 6; $reasons[] = 'monto dentro de tu rango'; }
    else $score += 3;
    if ($mods) { $score += 6; $reasons[] = 'modalidad: ' . (modalidades()[$t['modality']] ?? $t['modality']); }
    else $score += 3;
    // Urgencia: pequeño empujón a lo que vence pronto (no cambia la coincidencia)
    return ['score' => (int) min(100, round($score)), 'reasons' => implode(' · ', $reasons)];
}
function match_for_user(int $uid, ?int $sinceTenderId = null): int {
    $profile = row("SELECT * FROM profiles WHERE user_id=?", [$uid]);
    if (!$profile || trim((string) $profile['keywords']) === '') return 0;
    $terms = profile_terms($profile);
    if (!$terms) return 0;
    $min = (int) S('min_score', 45);
    $sql = "SELECT * FROM tenders WHERE status='abierto' AND (deadline IS NULL OR deadline>=?)";
    $p = [now()];
    if ($sinceTenderId) { $sql .= " AND id>?"; $p[] = $sinceTenderId; }
    $n = 0;
    foreach (rows($sql . " ORDER BY deadline LIMIT 5000", $p) as $t) {
        $r = score_tender($t, $profile, $terms);
        if (!$r || $r['score'] < $min) continue;
        q("INSERT INTO matches (user_id,tender_id,score,reasons,status,created_at) VALUES (?,?,?,?, 'nuevo', ?)
           ON DUPLICATE KEY UPDATE score=VALUES(score), reasons=VALUES(reasons)", [$uid, $t['id'], $r['score'], $r['reasons'], now()]);
        $n++;
    }
    // Retira coincidencias nuevas que ya no aplican (p. ej. cambió el perfil o venció)
    q("DELETE m FROM matches m JOIN tenders t ON t.id=m.tender_id WHERE m.user_id=? AND m.status='nuevo' AND m.notified_at IS NULL AND (t.status<>'abierto' OR (t.deadline IS NOT NULL AND t.deadline<?))", [$uid, now()]);
    return $n;
}
function match_all(): int {
    $n = 0;
    foreach (rows("SELECT u.id FROM users u WHERE u.role='client' AND u.status='activo' AND u.sub_ends>=?", [now()]) as $u) $n += match_for_user((int) $u['id']);
    return $n;
}
/** Inteligencia de precios: adjudicados parecidos. */
function price_stats(array $t): array {
    $tk = tokens($t['title']);
    $cands = rows("SELECT id,title,entity,department,awarded_amount,awarded_to,publish_date FROM tenders WHERE status='adjudicado' AND awarded_amount>0 AND id<>? AND category<=>? ORDER BY publish_date DESC LIMIT 600", [$t['id'], $t['category']]);
    $sim = [];
    foreach ($cands as $c) {
        $ct = tokens($c['title']);
        $inter = count(array_intersect($tk, $ct)); $union = count(array_unique(array_merge($tk, $ct)));
        $j = $union ? $inter / $union : 0;
        if ($j >= 0.2) { $c['sim'] = $j; $sim[] = $c; }
    }
    usort($sim, fn($a, $b) => $b['sim'] <=> $a['sim']);
    $sim = array_slice($sim, 0, 12);
    if (!$sim) return ['n' => 0, 'items' => []];
    $v = array_map(fn($x) => (float) $x['awarded_amount'], $sim); sort($v);
    $c = count($v); $med = $c % 2 ? $v[intdiv($c, 2)] : ($v[$c / 2 - 1] + $v[$c / 2]) / 2;
    return ['n' => $c, 'min' => $v[0], 'median' => $med, 'max' => $v[$c - 1], 'items' => array_slice($sim, 0, 5)];
}
