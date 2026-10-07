<?php
/**
 * Kaptor - Envío de correo por API, cuando el SMTP no sale del servidor.
 *
 * Muchos hosting compartidos llevan activada la opción de cPanel «SMTP
 * Restrictions»: cualquier conexión saliente a los puertos de correo se
 * desvía al Exim local de la máquina. El síntoma es desconcertante —se
 * pide conexión a un proveedor y contesta el servidor de uno mismo, que
 * lógicamente rechaza unas credenciales que no son suyas— y desde la
 * cuenta de hosting no se puede quitar.
 *
 * La salida es no usar SMTP: las API de los proveedores van por HTTPS, el
 * mismo puerto 443 por el que viaja la web, que ningún hosting bloquea
 * porque se quedaría sin servicio.
 *
 * De momento habla con Brevo. La forma de la clase deja sitio a otros:
 * cada proveedor es un caso de PROVEEDORES con su dirección y su manera
 * de armar el cuerpo.
 */
declare(strict_types=1);

final class ApiCorreo
{
    /** Proveedores que sabe usar. */
    public const PROVEEDORES = [
        'api_brevo' => 'API de Brevo (HTTPS, puerto 443)',
    ];

    private const BREVO_ENVIO  = 'https://api.brevo.com/v3/smtp/email';
    private const BREVO_CUENTA = 'https://api.brevo.com/v3/account';

    /** ¿Este buzón se manda por API en vez de por SMTP? */
    public static function esApi(string $via): bool
    {
        return isset(self::PROVEEDORES[$via]);
    }

    /**
     * Comprueba que la clave sirve, sin mandar nada a nadie.
     *
     * @return array{ok:bool,error?:string,detalle?:string,registro?:string}
     */
    public static function probar(array $fila): array
    {
        $clave = Cripto::descifrar((string) $fila['clave']);
        if ($clave === '') {
            return ['ok' => false, 'error' => 'Este buzón no tiene ninguna clave de API guardada.'];
        }

        $r = self::llamar(self::BREVO_CUENTA, null, $clave);
        $registro = self::registro('GET', self::BREVO_CUENTA, $r);

        if (!$r['ok']) {
            return ['ok' => false, 'error' => $r['error'], 'registro' => $registro];
        }

        $cuenta = json_decode($r['cuerpo'], true);
        $detalle = 'Clave correcta';
        if (is_array($cuenta)) {
            $correo = (string) ($cuenta['email'] ?? '');
            if ($correo !== '') { $detalle .= ' · cuenta ' . $correo; }
            $plan = $cuenta['plan'][0]['credits'] ?? null;
            if ($plan !== null) { $detalle .= ' · ' . cr_numero((int) $plan) . ' créditos'; }
        }

        return ['ok' => true, 'detalle' => $detalle, 'registro' => $registro];
    }

    /**
     * Manda un mensaje ya armado.
     *
     * @return array{ok:bool,error?:string,id?:string,registro?:string}
     */
    public static function enviar(array $fila, Mensaje $mensaje): array
    {
        $clave = Cripto::descifrar((string) $fila['clave']);
        if ($clave === '') {
            return ['ok' => false, 'error' => 'Este buzón no tiene ninguna clave de API guardada.'];
        }

        $cuerpo = self::cuerpoBrevo($mensaje->partes());
        $r = self::llamar(self::BREVO_ENVIO, $cuerpo, $clave);
        $registro = self::registro('POST', self::BREVO_ENVIO, $r);

        if (!$r['ok']) {
            return ['ok' => false, 'error' => $r['error'], 'registro' => $registro];
        }

        $datos = json_decode($r['cuerpo'], true);
        return [
            'ok'       => true,
            'id'       => (string) (is_array($datos) ? ($datos['messageId'] ?? '') : ''),
            'registro' => $registro,
        ];
    }

    // ------------------------------------------------------------- por dentro

