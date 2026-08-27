<?php
declare(strict_types=1);

namespace Vendor\Mailer;

/**
 * Cliente SMTP con TLS/SSL, autenticación LOGIN/PLAIN y mensajes MIME
 * multiparte (HTML + texto alterno + adjuntos). Reserva a mail() del servidor.
 *
 * ResidencialPro — librería local, sin composer.
 */
final class Mailer
{
    public string $host = '';
    public int    $puerto = 587;
    public string $usuario = '';
    public string $clave = '';
    public string $seguridad = 'tls';      // tls | ssl | ninguna
    public string $deCorreo = '';
    public string $deNombre = '';
    public string $responderA = '';
    public int    $tiempoEspera = 15;
    public bool   $verificarCertificado = true;

    private array $destinatarios = [];
    private array $adjuntos = [];
    private string $asunto = '';
    private string $html = '';
    private string $texto = '';
    private string $error = '';
    private array $traza = [];

    /** @var resource|null */
    private $conexion = null;

    public function para(string $correo, string $nombre = ''): self
    {
        $correo = trim($correo);
        if (filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $this->destinatarios[] = ['correo' => $correo, 'nombre' => $nombre];
        }
        return $this;
    }

    public function asunto(string $s): self
    {
        $this->asunto = trim(str_replace(["\r", "\n"], ' ', $s));
        return $this;
    }

    public function cuerpoHtml(string $html, string $textoAlterno = ''): self
    {
        $this->html  = $html;
        $this->texto = $textoAlterno !== '' ? $textoAlterno : self::htmlATexto($html);
        return $this;
    }

    public function adjuntar(string $ruta, string $nombre = '', string $mime = 'application/octet-stream'): self
    {
        if (is_file($ruta) && filesize($ruta) < 12 * 1024 * 1024) {
            $this->adjuntos[] = [
                'nombre'    => $nombre !== '' ? $nombre : basename($ruta),
                'contenido' => (string) file_get_contents($ruta),
                'mime'      => $mime,
            ];
        }
        return $this;
    }

    public function adjuntarContenido(string $contenido, string $nombre, string $mime): self
    {
        $this->adjuntos[] = ['nombre' => $nombre, 'contenido' => $contenido, 'mime' => $mime];
        return $this;
    }

    public function error(): string
    {
        return $this->error;
    }

    public function traza(): array
    {
        return $this->traza;
    }

    public function limpiar(): void
    {
        $this->destinatarios = [];
        $this->adjuntos = [];
        $this->asunto = '';
        $this->html = '';
        $this->texto = '';
    }

