<?php
/**
 * Fuente de datos. Interfaz de adaptadores:
 *   (a) OCDS/JSON/CSV desde una URL configurable (datos abiertos oficiales de GUATECOMPRAS: ocds.guatecompras.gt)
 *   (b) CSV manual desde el admin con mapeo de columnas
 *   (c) Datos demo
 * No se hace scraping ni se evaden protecciones: solo descargas de datos abiertos publicados.
 */
interface SourceAdapter { public function fetch(): array; public function label(): string; }

function guess_category(string $text): string {
    $t = ' ' . norm($text) . ' ';
    $map = ['construccion' => ['construccion','obra','pavimento','carretera','puente','muro','drenaje','mejoramiento','edificio','remodelacion'],
        'transporte' => ['combustible','diesel','gasolina','transporte','vehiculo','llanta','flete','bus','lubricante'],
        'medico' => ['medicamento','medico','insumo','hospital','jeringa','guante','reactivo','sanitario','quirurgico','vacuna'],
        'escolar' => ['util','escolar','cuaderno','libro','papeleria','lapiz','pizarron','mobiliario escolar','libreria'],
        'ferreteria' => ['ferreteria','tuberia','pvc','cemento','hierro','pintura','herramienta','lamina','cable','electrico','block'],
        'tecnologia' => ['computadora','laptop','software','impresora','toner','servidor','red','tecnologia','licencia','equipo de computo'],
        'alimentos' => ['alimento','refaccion','almuerzo','vivere','agua pura','racion','carne','verdura'],
        'limpieza' => ['limpieza','mantenimiento','jardineria','fumigacion','desinfectante','cloro','escoba','aseo'],
        'servicios' => ['servicio','consultoria','asesoria','capacitacion','auditoria','seguridad','vigilancia','publicidad','impresion']];
    foreach ($map as $c => $ws) foreach ($ws as $w) if (strpos($t, ' ' . $w) !== false) return $c;
    return 'otros';
}
function guess_modality(string $s): string {
    $n = norm($s);
    if (preg_match('/licit|open|abiert[oa] publico/', $n) && !strpos($n, 'contrato abierto')) return 'licitacion';
    if (strpos($n, 'contrato abierto') !== false || strpos($n, 'framework') !== false) return 'contrato_abierto';
    if (strpos($n, 'cotiz') !== false || strpos($n, 'selective') !== false) return 'cotizacion';
    if (strpos($n, 'directa') !== false || strpos($n, 'limited') !== false || strpos($n, 'direct') !== false || strpos($n, 'baja cuantia') !== false) return 'compra_directa';
    return 'cotizacion';
}
function guess_dept(string $text): ?string {
    $n = ' ' . norm($text) . ' ';
    foreach (deptos() as $d) if (strpos($n, ' ' . norm($d) . ' ') !== false) return $d;
    return null;
}
function to_date($s, bool $time = false): ?string {
    if (!$s) return null; $s = trim((string) $s);
    if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})(.*)$#', $s, $m)) $s = "{$m[3]}-{$m[2]}-{$m[1]}{$m[4]}";
    $t = strtotime($s); if (!$t) return null;
    return $time ? date('Y-m-d H:i:s', $t) : date('Y-m-d', $t);
}
function to_amount($s): ?float {
    if ($s === null || $s === '') return null;
    $s = preg_replace('/[^0-9.,\-]/', '', (string) $s);
    if ($s === '' || $s === '-') return null;
    $c = strrpos($s, ','); $d = strrpos($s, '.');
    if ($c !== false && $d !== false) { $dec = $c > $d ? ',' : '.'; }          // el último separador es el decimal
    elseif ($c !== false) { $dec = preg_match('/^-?\d{1,3}(,\d{3})+$/', $s) ? null : ','; }
    elseif ($d !== false) { $dec = preg_match('/^-?\d{1,3}(\.\d{3})+$/', $s) ? null : '.'; }
    else $dec = null;
    if ($dec === ',') $s = str_replace(',', '.', str_replace('.', '', $s));
    elseif ($dec === '.') $s = str_replace(',', '', $s);
    else $s = str_replace([',', '.'], '', $s);
    return is_numeric($s) ? (float) $s : null;
}
/** Inserta o actualiza un concurso normalizado. Devuelve 'new' | 'upd' | null. */
function upsert_tender(array $t, bool $demo = false): ?string {
    $t['title'] = mb_substr(trim((string) ($t['title'] ?? '')), 0, 400);
    $t['ext_id'] = mb_substr(trim((string) ($t['ext_id'] ?? '')), 0, 120);
    if ($t['title'] === '' || $t['ext_id'] === '') return null;
    $t['category'] = $t['category'] ?? null ?: guess_category($t['title'] . ' ' . ($t['description'] ?? ''));
    $t['modality'] = $t['modality'] ?? null ?: 'cotizacion';
    $t['department'] = ($t['department'] ?? null) ?: guess_dept(($t['entity'] ?? '') . ' ' . $t['title'] . ' ' . ($t['description'] ?? '')) ?: 'Nacional';
    $t['status'] = in_array($t['status'] ?? '', ['abierto','adjudicado','cerrado','desierto'], true) ? $t['status'] : 'abierto';
    if ($t['status'] === 'abierto' && !empty($t['deadline']) && strtotime($t['deadline']) < time()) $t['status'] = 'cerrado';
    $d = [
        'ext_id' => $t['ext_id'], 'title' => $t['title'], 'description' => $t['description'] ?? null, 'entity' => mb_substr((string) ($t['entity'] ?? ''), 0, 250),
        'department' => $t['department'], 'modality' => $t['modality'], 'category' => $t['category'], 'amount' => $t['amount'] ?? null,
        'publish_date' => $t['publish_date'] ?? date('Y-m-d'), 'deadline' => $t['deadline'] ?? null, 'status' => $t['status'],
        'awarded_amount' => $t['awarded_amount'] ?? null, 'awarded_to' => $t['awarded_to'] ?? null, 'url' => mb_substr((string) ($t['url'] ?? ''), 0, 500) ?: null,
        'requirements' => $t['requirements'] ?? null, 'is_demo' => $demo ? 1 : 0,
    ];
    $d['norm_text'] = tender_norm_text($d);
    $id = val("SELECT id FROM tenders WHERE ext_id=?", [$d['ext_id']]);
    if ($id) { unset($d['ext_id'], $d['is_demo']); update('tenders', $d, 'id=?', [$id]); return 'upd'; }
    $d['created_at'] = now(); insert('tenders', $d); return 'new';
}

