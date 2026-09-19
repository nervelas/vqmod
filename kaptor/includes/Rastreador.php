<?php
/**
 * Kaptor - Rastreador (crawler) y orquestador del escaneo.
 *
 * El escaneo se ejecuta por pasos cortos desde el navegador (AJAX). Cada paso
 * procesa unas pocas páginas y devuelve el progreso, de modo que:
 *   - nunca se agota el tiempo máximo de ejecución del hosting compartido;
 *   - el usuario ve una barra de progreso real (página actual, páginas
 *     revisadas y correos encontrados en vivo).
 *
 * Prioridades de la cola (menor = antes):
 *   0  página inicial
 *   1  páginas de contacto, nosotros, equipo, soporte...
 *   3  sitemap.xml y robots.txt
 *   5  resto de páginas internas
 *   8  recursos JS / CSS / JSON / SVG
 */
declare(strict_types=1);

final class Rastreador
{
    /** Palabras que delatan una página con información de contacto. */
    private const PALABRAS_CONTACTO = [
        'contacto', 'contact', 'contactanos', 'contactenos', 'kontakt', 'contatti',
        'nosotros', 'about', 'about-us', 'quienes-somos', 'quienessomos', 'empresa',
        'equipo', 'team', 'staff', 'personal', 'directorio', 'directory', 'plantilla',
        'soporte', 'support', 'ayuda', 'help', 'atencion', 'servicio',
        'impressum', 'legal', 'aviso-legal', 'privacidad', 'privacy', 'terminos',
        'ubicacion', 'sucursales', 'oficinas', 'donde-estamos', 'localizacion',
        'prensa', 'press', 'medios', 'empleo', 'jobs', 'careers', 'trabaja',
        'ventas', 'comercial', 'distribuidores', 'franquicias', 'colaboradores',
    ];

    /** Milisegundos de trabajo por petición AJAX (evita tiempos de espera). */
    private const PRESUPUESTO_MS = 6000;
    /** Máximo de páginas por petición AJAX. */
    private const MAX_POR_PASO = 6;
    /** Tope de recursos (JS/CSS/JSON/SVG) por escaneo. */
    private const MAX_RECURSOS = 60;

    // ==========================================================================
    //  INICIO DEL ESCANEO
    // ==========================================================================

    /**
     * Crea un escaneo nuevo y prepara la cola.
     *
     * @return array{ok:bool,error?:string,escaneo?:array}
     */
    /**
     * Arranca un escaneo sobre VARIAS webs a la vez.
     *
     * Se usa tanto para una lista pegada a mano como para los resultados de
     * una búsqueda. El escaneo se marca con el host "*", y así el rastreador
     * sabe que cada dirección es un sitio distinto y no debe mezclar los
     * enlaces de unos con los de otros.
     *
     * @param string[] $urls
     * @return array{ok:bool,escaneo?:array,error?:string,aceptadas?:int,descartadas?:int}
     */
    public static function iniciarVarias(array $urls, bool $profundo, int $usuarioId = 0, string $etiqueta = ''): array
    {
        $privadas = Ajustes::activo('permitir_privadas');
        $tope     = Ajustes::entero('max_sitios_lote', 100, 1, 500);

        $buenas = [];
        $malas  = 0;
        foreach ($urls as $u) {
            $u = trim((string) $u);
            if ($u === '') { continue; }
            if (!preg_match('~^https?://~i', $u)) { $u = 'https://' . ltrim($u, '/'); }

            $val = Seguridad::validarUrl($u, $privadas);
            if (!$val['ok']) { $malas++; continue; }

            $buenas[$val['url']] = $val['host'];
            if (count($buenas) >= $tope) { break; }
        }

        if (!$buenas) {
            return ['ok' => false, 'error' => 'Ninguna de las direcciones es válida.'];
        }

        $profundo = $profundo && Ajustes::activo('rastreo_profundo', true);
        // El tope de páginas se reparte entre los sitios, con un mínimo por sitio.
        $porSitio = $profundo ? Ajustes::entero('paginas_por_sitio', 4, 1, 50) : 1;
        $maxPag   = min(2000, count($buenas) * $porSitio + 10);

        $primera = array_key_first($buenas);
        $id = BD::insertar('cr_escaneos', [
            'token'           => cr_aleatorio(16),
            'usuario_id'      => $usuarioId > 0 ? $usuarioId : null,
            'url_origen'      => mb_substr($etiqueta !== '' ? $etiqueta : $primera, 0, 1000),
            'host'            => '*',
            'profundo'        => $profundo ? 1 : 0,
            'max_paginas'     => $maxPag,
            'max_profundidad' => $profundo ? Ajustes::entero('max_profundidad', 2, 0, 10) : 0,
            'estado'          => 'ejecutando',
            'ip'              => cr_ip(),
            'agente'          => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'inicio'          => date('Y-m-d H:i:s'),
        ]);

        foreach (array_keys($buenas) as $u) {
            self::encolar($id, $u, 0, 0, 'pagina');
        }

        Seguridad::registrarPeticion('escaneo');

        return [
            'ok'          => true,
            'escaneo'     => self::escaneo($id),
            'aceptadas'   => count($buenas),
            'descartadas' => $malas,
        ];
    }

