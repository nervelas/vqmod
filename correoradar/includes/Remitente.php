<?php
/**
 * CorreoRadar - Buzones de salida (cuentas SMTP).
 *
 * Cada buzón lleva sus propios límites por hora y por día. El motor va rotando
 * entre los buzones disponibles, que es justo lo que hacen las herramientas de
 * envío en frío: repartir el volumen evita que el proveedor te frene y mejora
 * mucho la entrega.
 */
declare(strict_types=1);

final class Remitente
{
    /** Todos los buzones. */
    public static function todos(): array
    {
        return BD::todos('SELECT * FROM `cr_remitentes` ORDER BY `activo` DESC, `nombre`');
    }

    /** Un buzón por su id. */
    public static function obtener(int $id): ?array
    {
        return BD::fila('SELECT * FROM `cr_remitentes` WHERE `id` = ?', [$id]);
    }

    /** Buzones activos. */
    public static function activos(): array
    {
        return BD::todos('SELECT * FROM `cr_remitentes` WHERE `activo` = 1 ORDER BY `id`');
    }

    /**
     * Crea o actualiza un buzón.
     *
     * @return array{ok:bool,id?:int,error?:string}
     */
    public static function guardar(array $datos, int $id = 0): array
    {
        $correo = mb_strtolower(trim((string) ($datos['de_correo'] ?? '')));
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'La dirección del remitente no es válida.'];
        }
        $host = trim((string) ($datos['host'] ?? ''));
        if ($host === '') {
            return ['ok' => false, 'error' => 'Escribe el servidor SMTP (por ejemplo mail.tudominio.com).'];
        }
        $responder = trim((string) ($datos['responder_a'] ?? ''));
        if ($responder !== '' && !filter_var($responder, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'La dirección de respuesta no es válida.'];
        }

        $fila = [
            'nombre'      => mb_substr(trim((string) ($datos['nombre'] ?? $correo)), 0, 120),
            'de_correo'   => $correo,
            'de_nombre'   => mb_substr(trim((string) ($datos['de_nombre'] ?? '')), 0, 120),
            'responder_a' => $responder,
            'host'        => mb_substr($host, 0, 190),
            'puerto'      => max(1, min(65535, (int) ($datos['puerto'] ?? 587))),
            'seguridad'   => in_array($datos['seguridad'] ?? 'tls', ['tls', 'ssl', 'ninguna'], true) ? $datos['seguridad'] : 'tls',
            'usuario'     => mb_substr(trim((string) ($datos['usuario'] ?? $correo)), 0, 190),
            'limite_hora' => max(1, min(5000, (int) ($datos['limite_hora'] ?? 60))),
            'limite_dia'  => max(1, min(50000, (int) ($datos['limite_dia'] ?? 300))),
            'activo'      => !empty($datos['activo']) ? 1 : 0,
        ];

        // La contraseña solo se toca si se ha escrito una nueva.
        $clave = (string) ($datos['clave'] ?? '');
        if ($clave !== '') {
            $fila['clave'] = Cripto::cifrar($clave);
        } elseif ($id === 0) {
            return ['ok' => false, 'error' => 'Escribe la contraseña del buzón.'];
        }

        if ($id > 0) {
            BD::actualizar('cr_remitentes', $fila, '`id` = ?', [$id]);
            return ['ok' => true, 'id' => $id];
        }

        $fila['creado'] = date('Y-m-d H:i:s');
        return ['ok' => true, 'id' => BD::insertar('cr_remitentes', $fila)];
    }

    /** Borra un buzón. */
    public static function borrar(int $id): void
    {
        BD::ejecutar('UPDATE `cr_envios` SET `remitente_id` = NULL WHERE `remitente_id` = ?', [$id]);
        BD::ejecutar('DELETE FROM `cr_remitentes` WHERE `id` = ?', [$id]);
    }

    /** Devuelve un cliente SMTP configurado para este buzón. */
    public static function smtp(array $fila): Smtp
    {
        return new Smtp([
            'host'      => (string) $fila['host'],
            'puerto'    => (int) $fila['puerto'],
            'seguridad' => (string) $fila['seguridad'],
            'usuario'   => (string) $fila['usuario'],
            'clave'     => Cripto::descifrar((string) $fila['clave']),
            'timeout'   => Ajustes::entero('smtp_timeout', 20, 5, 120),
        ]);
    }

    /**
     * Prueba la conexión y guarda el resultado.
     *
     * @return array{ok:bool,error?:string,detalle?:string,aviso?:string}
     */
    public static function probar(int $id): array
    {
        $fila = self::obtener($id);
        if (!$fila) { return ['ok' => false, 'error' => 'El buzón no existe.']; }

        $r = self::smtp($fila)->probar();
        BD::actualizar('cr_remitentes', [
            'probado_en'   => date('Y-m-d H:i:s'),
            'ultimo_error' => $r['ok'] ? null : mb_substr((string) ($r['error'] ?? ''), 0, 500),
        ], '`id` = ?', [$id]);

        return $r;
    }

    /** Envía un correo de prueba a la dirección indicada. */
    public static function enviarPrueba(int $id, string $destino): array
    {
        $fila = self::obtener($id);
        if (!$fila) { return ['ok' => false, 'error' => 'El buzón no existe.']; }
        if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'La dirección de prueba no es válida.'];
        }

        $sitio = Ajustes::obtener('sitio_nombre', 'CorreoRadar');
        $html = '<p>Hola,</p>'
            . '<p>Este es un <b>correo de prueba</b> enviado desde ' . e($sitio) . ' con el buzón <b>'
            . e((string) $fila['de_correo']) . '</b>.</p>'
            . '<p>Si lo estás leyendo, la configuración SMTP es correcta y ya puedes lanzar campañas.</p>'
            . '<p style="color:#888;font-size:13px">Servidor: ' . e((string) $fila['host'] . ':' . $fila['puerto'])
            . ' · Seguridad: ' . e(strtoupper((string) $fila['seguridad'])) . '</p>';

        $mensaje = new Mensaje(
            (string) $fila['de_correo'],
            (string) $fila['de_nombre'],
            $destino,
            '',
            'Prueba de configuración · ' . $sitio,
            $html,
            '',
            (string) $fila['responder_a']
        );

        $smtp = self::smtp($fila);
        $r = $smtp->enviar($mensaje);
        $smtp->cerrar();

        BD::actualizar('cr_remitentes', [
            'probado_en'   => date('Y-m-d H:i:s'),
            'ultimo_error' => $r['ok'] ? null : mb_substr((string) ($r['error'] ?? ''), 0, 500),
        ], '`id` = ?', [$id]);

        return $r;
    }

    // ------------------------------------------------------------- capacidad

    /** Cuántos correos ha enviado este buzón en la última hora. */
    public static function enviadosUltimaHora(int $id): int
    {
        return (int) BD::valor(
            'SELECT COUNT(*) FROM `cr_envios` WHERE `remitente_id` = ? AND `estado` = \'enviado\'
             AND `enviado_en` > (NOW() - INTERVAL 1 HOUR)',
            [$id],
            0
        );
    }

    /** Cuántos ha enviado hoy. */
    public static function enviadosHoy(int $id): int
    {
        return (int) BD::valor(
            'SELECT COUNT(*) FROM `cr_envios` WHERE `remitente_id` = ? AND `estado` = \'enviado\'
             AND DATE(`enviado_en`) = CURDATE()',
            [$id],
            0
        );
    }

    /** Cuántos correos más admite ahora mismo este buzón. */
    public static function capacidadRestante(array $fila): int
    {
        $id = (int) $fila['id'];
        $porHora = (int) $fila['limite_hora'] - self::enviadosUltimaHora($id);
        $porDia  = (int) $fila['limite_dia']  - self::enviadosHoy($id);
        return max(0, min($porHora, $porDia));
    }

    /**
     * Elige el siguiente buzón con capacidad, repartiendo la carga.
     *
     * @param int[] $permitidos Ids de los buzones que puede usar la campaña.
     */
    public static function siguienteDisponible(array $permitidos = []): ?array
    {
        $mejor = null;
        $mejorLibre = 0;

        foreach (self::activos() as $fila) {
            if ($permitidos && !in_array((int) $fila['id'], $permitidos, true)) { continue; }
            $libre = self::capacidadRestante($fila);
            if ($libre > $mejorLibre) {
                $mejor = $fila;
                $mejorLibre = $libre;
            }
        }
        return $mejor;
    }
}