/** (a) OCDS / JSON / CSV desde URL */
class UrlSource implements SourceAdapter {
    public function __construct(private string $url, private string $type) {}
    public function label(): string { return 'URL (' . $this->type . ')'; }
    public function fetch(): array {
        if (!preg_match('#^https?://#i', $this->url)) throw new RuntimeException('La URL de la fuente no es válida. Configúrala en Admin → Fuente de datos.');
        $ch = curl_init($this->url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT => S('source_ua', 'Licitador/' . APP_VERSION . ' (+' . site_url('') . '; ' . S('contact_email', 'contacto') . ')'), CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_MAXFILESIZE => 60 * 1024 * 1024, CURLOPT_ENCODING => '']);
        $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($body === false) throw new RuntimeException('No se pudo descargar la fuente: ' . $err);
        if ($code >= 400) throw new RuntimeException("La fuente respondió HTTP $code. No se aplicó ninguna evasión; revisa la URL o los límites de uso.");
        return $this->type === 'csv' ? csv_to_tenders($body, json_decode(S('csv_map', '{}'), true) ?: []) : ocds_to_tenders($body);
    }
}
function ocds_to_tenders(string $body): array {
    $j = json_decode($body, true);
    $releases = [];
    if (is_array($j)) {
        if (isset($j['releases'])) $releases = $j['releases'];
        elseif (isset($j['records'])) foreach ($j['records'] as $r) { if (isset($r['compiledRelease'])) $releases[] = $r['compiledRelease']; elseif (!empty($r['releases'])) $releases[] = end($r['releases']); }
        elseif (isset($j['ocid'])) $releases = [$j];
        elseif (isset($j[0])) $releases = $j;
    } else {
        foreach (preg_split('/\R/', $body) as $line) { $x = json_decode(trim($line), true); if (is_array($x)) { if (isset($x['releases'])) $releases = array_merge($releases, $x['releases']); else $releases[] = $x; } }
    }
    if (!$releases) throw new RuntimeException('El archivo no contiene entregas (releases) OCDS reconocibles.');
    $out = [];
    foreach ($releases as $r) {
        $t = $r['tender'] ?? []; if (!$t && empty($r['ocid'])) continue;
        $award = $r['awards'][0] ?? null;
        $st = $t['status'] ?? 'active';
        $status = $award && in_array($award['status'] ?? 'active', ['active', 'pending'], true) ? 'adjudicado' : (in_array($st, ['unsuccessful', 'cancelled', 'withdrawn'], true) ? 'desierto' : ($st === 'complete' ? 'cerrado' : 'abierto'));
        $items = array_map(fn($i) => $i['description'] ?? '', $t['items'] ?? []);
        $docs = array_map(fn($d) => $d['title'] ?? ($d['description'] ?? ''), $t['documents'] ?? []);
        $region = $t['items'][0]['deliveryAddress']['region'] ?? ($r['buyer']['address']['region'] ?? ($t['procuringEntity']['address']['region'] ?? null));
        $nog = preg_match('/(\d{5,})$/', (string) ($t['id'] ?? $r['ocid']), $m) ? $m[1] : null;
        $tpl = S('official_url_tpl', 'https://www.guatecompras.gt/concursos/consultaConcurso.aspx?nog={nog}');
        $out[] = [
            'ext_id' => $r['ocid'] ?? ($t['id'] ?? ''), 'title' => $t['title'] ?? ($t['description'] ?? ''), 'description' => trim(($t['description'] ?? '') . "\n" . implode('; ', array_filter($items))),
            'entity' => $t['procuringEntity']['name'] ?? ($r['buyer']['name'] ?? ''), 'department' => $region && in_array($region, deptos(), true) ? $region : guess_dept((string) $region . ' ' . ($t['title'] ?? '')),
            'modality' => guess_modality(($t['procurementMethodDetails'] ?? '') . ' ' . ($t['procurementMethod'] ?? '')), 'amount' => $t['value']['amount'] ?? null,
            'publish_date' => to_date($t['tenderPeriod']['startDate'] ?? ($r['date'] ?? null)), 'deadline' => to_date($t['tenderPeriod']['endDate'] ?? null, true),
            'status' => $status, 'awarded_amount' => $award['value']['amount'] ?? null, 'awarded_to' => $award['suppliers'][0]['name'] ?? null,
            'url' => $nog ? str_replace('{nog}', $nog, $tpl) : null, 'requirements' => implode("\n", array_filter($docs)) ?: null,
        ];
    }
    return $out;
}
function csv_parse(string $body): array {
    $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
    if (!mb_check_encoding($body, 'UTF-8')) $body = mb_convert_encoding($body, 'UTF-8', 'ISO-8859-1');
    $first = strtok($body, "\n");
    $delim = ','; $best = 0; foreach ([',', ';', "\t", '|'] as $d) { $c = substr_count($first, $d); if ($c > $best) { $best = $c; $delim = $d; } }
    $fh = fopen('php://temp', 'r+'); fwrite($fh, $body); rewind($fh);
    $rows = []; while (($r = fgetcsv($fh, 0, $delim, '"', '')) !== false) { if ($r === [null] || $r === []) continue; $rows[] = $r; }
    fclose($fh); return $rows;
}
function csv_fields(): array {
    return ['ext_id' => 'Identificador / NOG', 'title' => 'Título', 'description' => 'Descripción', 'entity' => 'Entidad compradora', 'department' => 'Departamento', 'modality' => 'Modalidad', 'category' => 'Categoría', 'amount' => 'Monto estimado', 'publish_date' => 'Fecha de publicación', 'deadline' => 'Fecha límite', 'status' => 'Estado', 'awarded_amount' => 'Monto adjudicado', 'awarded_to' => 'Adjudicado a', 'url' => 'Enlace oficial', 'requirements' => 'Requisitos / documentos'];
}
function csv_auto_map(array $header): array {
    $aliases = ['ext_id' => ['nog','id','identificador','ocid','codigo'], 'title' => ['titulo','nombre','objeto','title','concurso'], 'description' => ['descripcion','detalle','description'], 'entity' => ['entidad','comprador','buyer','institucion'], 'department' => ['departamento','region','depto'], 'modality' => ['modalidad','metodo','tipo'], 'category' => ['categoria','rubro','sector'], 'amount' => ['monto','valor','amount','presupuesto'], 'publish_date' => ['publicacion','fecha publicacion','inicio','publish'], 'deadline' => ['limite','cierre','fecha limite','deadline','recepcion'], 'status' => ['estado','status'], 'awarded_amount' => ['monto adjudicado','adjudicado monto','awarded'], 'awarded_to' => ['adjudicado a','proveedor','supplier'], 'url' => ['enlace','url','link'], 'requirements' => ['requisitos','documentos']];
    $map = [];
    foreach ($aliases as $f => $al) foreach ($header as $i => $h) { $n = norm($h); foreach ($al as $a) if ($n === $a || ($n !== '' && strpos($n, $a) !== false && !isset($map[$f]))) { $map[$f] = $h; break 2; } }
    return $map;
}
function csv_to_tenders(string $body, array $map): array {
    $rows = csv_parse($body); if (count($rows) < 2) throw new RuntimeException('El CSV está vacío o no tiene encabezados.');
    $header = array_map('trim', array_shift($rows));
    if (!$map) $map = csv_auto_map($header);
    $idx = []; foreach ($map as $f => $col) { $i = array_search($col, $header, true); if ($i !== false) $idx[$f] = $i; }
    if (!isset($idx['title'])) throw new RuntimeException('No se encontró la columna del título. Usa el mapeo de columnas.');
    $out = [];
    foreach ($rows as $n => $r) {
        $g = fn($f) => isset($idx[$f]) ? trim((string) ($r[$idx[$f]] ?? '')) : '';
        $st = norm($g('status'));
        $dep = $g('department'); $dep = in_array($dep, deptos(), true) ? $dep : (guess_dept($dep) ?: null);
        $out[] = ['ext_id' => $g('ext_id') ?: 'CSV-' . substr(md5($g('title') . $g('entity') . $g('deadline')), 0, 12), 'title' => $g('title'), 'description' => $g('description'), 'entity' => $g('entity'), 'department' => $dep,
            'modality' => $g('modality') ? guess_modality($g('modality')) : null, 'category' => $g('category') && isset(categorias()[norm($g('category'))]) ? norm($g('category')) : null, 'amount' => to_amount($g('amount')),
            'publish_date' => to_date($g('publish_date')), 'deadline' => to_date($g('deadline'), true),
            'status' => strpos($st, 'adjud') === 0 ? 'adjudicado' : (strpos($st, 'desiert') === 0 ? 'desierto' : (strpos($st, 'cerr') === 0 ? 'cerrado' : 'abierto')),
            'awarded_amount' => to_amount($g('awarded_amount')), 'awarded_to' => $g('awarded_to'), 'url' => $g('url'), 'requirements' => $g('requirements')];
    }
    return $out;
}
/** (c) Demo: agrega concursos frescos cada día para que el producto nunca se vea vacío. */
class DemoSource implements SourceAdapter {
    public function label(): string { return 'Datos demo'; }
    public function fetch(): array { return demo_generate(6, 'D' . date('Ymd')); }
}
function import_tenders(array $list, bool $demo = false): array {
    $new = 0; $upd = 0; $bad = 0;
    foreach ($list as $t) { try { $r = upsert_tender($t, $demo); if ($r === 'new') $new++; elseif ($r === 'upd') $upd++; else $bad++; } catch (Throwable $e) { $bad++; log_error('import: ' . $e->getMessage()); } }
    return [$new, $upd, $bad];
}
function log_error(string $m): void { @file_put_contents(APP_ROOT . '/storage/logs/app.log', '[' . date('c') . '] ' . $m . "\n", FILE_APPEND | LOCK_EX); }

