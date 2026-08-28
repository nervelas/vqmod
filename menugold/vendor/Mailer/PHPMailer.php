<?php
declare(strict_types=1);

namespace MenuGold\Vendor\Mailer;

/**
 * Cliente de correo compacto y autonomo con la interfaz clasica de PHPMailer.
 * Envia por SMTP (con STARTTLS o SSL implicito) o por la funcion mail() de PHP.
 * No requiere composer ni extensiones adicionales mas alla de openssl.
 */
class PHPMailer
{
    public string $Host = '';
    public int $Port = 587;
    public bool $SMTPAuth = false;
    public string $Username = '';
    public string $Password = '';
    public string $SMTPSecure = 'tls';       // tls | ssl | ''
    public bool $SMTPAutoTLS = true;
    public int $Timeout = 20;
    public string $CharSet = 'UTF-8';
    public string $Subject = '';
    public string $Body = '';
    public string $AltBody = '';
    public string $ErrorInfo = '';
    public bool $SMTPDebug = false;
    public array $DebugLog = [];

    private bool $usarSmtp = false;
    private bool $html = false;
    private array $de = ['', ''];
    private array $para = [];
    private array $cc = [];
    private array $bcc = [];
    private array $responder = [];
    private array $adjuntos = [];
    /** @var resource|null */
    private $conn = null;

    public function isSMTP(): void { $this->usarSmtp = true; }
    public function isMail(): void { $this->usarSmtp = false; }
    public function isHTML(bool $v = true): void { $this->html = $v; }

    public function setFrom(string $email, string $nombre = ''): bool
    {
        if (!$this->valido($email)) { $this->ErrorInfo = 'Remitente inválido.'; return false; }
        $this->de = [$email, $nombre];
        return true;
    }

    public function addAddress(string $email, string $nombre = ''): bool
    {
        if (!$this->valido($email)) { $this->ErrorInfo = 'Destinatario inválido: ' . $email; return false; }
        $this->para[] = [$email, $nombre];
        return true;
    }

    public function addCC(string $email, string $nombre = ''): bool
    {
        if (!$this->valido($email)) return false;
        $this->cc[] = [$email, $nombre];
        return true;
    }

    public function addBCC(string $email, string $nombre = ''): bool
    {
        if (!$this->valido($email)) return false;
        $this->bcc[] = [$email, $nombre];
        return true;
    }

    public function addReplyTo(string $email, string $nombre = ''): bool
    {
        if (!$this->valido($email)) return false;
        $this->responder[] = [$email, $nombre];
        return true;
    }

    /** Adjunta un archivo del disco o contenido en memoria. */
    public function addAttachment(string $ruta, string $nombre = '', string $mime = 'application/octet-stream'): bool
    {
        if (!is_file($ruta) || !is_readable($ruta)) { $this->ErrorInfo = 'Adjunto no legible.'; return false; }
        $this->adjuntos[] = [
            'nombre' => $nombre !== '' ? $nombre : basename($ruta),
            'datos'  => (string)file_get_contents($ruta),
            'mime'   => $mime,
        ];
        return true;
    }

    public function addStringAttachment(string $datos, string $nombre, string $mime = 'application/octet-stream'): void
    {
        $this->adjuntos[] = ['nombre' => $nombre, 'datos' => $datos, 'mime' => $mime];
    }

    public function clearAddresses(): void { $this->para = $this->cc = $this->bcc = []; }
    public function clearAttachments(): void { $this->adjuntos = []; }

    // =====================================================================
    public function send(): bool
    {
        if (!$this->para) { $this->ErrorInfo = 'No hay destinatarios.'; return false; }
        if ($this->de[0] === '') $this->de[0] = 'no-responder@' . (string)($_SERVER['HTTP_HOST'] ?? 'localhost');

        [$cabeceras, $cuerpo] = $this->construir();
        if ($this->usarSmtp && $this->Host !== '') {
            return $this->enviarSmtp($cabeceras, $cuerpo);
        }
        return $this->enviarMail($cabeceras, $cuerpo);
    }

