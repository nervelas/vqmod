<?php
declare(strict_types=1);

// Servidor PHP embebido y cliente HTTP mínimo para las pruebas públicas (sin cookies). Apaga solo el proceso que lanzó.
final class PubHttp
{
    public static int $port = 8192;
    private static $proc = null;
    private static int $pid = 0;

    public static function start(string $config, int $port = 8192): void
    {
        self::$port = $port;
        $root = dirname(__DIR__, 2);
        self::$proc = proc_open(
            ['php', '-S', '127.0.0.1:' . $port, '-t', $root, $root . '/tests/router.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            $root,
            ['AP_CONFIG' => $config, 'PATH' => (string) getenv('PATH')]
        );
        $st = proc_get_status(self::$proc);
        self::$pid = (int) ($st['pid'] ?? 0);
        register_shutdown_function([self::class, 'stop']);
        for ($i = 0; $i < 50; $i++) {
            $c = @fsockopen('127.0.0.1', $port, $en, $es, 0.2);
            if ($c) {
                fclose($c);
                return;
            }
            usleep(100000);
        }
    }

    public static function stop(): void
    {
        if (is_resource(self::$proc)) {
            @proc_terminate(self::$proc);
            if (self::$pid > 0 && function_exists('posix_kill')) {
                @posix_kill(self::$pid, 15);
            }
            @proc_close(self::$proc);
            self::$proc = null;
        }
    }

    /** @return array{0:int,1:string,2:array} estado, cuerpo y cabeceras (minúsculas) */
    public static function req(string $method, string $path, $data = null, array $headers = [], bool $json = true): array
    {
        $ch = curl_init('http://127.0.0.1:' . self::$port . $path);
        $h = [];
        foreach ($headers as $k => $v) {
            $h[] = $k . ': ' . $v;
        }
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 20]);
        if ($data !== null) {
            $multipart = is_array($data) && array_filter($data, static fn($v) => $v instanceof CURLFile);
            if ($multipart) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } elseif ($json) {
                $h[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
        $raw = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $hdr = [];
        foreach (explode("\r\n", substr($raw, 0, $hs)) as $line) {
            if (strpos($line, ':') !== false) {
                [$k, $v] = explode(':', $line, 2);
                $hdr[strtolower(trim($k))][] = trim($v);
            }
        }
        return [$code, substr($raw, $hs), $hdr];
    }

    /** Datos <script id="boot"> de una página pública. */
    public static function boot(string $path): array
    {
        [, $b] = self::req('GET', $path);
        return preg_match('#<script type="application/json" id="boot">(.*?)</script>#s', $b, $m) ? (array) json_decode($m[1], true) : [];
    }
}
