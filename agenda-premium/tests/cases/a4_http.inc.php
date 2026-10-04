<?php
declare(strict_types=1);

// Ayuda para las pruebas de Admin4: servidor embebido de PHP propio + cliente HTTP con cookies.
final class A4Http
{
    public string $base;
    private $proc;
    private string $jar;

    public function __construct(private int $port, string $config)
    {
        $root = dirname(__DIR__, 2);
        $this->base = 'http://127.0.0.1:' . $port;
        $this->jar = tempnam(sys_get_temp_dir(), 'a4jar');
        $env = ['AP_CONFIG' => $config, 'PATH' => getenv('PATH') ?: '/usr/bin:/bin'];
        $this->proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root, $root . '/tests/router.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $root, $env);
        for ($i = 0; $i < 50; $i++) {
            $s = @fsockopen('127.0.0.1', $port, $e, $m, 0.2);
            if ($s) {
                fclose($s);
                return;
            }
            usleep(100000);
        }
        throw new RuntimeException('El servidor de pruebas no arrancó');
    }

    public function stop(): void
    {
        if (is_resource($this->proc)) {
            proc_terminate($this->proc);
            proc_close($this->proc);
        }
        @unlink($this->jar);
    }

    public function newSession(): void
    {
        file_put_contents($this->jar, '');
    }

    /** @return array{code:int,body:string,headers:string,url:string} */
    public function req(string $method, string $path, array $fields = [], bool $multipart = false): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CUSTOMREQUEST => $method]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? $fields : http_build_query($fields));
        }
        $raw = (string) curl_exec($ch);
        $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => substr($raw, $hs), 'headers' => substr($raw, 0, $hs), 'url' => $path];
    }

    public function login(string $email, string $pass): bool
    {
        $this->newSession();
        $r = $this->req('GET', '/admin/login');
        if (!preg_match('/name="_csrf" value="([a-f0-9]+)"/', $r['body'], $m)) {
            return false;
        }
        $p = $this->req('POST', '/admin/login', ['_csrf' => $m[1], 'email' => $email, 'password' => $pass]);
        return $p['code'] === 302;
    }

    public function csrf(string $path = '/admin/ajustes'): string
    {
        $r = $this->req('GET', $path);
        return preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $r['body'], $m) ? $m[1] : '';
    }

    public function post(string $path, array $fields = [], bool $multipart = false, ?string $csrf = null): array
    {
        $fields['_csrf'] = $csrf ?? $this->csrf();
        return $this->req('POST', $path, $fields, $multipart);
    }
}

/** Receptor SMTP mínimo (Python) para probar el correo de prueba. Devuelve [pid-proceso, archivo de volcado]. */
function a4_smtp_sink(int $port): array
{
    $dump = tempnam(sys_get_temp_dir(), 'a4smtp');
    $py = <<<'PY'
import socketserver, sys
dump = sys.argv[2]
class H(socketserver.StreamRequestHandler):
    def handle(self):
        w = lambda s: (self.wfile.write((s + "\r\n").encode()), self.wfile.flush())
        w("220 test ESMTP")
        data = False
        buf = []
        while True:
            line = self.rfile.readline()
            if not line:
                break
            t = line.decode(errors="replace").rstrip("\r\n")
            if data:
                if t == ".":
                    data = False
                    open(dump, "a").write("\n".join(buf) + "\n=====\n")
                    w("250 OK queued")
                else:
                    buf.append(t)
                continue
            u = t.upper()
            if u.startswith("EHLO") or u.startswith("HELO"):
                self.wfile.write(b"250-test\r\n250 AUTH PLAIN LOGIN\r\n"); self.wfile.flush()
            elif u.startswith("AUTH PLAIN"):
                w("235 ok")
            elif u.startswith("AUTH LOGIN"):
                w("334 VXNlcm5hbWU6"); self.rfile.readline(); w("334 UGFzc3dvcmQ6"); self.rfile.readline(); w("235 ok")
            elif u.startswith("DATA"):
                data = True; buf = []; w("354 go")
            elif u.startswith("QUIT"):
                w("221 bye"); break
            else:
                w("250 OK")
class S(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
S(("127.0.0.1", int(sys.argv[1])), H).serve_forever()
PY;
    $f = tempnam(sys_get_temp_dir(), 'a4py') . '.py';
    file_put_contents($f, $py);
    $proc = proc_open(['python3', $f, (string) $port, $dump], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    for ($i = 0; $i < 50; $i++) {
        $s = @fsockopen('127.0.0.1', $port, $e, $m, 0.2);
        if ($s) {
            fclose($s);
            break;
        }
        usleep(100000);
    }
    return [$proc, $dump, $f];
}