    public static function iniciar(string $url, bool $profundo, int $usuarioId = 0): array
    {
        $privadas = Ajustes::activo('permitir_privadas');
        $val = Seguridad::validarUrl($url, $privadas);
        if (!$val['ok']) {
            return ['ok' => false, 'error' => $val['error']];
        }

        $urlLimpia = $val['url'];
        $host      = $val['host'];

        $profundo = $profundo && Ajustes::activo('rastreo_profundo', true);
        $maxPag   = $profundo ? Ajustes::entero('max_paginas', 30, 1, 1000) : 1;
        $maxProf  = $profundo ? Ajustes::entero('max_profundidad', 2, 0, 10) : 0;

        $id = BD::insertar('cr_escaneos', [
            'token'           => cr_aleatorio(16),
            'usuario_id'      => $usuarioId > 0 ? $usuarioId : null,
            'url_origen'      => mb_substr($urlLimpia, 0, 1000),
            'host'            => mb_substr($host, 0, 190),
            'profundo'        => $profundo ? 1 : 0,
            'max_paginas'     => $maxPag,
            'max_profundidad' => $maxProf,
            'estado'          => 'ejecutando',
            'ip'              => cr_ip(),
            'agente'          => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'inicio'          => date('Y-m-d H:i:s'),
        ]);

        // Página inicial.
        self::encolar($id, $urlLimpia, 0, 0, 'pagina');

        // En rastreo profundo aprovechamos sitemap.xml y robots.txt para
        // descubrir páginas que no están enlazadas en el menu.
        if ($profundo && Ajustes::activo('analizar_sitemap', true)) {
            $raiz = self::raiz($urlLimpia);
            self::encolar($id, $raiz . '/sitemap.xml', 0, 3, 'sitemap');
            self::encolar($id, $raiz . '/sitemap_index.xml', 0, 3, 'sitemap');
            self::encolar($id, $raiz . '/robots.txt', 0, 3, 'sitemap');
        }

        Seguridad::registrarPeticion('escaneo');

        return ['ok' => true, 'escaneo' => self::escaneo($id)];
    }

    // ==========================================================================
    //  UN PASO DEL ESCANEO
    // ==========================================================================

    /**
     * Procesa el siguiente lote de la cola.
     *
     * @return array Progreso listo para enviar al navegador.
     */
    public static function paso(int $escaneoId): array
    {
        $escaneo = self::escaneo($escaneoId);
        if (!$escaneo) {
            return ['ok' => false, 'error' => 'El escaneo no existe.'];
        }
        if ($escaneo['estado'] === 'completado' || $escaneo['estado'] === 'error'
            || $escaneo['estado'] === 'cancelado') {
            return self::progreso($escaneo, [], [], '', true);
        }

        $inicio    = microtime(true);
        $nuevos    = [];
        $nuevosTel = [];
        $actual    = '';
        $hechas  = 0;
        $pausa   = Ajustes::entero('pausa_ms', 0, 0, 5000);

        while ($hechas < self::MAX_POR_PASO) {
            // ¿Se alcanzo el límite de páginas o de correos?
            if (self::paginasProcesadas($escaneoId) >= (int) $escaneo['max_paginas']
                && !self::quedanRecursos($escaneoId)) {
                break;
            }
            if ((int) self::contarCorreos($escaneoId) >= Ajustes::entero('max_correos', 1000, 1, 100000)) {
                break;
            }

            // Si el usuario ha pulsado "Detener" mientras este paso corría, se
            // abandona entre página y página en lugar de agotar el turno.
            if (self::cancelado($escaneoId)) { break; }

            $item = self::siguiente($escaneoId);
            if (!$item) { break; }

            $actual = $item['url'];
            $encontrados = self::procesar($escaneo, $item);
            foreach ($encontrados['correos'] as $correo => $datos)   { $nuevos[$correo] = $datos; }
            foreach ($encontrados['telefonos'] as $num => $datosTel) { $nuevosTel[$num] = $datosTel; }
            $hechas++;

            if ($pausa > 0) { usleep($pausa * 1000); }
            if ((microtime(true) - $inicio) * 1000 >= self::PRESUPUESTO_MS) { break; }
        }

        // ¿Terminamos?
        $escaneo  = self::escaneo($escaneoId);
        $pendiente = self::siguiente($escaneoId, false) !== null;
        $limite    = self::paginasProcesadas($escaneoId) >= (int) $escaneo['max_paginas'] && !self::quedanRecursos($escaneoId);
        $terminado = !$pendiente || $limite;

        if ($terminado && $escaneo['estado'] === 'ejecutando') {
            self::finalizar($escaneoId);
            $escaneo = self::escaneo($escaneoId);
        }

        return self::progreso($escaneo, $nuevos, $nuevosTel, $actual, $terminado);
    }