    public function enviar(): bool
    {
        if ($this->destinatarios === []) {
            $this->error = 'No hay destinatarios válidos.';
            return false;
        }
        if ($this->deCorreo === '') {
            $this->deCorreo = 'no-responder@' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        return $this->host !== '' ? $this->enviarSmtp() : $this->enviarNativo();
    }

    /** Prueba de conexión sin enviar correo. */
    public function probarConexion(): bool
    {
        if ($this->host === '') {
            $this->error = 'No hay servidor SMTP configurado.';
            return false;
        }
        if (!$this->abrir()) {
            return false;
        }
        $this->cerrar();
        return true;
    }

    // ------------------------------------------------------------ Transporte

    private function enviarNativo(): bool
    {
        $para = [];
        foreach ($this->destinatarios as $d) {
            $para[] = $d['correo'];
        }
        $frontera = '=_rp_' . bin2hex(random_bytes(8));
        $cab = "MIME-Version: 1.0\r\n"
             . 'From: ' . $this->cabeceraDe() . "\r\n"
             . ($this->responderA !== '' ? 'Reply-To: ' . $this->responderA . "\r\n" : '')
             . 'Content-Type: multipart/mixed; boundary="' . $frontera . "\"\r\n";
        $ok = @mail(
            implode(', ', $para),
            self::codificarCabecera($this->asunto),
            $this->cuerpoMime($frontera),
            $cab
        );
        if (!$ok) {
            $this->error = 'La función mail() del servidor rechazó el mensaje.';
        }
        return $ok;
    }

    private function enviarSmtp(): bool
    {
        if (!$this->abrir()) {
            return false;
        }
        try {
            if (!$this->comando('MAIL FROM:<' . $this->deCorreo . '>', [250])) {
                return false;
            }
            foreach ($this->destinatarios as $d) {
                if (!$this->comando('RCPT TO:<' . $d['correo'] . '>', [250, 251])) {
                    return false;
                }
            }
            if (!$this->comando('DATA', [354])) {
                return false;
            }
            $frontera = '=_rp_' . bin2hex(random_bytes(8));
            $mensaje  = $this->cabeceras($frontera) . "\r\n" . $this->cuerpoMime($frontera);
            // Protección contra punto al inicio de línea.
            $mensaje  = preg_replace('/^\./m', '..', $mensaje) ?? $mensaje;
            $this->enviarLinea($mensaje . "\r\n.");
            if (!$this->esperar([250])) {
                return false;
            }
            $this->comando('QUIT', [221]);
            return true;
        } finally {
            $this->cerrar();
        }
    }

    private function abrir(): bool
    {
        $this->traza = [];
        $prefijo = $this->seguridad === 'ssl' ? 'ssl://' : '';
        $contexto = stream_context_create([
            'ssl' => [
                'verify_peer'       => $this->verificarCertificado,
                'verify_peer_name'  => $this->verificarCertificado,
                'allow_self_signed' => !$this->verificarCertificado,
                'SNI_enabled'       => true,
            ],
        ]);
        $err = 0;
        $msg = '';
        $con = @stream_socket_client(
            $prefijo . $this->host . ':' . $this->puerto,
            $err,
            $msg,
            $this->tiempoEspera,
            STREAM_CLIENT_CONNECT,
            $contexto
        );
        if ($con === false) {
            $this->error = 'No se pudo conectar con ' . $this->host . ':' . $this->puerto . ' (' . $msg . ')';
            return false;
        }
        stream_set_timeout($con, $this->tiempoEspera);
        $this->conexion = $con;
        if (!$this->esperar([220])) {
            $this->cerrar();
            return false;
        }
        $dominio = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $dominio = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $dominio) ?: 'localhost';
        if (!$this->comando('EHLO ' . $dominio, [250])) {
            if (!$this->comando('HELO ' . $dominio, [250])) {
                $this->cerrar();
                return false;
            }
        }
        if ($this->seguridad === 'tls') {
            if (!$this->comando('STARTTLS', [220])) {
                $this->cerrar();
                return false;
            }
            $cripto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cripto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (@stream_socket_enable_crypto($this->conexion, true, $cripto) !== true) {
                $this->error = 'No se pudo iniciar el cifrado TLS con el servidor de correo.';
                $this->cerrar();
                return false;
            }
            if (!$this->comando('EHLO ' . $dominio, [250])) {
                $this->cerrar();
                return false;
            }
        }
        if ($this->usuario !== '') {
            if (!$this->autenticar()) {
                $this->cerrar();
                return false;
            }
        }
        return true;
    }

    private function autenticar(): bool
    {
        if ($this->comando('AUTH LOGIN', [334])) {
            if (!$this->comando(base64_encode($this->usuario), [334])) {
                return false;
            }
            return $this->comando(base64_encode($this->clave), [235]);
        }
        $this->error = '';
        $token = base64_encode("\0" . $this->usuario . "\0" . $this->clave);
        return $this->comando('AUTH PLAIN ' . $token, [235]);
    }

    private function comando(string $cmd, array $esperados): bool
    {
        $this->enviarLinea($cmd);
        return $this->esperar($esperados);
    }

    private function enviarLinea(string $s): void
    {
        if ($this->conexion === null) {
            return;
        }
        @fwrite($this->conexion, $s . "\r\n");
    }