    private function construir(): array
    {
        $limite  = '=_mg_' . bin2hex(random_bytes(12));
        $limiteA = '=_ad_' . bin2hex(random_bytes(12));
        $eol = "\r\n";

        $h = [];
        $h[] = 'Date: ' . date('r');
        $h[] = 'From: ' . $this->dir($this->de);
        if ($this->cc)  $h[] = 'Cc: ' . implode(', ', array_map([$this, 'dir'], $this->cc));
        if ($this->responder) $h[] = 'Reply-To: ' . implode(', ', array_map([$this, 'dir'], $this->responder));
        $h[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $this->dominio() . '>';
        $h[] = 'MIME-Version: 1.0';
        $h[] = 'X-Mailer: MenuGold';

        $alt = $this->AltBody !== '' ? $this->AltBody : trim(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $this->Body)));

        if ($this->adjuntos) {
            $h[] = 'Content-Type: multipart/mixed; boundary="' . $limiteA . '"';
            $b  = '--' . $limiteA . $eol;
            $b .= 'Content-Type: multipart/alternative; boundary="' . $limite . '"' . $eol . $eol;
            $b .= $this->parteAlternativa($limite, $alt, $eol);
            foreach ($this->adjuntos as $a) {
                $b .= '--' . $limiteA . $eol;
                $b .= 'Content-Type: ' . $a['mime'] . '; name="' . $this->limpiar($a['nombre']) . '"' . $eol;
                $b .= 'Content-Transfer-Encoding: base64' . $eol;
                $b .= 'Content-Disposition: attachment; filename="' . $this->limpiar($a['nombre']) . '"' . $eol . $eol;
                $b .= chunk_split(base64_encode($a['datos']), 76, $eol) . $eol;
            }
            $b .= '--' . $limiteA . '--' . $eol;
        } elseif ($this->html) {
            $h[] = 'Content-Type: multipart/alternative; boundary="' . $limite . '"';
            $b = $this->parteAlternativa($limite, $alt, $eol);
        } else {
            $h[] = 'Content-Type: text/plain; charset=' . $this->CharSet;
            $h[] = 'Content-Transfer-Encoding: base64';
            $b = chunk_split(base64_encode($this->Body), 76, $eol);
        }
        return [implode($eol, $h), $b];
    }

    private function parteAlternativa(string $limite, string $alt, string $eol): string
    {
        $b  = '--' . $limite . $eol;
        $b .= 'Content-Type: text/plain; charset=' . $this->CharSet . $eol;
        $b .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
        $b .= chunk_split(base64_encode($alt), 76, $eol) . $eol;
        $b .= '--' . $limite . $eol;
        $b .= 'Content-Type: text/html; charset=' . $this->CharSet . $eol;
        $b .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
        $b .= chunk_split(base64_encode($this->Body), 76, $eol) . $eol;
        $b .= '--' . $limite . '--' . $eol;
        return $b;
    }

    private function enviarMail(string $cabeceras, string $cuerpo): bool
    {
        $to = implode(', ', array_map([$this, 'dir'], $this->para));
        $ok = @mail($to, $this->codificar($this->Subject), $cuerpo, $cabeceras, '-f' . $this->de[0]);
        if (!$ok) $this->ErrorInfo = 'La función mail() del servidor rechazó el envío.';
        return $ok;
    }

    // ------------------------------------------------------------- SMTP
    private function enviarSmtp(string $cabeceras, string $cuerpo): bool
    {
        $host = $this->Host;
        $seg  = strtolower($this->SMTPSecure);
        if ($seg === 'ssl') $host = 'ssl://' . $host;

        $ctx = stream_context_create(['ssl' => [
            'verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false,
        ]]);
        $err = 0; $msg = '';
        $this->conn = @stream_socket_client($host . ':' . $this->Port, $err, $msg, $this->Timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->conn) {
            $this->ErrorInfo = 'No se pudo conectar al servidor SMTP: ' . $msg;
            return false;
        }
        stream_set_timeout($this->conn, $this->Timeout);

        try {
            if (!$this->esperar('220')) throw new \RuntimeException('El servidor SMTP no respondió al saludo.');
            $ehlo = $this->dominio();
            $this->cmd('EHLO ' . $ehlo);
            $cap = $this->leer();
            if (strncmp($cap, '250', 3) !== 0) {
                $this->cmd('HELO ' . $ehlo);
                if (!$this->esperar('250')) throw new \RuntimeException('Saludo SMTP rechazado.');
            }

            if ($seg === 'tls' || ($this->SMTPAutoTLS && stripos($cap, 'STARTTLS') !== false && $seg !== 'ssl')) {
                $this->cmd('STARTTLS');
                if (!$this->esperar('220')) throw new \RuntimeException('STARTTLS no disponible.');
                $cripto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $cripto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (!@stream_socket_enable_crypto($this->conn, true, $cripto)) {
                    throw new \RuntimeException('No se pudo establecer el cifrado TLS.');
                }
                $this->cmd('EHLO ' . $ehlo);
                $this->leer();
            }

            if ($this->SMTPAuth && $this->Username !== '') {
                $this->cmd('AUTH LOGIN');
                if (!$this->esperar('334')) throw new \RuntimeException('El servidor no acepta AUTH LOGIN.');
                $this->cmd(base64_encode($this->Username));
                if (!$this->esperar('334')) throw new \RuntimeException('Usuario SMTP rechazado.');
                $this->cmd(base64_encode($this->Password));
                if (!$this->esperar('235')) throw new \RuntimeException('Contraseña SMTP rechazada.');
            }

            $this->cmd('MAIL FROM:<' . $this->de[0] . '>');
            if (!$this->esperar('250')) throw new \RuntimeException('Remitente rechazado por el servidor.');

            foreach (array_merge($this->para, $this->cc, $this->bcc) as $d) {
                $this->cmd('RCPT TO:<' . $d[0] . '>');
                if (!$this->esperar(['250', '251'])) throw new \RuntimeException('Destinatario rechazado: ' . $d[0]);
            }

            $this->cmd('DATA');
            if (!$this->esperar('354')) throw new \RuntimeException('El servidor no aceptó los datos.');

            $to = implode(', ', array_map([$this, 'dir'], $this->para));
            $mensaje = 'To: ' . $to . "\r\n"
                     . 'Subject: ' . $this->codificar($this->Subject) . "\r\n"
                     . $cabeceras . "\r\n\r\n"
                     . $cuerpo;
            // Punto al inicio de linea debe duplicarse
            $mensaje = preg_replace('/^\./m', '..', $mensaje) ?? $mensaje;
            $this->escribir($mensaje . "\r\n.\r\n");
            if (!$this->esperar('250')) throw new \RuntimeException('El servidor no confirmó la entrega.');

            $this->cmd('QUIT');
            fclose($this->conn);
            $this->conn = null;
            return true;
        } catch (\Throwable $e) {
            $this->ErrorInfo = $e->getMessage();
            if (is_resource($this->conn)) { @fwrite($this->conn, "QUIT\r\n"); @fclose($this->conn); }
            $this->conn = null;
            return false;
        }
    }

    private function cmd(string $c): void { $this->escribir($c . "\r\n"); }

    private function escribir(string $s): void
    {
        if (is_resource($this->conn)) @fwrite($this->conn, $s);
        if ($this->SMTPDebug) $this->DebugLog[] = '> ' . substr($s, 0, 120);
    }

    private function leer(): string
    {
        if (!is_resource($this->conn)) return '';
        $out = '';
        while (($linea = fgets($this->conn, 1024)) !== false) {
            $out .= $linea;
            if (strlen($linea) < 4 || $linea[3] === ' ') break;
            $meta = stream_get_meta_data($this->conn);
            if ($meta['timed_out']) break;
        }
        if ($this->SMTPDebug) $this->DebugLog[] = '< ' . trim($out);
        return $out;
    }

    private function esperar($codigos): bool
    {
        $r = $this->leer();
        foreach ((array)$codigos as $c) {
            if (strncmp($r, (string)$c, strlen((string)$c)) === 0) return true;
        }
        $this->ErrorInfo = trim($r) !== '' ? trim($r) : 'Sin respuesta del servidor SMTP.';
        return false;
    }

    // ------------------------------------------------------------- utiles
    private function dir(array $d): string
    {
        return $d[1] !== '' ? '"' . $this->codificar($this->limpiar($d[1])) . '" <' . $d[0] . '>' : $d[0];
    }

    private function codificar(string $s): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $s)) return $s;
        return '=?UTF-8?B?' . base64_encode($s) . '?=';
    }

    private function limpiar(string $s): string
    {
        return str_replace(["\r", "\n", '"'], '', $s);
    }

    private function valido(string $email): bool
    {
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL)
            && !preg_match('/[\r\n]/', $email);
    }

    private function dominio(): string
    {
        $h = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $h = preg_replace('/:\d+$/', '', $h) ?? 'localhost';
        return preg_match('/^[A-Za-z0-9.\-]+$/', $h) ? $h : 'localhost';
    }
}
