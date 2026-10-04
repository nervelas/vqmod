<?php
declare(strict_types=1);

// Ayuda de las pruebas a1_*: levanta un servidor PHP propio y ofrece un cliente HTTP con cookies y CSRF.
final class A1Http
{
    private static $proc = null;
    private static array $pipes = [];
    public static string $base = '';

    public static function start(int $port, string $config): void
    {
        $root = dirname(__DIR__, 2);
        $cmd = ['php', '-S', '127.0.0.1:' . $port, '-t', $root, $root . '/tests/router.php'];
        self::$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], self::$pipes, $root, ['AP_CONFIG' => $config, 'PATH' => (string) getenv('PATH')]);
        self::$base = 'http://127.0.0.1:' . $port;
        for ($i = 0; $i < 50; $i++) {
            $s = @fsockopen('127.0.0.1', $port, $e, $m, 0.2);
            if ($s) {
                fclose($s);
                break;
            }
            usleep(100000);
        }
        register_shutdown_function([self::class, 'stop']);
    }

    public static function stop(): void
    {
        if (self::$proc) {
            proc_terminate(self::$proc);
            proc_close(self::$proc);
            self::$proc = null;
        }
    }
}

final class A1Client
{
    public string $jar;
    public array $last = ['status' => 0, 'body' => '', 'headers' => ''];
    private string $token = '';

    public function __construct()
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'a1jar');
    }

    public function request(string $method, string $path, array $data = [], array $headers = [], bool $json = false): array
    {
        $ch = curl_init(A1Http::$base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $method]);
        if ($method === 'POST') {
            if ($json) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                $headers[] = 'Content-Type: application/json';
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $raw = (string) curl_exec($ch);
        $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->last = ['status' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE), 'headers' => substr($raw, 0, $size), 'body' => substr($raw, $size)];
        curl_close($ch);
        if (preg_match('/name="csrf-token" content="([^"]+)"/', $this->last['body'], $m) || preg_match('/name="_csrf" value="([^"]+)"/', $this->last['body'], $m)) {
            $this->token = $m[1];
        }
        return $this->last;
    }

    public function get(string $path, array $headers = []): array
    {
        return $this->request('GET', $path, [], $headers);
    }

    public function location(): string
    {
        return preg_match('/^Location:\s*(\S+)/mi', $this->last['headers'], $m) ? $m[1] : '';
    }

    /** Token CSRF de la última página (meta o campo oculto). */
    public function csrf(): string
    {
        return $this->token;
    }

    /** GET de $formPath para obtener el CSRF y luego POST. */
    public function postForm(string $formPath, string $postPath, array $data): array
    {
        $this->get($formPath);
        return $this->request('POST', $postPath, $data + ['_csrf' => $this->csrf()]);
    }

    public function login(string $email, string $password): array
    {
        return $this->postForm('/admin/login', '/admin/login', ['email' => $email, 'password' => $password]);
    }

    public function postJson(string $path, array $data): array
    {
        return $this->request('POST', $path, $data, ['X-CSRF-Token: ' . $this->csrf(), 'Accept: application/json', 'X-Requested-With: XMLHttpRequest'], true);
    }

    public function getJson(string $path): array
    {
        $r = $this->get($path, ['Accept: application/json', 'X-Requested-With: XMLHttpRequest']);
        $r['json'] = json_decode($r['body'], true);
        return $r;
    }
}
