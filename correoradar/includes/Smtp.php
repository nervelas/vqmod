<?php
/**
 * CorreoRadar - Cliente SMTP propio, sin dependencias externas.
 *
 * Habla SMTP directamente sobre sockets: no necesita Composer, PHPMailer ni la
 * función mail() (que en los hosting compartidos suele ir a la carpeta de spam
 * por no estar autenticada).
 *
 * Admite:
 *   - Puerto 587 con STARTTLS (lo habitual) y puerto 465 con SSL directo.
 *   - Autenticación AUTH LOGIN y AUTH PLAIN.
 *   - Reutilización de la conexión para enviar varios mensajes seguidos,
 *     que es mucho más rápido y más suave para el servidor.
 */
declare(strict_types=1);

final class Smtp
{
    /** Socket abierto con el servidor. @var resource|null */
    private $socket = null;

    /** @var string[] Capacidades anunciadas por el servidor tras el EHLO. */
    private array $capacidades = [];

    private bool $autenticado = false;
    private string $registro = '';

    /**
     * @param array{host:string,puerto:int,seguridad:string,usuario:string,clave:string,timeout?:int,dominio?:string} $config
     */
    public function __construct(private array $config)
    {
        $this->config['timeout'] = (int) ($config['timeout'] ?? 20);
        $this->config['dominio'] = (string) ($config['dominio'] ?? self::dominioLocal());
    }

    public function __destruct()
    {
        $this->cerrar();
    }

    // ============================================================== conexión

    /**
     * Abre la conexión, negocia TLS si procede y autentica.
     *
     * Se intenta primero verificando el certificado. Si el servidor presenta
     * uno autofirmado o emitido para otro nombre -algo frecuente en los hosting
     * compartidos- se cierra y se vuelve a conectar sin verificarlo, porque un
     * handshake fallido deja el socket inservible.
     *
     * @return array{ok:bool,error?:string,aviso?:string}
     */
    public function conectar(): array
    {
        if (is_resource($this->socket) && $this->autenticado) { return ['ok' => true]; }

        $r = $this->intentar(true);
        if ($r['ok'] || empty($r['reintentar'])) { return $r; }

        $this->cerrar();
        $r = $this->intentar(false);
        if ($r['ok']) {
            $r['aviso'] = 'El certificado del servidor de correo no se pudo verificar; la conexión sigue cifrada pero sin comprobar la identidad.';
        }
        return $r;
    }

    /**
     * Un intento completo de conexión.
     *
     * @param bool $verificar Comprobar o no el certificado del servidor.
     * @return array{ok:bool,error?:string,reintentar?:bool}
     */
    private function intentar(bool $verificar): array
    {
        $seguridad = strtolower((string) $this->config['seguridad']);
        $host      = (string) $this->config['host'];
        $puerto    = (int) $this->config['puerto'];
        $destino   = ($seguridad === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $puerto;

        $opcionesSsl = [
            'verify_peer'       => $verificar,
            'verify_peer_name'  => $verificar,
            'allow_self_signed' => !$verificar,
            'SNI_enabled'       => true,
            'peer_name'         => $host,
        ];
        $contexto = stream_context_create(['ssl' => $opcionesSsl]);

        $errNo = 0; $errStr = '';
        $socket = @stream_socket_client(
            $destino,
            $errNo,
            $errStr,
            (float) $this->config['timeout'],
            STREAM_CLIENT_CONNECT,
            $contexto
        );

        if (!$socket) {
            // En SSL directo el fallo del certificado llega como un error vacío,
            // así que cualquier fallo del primer intento se vuelve a probar sin
            // verificar. Si la causa era otra (puerto cerrado), el segundo
            // intento devolverá el mismo error y se informa igualmente.
            $rehusado = stripos($errStr, 'refused') !== false || stripos($errStr, 'timed out') !== false;
            return [
                'ok'    => false,
                'error' => 'No se pudo conectar con ' . $host . ':' . $puerto . ' (' . ($errStr !== '' ? trim($errStr) : 'error ' . $errNo) . ').',
                'reintentar' => $verificar && $seguridad === 'ssl' && !$rehusado,
            ];
        }

        $this->socket = $socket;
        stream_set_timeout($this->socket, (int) $this->config['timeout']);

        // Saludo del servidor
        $bienvenida = $this->leer();
        if (!$this->esCodigo($bienvenida, 220)) {
            $this->cerrar();
            return ['ok' => false, 'error' => 'El servidor no respondió correctamente al conectar: ' . $this->resumen($bienvenida)];
        }

        $paso = $this->ehlo();
        if (!$paso['ok']) { return $paso; }

        // STARTTLS (puerto 587 y similares)
        if ($seguridad === 'tls') {
            if (!$this->tieneCapacidad('STARTTLS')) {
                $this->cerrar();
                return ['ok' => false, 'error' => 'El servidor no admite STARTTLS en el puerto ' . $puerto . '. Prueba con el puerto 465 y seguridad SSL.'];
            }
            $r = $this->orden('STARTTLS');
            if (!$this->esCodigo($r, 220)) {
                $this->cerrar();
                return ['ok' => false, 'error' => 'El servidor rechazó STARTTLS: ' . $this->resumen($r)];
            }

            foreach ($opcionesSsl as $clave => $valor) {
                @stream_context_set_option($this->socket, 'ssl', $clave, $valor);
            }

            $cripto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) { $cripto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT; }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) { $cripto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT; }

            if (@stream_socket_enable_crypto($this->socket, true, $cripto) !== true) {
                $this->cerrar();
                return [
                    'ok'    => false,
                    'error' => 'No se pudo cifrar la conexión con TLS (el certificado del servidor no se pudo verificar).',
                    'reintentar' => $verificar,
                ];
            }

            // Tras STARTTLS hay que repetir el EHLO.
            $paso = $this->ehlo();
            if (!$paso['ok']) { return $paso; }
        }