    /** Traduce las piezas del mensaje al cuerpo que espera Brevo. */
    private static function cuerpoBrevo(array $p): array
    {
        $cuerpo = [
            'sender'      => array_filter([
                'email' => $p['de'],
                'name'  => $p['de_nombre'] !== '' ? $p['de_nombre'] : null,
            ]),
            'to'          => [array_filter([
                'email' => $p['para'],
                'name'  => $p['para_nombre'] !== '' ? $p['para_nombre'] : null,
            ])],
            'subject'     => $p['asunto'],
            'htmlContent' => $p['html'],
        ];

        if ($p['texto'] !== '')       { $cuerpo['textContent'] = $p['texto']; }
        if ($p['responder_a'] !== '') { $cuerpo['replyTo'] = ['email' => $p['responder_a']]; }
        if ($p['cabeceras'])          { $cuerpo['headers'] = $p['cabeceras']; }

        foreach ($p['adjuntos'] as $a) {
            $cuerpo['attachment'][] = [
                'name'    => $a['nombre'],
                'content' => base64_encode($a['datos']),
            ];
        }

        return $cuerpo;
    }

    /**
     * Una llamada a la API.
     *
     * @return array{ok:bool,codigo:int,cuerpo:string,error:string}
     */
    private static function llamar(string $url, ?array $cuerpo, string $clave): array
    {
        $cabeceras = [
            'api-key: ' . $clave,
            'Accept: application/json',
        ];
        $opciones = [
            'cabeceras' => $cabeceras,
            'timeout'   => Ajustes::entero('smtp_timeout', 20, 5, 120),
            'max_bytes' => 200000,
        ];
        if ($cuerpo !== null) {
            $opciones['cabeceras'][] = 'Content-Type: application/json';
            $opciones['datos'] = json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $r = Http::obtener($url, $opciones);
        $codigo = (int) $r['codigo'];
        $texto  = (string) $r['cuerpo'];

        if ($codigo === 0) {
            return ['ok' => false, 'codigo' => 0, 'cuerpo' => '',
                    'error' => 'No se pudo hablar con la API: ' . ($r['error'] ?: 'sin respuesta') . '.'];
        }
        if ($codigo >= 200 && $codigo < 300) {
            return ['ok' => true, 'codigo' => $codigo, 'cuerpo' => $texto, 'error' => ''];
        }

        return ['ok' => false, 'codigo' => $codigo, 'cuerpo' => $texto,
                'error' => self::explicar($codigo, $texto)];
    }

    /** Pone en castellano lo que contestó la API. */
    private static function explicar(int $codigo, string $cuerpo): string
    {
        $datos = json_decode($cuerpo, true);
        $suyo  = is_array($datos) ? trim((string) ($datos['message'] ?? '')) : '';

        $mio = match (true) {
            $codigo === 401 => 'La clave de API no es válida. En Brevo, «SMTP y API» → pestaña «Claves API y MCP» → genera una y pégala aquí (empieza por xkeysib-, no es la clave SMTP).',
            $codigo === 400 => 'La API rechazó el mensaje.',
            $codigo === 402 => 'Te has quedado sin créditos de envío en Brevo.',
            $codigo === 403 => 'Brevo no autoriza este envío: lo más habitual es que el remitente no esté verificado en «Remitentes, dominio, IP».',
            $codigo === 429 => 'Has pasado el límite de envíos por minuto de Brevo. Baja el máximo por hora.',
            $codigo >= 500  => 'Brevo está fallando ahora mismo (error ' . $codigo . '). Se reintenta en la siguiente pasada.',
            default         => 'La API respondió con el código ' . $codigo . '.',
        };

        return $suyo !== '' ? $mio . ' (' . $suyo . ')' : $mio;
    }

    /** Lo ocurrido, para enseñarlo en el panel. Sin la clave. */
    private static function registro(string $metodo, string $url, array $r): string
    {
        $lineas = [
            '> ' . $metodo . ' ' . $url,
            '> api-key: (oculta)',
            '< HTTP ' . ($r['codigo'] ?: 'sin respuesta'),
        ];
        if ($r['cuerpo'] !== '') {
            $lineas[] = '< ' . mb_substr($r['cuerpo'], 0, 600);
        }
        if (!$r['ok'] && $r['error'] !== '') {
            $lineas[] = '! ' . $r['error'];
        }
        return implode("\n", $lineas);
    }
}