    // ==========================================================================
    //  PROCESADO DE UNA URL
    // ==========================================================================

    /**
     * Descarga y analiza una entrada de la cola.
     *
     * @return array{correos:array,telefonos:array} Hallazgos nuevos de esta página.
     */
    private static function procesar(array $escaneo, array $item): array
    {
        $escaneoId = (int) $escaneo['id'];
        $url       = (string) $item['url'];
        $esRecurso = $item['tipo'] === 'recurso';
        $esSitemap = $item['tipo'] === 'sitemap';

        // Facebook e Instagram: se prueban varias direcciones de la misma
        // página, porque unas responden cuando otras devuelven el muro.
        $red = Ajustes::activo('redes_sociales', true) ? Social::tipo($url) : '';
        if ($red !== '') {
            $resp = ['ok' => false, 'codigo' => 0, 'cuerpo' => '', 'error' => ''];
            // Con tiempo de espera corto y como mucho tres direcciones: si la
            // red no responde, un perfil inalcanzable no puede comerse el
            // turno entero y dejar el resto del escaneo esperando.
            $espera = min(8, Ajustes::entero('timeout', 20, 3, 180));
            $probadas = 0;
            foreach (Social::variantes($url) as $variante) {
                $intento = Http::obtener($variante, [
                    'referer' => 'https://www.google.com/',
                    'timeout' => $espera,
                ]);
                $resp = $intento;
                if ($intento['ok'] && $intento['cuerpo'] !== '' && !Social::muro($intento['cuerpo'])) {
                    break;
                }
                // Si ni siquiera se pudo conectar, las demás direcciones de la
                // misma red tampoco van a responder: no se insiste.
                if ($intento['codigo'] === 0) { break; }
                if (++$probadas >= 3) { break; }
                usleep(200000);
            }
            if ($resp['ok'] && Social::muro($resp['cuerpo'])) {
                $resp['ok']     = false;
                $resp['error']  = ucfirst($red) . ' pidió iniciar sesión para ver esta página.';
                $resp['cuerpo'] = '';
            }
        } else {
            $resp = Http::obtener($url, [
                'referer' => (string) $escaneo['url_origen'],
            ]);
        }

        BD::actualizar('cr_cola', [
            'estado'      => $resp['ok'] ? 'hecho' : 'error',
            'http_codigo' => $resp['codigo'],
        ], '`id` = ?', [(int) $item['id']]);

        if (!$resp['ok'] || $resp['cuerpo'] === '') {
            BD::ejecutar('UPDATE `cr_escaneos` SET `paginas_error` = `paginas_error` + 1 WHERE `id` = ?', [$escaneoId]);
            return ['correos' => [], 'telefonos' => []];
        }

        // En un escaneo de varias webs cada dirección es su propio sitio, así
        // que el ámbito de los enlaces es el host de la página que toca.
        $hostAmbito = (string) $escaneo['host'];
        if ($hostAmbito === '*') { $hostAmbito = cr_host_de_url($url); }
        $extractor = new Extractor($hostAmbito);

        if ($red !== '') {
            // De una página de Facebook o Instagram interesan dos cosas: el
            // texto de la biografía y la ficha de contacto (donde puede estar
            // el correo), y la web propia del negocio, que se encola aparte
            // porque es donde el correo aparece casi siempre.
            $extractor->analizar(Social::texto($resp['cuerpo']), $url, 'text/plain');
            foreach (Social::webs($resp['cuerpo'], $url) as $web) {
                self::encolar($escaneoId, $web, (int) $item['profundidad'], 0, 'pagina');
            }
        }

        if ($esSitemap) {
            // robots.txt también puede apuntar a otros sitemaps.
            if (str_contains($url, 'robots.txt')) {
                if (preg_match_all('~^\s*sitemap:\s*(\S+)~mi', $resp['cuerpo'], $m)) {
                    foreach ($m[1] as $sm) {
                        self::encolar($escaneoId, Http::urlAbsoluta($sm, $url), 0, 3, 'sitemap');
                    }
                }
                $extractor->analizar($resp['cuerpo'], $url, 'text/plain');
            } else {
                $extractor->analizarSitemap($resp['cuerpo'], $url);
            }
        } else {
            $extractor->analizar($resp['cuerpo'], $resp['url_final'], $resp['tipo']);

            // Si la página no solto ningun correo y el servidor permite abrir un
            // navegador interno, se reintenta renderizando el JavaScript.
            if (!$esRecurso && Headless::disponible() && !$extractor->resultados()['correos']) {
                $render = Headless::renderizar($resp['url_final']);
                if ($render !== '') {
                    $extractor->analizar($render, $resp['url_final'], 'text/html');
                }
            }
        }

        $datos = $extractor->resultados();

        // --- Guardar correos -------------------------------------------------
        $nuevos = self::guardarCorreos($escaneo, $datos['correos'], $resp['url_final']);

        // --- Guardar teléfonos y números de WhatsApp --------------------------
        $nuevosTel = self::guardarTelefonos($escaneo, $datos['telefonos'], $resp['url_final']);

        // --- Enlaces de WhatsApp sin número y perfiles sociales ---------------
        if ($datos['enlaces_wa'] || $datos['redes']) {
            self::acumularEnlaces($escaneoId, $datos['enlaces_wa'], $datos['redes']);

            // Si la web enlaza su Facebook o su Instagram, se visitan también:
            // muchos negocios publican ahí el correo y no en su propia web.
            if ($red === '' && Ajustes::activo('redes_sociales', true) && Ajustes::activo('seguir_redes', true)) {
                $puestas = 0;
                foreach ($datos['redes'] as $perfil) {
                    if (Social::tipo($perfil) === '' || Social::usuario($perfil) === '') { continue; }
                    self::encolar($escaneoId, $perfil, (int) $item['profundidad'], 2, 'pagina');
                    if (++$puestas >= 2) { break; }   // Facebook e Instagram, nada más
                }
            }
        }

        // --- Encolar lo descubierto ------------------------------------------
        $profundidad = (int) $item['profundidad'];
        if ((int) $escaneo['profundo'] === 1 && $profundidad < (int) $escaneo['max_profundidad']) {
            foreach ($datos['enlaces'] as $enlace) {
                self::encolar($escaneoId, $enlace, $profundidad + 1, self::prioridad($enlace), 'pagina');
            }
        }
        // Los recursos (JS/CSS/JSON/SVG) se revisan siempre: es donde se esconden
        // muchos correos, incluso en un escaneo de una sola página. Un archivo JS
        // puede a su vez revelar endpoints JSON, así que también se encolan desde
        // los recursos, con un tope global para no entrar en bucle.
        if (Ajustes::activo('analizar_js_css', true)) {
            $encolados = self::recursosEncolados($escaneoId);
            foreach ($datos['recursos'] as $recurso) {
                if ($encolados >= self::MAX_RECURSOS) { break; }
                self::encolar($escaneoId, $recurso, $profundidad, 8, 'recurso');
                $encolados++;
            }
        }
        foreach ($datos['sitemaps'] as $sm) {
            self::encolar($escaneoId, $sm, 0, 3, 'sitemap');
        }

        // Contador de páginas revisadas (los recursos no cuentan como página).
        if (!$esRecurso) {
            BD::ejecutar('UPDATE `cr_escaneos` SET `paginas_ok` = `paginas_ok` + 1 WHERE `id` = ?', [$escaneoId]);
        }
        BD::actualizar('cr_cola', ['correos' => count($nuevos)], '`id` = ?', [(int) $item['id']]);

        return ['correos' => $nuevos, 'telefonos' => $nuevosTel];
    }

