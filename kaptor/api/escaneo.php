<?php
/**
 * Kaptor - API del escaneo (AJAX).
 *
 * Acciones:
 *   iniciar    Válida la URL, crea el escaneo y prepara la cola.
 *   paso       Procesa el siguiente lote de páginas y devuelve el progreso.
 *   resultado  Devuelve la tabla completa de correos de un escaneo.
 *   cancelar   Detiene un escaneo en curso.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$datos  = cr_cuerpo_json();
$accion = (string) ($datos['accion'] ?? '');

// Todas las acciones exigen token CSRF válido.
Seguridad::exigirCsrf((string) ($datos['csrf'] ?? ''), true);

// ¿Puede este visitante usar el extractor?
if (!Auth::puedeExtraer()) {
    cr_json(['ok' => false, 'error' => 'Necesitas iniciar sesión para extraer correos.', 'requiere_login' => true], 403);
}

switch ($accion) {

    // ---------------------------------------------------------------- iniciar
    case 'iniciar':
        if (Seguridad::limiteAlcanzado('escaneo')) {
            cr_json([
                'ok' => false,
                'error' => 'Has alcanzado el límite de extracciones por hora (' . Ajustes::entero('limite_ip_hora', 30) . '). Inténtalo más tarde.',
            ], 429);
        }

        $entrada  = trim((string) ($datos['url'] ?? ''));
        $profundo = !empty($datos['profundo']);

        // Búsqueda inteligente: "solo quiero correos .edu.gt".
        $objetivo = Depurador::extensiones((string) ($datos['objetivo'] ?? ''));
        $objetivoTxt = $objetivo ? implode(',', $objetivo) : '';

        // ¿Qué ha pegado el usuario? Una web, una lista de webs, el enlace de
        // una búsqueda de Google, o directamente unas palabras para buscar.
        $lineas = preg_split('~[\r\n,;]+~', $entrada) ?: [];
        $lineas = array_values(array_filter(array_map('trim', $lineas), static fn($l) => $l !== ''));

        $consulta = Buscador::consultaDeUrl($entrada);
        $aviso    = null;

        if ($consulta === '' && count($lineas) === 1 && Buscador::pareceConsulta($entrada)) {
            $consulta = $entrada;                       // son palabras de búsqueda
        }

        if ($consulta !== '') {
            if (!Ajustes::activo('buscar_activo', true)) {
                cr_json(['ok' => false, 'error' => 'La búsqueda por palabras está desactivada en los ajustes.'], 400);
            }
            $hallazgo = Buscador::buscar($consulta, Ajustes::entero('buscador_max', 100, 10, 300), $objetivo);
            if (!$hallazgo['ok']) {
                cr_json([
                    'ok'      => false,
                    'error'   => $hallazgo['error'],
                    'detalle' => $hallazgo['detalle'] ?? null,
                ], 502);
            }
            // En una búsqueda hay que entrar en cada web: el correo casi nunca
            // está en la portada, sino en "Contacto" o "Nosotros". Por eso el
            // rastreo dentro de cada sitio va activado salvo que el
            // administrador lo haya apagado del todo en los ajustes.
            $res = Rastreador::iniciarVarias(
                $hallazgo['urls'], true, Auth::id(), 'Búsqueda: ' . $consulta, $objetivoTxt
            );
            $aviso = count($hallazgo['urls']) . ' webs encontradas en ' . $hallazgo['motor']
                   . ' para «' . $consulta . '».';
        } elseif (count($lineas) > 1) {
            $res   = Rastreador::iniciarVarias($lineas, true, Auth::id(), count($lineas) . ' webs', $objetivoTxt);
            $aviso = ($res['aceptadas'] ?? 0) . ' webs en la lista'
                   . (!empty($res['descartadas']) ? ', ' . $res['descartadas'] . ' descartadas por no ser válidas' : '') . '.';
        } else {
            $res = Rastreador::iniciar($entrada, $profundo, Auth::id(), $objetivoTxt);
        }

        if ($objetivo) {
            $aviso = trim((string) $aviso . ' Solo se guardarán los correos que terminen en '
                   . implode(', ', array_map(static fn($x) => '.' . $x, $objetivo)) . '.');
        }

        if (!$res['ok']) {
            cr_json(['ok' => false, 'error' => $res['error']], 400);
        }

        $escaneo = $res['escaneo'];
        cr_marcar_escaneo((int) $escaneo['id']);   // permite consultarlo sin cuenta

        cr_json([
            'ok'         => true,
            'escaneo_id' => (int) $escaneo['id'],
            'token'      => $escaneo['token'],
            'url'        => $escaneo['url_origen'],
            'host'       => $escaneo['host'],
            'profundo'   => (int) $escaneo['profundo'] === 1,
            'max_paginas'=> (int) $escaneo['max_paginas'],
            'aviso_js'   => Headless::aviso(),
            'aviso'      => $aviso,
            'sitios'     => $res['aceptadas'] ?? 1,
            'objetivo'   => $objetivo ? implode(', ', array_map(static fn($x) => '.' . $x, $objetivo)) : '',
        ]);
        // no se alcanza

    // ------------------------------------------------------------------- paso
    case 'paso':
        $id = (int) ($datos['escaneo_id'] ?? 0);
        if ($id <= 0) { cr_json(['ok' => false, 'error' => 'Escaneo no válido.'], 400); }

        $escaneo = Rastreador::escaneo($id);
        if (!$escaneo) { cr_json(['ok' => false, 'error' => 'El escaneo no existe.'], 404); }
        if (!cr_puede_ver_escaneo($escaneo)) { cr_json(['ok' => false, 'error' => 'Sin permiso.'], 403); }

        // Un paso puede tardar varios segundos y PHP bloquea el archivo de
        // sesión mientras dura la petición. Sin cerrarla aquí, la llamada a
        // "cancelar" quedaría esperando en cola y el botón Detener no surtiría
        // efecto hasta que el paso terminara. Ya no se escribe nada en la
        // sesión a partir de este punto.
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

        try {
            $progreso = Rastreador::paso($id);
        } catch (Throwable $e) {
            error_log('Kaptor / paso: ' . $e->getMessage());
            Rastreador::fallar($id, $e->getMessage());
            cr_json(['ok' => false, 'error' => 'Se produjo un error durante el escaneo.'], 500);
        }
        cr_json($progreso);
        // no se alcanza

    // -------------------------------------------------------------- resultado
    case 'resultado':
        $id = (int) ($datos['escaneo_id'] ?? 0);
        $escaneo = Rastreador::escaneo($id);
        if (!$escaneo) { cr_json(['ok' => false, 'error' => 'El escaneo no existe.'], 404); }
        if (!cr_puede_ver_escaneo($escaneo)) { cr_json(['ok' => false, 'error' => 'Sin permiso.'], 403); }

        $correos = [];
        foreach (Rastreador::correos($id) as $fila) {
            $correos[] = [
                'correo'    => $fila['correo'],
                'dominio'   => $fila['dominio'],
                'url'       => $fila['url_origen'],
                'metodo'    => $fila['metodo'],
                'tipo'      => $fila['tipo'],
                'confianza' => (int) $fila['confianza'],
                'mx'        => $fila['mx'] === null ? null : ((int) $fila['mx'] === 1),
                'veces'     => (int) $fila['veces'],
            ];
        }

        $telefonos = [];
        foreach (Rastreador::telefonos($id) as $fila) {
            $telefonos[] = [
                'numero'    => $fila['numero'],
                'formato'   => $fila['formato'],
                'pais'      => $fila['pais'],
                'iso'       => $fila['iso'],
                'whatsapp'  => (int) $fila['whatsapp'] === 1,
                'enlace_wa' => Telefono::enlaceWhatsapp((string) $fila['numero']),
                'metodo'    => $fila['metodo'],
                'confianza' => (int) $fila['confianza'],
                'veces'     => (int) $fila['veces'],
                'url'       => $fila['url_origen'],
            ];
        }

        cr_json([
            'ok'         => true,
            'token'      => $escaneo['token'],
            'url'        => $escaneo['url_origen'],
            'host'       => $escaneo['host'],
            'estado'     => $escaneo['estado'],
            'revisadas'  => (int) $escaneo['paginas_ok'],
            'errores'    => (int) $escaneo['paginas_error'],
            'correos'    => $correos,
            'telefonos'  => $telefonos,
            'enlaces_wa' => array_values(array_filter(explode(',', (string) $escaneo['enlaces_wa']))),
            'redes'      => array_values(array_filter(explode(',', (string) $escaneo['redes']))),
        ]);
        // no se alcanza

    // --------------------------------------------------------------- cancelar
    case 'cancelar':
        $id = (int) ($datos['escaneo_id'] ?? 0);
        $escaneo = Rastreador::escaneo($id);
        if ($escaneo && cr_puede_ver_escaneo($escaneo)) {
            BD::actualizar('cr_escaneos', ['estado' => 'cancelado', 'fin' => date('Y-m-d H:i:s')], '`id` = ?', [$id]);
            BD::ejecutar('DELETE FROM `cr_cola` WHERE `escaneo_id` = ? AND `estado` = \'pendiente\'', [$id]);
        }
        cr_json(['ok' => true]);
        // no se alcanza

    default:
        cr_json(['ok' => false, 'error' => 'Accion no reconocida.'], 400);
}
