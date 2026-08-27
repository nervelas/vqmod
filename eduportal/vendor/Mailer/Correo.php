<?php
declare(strict_types=1);

namespace Vendor\Mailer;

/**
 * Cliente de correo local: SMTP con STARTTLS/SSL y AUTH LOGIN/PLAIN,
 * con respaldo a mail() cuando SMTP no esta configurado.
 */
class Correo
{
    private string $host = '';
    private int $puerto = 587;
    private string $usuario = '';
    private string $password = '';
    private string $seguridad = 'tls'; // tls | ssl | none
    private bool $usarSmtp = false;
    private int $timeout = 15;

    private string $deEmail = '';
    private string $deNombre = 'EduPortal';
    /** @var array<int,array{0:string,1:string}> */
    private array $para = [];
    private array $copiaOculta = [];
    private string $responderA = '';
    private string $asunto = '';
    private string $html = '';
    private string $texto = '';
    /** @var array<int,array{nombre:string,contenido:string,mime:string}> */
    private array $adjuntos = [];
    private string $ultimoError = '';

    public function configurarSmtp(string $host, int $puerto, string $usuario, string $password, string $seguridad = 'tls'): void
    {
        $this->host = trim($host);
        $this->puerto = $puerto > 0 ? $puerto : 587;
        $this->usuario = $usuario;
        $this->password = $password;
        $this->seguridad = in_array($seguridad, ['tls', 'ssl', 'none'], true) ? $seguridad : 'tls';
        $this->usarSmtp = $this->host !== '';
    }

    public function remitente(string $email, string $nombre = 'EduPortal'): void
    {
        $this->deEmail = $email;
        $this->deNombre = $nombre;
    }

    public function responderA(string $email): void { $this->responderA = $email; }

