<?php
/**
 * CorreoRadar - Motor de campañas de correo.
 *
 * Cómo funciona el envío:
 *   1. Al preparar la campaña se crea una fila en cr_envios por cada contacto
 *      que NO esté en la lista de supresión ni dado de baja.
 *   2. El motor avanza por pasos cortos, llamado desde el panel (AJAX) o desde
 *      cron.php. Cada paso respeta el límite por hora de la campaña, el límite
 *      por hora y por día de cada buzón, y una pausa aleatoria entre correos.
 *   3. Los buzones se van rotando para repartir la carga.
 *   4. Un rechazo permanente (5xx) marca el correo como rebotado y lo añade a
 *      la lista de supresión automáticamente.
 *
 * El envío en frío es lento a propósito: mandar 5.000 correos de golpe desde un
 * hosting compartido es la forma más rápida de acabar en la carpeta de spam.
 */
declare(strict_types=1);

final class Campana
{
    /** Intentos antes de dar un envío por perdido. */
    private const MAX_INTENTOS = 3;

    // ============================================================ consultas

    public static function obtener(int $id): ?array
    {
        return BD::fila('SELECT * FROM `cr_campanas` WHERE `id` = ?', [$id]);
    }

    public static function todas(): array
    {
        return BD::todos(
            'SELECT c.*, l.`nombre` AS lista, p.`nombre` AS plantilla
             FROM `cr_campanas` c
             LEFT JOIN `cr_listas` l ON l.`id` = c.`lista_id`
             LEFT JOIN `cr_plantillas` p ON p.`id` = c.`plantilla_id`
             ORDER BY c.`id` DESC'
        );
    }

    /** Campañas que el cron debe seguir empujando. */
    public static function enMarcha(): array
    {
        return BD::todos('SELECT * FROM `cr_campanas` WHERE `estado` = \'enviando\' ORDER BY `id`');
    }

    // ============================================================= preparar