function get_adapter(): ?SourceAdapter {
    $type = S('source_type', 'demo');
    if ($type === 'ocds' || $type === 'csv') return new UrlSource(S('source_url'), $type);
    if ($type === 'demo') return new DemoSource();
    return null;
}
/** Cierra vencidos, importa, calcula coincidencias, envía alertas y recordatorios. Nunca lanza excepciones. */
function run_sync(string $by = 'cron'): array {
    $lockf = fopen(APP_ROOT . '/storage/tmp/sync.lock', 'c');
    if ($lockf && !flock($lockf, LOCK_EX | LOCK_NB)) return ['status' => 'ocupado', 'message' => 'Ya hay una sincronización en curso.'];
    $ad = null; $label = S('source_type', 'demo');
    $runId = insert('sync_runs', ['source' => $label . ' (' . $by . ')', 'started_at' => now(), 'status' => 'ok']);
    $msgs = []; $status = 'ok'; $new = 0; $upd = 0; $alerts = 0;
    try {
        $ad = get_adapter();
        if ($ad) {
            $list = $ad->fetch();
            [$new, $upd, $bad] = import_tenders($list, $ad instanceof DemoSource);
            $msgs[] = "Importados: $new nuevos, $upd actualizados" . ($bad ? ", $bad omitidos" : '') . '.';
        } else $msgs[] = 'Fuente desactivada.';
    } catch (Throwable $e) {
        $status = 'error'; $msgs[] = 'ERROR de importación (el sistema sigue con los datos existentes): ' . $e->getMessage(); log_error('sync: ' . $e->getMessage());
        try { admin_notify_error($e->getMessage()); } catch (Throwable $e2) {}
    }
    try {
        q("UPDATE tenders SET status='cerrado' WHERE status='abierto' AND deadline IS NOT NULL AND deadline<?", [now()]);
        $m = match_all(); $msgs[] = "Coincidencias evaluadas: $m.";
        $alerts = create_alerts(); $msgs[] = "Alertas creadas: $alerts.";
        $r = subscription_reminders(); $msgs[] = "Recordatorios de suscripción: $r.";
        $p = process_queue(100); $msgs[] = "Cola: {$p['sent']} enviados, {$p['manual']} manuales, {$p['err']} con error.";
        if ($p['err']) $status = $status === 'ok' ? 'parcial' : $status;
    } catch (Throwable $e) { $status = 'error'; $msgs[] = 'ERROR interno: ' . $e->getMessage(); log_error('sync2: ' . $e->getMessage()); }
    update('sync_runs', ['finished_at' => now(), 'status' => $status, 'imported' => $new, 'updated' => $upd, 'alerts' => $alerts, 'message' => implode(' ', $msgs)], 'id=?', [$runId]);
    set_setting('last_sync', now()); set_setting('last_sync_status', $status);
    if ($lockf) { flock($lockf, LOCK_UN); fclose($lockf); }
    return ['status' => $status, 'message' => implode(' ', $msgs)];
}
function admin_notify_error(string $msg): void {
    $to = S('admin_alert_email', S('contact_email'));
    if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) send_mail($to, 'Licitador: falló la sincronización', "La última sincronización falló:\n\n" . $msg . "\n\nEl sistema sigue funcionando con los datos existentes.");
}