        // Autenticación
        if ((string) $this->config['usuario'] !== '') {
            $paso = $this->autenticar();
            if (!$paso['ok']) { return $paso; }
        }
        $this->autenticado = true;

        return ['ok' => true];
    }

    /** Saluda al servidor y guarda sus capacidades. */
    private function ehlo(): array
    {
        $r = $this->orden('EHLO ' . $this->config['dominio']);
        if (!$this->esCodigo($r, 250)) {
            // Servidores antiguos: se prueba con HELO.
            $r = $this->orden('HELO ' . $this->config['dominio']);
            if (!$this->esCodigo($r, 250)) {
                $this->cerrar();
                return ['ok' => false, 'error' => 'El servidor rechazó el saludo: ' . $this->resumen($r)];
            }
        }

        $this->capacidades = [];
        foreach (explode("\n", $r) as $linea) {
            if (preg_match('~^250[\s-]+(.+)$~', trim($linea), $m)) {
                $this->capacidades[] = strtoupper(trim($m[1]));
            }
        }
        return ['ok' => true];
    }

    /** Ejecuta AUTH con el método que admita el servidor. */
    private function autenticar(): array
    {
        $usuario = (string) $this->config['usuario'];
        $clave   = (string) $this->config['clave'];
        $auth    = $this->capacidad('AUTH');

        // AUTH LOGIN es el más compatible con los hosting compartidos.
        if ($auth === '' || str_contains($auth, 'LOGIN')) {
            $r = $this->orden('AUTH LOGIN');
            if ($this->esCodigo($r, 334)) {
                $r = $this->orden(base64_encode($usuario));
                if (!$this->esCodigo($r, 334)) {
                    $this->cerrar();
                    return ['ok' => false, 'error' => 'El servidor no aceptó el usuario: ' . $this->resumen($r)];
                }
                $r = $this->orden(base64_encode($clave));
                if ($this->esCodigo($r, 235)) { return ['ok' => true]; }

                $this->cerrar();
                return ['ok' => false, 'error' => 'Usuario o contraseña del correo incorrectos: ' . $this->resumen($r)];
            }
        }

        if (str_contains($auth, 'PLAIN')) {
            $r = $this->orden('AUTH PLAIN ' . base64_encode("\0" . $usuario . "\0" . $clave));
            if ($this->esCodigo($r, 235)) { return ['ok' => true]; }
            $this->cerrar();
            return ['ok' => false, 'error' => 'Usuario o contraseña del correo incorrectos: ' . $this->resumen($r)];
        }

        $this->cerrar();
        return ['ok' => false, 'error' => 'El servidor no admite ningún método de autenticación compatible (' . $auth . ').'];
    }

    // ================================================================== envío

    /**
     * Envía un mensaje ya construido.
     *
     * @return array{ok:bool,error?:string,respuesta?:string,permanente?:bool}
     */
    public function enviar(Mensaje $mensaje): array
    {
        $conexion = $this->conectar();
        if (!$conexion['ok']) { return $conexion; }

        $de   = Mensaje::limpiar($mensaje->remitente());
        $para = Mensaje::limpiar($mensaje->destinatario());

        $r = $this->orden('MAIL FROM:<' . $de . '>');
        if (!$this->esCodigo($r, 250)) {
            $this->reiniciar();
            return ['ok' => false, 'error' => 'El servidor rechazó el remitente: ' . $this->resumen($r), 'permanente' => $this->esPermanente($r)];
        }

        $r = $this->orden('RCPT TO:<' . $para . '>');
        if (!$this->esCodigo($r, 250) && !$this->esCodigo($r, 251)) {
            $this->reiniciar();
            return ['ok' => false, 'error' => 'El servidor rechazó el destinatario: ' . $this->resumen($r), 'permanente' => $this->esPermanente($r)];
        }

        $r = $this->orden('DATA');
        if (!$this->esCodigo($r, 354)) {
            $this->reiniciar();
            return ['ok' => false, 'error' => 'El servidor no aceptó los datos: ' . $this->resumen($r), 'permanente' => $this->esPermanente($r)];
        }

        // Cuerpo con "dot-stuffing": una línea que empiece por punto se duplica.
        $cuerpo = $mensaje->construir();
        $cuerpo = preg_replace('~^\.~m', '..', $cuerpo) ?? $cuerpo;
        $this->escribir($cuerpo . "\r\n.");

        $r = $this->leer();
        if (!$this->esCodigo($r, 250)) {
            return ['ok' => false, 'error' => 'El servidor no aceptó el mensaje: ' . $this->resumen($r), 'permanente' => $this->esPermanente($r)];
        }

        return ['ok' => true, 'respuesta' => $this->resumen($r)];
    }

    /**
     * Comprueba la configuración: conecta, autentica y cierra.
     *
     * @return array{ok:bool,error?:string,detalle?:string}
     */
    public function probar(): array
    {
        $r = $this->conectar();
        if (!$r['ok']) { return $r; }

        $detalle = 'Conexión correcta'
            . ($this->tieneCapacidad('STARTTLS') ? ' · STARTTLS disponible' : '')
            . ($this->capacidad('SIZE') !== '' ? ' · tamaño máx. ' . trim(str_replace('SIZE', '', $this->capacidad('SIZE'))) . ' bytes' : '');

        $this->cerrar();
        return ['ok' => true, 'detalle' => $detalle];
    }

    /** Cierra la sesión de forma limpia. */
    public function cerrar(): void
    {
        if (is_resource($this->socket)) {
            @$this->escribir('QUIT');
            @fclose($this->socket);
        }
        $this->socket = null;
        $this->autenticado = false;
        $this->capacidades = [];
    }

    /** Registro de la conversación (útil para depurar desde el panel). */
    public function registro(): string
    {
        return $this->registro;
    }

    // ============================================================ utilidades

    private function reiniciar(): void
    {
        if (is_resource($this->socket)) { $this->orden('RSET'); }
    }

    private function orden(string $orden): string
    {
        $this->escribir($orden);
        return $this->leer();
    }

    private function escribir(string $datos): void
    {
        if (!is_resource($this->socket)) { return; }
        // No se registran las credenciales.
        $this->anotar('> ' . (preg_match('~^(AUTH|[A-Za-z0-9+/=]{12,})~', $datos) ? '(credenciales ocultas)' : $datos));
        @fwrite($this->socket, $datos . "\r\n");
    }

    private function leer(): string
    {
        if (!is_resource($this->socket)) { return ''; }

        $respuesta = '';
        while (($linea = @fgets($this->socket, 1024)) !== false) {
            $respuesta .= $linea;
            $estado = stream_get_meta_data($this->socket);
            if (!empty($estado['timed_out'])) { break; }
            // El último renglón de una respuesta lleva un espacio tras el código.
            if (strlen($linea) >= 4 && $linea[3] === ' ') { break; }
        }
        $this->anotar('< ' . trim($respuesta));
        return $respuesta;
    }

    private function anotar(string $linea): void
    {
        if (strlen($this->registro) < 8000) {
            $this->registro .= $linea . "\n";
        }
    }

    private function esCodigo(string $respuesta, int $codigo): bool
    {
        return str_starts_with(trim($respuesta), (string) $codigo);
    }

    /** ¿El error es permanente (5xx)? Entonces no merece la pena reintentar. */
    private function esPermanente(string $respuesta): bool
    {
        return (bool) preg_match('~^5\d\d~', trim($respuesta));
    }

    private function resumen(string $respuesta): string
    {
        return mb_substr(trim(preg_replace('~\s+~', ' ', $respuesta) ?? $respuesta), 0, 220);
    }

    private function tieneCapacidad(string $nombre): bool
    {
        foreach ($this->capacidades as $c) {
            if (str_starts_with($c, strtoupper($nombre))) { return true; }
        }
        return false;
    }

    private function capacidad(string $nombre): string
    {
        foreach ($this->capacidades as $c) {
            if (str_starts_with($c, strtoupper($nombre))) { return $c; }
        }
        return '';
    }

    /** Nombre con el que este servidor se presenta ante el SMTP. */
    public static function dominioLocal(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? gethostname() ?: 'localhost');
        $host = preg_replace('~:\d+$~', '', $host) ?? $host;
        return preg_match('~^[a-z0-9.\-]+\.[a-z]{2,}$~i', $host) ? $host : 'localhost';
    }
}