    public function agregarDestinatario(string $email, string $nombre = ''): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $this->para[] = [$email, $nombre];
        return true;
    }

    public function agregarOculto(string $email): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->copiaOculta[] = $email;
        }
    }

    public function asunto(string $asunto): void { $this->asunto = $asunto; }

    public function cuerpo(string $html, string $texto = ''): void
    {
        $this->html = $html;
        $this->texto = $texto !== '' ? $texto : trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>'], "\n", $html)), ENT_QUOTES, 'UTF-8'));
    }

    public function adjuntar(string $nombre, string $contenido, string $mime = 'application/octet-stream'): void
    {
        $this->adjuntos[] = ['nombre' => $nombre, 'contenido' => $contenido, 'mime' => $mime];
    }

    public function error(): string { return $this->ultimoError; }

    public function limpiarDestinatarios(): void
    {
        $this->para = [];
        $this->copiaOculta = [];
        $this->adjuntos = [];
    }

    public function enviar(): bool
    {
        if ($this->para === []) {
            $this->ultimoError = 'No hay destinatarios.';
            return false;
        }
        if ($this->deEmail === '') {
            $this->deEmail = 'no-reply@' . (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        $limite = 'b' . bin2hex(random_bytes(12));
        $cuerpo = $this->construirCuerpo($limite);
        $cabeceras = $this->construirCabeceras($limite);

        if ($this->usarSmtp) {
            return $this->enviarSmtp($cabeceras, $cuerpo);
        }
        $lista = implode(', ', array_map(fn($p) => $this->direccion($p[0], $p[1]), $this->para));
        $h = [];
        foreach ($cabeceras as $k => $v) {
            if (strcasecmp($k, 'To') !== 0 && strcasecmp($k, 'Subject') !== 0) {
                $h[] = $k . ': ' . $v;
            }
        }
        $ok = @mail($lista, $this->codificarCabecera($this->asunto), $cuerpo, implode("\r\n", $h));
        if (!$ok) {
            $this->ultimoError = 'La funcion mail() del servidor rechazo el envio.';
        }
        return $ok;
    }

    private function direccion(string $email, string $nombre = ''): string
    {
        return $nombre === '' ? $email : $this->codificarCabecera($nombre) . ' <' . $email . '>';
    }

    private function codificarCabecera(string $texto): string
    {
        return preg_match('/[^\x20-\x7E]/', $texto)
            ? '=?UTF-8?B?' . base64_encode($texto) . '?='
            : $texto;
    }

    /** @return array<string,string> */
    private function construirCabeceras(string $limite): array
    {
        $h = [
            'Date'         => date('r'),
            'From'         => $this->direccion($this->deEmail, $this->deNombre),
            'To'           => implode(', ', array_map(fn($p) => $this->direccion($p[0], $p[1]), $this->para)),
            'Subject'      => $this->codificarCabecera($this->asunto),
            'Message-ID'   => '<' . bin2hex(random_bytes(12)) . '@' . (string)($_SERVER['HTTP_HOST'] ?? 'eduportal') . '>',
            'MIME-Version' => '1.0',
        ];
        if ($this->responderA !== '') {
            $h['Reply-To'] = $this->responderA;
        }
        $h['Content-Type'] = $this->adjuntos !== []
            ? 'multipart/mixed; boundary="' . $limite . '"'
            : 'multipart/alternative; boundary="' . $limite . '"';
        return $h;
    }

    private function construirCuerpo(string $limite): string
    {
        $eol = "\r\n";
        $alt = 'a' . bin2hex(random_bytes(10));
        $partesAlt = '--' . $alt . $eol
            . 'Content-Type: text/plain; charset=UTF-8' . $eol
            . 'Content-Transfer-Encoding: base64' . $eol . $eol
            . chunk_split(base64_encode($this->texto), 76, $eol) . $eol
            . '--' . $alt . $eol
            . 'Content-Type: text/html; charset=UTF-8' . $eol
            . 'Content-Transfer-Encoding: base64' . $eol . $eol
            . chunk_split(base64_encode($this->html), 76, $eol) . $eol
            . '--' . $alt . '--' . $eol;

        if ($this->adjuntos === []) {
            // multipart/alternative directo usando el limite principal
            return '--' . $limite . $eol
                . 'Content-Type: text/plain; charset=UTF-8' . $eol
                . 'Content-Transfer-Encoding: base64' . $eol . $eol
                . chunk_split(base64_encode($this->texto), 76, $eol) . $eol
                . '--' . $limite . $eol
                . 'Content-Type: text/html; charset=UTF-8' . $eol
                . 'Content-Transfer-Encoding: base64' . $eol . $eol
                . chunk_split(base64_encode($this->html), 76, $eol) . $eol
                . '--' . $limite . '--' . $eol;
        }

        $cuerpo = '--' . $limite . $eol
            . 'Content-Type: multipart/alternative; boundary="' . $alt . '"' . $eol . $eol
            . $partesAlt . $eol;
        foreach ($this->adjuntos as $a) {
            $cuerpo .= '--' . $limite . $eol
                . 'Content-Type: ' . $a['mime'] . '; name="' . $a['nombre'] . '"' . $eol
                . 'Content-Transfer-Encoding: base64' . $eol
                . 'Content-Disposition: attachment; filename="' . $a['nombre'] . '"' . $eol . $eol
                . chunk_split(base64_encode($a['contenido']), 76, $eol) . $eol;
        }
        return $cuerpo . '--' . $limite . '--' . $eol;
    }

    private function enviarSmtp(array $cabeceras, string $cuerpo): bool
    {
        $eol = "\r\n";
        $destino = ($this->seguridad === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->puerto;
        $contexto = stream_context_create([
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
        ]);
        $sock = @stream_socket_client($destino, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $contexto);
        if (!$sock) {
            $this->ultimoError = 'No se pudo conectar al servidor SMTP: ' . $errstr;
            return false;
        }
        stream_set_timeout($sock, $this->timeout);

        $leer = function () use ($sock): string {
            $resp = '';
            while (($linea = fgets($sock, 515)) !== false) {
                $resp .= $linea;
                if (strlen($linea) < 4 || $linea[3] !== '-') {
                    break;
                }
            }
            return $resp;
        };
        $enviar = function (string $cmd) use ($sock, $leer, $eol): string {
            fwrite($sock, $cmd . $eol);
            return $leer();
        };
        $codigo = static fn(string $r): int => (int)substr(trim($r), 0, 3);

        $r = $leer();
        if ($codigo($r) !== 220) {
            $this->ultimoError = 'Saludo SMTP inesperado: ' . trim($r);
            fclose($sock);
            return false;
        }
        $dominio = (string)($_SERVER['HTTP_HOST'] ?? 'eduportal.local');
        $r = $enviar('EHLO ' . $dominio);
        if ($codigo($r) !== 250) {
            $r = $enviar('HELO ' . $dominio);
        }
        if ($this->seguridad === 'tls') {
            $r = $enviar('STARTTLS');
            if ($codigo($r) !== 220) {
                $this->ultimoError = 'El servidor no acepto STARTTLS: ' . trim($r);
                fclose($sock);
                return false;
            }
            $metodo = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $metodo |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (!@stream_socket_enable_crypto($sock, true, $metodo)) {
                $this->ultimoError = 'No se pudo establecer el cifrado TLS.';
                fclose($sock);
                return false;
            }
            $enviar('EHLO ' . $dominio);
        }
        if ($this->usuario !== '') {
            $r = $enviar('AUTH LOGIN');
            if ($codigo($r) === 334) {
                $r = $enviar(base64_encode($this->usuario));
                $r = $enviar(base64_encode($this->password));
            } else {
                $r = $enviar('AUTH PLAIN ' . base64_encode("\0" . $this->usuario . "\0" . $this->password));
            }
            if ($codigo($r) !== 235) {
                $this->ultimoError = 'Autenticacion SMTP rechazada: ' . trim($r);
                fclose($sock);
                return false;
            }
        }
        $r = $enviar('MAIL FROM:<' . $this->deEmail . '>');
        if ($codigo($r) !== 250) {
            $this->ultimoError = 'Remitente rechazado: ' . trim($r);
            fclose($sock);
            return false;
        }
        $destinatarios = array_merge(array_map(static fn($p) => $p[0], $this->para), $this->copiaOculta);
        foreach ($destinatarios as $email) {
            $r = $enviar('RCPT TO:<' . $email . '>');
            if (!in_array($codigo($r), [250, 251], true)) {
                $this->ultimoError = 'Destinatario rechazado (' . $email . '): ' . trim($r);
                fclose($sock);
                return false;
            }
        }
        $r = $enviar('DATA');
        if ($codigo($r) !== 354) {
            $this->ultimoError = 'El servidor no acepto DATA: ' . trim($r);
            fclose($sock);
            return false;
        }
        $mensaje = '';
        foreach ($cabeceras as $k => $v) {
            $mensaje .= $k . ': ' . $v . $eol;
        }
        $mensaje .= $eol . $cuerpo;
        // Transparencia de punto inicial
        $mensaje = preg_replace('/^\./m', '..', $mensaje) ?? $mensaje;
        fwrite($sock, $mensaje . $eol . '.' . $eol);
        $r = $leer();
        $ok = $codigo($r) === 250;
        if (!$ok) {
            $this->ultimoError = 'El servidor rechazo el mensaje: ' . trim($r);
        }
        $enviar('QUIT');
        fclose($sock);
        return $ok;
    }
}
