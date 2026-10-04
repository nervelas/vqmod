<?php
declare(strict_types=1);

// Utilidades de pruebas de Servicios A: levanta y apaga servidores locales (solo los que esta prueba lanzó).
final class SaHelper
{
    /** @var array<int,array{proc:resource,port:int}> */
    private static array $procs = [];
    public static string $dir = '';

    public static function workDir(string $name): string
    {
        self::$dir = sys_get_temp_dir() . '/ap-sa-' . $name . '-' . getmypid();
        @mkdir(self::$dir, 0777, true);
        register_shutdown_function([self::class, 'stopAll']);
        return self::$dir;
    }

    public static function startPhp(int $port, string $router, array $env = [], ?string $root = null): void
    {
        $cmd = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root ?? dirname(__DIR__, 2), $router];
        self::spawn($cmd, $port, $env + ['PHP_CLI_SERVER_WORKERS' => '4', 'SA_DIR' => self::$dir]);
    }

    public static function startSmtp(int $port, string $mode, string $outFile, array $extra = []): void
    {
        self::spawn(array_merge(['python3', __DIR__ . '/fake_smtp.py', (string) $port, $mode, $outFile], $extra), $port, []);
    }

    /** Servidor que acepta conexiones y nunca responde (para probar tiempos de espera). */
    public static function startSilent(int $port): void
    {
        self::spawn(['python3', __DIR__ . '/silent_server.py', (string) $port], $port, []);
    }

    /** Analiza un correo MIME: devuelve [cabeceras decodificadas, partes [tipo, nombre, contenido]]. */
    public static function parseEml(string $raw): array
    {
        [$h, $body] = explode("\r\n\r\n", $raw, 2);
        $headers = [];
        foreach (explode("\n", preg_replace('/\r\n[ \t]+/', ' ', $h)) as $l) {
            if (strpos($l, ':') !== false) {
                [$k, $v] = explode(':', rtrim($l, "\r"), 2);
                $headers[strtolower($k)] = trim($v);
            }
        }
        return [$headers, self::parts($headers, $body)];
    }

    private static function parts(array $headers, string $body): array
    {
        $ct = $headers['content-type'] ?? 'text/plain';
        if (preg_match('/boundary="([^"]+)"/', $ct, $m)) {
            $out = [];
            foreach (explode('--' . $m[1], $body) as $chunk) {
                $chunk = ltrim($chunk, "\r\n");
                if ($chunk === '' || strncmp($chunk, '--', 2) === 0) {
                    continue;
                }
                [$ph, $pb] = explode("\r\n\r\n", $chunk, 2) + [1 => ''];
                $hh = [];
                foreach (explode("\n", preg_replace('/\r\n[ \t]+/', ' ', $ph)) as $l) {
                    if (strpos($l, ':') !== false) {
                        [$k, $v] = explode(':', rtrim($l, "\r"), 2);
                        $hh[strtolower($k)] = trim($v);
                    }
                }
                $out = array_merge($out, self::parts($hh, $pb));
            }
            return $out;
        }
        $enc = strtolower($headers['content-transfer-encoding'] ?? '7bit');
        $content = $enc === 'base64' ? (string) base64_decode($body) : ($enc === 'quoted-printable' ? quoted_printable_decode($body) : $body);
        $name = null;
        if (preg_match('/filename="([^"]+)"/', $headers['content-disposition'] ?? '', $m)) {
            $name = iconv_mime_decode($m[1], 0, 'UTF-8');
        }
        return [['type' => strtolower(trim(explode(';', $ct)[0])), 'name' => $name, 'content' => $content]];
    }

    private static function spawn(array $cmd, int $port, array $env): void
    {
        $log = self::$dir . '/server_' . $port . '.log';
        $proc = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, null, array_merge(getenv(), $env));
        if (!is_resource($proc)) {
            throw new RuntimeException('No se pudo lanzar el servidor de prueba en el puerto ' . $port);
        }
        self::$procs[$port] = ['proc' => $proc, 'port' => $port];
        for ($i = 0; $i < 100; $i++) {
            $fp = @fsockopen('127.0.0.1', $port, $e, $s, 0.2);
            if ($fp) {
                fclose($fp);
                return;
            }
            usleep(100000);
        }
        throw new RuntimeException('El servidor de prueba del puerto ' . $port . ' no arrancó');
    }

    public static function stop(int $port): void
    {
        if (isset(self::$procs[$port])) {
            proc_terminate(self::$procs[$port]['proc'], 15);
            usleep(150000);
            @proc_terminate(self::$procs[$port]['proc'], 9);
            @proc_close(self::$procs[$port]['proc']);
            unset(self::$procs[$port]);
        }
    }

    public static function stopAll(): void
    {
        foreach (array_keys(self::$procs) as $port) {
            self::stop($port);
        }
    }

    /** @return array<int,array> líneas JSON de un archivo (vacío si no existe) */
    public static function jsonl(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $out = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
            $out[] = json_decode($l, true);
        }
        return $out;
    }

    public static function selfSignedCert(): array
    {
        $key = self::$dir . '/smtp.key';
        $crt = self::$dir . '/smtp.crt';
        $pk = openssl_pkey_new(['private_key_bits' => 2048]);
        $csr = openssl_csr_new(['commonName' => 'localhost'], $pk);
        $x = openssl_csr_sign($csr, null, $pk, 2);
        openssl_x509_export_to_file($x, $crt);
        openssl_pkey_export_to_file($pk, $key);
        return [$crt, $key];
    }

    /** Crea un tipo de evento mínimo (requiere T::boot). */
    public static function makeEvent(array $o = []): int
    {
        $now = gmdate('Y-m-d H:i:s');
        return \App\Core\Db::insert('event_types', $o + [
            'slug' => 'ev-' . bin2hex(random_bytes(4)), 'name' => 'Consulta general', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    /** Crea una cita directamente en la base. Fechas en UTC. */
    public static function makeBooking(int $eventId, array $o = []): int
    {
        $start = $o['starts_at'] ?? gmdate('Y-m-d H:i:s', time() + 5 * 86400);
        $end = $o['ends_at'] ?? gmdate('Y-m-d H:i:s', strtotime($start . ' UTC') + 1800);
        $now = gmdate('Y-m-d H:i:s');
        $hostId = $o['host_id'] ?? (int) \App\Core\Db::val('SELECT id FROM hosts ORDER BY id LIMIT 1');
        $id = \App\Core\Db::insert('bookings', $o + [
            'token' => bin2hex(random_bytes(16)), 'event_type_id' => $eventId, 'host_id' => $hostId,
            'starts_at' => $start, 'ends_at' => $end, 'blocked_start' => $start, 'blocked_end' => $end, 'duration' => 30,
            'guest_name' => 'Lucía Méndez', 'guest_email' => 'lucia@example.test', 'guest_phone' => '55551234',
            'guest_timezone' => 'America/Guatemala', 'status' => 'confirmed', 'created_at' => $now, 'updated_at' => $now,
        ]);
        \App\Core\Db::exec('INSERT IGNORE INTO booking_hosts (booking_id, host_id) VALUES (?, ?)', [$id, $hostId]);
        return $id;
    }
}