    private function esperar(array $codigos): bool
    {
        if ($this->conexion === null) {
            return false;
        }
        $respuesta = '';
        while (($linea = @fgets($this->conexion, 1024)) !== false) {
            $respuesta .= $linea;
            if (strlen($linea) < 4 || $linea[3] !== '-') {
                break;
            }
        }
        $this->traza[] = trim($respuesta);
        $codigo = (int) substr(trim($respuesta), 0, 3);
        if (!in_array($codigo, $codigos, true)) {
            $this->error = 'Respuesta SMTP inesperada: ' . trim($respuesta);
            return false;
        }
        return true;
    }

    private function cerrar(): void
    {
        if ($this->conexion !== null) {
            @fclose($this->conexion);
            $this->conexion = null;
        }
    }

    // ----------------------------------------------------------------- MIME

    private function cabeceraDe(): string
    {
        return $this->deNombre !== ''
            ? self::codificarCabecera($this->deNombre) . ' <' . $this->deCorreo . '>'
            : $this->deCorreo;
    }

    private function cabeceras(string $frontera): string
    {
        $para = [];
        foreach ($this->destinatarios as $d) {
            $para[] = $d['nombre'] !== ''
                ? self::codificarCabecera($d['nombre']) . ' <' . $d['correo'] . '>'
                : $d['correo'];
        }
        $h  = 'Date: ' . date('r') . "\r\n";
        $h .= 'From: ' . $this->cabeceraDe() . "\r\n";
        $h .= 'To: ' . implode(', ', $para) . "\r\n";
        if ($this->responderA !== '') {
            $h .= 'Reply-To: ' . $this->responderA . "\r\n";
        }
        $h .= 'Subject: ' . self::codificarCabecera($this->asunto) . "\r\n";
        $h .= 'Message-ID: <' . bin2hex(random_bytes(12)) . '@'
            . preg_replace('/[^a-zA-Z0-9\.\-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) . ">\r\n";
        $h .= "MIME-Version: 1.0\r\n";
        $h .= 'Content-Type: multipart/mixed; boundary="' . $frontera . "\"\r\n";
        $h .= "X-Mailer: ResidencialPro\r\n";
        return $h;
    }

    private function cuerpoMime(string $frontera): string
    {
        $alt = '=_alt_' . bin2hex(random_bytes(6));
        $c  = "Este mensaje usa formato MIME.\r\n\r\n";
        $c .= '--' . $frontera . "\r\n";
        $c .= 'Content-Type: multipart/alternative; boundary="' . $alt . "\"\r\n\r\n";
        $c .= '--' . $alt . "\r\n";
        $c .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $c .= chunk_split(base64_encode($this->texto)) . "\r\n";
        $c .= '--' . $alt . "\r\n";
        $c .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $c .= chunk_split(base64_encode($this->html)) . "\r\n";
        $c .= '--' . $alt . "--\r\n\r\n";
        foreach ($this->adjuntos as $a) {
            $c .= '--' . $frontera . "\r\n";
            $c .= 'Content-Type: ' . $a['mime'] . '; name="' . $a['nombre'] . "\"\r\n";
            $c .= "Content-Transfer-Encoding: base64\r\n";
            $c .= 'Content-Disposition: attachment; filename="' . $a['nombre'] . "\"\r\n\r\n";
            $c .= chunk_split(base64_encode($a['contenido'])) . "\r\n";
        }
        $c .= '--' . $frontera . "--\r\n";
        return $c;
    }

    public static function codificarCabecera(string $s): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $s) === 1) {
            return $s;
        }
        return '=?UTF-8?B?' . base64_encode($s) . '?=';
    }

    public static function htmlATexto(string $html): string
    {
        $t = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $t = preg_replace('#</(p|div|tr|h1|h2|h3|li)>#i', "\n", $t) ?? $t;
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
        $t = preg_replace("/\n{3,}/", "\n\n", $t) ?? $t;
        return trim($t);
    }
}