    /**
     * Guarda los correos de una página, evitando duplicados y fusionando los
     * métodos de detección cuando el mismo correo aparece varias veces.
     */
    private static function guardarCorreos(array $escaneo, array $correos, string $urlOrigen): array
    {
        if (!$correos) { return []; }

        $escaneoId  = (int) $escaneo['id'];
        $host       = (string) $escaneo['host'];
        $verificar  = Ajustes::activo('verificar_mx');
        $maxCorreos = Ajustes::entero('max_correos', 1000, 1, 100000);
        $nuevos     = [];

        foreach ($correos as $correo => $info) {
            if (self::contarCorreos($escaneoId) >= $maxCorreos) { break; }

            $metodos = implode(',', array_keys($info['metodos']));
            $veces   = max(1, (int) $info['veces']);

            $existe = BD::fila(
                'SELECT `id`, `metodo`, `veces` FROM `cr_correos` WHERE `escaneo_id` = ? AND `correo` = ?',
                [$escaneoId, $correo]
            );

            if ($existe) {
                // Fusiona los métodos sin repetirlos.
                $lista = array_values(array_unique(array_filter(array_merge(
                    explode(',', (string) $existe['metodo']),
                    array_keys($info['metodos'])
                ))));
                BD::actualizar('cr_correos', [
                    'metodo' => mb_substr(implode(',', $lista), 0, 190),
                    'veces'  => (int) $existe['veces'] + $veces,
                ], '`id` = ?', [(int) $existe['id']]);
                continue;
            }

            $dominio = explode('@', $correo)[1] ?? '';
            $mx      = $verificar ? Validador::tieneMx($dominio) : null;

            BD::insertar('cr_correos', [
                'escaneo_id' => $escaneoId,
                'correo'     => $correo,
                'dominio'    => $dominio,
                'url_origen' => mb_substr($urlOrigen, 0, 1000),
                'metodo'     => mb_substr($metodos, 0, 190),
                'tipo'       => Validador::tipo($correo),
                'confianza'  => Validador::confianza($correo, $metodos, $mx, $host, $urlOrigen),
                'mx'         => $mx === null ? null : ($mx ? 1 : 0),
                'veces'      => $veces,
                'detectado'  => date('Y-m-d H:i:s'),
            ]);

            $nuevos[$correo] = [
                'correo'    => $correo,
                'dominio'   => $dominio,
                'metodo'    => $metodos,
                'tipo'      => Validador::tipo($correo),
                'confianza' => Validador::confianza($correo, $metodos, $mx, $host, $urlOrigen),
                'mx'        => $mx,
                'url'       => $urlOrigen,
            ];
        }

        if ($nuevos) {
            BD::ejecutar(
                'UPDATE `cr_escaneos` SET `correos` = (SELECT COUNT(*) FROM `cr_correos` WHERE `escaneo_id` = ?) WHERE `id` = ?',
                [$escaneoId, $escaneoId]
            );
        }
        return $nuevos;
    }

