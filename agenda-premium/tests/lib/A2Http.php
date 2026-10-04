<?php
declare(strict_types=1);

/** Cliente HTTP mínimo para pruebas de Admin2: servidor embebido propio, sesión por cookies y CSRF. */
final class A2Http
{
    private static $proc = null;
    private static int $port = 0;
    private string $jar;
    private string $csrf = '';

    public static function start(string $name, int $port): void
    {
        self::$port = $port;
        $root = dirname(__DIR__, 2);
        $env = array_merge($_ENV, ['AP_CONFIG' => '/tmp/ap-t-' . $name . '.config.php', 'PATH' => (string) getenv('PATH')]);
        self::$proc = proc_open(
            ['php', '-S', '127.0.0.1:' . $port, '-t', $root, $root . '/tests/router.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/tmp/a2-http-' . $port . '.log', 'w'], 2 => ['file', '/tmp/a2-http-' . $port . '.log', 'w']],
            $pipes,
            $root,
            $env
        );
        for ($i = 0; $i < 50; $i++) {
            $s = @fsockopen('127.0.0.1', $port, $e, $m, 0.2);
            if ($s) {
                fclose($s);
                return;
            }
            usleep(100000);
        }
        throw new RuntimeException('No arrancó el servidor de pruebas');
    }

    public static function stop(): void
    {
        if (self::$proc) {
            proc_terminate(self::$proc);
            proc_close(self::$proc);
            self::$proc = null;
        }
    }

    public function __construct()
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'a2jar');
    }

    public function login(string $email, string $pass): bool
    {
        $g = $this->get('/admin/login');
        preg_match('/name="_csrf" value="([^"]+)"/', $g['body'], $m);
        $r = $this->post('/admin/login', ['email' => $email, 'password' => $pass, '_csrf' => $m[1] ?? ''], false);
        if ($r['status'] !== 302) {
            return false;
        }
        $this->refreshCsrf();
        return true;
    }

    public function refreshCsrf(): void
    {
        $r = $this->get('/admin');
        if (preg_match('/name="csrf-token" content="([^"]+)"/', $r['body'], $m)) {
            $this->csrf = html_entity_decode($m[1]);
        }
    }

    /** @return array{status:int,body:string,loc:string} */
    public function get(string $path, bool $json = false): array
    {
        return $this->req('GET', $path, null, $json);
    }

    /** $fields puede incluir CURLFile para subir archivos. */
    public function post(string $path, array $fields = [], bool $csrf = true, bool $json = false): array
    {
        if ($csrf) {
            $fields['_csrf'] = $this->csrf;
        }
        return $this->req('POST', $path, $fields, $json);
    }

    private function req(string $method, string $path, ?array $fields, bool $json): array
    {
        $ch = curl_init('http://127.0.0.1:' . self::$port . $path);
        $hdr = $json ? ['Accept: application/json', 'X-Requested-With: XMLHttpRequest'] : [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_HTTPHEADER => $hdr, CURLOPT_TIMEOUT => 30,
        ]);
        if ($method === 'POST') {
            $hasFile = false;
            foreach ($fields as $v) {
                if ($v instanceof CURLFile) {
                    $hasFile = true;
                }
            }
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $hasFile ? $fields : http_build_query($fields));
        }
        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $head = substr($raw, 0, $size);
        $loc = preg_match('/^Location:\s*(.+)$/mi', $head, $m) ? trim($m[1]) : '';
        return ['status' => $status, 'body' => substr($raw, $size), 'loc' => $loc];
    }
}
