<?php
// Mini-framework de pruebas + cliente HTTP con cookies (solo desarrollo; no va en el ZIP).
$GLOBALS['__results'] = [];

function t(string $name, bool $ok, string $detail = ''): void
{
    $GLOBALS['__results'][] = ['section' => $GLOBALS['__section'] ?? '', 'name' => $name, 'ok' => $ok, 'detail' => $detail];
    echo ($ok ? "  \033[32mPASS\033[0m " : "  \033[31mFAIL\033[0m ") . $name . ($ok || $detail === '' ? '' : "  → $detail") . "\n";
}
function section(string $s): void { echo "\n== $s ==\n"; $GLOBALS['__section'] = $s; }

final class Http
{
    public string $base; public string $jar; public array $last = [];
    public function __construct(string $base) { $this->base = rtrim($base, '/'); $this->jar = tempnam(sys_get_temp_dir(), 'cj'); }
    public function req(string $method, string $path, $data = null, array $headers = []): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => false]);
        if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $data ?? []); }
        if ($headers) { curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); }
        $raw = (string)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $h = substr($raw, 0, $hs); $body = substr($raw, $hs);
        $hdr = [];
        foreach (explode("\r\n", $h) as $l) { if (strpos($l, ':') !== false) { [$k, $v] = explode(':', $l, 2); $hdr[strtolower(trim($k))] = trim($v); } }
        return $this->last = ['code' => $code, 'body' => $body, 'headers' => $hdr, 'location' => $hdr['location'] ?? ''];
    }
    public function get(string $p, array $h = []): array { return $this->req('GET', $p, null, $h); }
    public function post(string $p, $d = [], array $h = []): array { return $this->req('POST', $p, $d, $h); }
    public function json(string $p, $d = null): array { $r = $d === null ? $this->get($p) : $this->post($p, $d); return (array)json_decode($r['body'], true) + ['_code' => $r['code']]; }
    /** Token CSRF del panel (de cualquier formulario de la página). */
    public function csrf(string $page = '/admin/login'): string
    {
        $r = $this->get($page);
        return preg_match('/name="_csrf" value="([^"]+)"/', $r['body'], $m) ? html_entity_decode($m[1]) : '';
    }
    /** Token público firmado (del JSON de arranque de /reservar). */
    public function pubCsrf(): string
    {
        $r = $this->get('/reservar');
        if (preg_match('#<script type="application/json" id="boot">(.*?)</script>#s', $r['body'], $m)) { $j = json_decode(html_entity_decode($m[1], ENT_QUOTES), true); return (string)($j['csrf'] ?? ''); }
        return '';
    }
}

/** Peticiones simultáneas con curl_multi. @return array[] respuestas */
function parallel(array $reqs): array
{
    $mh = curl_multi_init(); $hs = [];
    foreach ($reqs as $i => [$url, $fields]) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields, CURLOPT_TIMEOUT => 120]);
        curl_multi_add_handle($mh, $ch); $hs[$i] = $ch;
    }
    do { $st = curl_multi_exec($mh, $run); if ($run) { curl_multi_select($mh, 1); } } while ($run && $st === CURLM_OK);
    $out = [];
    foreach ($hs as $i => $ch) { $out[$i] = ['code' => curl_getinfo($ch, CURLINFO_HTTP_CODE), 'body' => curl_multi_getcontent($ch)]; curl_multi_remove_handle($mh, $ch); }
    curl_multi_close($mh);
    return $out;
}
