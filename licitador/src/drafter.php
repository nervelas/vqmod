<?php
/** Borrador de oferta: plantilla inteligente o API de Claude; exportación a DOCX y PDF sin dependencias. */

function draft_template(array $t, array $u, array $p, array $price): array {
    $co = $p['company'] ?: $u['name']; $rep = $p['legal_rep'] ?: $u['name'];
    $hoy = date('j') . ' de ' . $GLOBALS['MESES'][(int) date('n') - 1] . ' de ' . date('Y');
    $letter = "Guatemala, $hoy\n\nSeñores\n{$t['entity']}\nPresente\n\nReferencia: {$t['title']} (Concurso " . $t['ext_id'] . ")\n\n" .
        "Por este medio, $co" . ($p['nit'] ? ", con NIT {$p['nit']}," : ',') . " representada por $rep, presenta su oferta para el concurso de la referencia, manifestando que conoce y acepta los términos de las bases, y que cuenta con la capacidad para suministrar lo requerido en los plazos y condiciones establecidos.\n\n" .
        "Descripción de lo ofertado: " . trim(preg_replace('/\s*Concurso de ejemplo \(DEMO\).*$/', '', (string) $t['description'])) . "\n\n" .
        "La presente oferta tiene una vigencia de 30 días calendario contados a partir de la fecha de recepción de ofertas. Declaramos bajo juramento no encontrarnos en ninguna de las causales que impiden contratar con el Estado.\n\n" .
        "Sin otro particular, atentamente,\n\n\n$rep\n$co" . ($p['address'] ? "\n{$p['address']}" : '') . "\nTel. " . ($u['phone'] ?: '________') . "  ·  " . $u['email'];
    $docs = array_values(array_filter(array_map('trim', explode("\n", (string) $t['requirements']))));
    if (!$docs) $docs = ['Constancia de inscripción en el RGAE', 'RTU / NIT actualizado', 'Patente de comercio', 'Solvencia fiscal', 'DPI del representante legal', 'Oferta económica firmada y sellada'];
    $ref = ($price['n'] ?? 0) ? ' (referencia de adjudicados parecidos: mínimo ' . money($price['min']) . ', mediana ' . money($price['median']) . ', máximo ' . money($price['max']) . ')' : '';
    $unit = ($price['n'] ?? 0) ? round($price['median'] / 10) * 10 : ($t['amount'] ? round($t['amount'] / 10) : 0);
    $table = [['No.', 'Descripción', 'Cantidad', 'Precio unitario (Q)', 'Subtotal (Q)']];
    $table[] = ['1', mb_substr($t['title'], 0, 90), '1', $unit ? number_format($unit, 2, '.', ',') : '________', $unit ? number_format($unit, 2, '.', ',') : '________'];
    $table[] = ['', 'TOTAL OFERTADO (incluye IVA)', '', '', $unit ? number_format($unit, 2, '.', ',') : '________'];
    return ['letter' => $letter, 'docs' => $docs, 'table' => $table, 'note' => 'Ajusta cantidades y precios reales según las bases' . $ref . '. Este precio es solo un punto de partida, no una recomendación de oferta.'];
}
function draft_ai(array $t, array $u, array $p, array $price) {
    $key = S('ai_key'); if (!$key) return null;
    $ctx = "Concurso: {$t['title']}\nEntidad: {$t['entity']}\nModalidad: " . (modalidades()[$t['modality']] ?? $t['modality']) . "\nDepartamento: {$t['department']}\nMonto estimado: " . money($t['amount']) . "\nDescripción: {$t['description']}\nRequisitos conocidos:\n{$t['requirements']}\n\nEmpresa: " . ($p['company'] ?: $u['name']) . ", NIT " . ($p['nit'] ?: 'N/D') . ", representante " . ($p['legal_rep'] ?: $u['name']) . ", dirección " . ($p['address'] ?: 'N/D') . "\nPrecios de referencia: " . (($price['n'] ?? 0) ? "mín " . $price['min'] . ", mediana " . $price['median'] . ", máx " . $price['max'] : 'sin datos');
    $prompt = "Eres un asistente que redacta borradores de oferta para compras del Estado de Guatemala, en español de Guatemala, formal y breve. Con el contexto dado devuelve SOLO un JSON con las claves: \"letter\" (texto de la carta de oferta con saludo, referencia y firma; sin inventar datos que no estén en el contexto, usa ________ si falta), \"docs\" (arreglo de documentos a presentar), \"table\" (arreglo de filas [No., Descripción, Cantidad, Precio unitario (Q), Subtotal (Q)] con encabezado en la primera fila; usa precios de referencia solo como punto de partida), \"note\" (advertencia breve). No prometas adjudicación.\n\n" . $ctx;
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => ['x-api-key: ' . $key, 'anthropic-version: 2023-06-01', 'content-type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['model' => S('ai_model', 'claude-sonnet-5-5'), 'max_tokens' => 2500, 'messages' => [['role' => 'user', 'content' => $prompt]]])]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($r === false || $code >= 300) { log_error("IA HTTP $code"); return null; }
    $j = json_decode($r, true); $txt = $j['content'][0]['text'] ?? '';
    if (preg_match('/\{.*\}/s', $txt, $m)) $d = json_decode($m[0], true);
    if (empty($d['letter']) || empty($d['docs']) || empty($d['table']) || !is_array($d['table'])) return null;
    return ['letter' => (string) $d['letter'], 'docs' => array_map('strval', (array) $d['docs']), 'table' => array_map(fn($r) => array_map('strval', (array) $r), $d['table']), 'note' => (string) ($d['note'] ?? '')];
}
function make_draft(array $t, array $u): array {
    $p = row("SELECT * FROM profiles WHERE user_id=?", [$u['id']]) ?: ['company' => '', 'nit' => '', 'address' => '', 'legal_rep' => ''];
    $price = price_stats($t);
    $d = null; $src = 'plantilla';
    try { $d = draft_ai($t, $u, $p, $price); if ($d) $src = 'ia'; } catch (Throwable $e) { log_error('ia: ' . $e->getMessage()); }
    if (!$d) $d = draft_template($t, $u, $p, $price);
    $id = insert('drafts', ['user_id' => $u['id'], 'tender_id' => $t['id'], 'content' => json_encode($d, JSON_UNESCAPED_UNICODE), 'source' => $src, 'created_at' => now()]);
    return ['id' => $id] + $d + ['source' => $src];
}

// ---------- DOCX ----------
function x(string $s): string { return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
function docx_para(string $text, bool $bold = false, int $size = 22): string {
    $out = '';
    foreach (explode("\n", $text) as $line) $out .= '<w:p><w:r><w:rPr>' . ($bold ? '<w:b/>' : '') . '<w:sz w:val="' . $size . '"/></w:rPr><w:t xml:space="preserve">' . x($line) . '</w:t></w:r></w:p>';
    return $out;
}
function build_docx(array $d, array $t, string $path): bool {
    if (!class_exists('ZipArchive')) return false;
    $body = docx_para('BORRADOR A REVISAR — no presentar sin verificar las bases oficiales', true, 20) . docx_para('1. Carta de oferta', true, 28) . docx_para($d['letter']) . docx_para('2. Documentos a presentar', true, 28);
    foreach ($d['docs'] as $i => $doc) $body .= docx_para('☐ ' . $doc);
    $body .= docx_para('3. Cuadro de precios', true, 28) . '<w:tbl><w:tblPr><w:tblBorders>';
    foreach (['top','left','bottom','right','insideH','insideV'] as $b) $body .= "<w:$b w:val=\"single\" w:sz=\"4\" w:color=\"888888\"/>";
    $body .= '</w:tblBorders><w:tblW w:w="5000" w:type="pct"/></w:tblPr>';
    foreach ($d['table'] as $ri => $r) { $body .= '<w:tr>'; foreach ($r as $c) $body .= '<w:tc><w:p><w:r><w:rPr>' . ($ri === 0 ? '<w:b/>' : '') . '<w:sz w:val="20"/></w:rPr><w:t xml:space="preserve">' . x($c) . '</w:t></w:r></w:p></w:tc>'; $body .= '</w:tr>'; }
    $body .= '</w:tbl>' . docx_para('') . docx_para('Nota: ' . ($d['note'] ?? ''), false, 18);
    $doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $body . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1200" w:right="1200" w:bottom="1200" w:left="1200"/></w:sectPr></w:body></w:document>';
    $z = new ZipArchive(); if ($z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;
    $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
    $z->addFromString('word/document.xml', $doc);
    $z->close(); return true;
}

// ---------- PDF mínimo (Helvetica, WinAnsi) ----------
function pdf_text(string $s): string {
    $s = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $s);
}
function pdf_wrap(string $text, int $max): array {
    $lines = [];
    foreach (explode("\n", $text) as $para) {
        $line = '';
        foreach (preg_split('/\s+/', $para) as $w) {
            if ($w === '') { continue; }
            if (mb_strlen($line . ' ' . $w) > $max) { $lines[] = $line; $line = $w; } else $line = $line === '' ? $w : $line . ' ' . $w;
        }
        $lines[] = $line;
    }
    return $lines;
}
function build_pdf(array $d, array $t): string {
    $L = [['BORRADOR A REVISAR - no presentar sin verificar las bases oficiales', 9, true], ['', 9, false], ['1. Carta de oferta', 14, true]];
    foreach (pdf_wrap($d['letter'], 95) as $l) $L[] = [$l, 10, false];
    $L[] = ['', 10, false]; $L[] = ['2. Documentos a presentar', 14, true];
    foreach ($d['docs'] as $doc) foreach (pdf_wrap('[  ] ' . $doc, 92) as $i => $l) $L[] = [($i ? '     ' : '') . $l, 10, false];
    $L[] = ['', 10, false]; $L[] = ['3. Cuadro de precios', 14, true];
    foreach ($d['table'] as $ri => $r) $L[] = [sprintf('%-4s %-52s %-8s %-14s %s', mb_substr($r[0] ?? '', 0, 4), mb_substr($r[1] ?? '', 0, 52), mb_substr($r[2] ?? '', 0, 8), mb_substr($r[3] ?? '', 0, 14), $r[4] ?? ''), 8, $ri === 0];
    $L[] = ['', 9, false];
    foreach (pdf_wrap('Nota: ' . ($d['note'] ?? ''), 105) as $l) $L[] = [$l, 8, false];
    $pages = []; $cur = ''; $y = 800; $n = 0;
    foreach ($L as [$txt, $sz, $bold]) {
        if ($y < 60) { $pages[] = $cur; $cur = ''; $y = 800; }
        $cur .= 'BT /' . ($bold ? 'F2' : 'F1') . " $sz Tf 50 $y Td (" . pdf_text($txt) . ") Tj ET\n";
        $y -= $sz + 5;
    }
    $pages[] = $cur;
    $objs = []; $kids = [];
    $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    $nextId = 5;
    foreach ($pages as $content) {
        $pid = $nextId++; $cid = $nextId++;
        $objs[$cid] = "<< /Length " . strlen($content) . " >>\nstream\n$content" . "endstream";
        $objs[$pid] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $cid 0 R >>";
        $kids[] = "$pid 0 R";
    }
    $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
    ksort($objs);
    $out = "%PDF-1.4\n"; $offs = [];
    foreach ($objs as $id => $o) { $offs[$id] = strlen($out); $out .= "$id 0 obj\n$o\nendobj\n"; }
    $xref = strlen($out);
    $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($offs as $o) $out .= sprintf("%010d 00000 n \n", $o);
    $out .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    return $out;
}
