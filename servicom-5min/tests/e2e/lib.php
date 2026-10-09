<?php
declare(strict_types=1);
/** Cliente HTTP mínimo para las pruebas E2E (cookies, CSRF, JSON, subidas). */
final class Client
{
    public string $base;
    public string $host;
    public string $jar;
    public ?string $csrf = null;

    public function __construct(string $base = 'http://127.0.0.1:8201', string $host = 'crear.servicom.test:8201')
    {
        $this->base = $base;
        $this->host = $host;
        $this->jar = tempnam('/tmp', 'cj');
    }

    public function req(string $method, string $path, $body = null, array $headers = [], bool $json = true): array
    {
        $ch = curl_init($this->base . $path);
        $h = array_merge(['Host: ' . $this->host], $headers);
        if ($this->csrf) { $h[] = 'X-CSRF: ' . $this->csrf; }
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 120, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false];
        if ($body !== null) {
            if (is_array($body) && !$this->hasFile($body)) {
                $h[] = 'Content-Type: application/json';
                $opts[CURLOPT_POSTFIELDS] = json_encode($body);
            } else {
                $opts[CURLOPT_POSTFIELDS] = $body;
            }
        }
        $opts[CURLOPT_HTTPHEADER] = $h;
        curl_setopt_array($ch, $opts);
        $raw = (string) curl_exec($ch);
        $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $head = substr($raw, 0, $hs);
        $b = substr($raw, $hs);
        return ['status' => $status, 'headers' => $head, 'body' => $b, 'json' => json_decode($b, true)];
    }

    private function hasFile(array $a): bool
    {
        foreach ($a as $v) { if ($v instanceof CURLFile) { return true; } }
        return false;
    }

    public function page(string $path): array
    {
        $r = $this->req('GET', $path);
        if (preg_match('/<meta name="csrf" content="([a-f0-9]+)"/', $r['body'], $m) || preg_match('/name="_csrf" value="([a-f0-9]+)"/', $r['body'], $m)) { $this->csrf = $m[1]; }
        return $r;
    }

    public function api(string $method, string $path, $body = null): array
    {
        return $this->req($method, $path, $body);
    }

    public function upload(string $token, string $tipo, string $file, string $mime = 'image/jpeg'): array
    {
        return $this->req('POST', "/api/borrador/$token/subir", ['tipo' => $tipo, 'archivo' => new CURLFile($file, $mime, basename($file))]);
    }

    /** Formulario del admin (cookies + _csrf). */
    public function form(string $path, array $fields): array
    {
        $fields['_csrf'] = $this->csrf;
        return $this->req('POST', $path, http_build_query($fields), ['Content-Type: application/x-www-form-urlencoded']);
    }
}

function t_ok(bool $cond, string $name, string $detail = ''): bool
{
    static $n = 0, $f = 0;
    $n++;
    if ($cond) { echo "  \033[32mPASS\033[0m $name\n"; } else { $f++; echo "  \033[31mFAIL\033[0m $name" . ($detail !== '' ? " — $detail" : '') . "\n"; }
    $GLOBALS['__t_fail'] = $f; $GLOBALS['__t_n'] = $n;
    return $cond;
}

function mk_img(string $path, int $w = 900, int $h = 600, int $seed = 1): string
{
    $im = imagecreatetruecolor($w, $h);
    $c1 = imagecolorallocate($im, 40 + $seed * 30 % 200, 80 + $seed * 17 % 150, 120 + $seed * 11 % 100);
    imagefilledrectangle($im, 0, 0, $w, $h, $c1);
    for ($i = 0; $i < 12; $i++) {
        $c = imagecolorallocate($im, ($seed * 53 + $i * 29) % 255, ($seed * 31 + $i * 47) % 255, ($seed * 71 + $i * 13) % 255);
        imagefilledellipse($im, ($i * 97 + $seed * 41) % $w, ($i * 61 + $seed * 23) % $h, 120 + $i * 18, 90 + $i * 12, $c);
    }
    imagejpeg($im, $path, 85);
    return $path;
}

function mysql_val(string $sql): ?string
{
    $o = trim((string) shell_exec('mysql -N -e ' . escapeshellarg($sql) . ' 2>/dev/null'));
    return $o === '' ? null : $o;
}
