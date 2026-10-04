<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Cliente SMTP nativo (sockets). Soporta SSL implícito, STARTTLS, AUTH LOGIN y PLAIN.
 * Todos los errores se lanzan como RuntimeException con mensaje amable en español;
 * nunca se incluyen contraseñas en los mensajes.
 */
final class SmtpClient
{
    /** @var resource|null */
    private $fp = null;
    /** @var string[] capacidades anunciadas en EHLO (en mayúsculas) */
    private array $caps = [];

    public function __construct(
        private string $host,
        private int $port,
        private string $secure = 'tls',
        private string $user = '',
        private string $pass = '',
        private int $timeout = 15,
        private ?string $helo = null,
        private bool $verifyPeer = true
    ) {
        if (!in_array($this->secure, ['ssl', 'tls', 'none'], true)) {
            $this->secure = 'none';
        }
        $this->timeout = max(3, min(60, $this->timeout));
    }

    /** Abre la conexión, negocia el cifrado y se autentica si hay usuario. */
    public function connect(): void
    {
        $target = (strpos($this->host, ':') !== false && $this->host[0] !== '[') ? '[' . $this->host . ']' : $this->host;
        $ctx = stream_context_create(['ssl' => [
            'verify_peer' => $this->verifyPeer,
            'verify_peer_name' => $this->verifyPeer,
            'allow_self_signed' => !$this->verifyPeer,
            'peer_name' => $this->host,
            'SNI_enabled' => true,
        ]]);
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client(($this->secure === 'ssl' ? 'ssl://' : 'tcp://') . $target . ':' . $this->port, $errno, $errstr, (float) $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!is_resource($fp)) {
            throw new RuntimeException($this->connectError($errno, $errstr));
        }
        $this->fp = $fp;
        stream_set_timeout($this->fp, $this->timeout);

        $this->expect($this->readReply(), [220], 'el saludo inicial');
        $this->ehlo();

        if ($this->secure === 'tls') {
            if (!in_array('STARTTLS', $this->caps, true)) {
                throw new RuntimeException('El servidor de correo no ofrece cifrado STARTTLS. Prueba con seguridad SSL (puerto 465) o revisa el puerto.');
            }
            $this->expect($this->command('STARTTLS'), [220], 'el inicio del cifrado');
            $ok = @stream_socket_enable_crypto($this->fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($ok !== true) {
                throw new RuntimeException('No se pudo establecer la conexión cifrada con el servidor de correo. Revisa el certificado del servidor o el tipo de seguridad.');
            }
            $this->ehlo();
        }
        if ($this->user !== '') {
            $this->authenticate();
        }
    }

    /** Envía un mensaje ya construido (cabeceras + cuerpo, líneas CRLF). */
    public function send(string $from, array $recipients, string $message): void
    {
        $this->requireOpen();
        $this->expect($this->command('MAIL FROM:<' . $this->addr($from) . '>'), [250], 'el remitente');
        $accepted = 0;
        $lastError = '';
        foreach ($recipients as $rcpt) {
            $reply = $this->command('RCPT TO:<' . $this->addr((string) $rcpt) . '>');
            if (in_array($reply[0], [250, 251], true)) {
                $accepted++;
            } else {
                $lastError = $this->friendly($reply, 'el destinatario');
            }
        }
        if ($accepted === 0) {
            throw new RuntimeException($lastError !== '' ? $lastError : 'El servidor no aceptó al destinatario.');
        }
        $this->expect($this->command('DATA'), [354], 'el inicio del mensaje');

        $message = preg_replace('/\r\n|\r|\n/', "\r\n", $message) ?? $message;
        // Relleno de puntos (RFC 5321 §4.5.2): una línea que empieza con "." se duplica.
        $message = str_replace("\r\n.", "\r\n..", $message);
        if ($message !== '' && $message[0] === '.') {
            $message = '.' . $message;
        }
        if (substr($message, -2) !== "\r\n") {
            $message .= "\r\n";
        }
        $this->write($message . ".\r\n");
        $this->expect($this->readReply(), [250], 'el envío del mensaje');
    }

    public function close(): void
    {
        if (is_resource($this->fp)) {
            @fwrite($this->fp, "QUIT\r\n");
            @fclose($this->fp);
        }
        $this->fp = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function ehlo(): void
    {
        $reply = $this->command('EHLO ' . $this->heloName());
        if ($reply[0] !== 250) {
            // Servidores antiguos: HELO sin extensiones
            $reply = $this->command('HELO ' . $this->heloName());
            $this->expect($reply, [250], 'la presentación (HELO)');
            $this->caps = [];
            return;
        }
        $this->caps = [];
        foreach (array_slice(explode("\n", $reply[1]), 1) as $line) {
            $this->caps[] = strtoupper(trim($line));
        }
    }

    private function authenticate(): void
    {
        $mechs = [];
        foreach ($this->caps as $cap) {
            if (strncmp($cap, 'AUTH ', 5) === 0 || strncmp($cap, 'AUTH=', 5) === 0) {
                $mechs = array_merge($mechs, preg_split('/\s+/', substr($cap, 5)) ?: []);
            }
        }
        $mech = in_array('LOGIN', $mechs, true) ? 'LOGIN' : (in_array('PLAIN', $mechs, true) ? 'PLAIN' : ($mechs === [] ? 'LOGIN' : ''));
        if ($mech === '') {
            throw new RuntimeException('El servidor de correo no acepta los métodos de autenticación LOGIN ni PLAIN.');
        }
        if ($mech === 'PLAIN') {
            $reply = $this->command('AUTH PLAIN ' . base64_encode("\0" . $this->user . "\0" . $this->pass));
        } else {
            $reply = $this->command('AUTH LOGIN');
            if ($reply[0] === 334) {
                $reply = $this->command(base64_encode($this->user));
            }
            if ($reply[0] === 334) {
                $reply = $this->command(base64_encode($this->pass));
            }
        }
        if ($reply[0] !== 235) {
            throw new RuntimeException('El servidor de correo rechazó el usuario o la contraseña. Revisa los datos de acceso.');
        }
    }

    /** @return array{0:int,1:string} [código, texto (varias líneas separadas por \n)] */
    private function command(string $line): array
    {
        $this->write($line . "\r\n");
        return $this->readReply();
    }

    private function write(string $data): void
    {
        $this->requireOpen();
        $len = strlen($data);
        $sent = 0;
        while ($sent < $len) {
            $n = @fwrite($this->fp, substr($data, $sent, 16384));
            if ($n === false || $n === 0) {
                $meta = is_resource($this->fp) ? stream_get_meta_data($this->fp) : [];
                throw new RuntimeException(!empty($meta['timed_out'])
                    ? 'El servidor de correo tardó demasiado en responder.'
                    : 'Se perdió la conexión con el servidor de correo.');
            }
            $sent += $n;
        }
    }

    /** @return array{0:int,1:string} */
    private function readReply(): array
    {
        $this->requireOpen();
        $text = [];
        $code = 0;
        for ($i = 0; $i < 200; $i++) {
            $line = @fgets($this->fp, 4096);
            if ($line === false) {
                $meta = stream_get_meta_data($this->fp);
                throw new RuntimeException(!empty($meta['timed_out'])
                    ? 'El servidor de correo tardó demasiado en responder.'
                    : 'El servidor de correo cerró la conexión de forma inesperada.');
            }
            $line = rtrim($line, "\r\n");
            if (!preg_match('/^(\d{3})([ -])?(.*)$/', $line, $m)) {
                throw new RuntimeException('El servidor de correo respondió algo que no se entiende.');
            }
            $code = (int) $m[1];
            $text[] = $m[3];
            if (($m[2] ?? ' ') !== '-') {
                break;
            }
        }
        return [$code, implode("\n", $text)];
    }

    /** @param array{0:int,1:string} $reply */
    private function expect(array $reply, array $codes, string $stage): void
    {
        if (!in_array($reply[0], $codes, true)) {
            throw new RuntimeException($this->friendly($reply, $stage));
        }
    }

    /** @param array{0:int,1:string} $reply */
    private function friendly(array $reply, string $stage): string
    {
        $detail = mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $reply[1])), 0, 160);
        $base = $reply[0] >= 500
            ? 'El servidor de correo rechazó ' . $stage . ' (código ' . $reply[0] . ').'
            : 'El servidor de correo no pudo completar ' . $stage . ' por ahora (código ' . $reply[0] . ').';
        return $detail !== '' ? $base . ' Respuesta: ' . $detail : $base;
    }

    private function connectError(int $errno, string $errstr): string
    {
        $e = strtolower($errstr);
        if (strpos($e, 'ssl') !== false || strpos($e, 'tls') !== false || strpos($e, 'certificate') !== false) {
            return 'No se pudo establecer la conexión segura con el servidor de correo. Revisa el certificado, el puerto y el tipo de seguridad.';
        }
        if (strpos($e, 'getaddrinfo') !== false || strpos($e, 'resolve') !== false || strpos($e, 'name or service') !== false) {
            return 'No se encontró el servidor de correo «' . $this->host . '». Revisa que el nombre esté bien escrito.';
        }
        if ($errno === 111 || strpos($e, 'refused') !== false) {
            return 'El servidor de correo rechazó la conexión en el puerto ' . $this->port . '. Revisa el puerto.';
        }
        if ($errno === 110 || strpos($e, 'timed out') !== false) {
            return 'El servidor de correo no respondió a tiempo. Revisa el servidor y el puerto, o si tu hosting bloquea las conexiones salientes.';
        }
        return 'No se pudo conectar con el servidor de correo. Revisa el servidor y el puerto.';
    }

    private function heloName(): string
    {
        $h = $this->helo ?? (string) gethostname();
        return preg_match('/^[A-Za-z0-9.\-]{1,200}$/', $h) ? $h : 'localhost';
    }

    /** Dirección sin caracteres que rompan el protocolo. */
    private function addr(string $a): string
    {
        return str_replace(["\r", "\n", '<', '>', ' '], '', $a);
    }

    private function requireOpen(): void
    {
        if (!is_resource($this->fp)) {
            throw new RuntimeException('No hay conexión abierta con el servidor de correo.');
        }
    }
}