    /**
     * Genera la cola de envíos de una campaña.
     *
     * @return array{ok:bool,error?:string,total?:int,excluidos?:int}
     */
    public static function preparar(int $id): array
    {
        $campana = self::obtener($id);
        if (!$campana) { return ['ok' => false, 'error' => 'La campaña no existe.']; }
        if (in_array($campana['estado'], ['enviando', 'completada'], true)) {
            return ['ok' => false, 'error' => 'Esta campaña ya está en marcha o terminada.'];
        }
        if (!self::remitentesDe($campana)) {
            return ['ok' => false, 'error' => 'Elige al menos un buzón de salida activo.'];
        }
        if (!BD::fila('SELECT `id` FROM `cr_plantillas` WHERE `id` = ?', [(int) $campana['plantilla_id']])) {
            return ['ok' => false, 'error' => 'La plantilla de la campaña ya no existe.'];
        }

        // Empezamos de cero por si se había preparado antes.
        BD::ejecutar('DELETE FROM `cr_envios` WHERE `campana_id` = ?', [$id]);

        $total = 0;
        $excluidos = 0;
        $ahora = date('Y-m-d H:i:s');

        $contactos = BD::todos(
            'SELECT `id`, `correo` FROM `cr_contactos` WHERE `lista_id` = ? AND `estado` = \'activo\' ORDER BY `id`',
            [(int) $campana['lista_id']]
        );

        foreach ($contactos as $c) {
            $correo = mb_strtolower(trim((string) $c['correo']));
            if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL) || Supresion::esta($correo)) {
                $excluidos++;
                continue;
            }
            try {
                BD::insertar('cr_envios', [
                    'campana_id'  => $id,
                    'contacto_id' => (int) $c['id'],
                    'correo'      => $correo,
                    'token'       => cr_aleatorio(16),
                    'estado'      => 'pendiente',
                    'creado'      => $ahora,
                ]);
                $total++;
            } catch (PDOException) {
                $excluidos++;   // duplicado dentro de la misma campaña
            }
        }

        BD::actualizar('cr_campanas', [
            'estado'   => 'preparada',
            'total'    => $total,
            'enviados' => 0, 'errores' => 0, 'rebotes' => 0,
            'aperturas' => 0, 'clics' => 0, 'bajas' => 0,
        ], '`id` = ?', [$id]);

        return ['ok' => true, 'total' => $total, 'excluidos' => $excluidos];
    }

    // =============================================================== estado

    public static function iniciar(int $id): array
    {
        $campana = self::obtener($id);
        if (!$campana) { return ['ok' => false, 'error' => 'La campaña no existe.']; }

        if ($campana['estado'] === 'borrador') {
            $r = self::preparar($id);
            if (!$r['ok']) { return $r; }
            $campana = self::obtener($id);
        }
        if ((int) $campana['total'] === 0) {
            return ['ok' => false, 'error' => 'No hay destinatarios: la lista está vacía o todos están suprimidos.'];
        }

        BD::actualizar('cr_campanas', [
            'estado'   => 'enviando',
            'iniciada' => $campana['iniciada'] ?: date('Y-m-d H:i:s'),
        ], '`id` = ?', [$id]);

        return ['ok' => true];
    }

    public static function pausar(int $id): void
    {
        BD::ejecutar('UPDATE `cr_campanas` SET `estado` = \'pausada\' WHERE `id` = ? AND `estado` = \'enviando\'', [$id]);
    }

    public static function cancelar(int $id): void
    {
        BD::ejecutar('UPDATE `cr_campanas` SET `estado` = \'cancelada\', `finalizada` = NOW() WHERE `id` = ?', [$id]);
        BD::ejecutar('UPDATE `cr_envios` SET `estado` = \'cancelado\' WHERE `campana_id` = ? AND `estado` = \'pendiente\'', [$id]);
    }

    public static function finalizar(int $id): void
    {
        BD::actualizar('cr_campanas', ['estado' => 'completada', 'finalizada' => date('Y-m-d H:i:s')], '`id` = ?', [$id]);
    }

    // =============================================================== motor

    /**
     * Procesa un lote de envíos.
     *
     * @param int $presupuesto Segundos máximos de trabajo en esta llamada.
     * @return array Progreso para el panel.
     */
    public static function paso(int $id, int $presupuesto = 20): array
    {
        $campana = self::obtener($id);
        if (!$campana) { return ['ok' => false, 'error' => 'La campaña no existe.']; }

        if ($campana['estado'] !== 'enviando') {
            return self::progreso($campana, 0, 'La campaña no está enviando.');
        }

        $pendientes = self::pendientes($id);
        if ($pendientes === 0) {
            self::finalizar($id);
            return self::progreso(self::obtener($id), 0, 'Campaña completada.');
        }

        // Cupo que queda en esta hora según el límite de la campaña.
        $enviadosHora = (int) BD::valor(
            'SELECT COUNT(*) FROM `cr_envios` WHERE `campana_id` = ? AND `estado` = \'enviado\'
             AND `enviado_en` > (NOW() - INTERVAL 1 HOUR)',
            [$id],
            0
        );
        $cupoHora = max(0, (int) $campana['limite_hora'] - $enviadosHora);
        if ($cupoHora === 0) {
            return self::progreso($campana, 0, 'Límite por hora alcanzado. Continuará automáticamente.');
        }

        $permitidos = self::remitentesDe($campana);
        if (!$permitidos) {
            return self::progreso($campana, 0, 'No hay buzones de salida activos.');
        }

        $plantilla = BD::fila('SELECT * FROM `cr_plantillas` WHERE `id` = ?', [(int) $campana['plantilla_id']]);
        if (!$plantilla) {
            return self::progreso($campana, 0, 'La plantilla ya no existe.');
        }

        $pausaMin = max(0, (int) $campana['pausa_min']);
        $pausaMax = max($pausaMin, (int) $campana['pausa_max']);
        $inicio   = microtime(true);
        $enviados = 0;
        $aviso    = '';
        /** @var array<int,Smtp> Conexiones abiertas, una por buzón. */
        $conexiones = [];

        while ($enviados < $cupoHora) {
            if ((microtime(true) - $inicio) >= $presupuesto) { break; }

            $remitente = Remitente::siguienteDisponible($permitidos);
            if (!$remitente) {
                $aviso = 'Los buzones han llegado a su límite. El envío continuará más tarde.';
                break;
            }

            $envio = self::siguienteEnvio($id);
            if (!$envio) { break; }

            $rid = (int) $remitente['id'];
            if (!isset($conexiones[$rid])) {
                $conexiones[$rid] = Remitente::smtp($remitente);
            }

            $resultado = self::enviarUno($campana, $plantilla, $envio, $remitente, $conexiones[$rid]);
            if ($resultado['enviado']) { $enviados++; }

            // Pausa aleatoria: imita el ritmo de una persona escribiendo.
            if ($pausaMax > 0 && $enviados < $cupoHora) {
                $espera = random_int($pausaMin, $pausaMax);
                $restante = $presupuesto - (microtime(true) - $inicio);
                if ($espera > $restante) { break; }      // se continúa en el siguiente paso
                sleep($espera);
            }
        }

        foreach ($conexiones as $smtp) { $smtp->cerrar(); }

        $campana = self::obtener($id);
        if (self::pendientes($id) === 0 && $campana['estado'] === 'enviando') {
            self::finalizar($id);
            $campana = self::obtener($id);
            $aviso = 'Campaña completada.';
        }

        return self::progreso($campana, $enviados, $aviso);
    }

    /**
     * Envía un mensaje concreto y actualiza su fila.
     *
     * @return array{enviado:bool,error?:string}
     */
    private static function enviarUno(array $campana, array $plantilla, array $envio, array $remitente, Smtp $smtp): array
    {
        $contacto = BD::fila('SELECT * FROM `cr_contactos` WHERE `id` = ?', [(int) $envio['contacto_id']]) ?? [];

        // Última comprobación: pudo darse de baja mientras la campaña avanzaba.
        if (Supresion::esta((string) $envio['correo'])) {
            BD::actualizar('cr_envios', ['estado' => 'cancelado', 'error' => 'En la lista de supresión'], '`id` = ?', [(int) $envio['id']]);
            return ['enviado' => false];
        }

        $mensaje = self::construirMensaje($campana, $plantilla, $contacto, $envio, $remitente);
        $r = $smtp->enviar($mensaje);

        if (!empty($r['ok'])) {
            BD::actualizar('cr_envios', [
                'estado'       => 'enviado',
                'remitente_id' => (int) $remitente['id'],
                'enviado_en'   => date('Y-m-d H:i:s'),
                'error'        => null,
                'intentos'     => (int) $envio['intentos'] + 1,
            ], '`id` = ?', [(int) $envio['id']]);
            BD::ejecutar('UPDATE `cr_campanas` SET `enviados` = `enviados` + 1 WHERE `id` = ?', [(int) $campana['id']]);
            return ['enviado' => true];
        }

        $intentos   = (int) $envio['intentos'] + 1;
        $permanente = !empty($r['permanente']);
        $error      = mb_substr((string) ($r['error'] ?? 'Error desconocido'), 0, 400);

        if ($permanente) {
            // Dirección inexistente: se marca como rebote y se suprime.
            BD::actualizar('cr_envios', ['estado' => 'rebotado', 'error' => $error, 'intentos' => $intentos,
                'remitente_id' => (int) $remitente['id']], '`id` = ?', [(int) $envio['id']]);
            BD::ejecutar('UPDATE `cr_campanas` SET `rebotes` = `rebotes` + 1 WHERE `id` = ?', [(int) $campana['id']]);
            Supresion::agregar((string) $envio['correo'], 'rebote', $error);
        } elseif ($intentos >= self::MAX_INTENTOS) {
            BD::actualizar('cr_envios', ['estado' => 'error', 'error' => $error, 'intentos' => $intentos], '`id` = ?', [(int) $envio['id']]);
            BD::ejecutar('UPDATE `cr_campanas` SET `errores` = `errores` + 1 WHERE `id` = ?', [(int) $campana['id']]);
        } else {
            // Error temporal: vuelve a la cola para reintentarlo.
            BD::actualizar('cr_envios', ['estado' => 'pendiente', 'error' => $error, 'intentos' => $intentos], '`id` = ?', [(int) $envio['id']]);
        }

        return ['enviado' => false, 'error' => $error];
    }

    /** Toma el siguiente envío pendiente y lo reserva. */
    private static function siguienteEnvio(int $campanaId): ?array
    {
        $pdo = BD::pdo();
        try {
            $pdo->beginTransaction();
            $fila = BD::fila(
                'SELECT * FROM `cr_envios` WHERE `campana_id` = ? AND `estado` = \'pendiente\'
                 AND `intentos` < ? ORDER BY `id` LIMIT 1 FOR UPDATE',
                [$campanaId, self::MAX_INTENTOS]
            );
            if (!$fila) { $pdo->commit(); return null; }
            // Se marca como "error" temporalmente: si el proceso muere a mitad,
            // no se reenvía por accidente; enviarUno() lo corrige enseguida.
            BD::actualizar('cr_envios', ['estado' => 'error'], '`id` = ?', [(int) $fila['id']]);
            $pdo->commit();
            return $fila;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('CorreoRadar / cola de envío: ' . $e->getMessage());
            return null;
        }
    }

    // ======================================================== construcción

    /** Arma el mensaje personalizado de un destinatario. */
    public static function construirMensaje(array $campana, array $plantilla, array $contacto, array $envio, array $remitente): Mensaje
    {
        $token   = (string) $envio['token'];
        $urlBaja = cr_url('baja.php?t=' . $token);

        $vars = self::variables($contacto, $remitente, $urlBaja);

        $asunto = self::sustituir((string) $plantilla['asunto'], $vars);
        $cuerpo = self::sustituir((string) $plantilla['cuerpo'], $vars);

        $html = self::envolver($cuerpo, $urlBaja, $remitente);
        if (Ajustes::activo('seguimiento_clics', true)) {
            $html = self::reescribirEnlaces($html, $token, $urlBaja);
        }
        if (Ajustes::activo('seguimiento_aperturas', true)) {
            $html .= '<img src="' . e(cr_url('api/pixel.php?t=' . $token)) . '" width="1" height="1" alt="" style="display:block;border:0">';
        }

        $mensaje = new Mensaje(
            (string) $remitente['de_correo'],
            (string) $remitente['de_nombre'],
            (string) $envio['correo'],
            (string) ($contacto['nombre'] ?? ''),
            $asunto,
            $html,
            '',
            (string) $remitente['responder_a']
        );

        // Cabeceras que los proveedores usan para decidir si eres legítimo.
        $mensaje->cabecera('List-Unsubscribe', '<' . $urlBaja . '>, <mailto:' . $remitente['de_correo'] . '?subject=Baja>');
        $mensaje->cabecera('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        $mensaje->cabecera('List-Id', mb_substr((string) $campana['nombre'], 0, 60) . ' <campana-' . (int) $campana['id'] . '.' . cr_host_de_url(cr_url('')) . '>');
        $mensaje->cabecera('X-Mailer', 'CorreoRadar ' . CR_VERSION);

        return $mensaje;
    }

    /** Variables disponibles en asunto y cuerpo. */
    private static function variables(array $contacto, array $remitente, string $urlBaja): array
    {
        $nombre = trim((string) ($contacto['nombre'] ?? ''));
        $centro = trim((string) ($contacto['centro'] ?? ''));

        return [
            'nombre'    => $nombre !== '' ? $nombre : ($centro !== '' ? $centro : 'hola'),
            'centro'    => $centro !== '' ? $centro : 'su centro',
            'correo'    => (string) ($contacto['correo'] ?? ''),
            'dominio'   => (string) ($contacto['dominio'] ?? ''),
            'telefono'  => (string) ($contacto['telefono'] ?? ''),
            'remitente' => (string) ($remitente['de_nombre'] ?: $remitente['de_correo']),
            'firma'     => (string) ($remitente['de_nombre'] ?: $remitente['de_correo']),
            'sitio'     => Ajustes::obtener('sitio_nombre', 'CorreoRadar'),
            'baja'      => $urlBaja,
            'fecha'     => date('d/m/Y'),
        ];
    }

    /** Sustituye {{variable}} por su valor. */
    public static function sustituir(string $texto, array $vars): string
    {
        return preg_replace_callback(
            '~\{\{\s*([a-z_]+)\s*\}\}~i',
            static fn(array $m): string => (string) ($vars[strtolower($m[1])] ?? ''),
            $texto
        ) ?? $texto;
    }

    /** Envuelve el cuerpo en una plantilla HTML con el pie obligatorio de baja. */
    private static function envolver(string $cuerpo, string $urlBaja, array $remitente): string
    {
        // Si el usuario escribió texto plano, se convierte en párrafos y las
        // direcciones web se vuelven enlaces (si no, no se pueden medir ni
        // pinchar cómodamente desde el móvil).
        if (!preg_match('~<(p|div|table|br|h[1-6]|ul|ol)\b~i', $cuerpo)) {
            $parrafos = preg_split('~\n\s*\n~', trim($cuerpo)) ?: [];
            $cuerpo = implode('', array_map(
                static function (string $parrafo): string {
                    $texto = nl2br(e(trim($parrafo)));
                    // La cadena ya está escapada: los & aparecen como &amp;,
                    // que es justo lo correcto dentro de un href.
                    $texto = preg_replace(
                        '~(?<![">=])\b(https?://[^\s<>"]+)~',
                        '<a href="$1" style="color:#0f6fd6">$1</a>',
                        $texto
                    ) ?? $texto;
                    return '<p>' . $texto . '</p>';
                },
                $parrafos
            ));
        }

        $postal = trim(Ajustes::obtener('remitente_postal', ''));
        $pie = '<p style="margin:26px 0 0;padding-top:16px;border-top:1px solid #e6e2d8;color:#7a8089;font-size:12px;line-height:1.6">'
            . 'Recibes este mensaje porque tu dirección aparece publicada en la web de tu organización. '
            . '<a href="' . e($urlBaja) . '" style="color:#7a8089">Darse de baja</a> y no volver a recibir nada.';
        if ($postal !== '') {
            $pie .= '<br>' . e($postal);
        }
        $pie .= '</p>';

        return '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#f6f3eb">'
            . '<div style="max-width:600px;margin:0 auto;padding:26px 22px;background:#ffffff;'
            . 'font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;'
            . 'font-size:15px;line-height:1.65;color:#16181c">'
            . $cuerpo . $pie
            . '</div></body></html>';
    }

    /** Convierte los enlaces en enlaces de seguimiento. */
    private static function reescribirEnlaces(string $html, string $token, string $urlBaja): string
    {
        return preg_replace_callback(
            '~href\s*=\s*"(https?://[^"]+)"~i',
            static function (array $m) use ($token, $urlBaja): string {
                // El href viene escapado para HTML (&amp;): hay que devolverlo a
                // su forma real antes de firmarlo, o la redirección final
                // llevaría un "&amp;" literal en la dirección.
                $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // El enlace de baja y el píxel nunca se reescriben.
                if ($url === $urlBaja || str_contains($url, '/baja.php') || str_contains($url, '/api/pixel.php')) {
                    return $m[0];
                }
                $firma = substr(hash_hmac('sha256', $url . '|' . $token, (string) CR_CLAVE_APP), 0, 16);
                return 'href="' . e(cr_url('api/clic.php?t=' . $token . '&f=' . $firma . '&u=' . rawurlencode($url))) . '"';
            },
            $html
        ) ?? $html;
    }

    // ======================================================== seguimiento

    /** Registra la apertura de un mensaje. */
    public static function registrarApertura(string $token): void
    {
        $envio = BD::fila('SELECT `id`,`campana_id`,`aperturas` FROM `cr_envios` WHERE `token` = ?', [$token]);
        if (!$envio) { return; }

        BD::ejecutar('UPDATE `cr_envios` SET `aperturas` = `aperturas` + 1,
                      `abierto_en` = IFNULL(`abierto_en`, NOW()) WHERE `id` = ?', [(int) $envio['id']]);
        if ((int) $envio['aperturas'] === 0) {
            BD::ejecutar('UPDATE `cr_campanas` SET `aperturas` = `aperturas` + 1 WHERE `id` = ?', [(int) $envio['campana_id']]);
        }
    }

    /** Registra un clic y devuelve la URL de destino, o '' si la firma no cuadra. */
    public static function registrarClic(string $token, string $url, string $firma): string
    {
        $esperada = substr(hash_hmac('sha256', $url . '|' . $token, (string) CR_CLAVE_APP), 0, 16);
        if (!hash_equals($esperada, $firma)) { return ''; }

        $envio = BD::fila('SELECT `id`,`campana_id`,`clics` FROM `cr_envios` WHERE `token` = ?', [$token]);
        if (!$envio) { return ''; }

        BD::ejecutar('UPDATE `cr_envios` SET `clics` = `clics` + 1 WHERE `id` = ?', [(int) $envio['id']]);
        if ((int) $envio['clics'] === 0) {
            BD::ejecutar('UPDATE `cr_campanas` SET `clics` = `clics` + 1 WHERE `id` = ?', [(int) $envio['campana_id']]);
        }
        return $url;
    }

    /**
     * Tramita una baja.
     *
     * @return array{ok:bool,correo?:string}
     */
    public static function procesarBaja(string $token): array
    {
        $envio = BD::fila('SELECT * FROM `cr_envios` WHERE `token` = ?', [$token]);
        if (!$envio) { return ['ok' => false]; }

        Supresion::agregar((string) $envio['correo'], 'baja', 'Baja solicitada desde el correo');
        BD::ejecutar('UPDATE `cr_campanas` SET `bajas` = `bajas` + 1 WHERE `id` = ?', [(int) $envio['campana_id']]);
        BD::ejecutar('UPDATE `cr_envios` SET `estado` = \'cancelado\' WHERE `correo` = ? AND `estado` = \'pendiente\'', [(string) $envio['correo']]);

        return ['ok' => true, 'correo' => (string) $envio['correo']];
    }

    // ========================================================== utilidades

    /** Envíos que quedan por mandar. */
    public static function pendientes(int $id): int
    {
        return (int) BD::valor(
            'SELECT COUNT(*) FROM `cr_envios` WHERE `campana_id` = ? AND `estado` = \'pendiente\' AND `intentos` < ?',
            [$id, self::MAX_INTENTOS],
            0
        );
    }

    /** Ids de los buzones activos que puede usar la campaña. @return int[] */
    public static function remitentesDe(array $campana): array
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $campana['remitentes'])));
        $activos = array_map(static fn($r) => (int) $r['id'], Remitente::activos());
        $validos = $ids ? array_values(array_intersect($ids, $activos)) : $activos;
        return $validos;
    }

    /** Estructura de progreso para el panel. */
    private static function progreso(array $campana, int $enviadosAhora, string $aviso = ''): array
    {
        $id = (int) $campana['id'];
        $pendientes = self::pendientes($id);
        $total = max(1, (int) $campana['total']);
        $hechos = (int) $campana['enviados'] + (int) $campana['errores'] + (int) $campana['rebotes'];

        return [
            'ok'            => true,
            'campana_id'    => $id,
            'estado'        => (string) $campana['estado'],
            'total'         => (int) $campana['total'],
            'enviados'      => (int) $campana['enviados'],
            'errores'       => (int) $campana['errores'],
            'rebotes'       => (int) $campana['rebotes'],
            'aperturas'     => (int) $campana['aperturas'],
            'clics'         => (int) $campana['clics'],
            'bajas'         => (int) $campana['bajas'],
            'pendientes'    => $pendientes,
            'enviados_ahora'=> $enviadosAhora,
            'porcentaje'    => min(100, (int) round($hechos / $total * 100)),
            'terminado'     => in_array($campana['estado'], ['completada', 'cancelada'], true),
            'aviso'         => $aviso,
        ];
    }

    /** Estadísticas completas de una campaña. */
    public static function estadisticas(int $id): array
    {
        $campana = self::obtener($id);
        if (!$campana) { return []; }

        $enviados = max(1, (int) $campana['enviados']);
        return [
            'total'      => (int) $campana['total'],
            'enviados'   => (int) $campana['enviados'],
            'pendientes' => self::pendientes($id),
            'errores'    => (int) $campana['errores'],
            'rebotes'    => (int) $campana['rebotes'],
            'aperturas'  => (int) $campana['aperturas'],
            'clics'      => (int) $campana['clics'],
            'bajas'      => (int) $campana['bajas'],
            'tasa_apertura' => round((int) $campana['aperturas'] / $enviados * 100, 1),
            'tasa_clic'     => round((int) $campana['clics'] / $enviados * 100, 1),
            'tasa_rebote'   => round((int) $campana['rebotes'] / $enviados * 100, 1),
            'tasa_baja'     => round((int) $campana['bajas'] / $enviados * 100, 1),
        ];
    }
}