    /**
     * Guarda los teléfonos y números de WhatsApp de una página.
     *
     * @return array Números nuevos (los que no estaban ya en este escaneo).
     */
    private static function guardarTelefonos(array $escaneo, array $telefonos, string $urlOrigen): array
    {
        if (!$telefonos) { return []; }

        $escaneoId = (int) $escaneo['id'];
        $tope      = Ajustes::entero('max_telefonos', 500, 1, 100000);
        $nuevos    = [];

        foreach ($telefonos as $numero => $info) {
            if (self::contarTelefonos($escaneoId) >= $tope) { break; }

            $metodos  = implode(',', array_keys($info['metodos']));
            $esWa     = !empty($info['whatsapp']);
            $datos    = $info['datos'];

            $existe = BD::fila(
                'SELECT `id`, `metodo`, `veces`, `whatsapp` FROM `cr_telefonos` WHERE `escaneo_id` = ? AND `numero` = ?',
                [$escaneoId, $numero]
            );

            if ($existe) {
                $lista = array_values(array_unique(array_filter(array_merge(
                    explode(',', (string) $existe['metodo']),
                    array_keys($info['metodos'])
                ))));
                BD::actualizar('cr_telefonos', [
                    'metodo'   => mb_substr(implode(',', $lista), 0, 190),
                    'veces'    => (int) $existe['veces'] + max(1, (int) $info['veces']),
                    'whatsapp' => ((int) $existe['whatsapp'] === 1 || $esWa) ? 1 : 0,
                ], '`id` = ?', [(int) $existe['id']]);
                continue;
            }

            $confianza = Telefono::confianza($metodos, $esWa, $urlOrigen);

            BD::insertar('cr_telefonos', [
                'escaneo_id' => $escaneoId,
                'numero'     => $numero,
                'formato'    => mb_substr((string) $datos['formato'], 0, 32),
                'pais'       => mb_substr((string) $datos['pais'], 0, 60),
                'iso'        => mb_substr((string) $datos['iso'], 0, 2),
                'whatsapp'   => $esWa ? 1 : 0,
                'url_origen' => mb_substr($urlOrigen, 0, 1000),
                'metodo'     => mb_substr($metodos, 0, 190),
                'confianza'  => $confianza,
                'veces'      => max(1, (int) $info['veces']),
                'detectado'  => date('Y-m-d H:i:s'),
            ]);

            $nuevos[$numero] = [
                'numero'    => $numero,
                'formato'   => $datos['formato'],
                'pais'      => $datos['pais'],
                'iso'       => $datos['iso'],
                'whatsapp'  => $esWa,
                'enlace_wa' => Telefono::enlaceWhatsapp($numero),
                'metodo'    => $metodos,
                'confianza' => $confianza,
                'url'       => $urlOrigen,
            ];
        }

        if ($nuevos) {
            BD::ejecutar(
                'UPDATE `cr_escaneos` SET
                   `telefonos_n` = (SELECT COUNT(*) FROM `cr_telefonos` WHERE `escaneo_id` = ?),
                   `whatsapps`   = (SELECT COUNT(*) FROM `cr_telefonos` WHERE `escaneo_id` = ? AND `whatsapp` = 1)
                 WHERE `id` = ?',
                [$escaneoId, $escaneoId, $escaneoId]
            );
        }
        return $nuevos;
    }

    /** Acumula los enlaces de WhatsApp sin número y los perfiles sociales. */
    private static function acumularEnlaces(int $escaneoId, array $enlacesWa, array $redes): void
    {
        $fila = BD::fila('SELECT `enlaces_wa`, `redes` FROM `cr_escaneos` WHERE `id` = ?', [$escaneoId]);
        if (!$fila) { return; }

        $wa = array_values(array_unique(array_merge(
            array_filter(explode(',', (string) ($fila['enlaces_wa'] ?? ''))),
            $enlacesWa
        )));
        $red = array_values(array_unique(array_merge(
            array_filter(explode(',', (string) ($fila['redes'] ?? ''))),
            $redes
        )));

        BD::actualizar('cr_escaneos', [
            'enlaces_wa' => mb_substr(implode(',', array_slice($wa, 0, 40)), 0, 60000),
            'redes'      => mb_substr(implode(',', array_slice($red, 0, 60)), 0, 60000),
        ], '`id` = ?', [$escaneoId]);
    }

    // ==========================================================================
    //  COLA
    // ==========================================================================

    /** Añade una URL a la cola si no estaba ya. */
    public static function encolar(int $escaneoId, string $url, int $profundidad, int $prioridad, string $tipo = 'pagina'): void
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 1000) { return; }
        if (!preg_match('~^https?://~i', $url)) { return; }

        try {
            BD::ejecutar(
                'INSERT IGNORE INTO `cr_cola` (`escaneo_id`,`url`,`url_hash`,`tipo`,`profundidad`,`prioridad`,`estado`,`creado`)
                 VALUES (?,?,?,?,?,?,\'pendiente\',NOW())',
                [$escaneoId, $url, sha1(strtolower($url)), $tipo, $profundidad, $prioridad]
            );
        } catch (PDOException $e) {
            error_log('Kaptor / encolar: ' . $e->getMessage());
        }
    }

    /**
     * Toma la siguiente URL pendiente. Con $reservar = true la marca como
     * "procesando" para que dos peticiones simultaneas no repitan trabajo.
     */
    private static function siguiente(int $escaneoId, bool $reservar = true): ?array
    {
        $pdo = BD::pdo();
        if (!$reservar) {
            return BD::fila(
                'SELECT * FROM `cr_cola` WHERE `escaneo_id` = ? AND `estado` = \'pendiente\' ORDER BY `prioridad`, `id` LIMIT 1',
                [$escaneoId]
            );
        }

        try {
            $pdo->beginTransaction();
            $fila = BD::fila(
                'SELECT * FROM `cr_cola` WHERE `escaneo_id` = ? AND `estado` = \'pendiente\'
                 ORDER BY `prioridad`, `id` LIMIT 1 FOR UPDATE',
                [$escaneoId]
            );
            if (!$fila) { $pdo->commit(); return null; }
            BD::actualizar('cr_cola', ['estado' => 'procesando'], '`id` = ?', [(int) $fila['id']]);
            $pdo->commit();
            return $fila;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('Kaptor / cola: ' . $e->getMessage());
            return null;
        }
    }

    /** Prioridad de una URL según lo prometedora que sea. */
    private static function prioridad(string $url): int
    {
        $ruta = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        foreach (self::PALABRAS_CONTACTO as $palabra) {
            if (str_contains($ruta, $palabra)) { return 1; }
        }
        // Las páginas cercanas a la raiz suelen ser mas útiles que las profundas.
        $niveles = substr_count(trim($ruta, '/'), '/');
        return $niveles <= 1 ? 5 : 6;
    }

    /** Páginas ya procesadas (sin contar recursos). */
    private static function paginasProcesadas(int $escaneoId): int
    {
        return (int) BD::valor(
            'SELECT COUNT(*) FROM `cr_cola` WHERE `escaneo_id` = ? AND `tipo` <> \'recurso\' AND `estado` IN (\'hecho\',\'error\',\'procesando\')',
            [$escaneoId],
            0
        );
    }

    /** Recursos ya encolados en este escaneo. */
    private static function recursosEncolados(int $escaneoId): int
    {
        return (int) BD::valor(
            'SELECT COUNT(*) FROM `cr_cola` WHERE `escaneo_id` = ? AND `tipo` = \'recurso\'',
            [$escaneoId],
            0
        );
    }

    /** ¿Quedan recursos (JS/CSS) pendientes aunque se agotaran las páginas? */
    private static function quedanRecursos(int $escaneoId): bool
    {
        return (int) BD::valor(
            'SELECT COUNT(*) FROM `cr_cola` WHERE `escaneo_id` = ? AND `tipo` = \'recurso\' AND `estado` = \'pendiente\'',
            [$escaneoId],
            0
        ) > 0;
    }

    /** Número de correos encontrados hasta ahora. */
    public static function contarCorreos(int $escaneoId): int
    {
        return (int) BD::valor('SELECT COUNT(*) FROM `cr_correos` WHERE `escaneo_id` = ?', [$escaneoId], 0);
    }

    /** Número de teléfonos encontrados hasta ahora. */
    public static function contarTelefonos(int $escaneoId): int
    {
        return (int) BD::valor('SELECT COUNT(*) FROM `cr_telefonos` WHERE `escaneo_id` = ?', [$escaneoId], 0);
    }

    /** Número de ellos que son WhatsApp confirmados. */
    public static function contarWhatsapps(int $escaneoId): int
    {
        return (int) BD::valor('SELECT COUNT(*) FROM `cr_telefonos` WHERE `escaneo_id` = ? AND `whatsapp` = 1', [$escaneoId], 0);
    }

    // ==========================================================================
    //  ESTADO Y RESULTADOS
    // ==========================================================================

    /**
     * Elimina los correos "fantasma" que deja la pasada ROT13 cuando el correo
     * real y su reverso aparecen en páginas distintas del mismo escaneo.
     *
     * Dentro de una misma página lo resuelve el propio Extractor; aquí se
     * repasa el escaneo completo, que es donde pueden quedar separados.
     */
    private static function limpiarParesRot13(int $escaneoId): void
    {
        $filas = BD::todos(
            'SELECT `id`, `correo` FROM `cr_correos` WHERE `escaneo_id` = ?',
            [$escaneoId]
        );
        if (!$filas) { return; }

        $porCorreo = [];
        foreach ($filas as $fila) { $porCorreo[$fila['correo']] = (int) $fila['id']; }

        $sobran = [];
        foreach ($porCorreo as $correo => $id) {
            $inverso = str_rot13($correo);
            if ($inverso === $correo || !isset($porCorreo[$inverso])) { continue; }
            if (isset($sobran[$id])) { continue; }

            $puntosA = Validador::plausibilidad($correo);
            $puntosB = Validador::plausibilidad($inverso);
            if ($puntosA === $puntosB) { continue; }

            if ($puntosA > $puntosB) { $sobran[$porCorreo[$inverso]] = true; }
            else                     { $sobran[$id] = true; }
        }
        if (!$sobran) { return; }

        $ids    = array_keys($sobran);
        $huecos = implode(',', array_fill(0, count($ids), '?'));
        BD::ejecutar('DELETE FROM `cr_correos` WHERE `id` IN (' . $huecos . ')', $ids);
    }

    /** ¿El escaneo ha sido detenido a mano mientras este paso se ejecutaba? */
    private static function cancelado(int $escaneoId): bool
    {
        try {
            $estado = BD::valor('SELECT `estado` FROM `cr_escaneos` WHERE `id` = ?', [$escaneoId], '');
        } catch (Throwable $e) {
            return false;
        }
        return $estado === 'cancelado';
    }

    /** Marca el escaneo como completado. */
    public static function finalizar(int $escaneoId): void
    {
        self::limpiarParesRot13($escaneoId);

        BD::actualizar('cr_escaneos', [
            'estado'      => 'completado',
            'correos'     => self::contarCorreos($escaneoId),
            'telefonos_n' => self::contarTelefonos($escaneoId),
            'whatsapps'   => self::contarWhatsapps($escaneoId),
            'fin'         => date('Y-m-d H:i:s'),
        ], '`id` = ?', [$escaneoId]);

        // Libera lo que quedara reservado.
        BD::ejecutar('UPDATE `cr_cola` SET `estado` = \'pendiente\' WHERE `escaneo_id` = ? AND `estado` = \'procesando\'', [$escaneoId]);

        // Si el historial esta desactivado, se borra todo menos el resumen.
        if (!Ajustes::activo('guardar_historial', true)) {
            BD::ejecutar('DELETE FROM `cr_cola` WHERE `escaneo_id` = ?', [$escaneoId]);
        }
    }

    /** Marca el escaneo como fallido. */
    public static function fallar(int $escaneoId, string $mensaje): void
    {
        BD::actualizar('cr_escaneos', [
            'estado'  => 'error',
            'mensaje' => mb_substr($mensaje, 0, 500),
            'fin'     => date('Y-m-d H:i:s'),
        ], '`id` = ?', [$escaneoId]);
    }

    /** Cabecera del escaneo. */
    public static function escaneo(int $id): ?array
    {
        return BD::fila('SELECT * FROM `cr_escaneos` WHERE `id` = ?', [$id]);
    }

    /** Cabecera del escaneo a partir de su token público. */
    public static function porToken(string $token): ?array
    {
        return BD::fila('SELECT * FROM `cr_escaneos` WHERE `token` = ?', [$token]);
    }

    /** Teléfonos y WhatsApp de un escaneo: primero los de WhatsApp. */
    public static function telefonos(int $escaneoId): array
    {
        return BD::todos(
            'SELECT * FROM `cr_telefonos` WHERE `escaneo_id` = ? ORDER BY `whatsapp` DESC, `confianza` DESC, `numero` ASC',
            [$escaneoId]
        );
    }

    /** Correos de un escaneo, ordenados por confianza. */
    public static function correos(int $escaneoId): array
    {
        return BD::todos(
            'SELECT * FROM `cr_correos` WHERE `escaneo_id` = ? ORDER BY `confianza` DESC, `dominio` ASC, `correo` ASC',
            [$escaneoId]
        );
    }

    /** Estructura de progreso que consume el JavaScript de la portada. */
    private static function progreso(array $escaneo, array $nuevos, array $nuevosTel, string $actual, bool $terminado): array
    {
        $escaneoId = (int) $escaneo['id'];
        $revisadas = self::paginasProcesadas($escaneoId);
        $total     = max($revisadas, min(
            (int) $escaneo['max_paginas'],
            $revisadas + (int) BD::valor(
                'SELECT COUNT(*) FROM `cr_cola` WHERE `escaneo_id` = ? AND `tipo` <> \'recurso\' AND `estado` = \'pendiente\'',
                [$escaneoId],
                0
            )
        ));
        $recursos = (int) BD::valor(
            'SELECT COUNT(*) FROM `cr_cola` WHERE `escaneo_id` = ? AND `tipo` = \'recurso\' AND `estado` = \'pendiente\'',
            [$escaneoId],
            0
        );

        $porcentaje = $terminado ? 100 : ($total > 0 ? (int) round($revisadas / max(1, $total) * 92) : 5);

        return [
            'ok'          => true,
            'terminado'   => $terminado,
            'estado'      => (string) $escaneo['estado'],
            'token'       => (string) $escaneo['token'],
            'escaneo_id'  => $escaneoId,
            'url_actual'  => $actual,
            'revisadas'   => $revisadas,
            'total'       => $total,
            'pendientes'  => max(0, $total - $revisadas) + $recursos,
            'correos'     => self::contarCorreos($escaneoId),
            'telefonos'   => self::contarTelefonos($escaneoId),
            'whatsapps'   => self::contarWhatsapps($escaneoId),
            'errores'     => (int) $escaneo['paginas_error'],
            'nuevos'      => array_values($nuevos),
            'nuevos_tel'  => array_values($nuevosTel),
            'porcentaje'  => max(3, min(100, $porcentaje)),
        ];
    }

    /** Primera parte de la URL (esquema + host). */
    private static function raiz(string $url): string
    {
        $p = parse_url($url);
        if (!$p || empty($p['host'])) { return ''; }
        return ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    }
}
