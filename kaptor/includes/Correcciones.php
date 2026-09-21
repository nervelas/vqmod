<?php
/**
 * Kaptor - El archivo de correcciones.
 *
 * El informe dice qué está mal. Esto entrega lo que hay que pegar para
 * arreglarlo, ya escrito con los datos de ESE sitio.
 *
 * Solo se incluye lo que de verdad le falta al sitio analizado: si ya tiene la
 * compresión activada, la compresión no sale. Un archivo con veinte bloques,
 * de los cuales dieciocho sobran, no lo usa nadie.
 *
 * Los bloques van en tres grupos, del más barato al más caro:
 *   .htaccess   se pega y ya está, sin tocar el sitio
 *   código      va dentro de las páginas; hay que saber dónde
 *   a mano      no hay código que valga: es trabajo
 */
declare(strict_types=1);

final class Correcciones
{
    /**
     * Arma el paquete a partir de los hallazgos de una auditoría.
     *
     * @return array{htaccess:string,bloques:array,manual:array,resumen:string}
     */
    public static function armar(array $fila, array $hallazgos, array $datos): array
    {
        // Solo interesan los hallazgos que están mal o a medias.
        $malos = [];
        foreach ($hallazgos as $h) {
            if (in_array($h['estado'] ?? '', [Chequeos::MAL, Chequeos::AVISO], true)) {
                $malos[(string) $h['clave']] = $h;
            }
        }

        $host = (string) ($fila['host'] ?? '');
        $url  = (string) ($datos['url'] ?? $fila['url'] ?? '');

        return [
            'htaccess' => self::htaccess($malos, $datos),
            'bloques'  => self::bloques($malos, $host, $url, $datos),
            'manual'   => self::manual($malos),
            'resumen'  => self::resumen($malos),
        ];
    }

    /** ¿Hay algo que entregar? */
    public static function hayAlgo(array $paquete): bool
    {
        return $paquete['htaccess'] !== '' || $paquete['bloques'] !== [] || $paquete['manual'] !== [];
    }

    // =====================================================================
    //  1. El .htaccess
    // =====================================================================

    /**
     * Las reglas del servidor que le faltan a este sitio.
     *
     * Es el bloque que más rinde de todo el paquete: son unas líneas pegadas
     * en un archivo y arreglan de golpe media docena de hallazgos, sin entrar
     * al sitio ni tocar una sola página.
     */
    private static function htaccess(array $malos, array $datos): string
    {
        $partes = [];

        if (isset($malos['compresion'])) {
            $partes[] = <<<'TXT'
# --- Comprimir lo que se envía (baja el peso entre un 60 % y un 80 %) --------
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css \
    text/javascript application/javascript application/json application/xml \
    image/svg+xml font/woff2
</IfModule>
TXT;
        }

        if (isset($malos['cache'])) {
            $partes[] = <<<'TXT'
# --- Caché del navegador: la segunda visita se vuelve instantánea -----------
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType image/jpeg              "access plus 1 year"
  ExpiresByType image/png               "access plus 1 year"
  ExpiresByType image/webp              "access plus 1 year"
  ExpiresByType image/svg+xml           "access plus 1 year"
  ExpiresByType font/woff2              "access plus 1 year"
  ExpiresByType text/css                "access plus 1 month"
  ExpiresByType application/javascript  "access plus 1 month"
  ExpiresByType text/html               "access plus 1 hour"
</IfModule>
TXT;
        }

        if (isset($malos['http_a_https'])) {
            $partes[] = <<<'TXT'
# --- Todo el mundo entra por la versión segura ------------------------------
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{HTTPS} !=on
  RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
</IfModule>
TXT;
        }

        // Las cabeceras que falten, una a una.
        $cab = [];
        if (isset($malos['cab_nosniff']))   { $cab[] = '  Header always set X-Content-Type-Options "nosniff"'; }
        if (isset($malos['cab_marco']))     { $cab[] = '  Header always set X-Frame-Options "SAMEORIGIN"'; }
        if (isset($malos['cab_referente'])) { $cab[] = '  Header always set Referrer-Policy "strict-origin-when-cross-origin"'; }
        if (isset($malos['version_expuesta'])) {
            $cab[] = '  Header unset X-Powered-By';
            $cab[] = '  Header unset X-Generator';
        }
        if ($cab) {
            $partes[] = "# --- Cabeceras de protección ------------------------------------------------\n"
                . "<IfModule mod_headers.c>\n" . implode("\n", $cab) . "\n</IfModule>";
        }

        // HSTS va aparte y con aviso: mal puesto deja el sitio inaccesible.
        if (isset($malos['hsts']) || isset($malos['cab_hsts'])) {
            $esHttps = ($datos['esquema'] ?? '') === 'https';
            $partes[] = ($esHttps ? '' : '# ')
                . "# --- Obligar SIEMPRE a la conexión segura -----------------------------------\n"
                . ($esHttps
                    ? "# CUIDADO: ponlo SOLO cuando el candado lleve semanas funcionando bien.\n"
                    . "# Una vez activado, los navegadores se niegan a entrar sin cifrado durante\n"
                    . "# un año, y si el certificado falla el sitio queda inaccesible.\n"
                    : "# El sitio todavía NO tiene candado. Instala el certificado primero y\n"
                    . "# quita las almohadillas de estas líneas semanas después.\n")
                . ($esHttps ? '' : '# ') . "<IfModule mod_headers.c>\n"
                . ($esHttps ? '' : '# ') . "  Header always set Strict-Transport-Security \"max-age=31536000\"\n"
                . ($esHttps ? '' : '# ') . "</IfModule>";
        }

        if (!$partes) { return ''; }

        $cabecera = "# ===========================================================================\n"
            . "#  Correcciones generadas por Kaptor\n"
            . "#  Sitio: " . (string) ($datos['host'] ?? '') . "\n"
            . "#  Fecha: " . date('d/m/Y') . "\n"
            . "#\n"
            . "#  CÓMO SE USA\n"
            . "#  1. Entra al hosting (cPanel > Administrador de archivos) y abre public_html.\n"
            . "#  2. Si YA hay un archivo .htaccess, DESCÁRGALO ANTES como copia de seguridad.\n"
            . "#  3. Pega esto AL PRINCIPIO del archivo, sin borrar lo que ya hubiera debajo.\n"
            . "#     (En WordPress, encima del bloque que empieza por '# BEGIN WordPress'.)\n"
            . "#  4. Guarda y abre el sitio. Si algo fallara, restaura la copia y avísanos.\n"
            . "# ===========================================================================\n";

        return $cabecera . "\n" . implode("\n\n", $partes) . "\n";
    }

    // =====================================================================
    //  2. Los trozos de código
    // =====================================================================

    /**
     * Fragmentos listos para pegar, ya rellenos con los datos del sitio.
     *
     * @return array<int,array{titulo:string,donde:string,codigo:string,nota:string}>
     */
    private static function bloques(array $malos, string $host, string $url, array $datos): array
    {
        $b = [];
        $nombre = self::nombreDelSitio($datos, $host);
        $raiz   = $url !== '' ? rtrim(Auditor::raiz($url), '/') : 'https://' . $host;

        // --- Preparada para el celular --------------------------------------
        if (isset($malos['viewport'])) {
            $b[] = [
                'titulo' => 'Que la página funcione en celulares',
                'donde'  => 'Dentro de <head>, lo más arriba posible',
                'codigo' => '<meta name="viewport" content="width=device-width, initial-scale=1">',
                'nota'   => 'Esta línea es imprescindible, pero por sí sola no basta: '
                          . 'el diseño tiene que estar hecho para adaptarse. Si el sitio sigue '
                          . 'viéndose mal en el teléfono, eso ya es trabajo de maquetación.',
            ];
        }

        // --- Título y descripción ---------------------------------------------
        if (isset($malos['titulo']) || isset($malos['descripcion'])) {
            $b[] = [
                'titulo' => 'Título y descripción para Google',
                'donde'  => 'Dentro de <head>, uno por página (cambia el texto en cada una)',
                'codigo' => "<title>{$nombre} · lo que vendes, en pocas palabras</title>\n"
                          . '<meta name="description" content="Frase de 70 a 160 caracteres que '
                          . 'diga qué ofreces, a quién y dónde. Es el anuncio gratis que sale en Google.">',
                'nota'   => 'El título, entre 30 y 60 caracteres. Y distinto en cada página: '
                          . 'repetirlo hace que tus propias páginas compitan entre ellas.',
            ];
        }

        // --- Cómo se ve al compartirlo ------------------------------------------
        if (isset($malos['og'])) {
            $b[] = [
                'titulo' => 'Que el enlace se vea bien al compartirlo por WhatsApp',
                'donde'  => 'Dentro de <head>',
                'codigo' => "<meta property=\"og:title\" content=\"{$nombre}\">\n"
                          . "<meta property=\"og:description\" content=\"Lo que vendes, en una línea.\">\n"
                          . "<meta property=\"og:image\" content=\"{$raiz}/portada.jpg\">\n"
                          . "<meta property=\"og:url\" content=\"{$raiz}/\">\n"
                          . "<meta property=\"og:type\" content=\"website\">\n"
                          . "<meta name=\"twitter:card\" content=\"summary_large_image\">",
                'nota'   => 'La imagen tiene que medir 1200 × 630 píxeles y estar subida al sitio. '
                          . 'Sin esto, quien pegue tu dirección en WhatsApp ve un enlace gris que nadie toca.',
            ];
        }

        // --- Dirección oficial ----------------------------------------------------
        if (isset($malos['canonical']) || isset($malos['canonical_sitio'])) {
            $b[] = [
                'titulo' => 'Declarar la dirección oficial de cada página',
                'donde'  => 'Dentro de <head>, apuntando a la dirección de ESA página',
                'codigo' => "<link rel=\"canonical\" href=\"{$raiz}/\">",
                'nota'   => 'En cada página va su propia dirección, no siempre la de la portada.',
            ];
        }

        // --- WhatsApp ----------------------------------------------------------------
        if (isset($malos['whatsapp'])) {
            $b[] = [
                'titulo' => 'Botón flotante de WhatsApp',
                'donde'  => 'Justo antes de </body>, en todas las páginas',
                'codigo' => self::botonWhatsapp(),
                'nota'   => 'Cambia el 50200000000 por el número real, con el prefijo del país '
                          . 'y sin espacios ni guiones. En Guatemala es 502 y luego los ocho dígitos.',
            ];
        }

        // --- Teléfono que se marca solo ------------------------------------------------
        if (isset($malos['telefono'])) {
            $b[] = [
                'titulo' => 'Teléfono que se marca de un toque',
                'donde'  => 'Donde ahora aparezca el número escrito a secas',
                'codigo' => '<a href="tel:+50222223333">2222 3333</a>',
                'nota'   => 'Desde el celular, un número que no es enlace obliga a memorizarlo y '
                          . 'teclearlo a mano. Mucha gente no llega al final de ese recorrido.',
            ];
        }

        // --- Ficha del negocio -----------------------------------------------------------
        if (isset($malos['schema']) || isset($malos['schema_negocio'])) {
            $b[] = [
                'titulo' => 'Ficha del negocio para Google y para las IA',
                'donde'  => 'Dentro de <head>, solo en la portada',
                'codigo' => self::fichaNegocio($nombre, $raiz),
                'nota'   => 'Rellena la dirección, el teléfono y el horario reales. Es lo que hace '
                          . 'que salgan los datos del negocio directamente en los resultados de Google, '
                          . 'y lo que permite que ChatGPT lo recomiende cuando alguien pregunta por la zona.',
            ];
        }

        // --- Preguntas frecuentes ------------------------------------------------------------
        if (isset($malos['faq'])) {
            $b[] = [
                'titulo' => 'Preguntas frecuentes (para salir en las respuestas de las IA)',
                'donde'  => 'En la página donde estén las preguntas',
                'codigo' => self::fichaFaq(),
                'nota'   => 'Pon las tres o cuatro preguntas que de verdad te hacen los clientes, '
                          . 'con su respuesta. Es la forma más barata de que te citen.',
            ];
        }

        // --- Medir las visitas -------------------------------------------------------------------
        if (isset($malos['analitica'])) {
            $b[] = [
                'titulo' => 'Medir las visitas (Google Analytics)',
                'donde'  => 'Dentro de <head>, en todas las páginas',
                'codigo' => "<!-- Cambia G-XXXXXXXXXX por tu identificador de Google Analytics 4 -->\n"
                          . "<script async src=\"https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX\"></script>\n"
                          . "<script>\n"
                          . "  window.dataLayer = window.dataLayer || [];\n"
                          . "  function gtag(){dataLayer.push(arguments);}\n"
                          . "  gtag('js', new Date());\n"
                          . "  gtag('config', 'G-XXXXXXXXXX');\n"
                          . "</script>",
                'nota'   => 'El identificador se saca en analytics.google.com creando una propiedad. '
                          . 'Sin esto no se sabe cuánta gente entra ni de dónde viene: se decide a ciegas.',
            ];
        }

        // --- robots.txt y mapa del sitio -------------------------------------------------------------
        if (isset($malos['robots']) || isset($malos['sitemap'])) {
            $b[] = [
                'titulo' => 'Archivo robots.txt',
                'donde'  => 'Un archivo llamado robots.txt en la raíz del sitio (public_html)',
                'codigo' => "User-agent: *\nAllow: /\n\nSitemap: {$raiz}/sitemap.xml",
                'nota'   => 'Si el sitio es WordPress, el mapa lo genera solo el plugin Yoast o Rank Math. '
                          . 'Después hay que darlo de alta en Google Search Console.',
            ];
        }

        // --- Dejar entrar a las IA ------------------------------------------------------------------
        if (isset($malos['bots_ia'])) {
            $b[] = [
                'titulo' => 'Dejar que las inteligencias artificiales lean el sitio',
                'donde'  => 'En el robots.txt: hay que QUITAR las reglas que las bloquean',
                'codigo' => "# QUITA del robots.txt los bloques que se parezcan a esto:\n"
                          . "#\n"
                          . "#   User-agent: GPTBot\n"
                          . "#   Disallow: /\n"
                          . "#\n"
                          . "# Los robots que conviene dejar entrar son:\n"
                          . "#   GPTBot y OAI-SearchBot  (ChatGPT)\n"
                          . "#   ClaudeBot               (Claude)\n"
                          . "#   PerplexityBot           (Perplexity)\n"
                          . "#   Google-Extended         (Gemini)",
                'nota'   => 'Si el robot no puede entrar, el negocio no puede ser recomendado nunca '
                          . 'cuando alguien pregunte por su sector en una IA.',
            ];
        }

        // --- Idioma y iconito --------------------------------------------------------------------------
        if (isset($malos['idioma'])) {
            $b[] = [
                'titulo' => 'Declarar el idioma',
                'donde'  => 'En la etiqueta <html> de todas las páginas',
                'codigo' => '<html lang="es">',
                'nota'   => 'Google lo usa para decidir a quién le enseña el sitio.',
            ];
        }
        if (isset($malos['favicon'])) {
            $b[] = [
                'titulo' => 'Iconito de la pestaña',
                'donde'  => 'Dentro de <head>, con el archivo subido a la raíz',
                'codigo' => "<link rel=\"icon\" href=\"{$raiz}/favicon.ico\" sizes=\"any\">\n"
                          . "<link rel=\"icon\" href=\"{$raiz}/icono.svg\" type=\"image/svg+xml\">\n"
                          . "<link rel=\"apple-touch-icon\" href=\"{$raiz}/icono-180.png\">",
                'nota'   => 'Es el detalle que separa un sitio cuidado de uno improvisado.',
            ];
        }

        // --- JavaScript que frena el pintado -------------------------------------------------------------
        if (isset($malos['js_bloqueante'])) {
            $b[] = [
                'titulo' => 'Que el código no frene el dibujado de la página',
                'donde'  => 'En las etiquetas <script> que estén dentro de <head>',
                'codigo' => "<!-- Antes -->\n<script src=\"archivo.js\"></script>\n\n"
                          . "<!-- Después: con defer, el navegador ya no se para a esperarlo -->\n"
                          . "<script src=\"archivo.js\" defer></script>",
                'nota'   => 'Con defer el archivo se descarga mientras la página se dibuja, y se '
                          . 'ejecuta al final. La pantalla en blanco del principio desaparece.',
            ];
        }

        // --- Imágenes -----------------------------------------------------------------------------------
        if (isset($malos['imagenes_medidas']) || isset($malos['imagenes_diferidas'])) {
            $b[] = [
                'titulo' => 'Imágenes que no descuadran la página',
                'donde'  => 'En cada etiqueta <img> del sitio',
                'codigo' => '<img src="foto.webp" alt="Describe lo que se ve en la foto"'
                          . "\n     width=\"800\" height=\"600\" loading=\"lazy\">",
                'nota'   => 'width y height con las medidas reales del archivo: es lo que impide que la '
                          . 'página "salte" mientras carga. loading="lazy" solo en las imágenes que quedan '
                          . 'por debajo de la primera pantalla, nunca en la de arriba.',
            ];
        }

        return $b;
    }

    // =====================================================================
    //  3. Lo que no se arregla pegando código
    // =====================================================================

    /**
     * @return array<int,array{titulo:string,pasos:string}>
     */
    private static function manual(array $malos): array
    {
        $m = [];

        if (isset($malos['https'])) {
            $m[] = ['titulo' => 'Instalar el certificado de seguridad (el candado)',
                    'pasos'  => 'Entra al hosting → SSL/TLS → Let\'s Encrypt → instalar para el dominio. '
                              . 'Es gratuito y en casi todos los proveedores se hace con un clic. '
                              . 'Déjalo con renovación automática activada.'];
        }
        if (isset($malos['ssl_vence'])) {
            $m[] = ['titulo' => 'Renovar el certificado antes de que venza',
                    'pasos'  => 'Hosting → SSL/TLS → renovar. Y esta vez activa la renovación automática '
                              . 'y una alerta por correo treinta días antes: casi siempre vence un fin de '
                              . 'semana y nadie se entera hasta el lunes.'];
        }
        if (isset($malos['noindex']) || isset($malos['noindex_sitio'])) {
            $m[] = ['titulo' => 'Quitar la instrucción que esconde el sitio de Google',
                    'pasos'  => 'En WordPress: Ajustes → Lectura → desmarcar «Disuade a los motores de '
                              . 'búsqueda». En otros casos, buscar en el código la etiqueta meta robots '
                              . 'con noindex y borrarla. Es el arreglo más rápido y más rentable de toda la lista.'];
        }
        if (isset($malos['http2'])) {
            $m[] = ['titulo' => 'Pedir HTTP/2 al hosting',
                    'pasos'  => 'Escríbele al soporte de tu proveedor: «por favor activen HTTP/2 en mi cuenta». '
                              . 'Lo hacen ellos, no cuesta nada y casi todos ya lo tienen.'];
        }
        if (isset($malos['ttfb'])) {
            $m[] = ['titulo' => 'El servidor tarda en responder',
                    'pasos'  => 'Activa una caché del lado del servidor (en WordPress, LiteSpeed Cache o '
                              . 'WP Super Cache). Si aun así sigue lento, el plan de hosting se ha quedado corto.'];
        }
        if (isset($malos['imagenes_peso']) || isset($malos['imagenes_formato'])) {
            $m[] = ['titulo' => 'Bajar el peso de las imágenes',
                    'pasos'  => 'Conviértelas a WebP y redúcelas al tamaño en que de verdad se ven. '
                              . 'En WordPress lo hacen solos los plugins ShortPixel o Smush. Se puede bajar '
                              . 'el peso un 80 % sin que se note a simple vista, y suele ser la mitad del problema de velocidad.'];
        }
        if (isset($malos['css_adaptable']) || isset($malos['ancho_fijo'])) {
            $m[] = ['titulo' => 'El diseño no se adapta al celular',
                    'pasos'  => 'Esto no se arregla con un parche: hay que rehacer la maquetación con reglas '
                              . 'para pantallas pequeñas. Es el trabajo grande de la lista, y también el que '
                              . 'más cambia los resultados, porque siete de cada diez visitas llegan desde un teléfono.'];
        }
        if (isset($malos['contenido']) || isset($malos['contenido_pobre'])) {
            $m[] = ['titulo' => 'Escribir contenido',
                    'pasos'  => 'Google no puede posicionar lo que no puede leer. Cada página necesita al menos '
                              . '300 palabras útiles: qué se ofrece, para quién, dónde y por qué tú. '
                              . 'Sin esto, lo demás de la lista rinde la mitad.'];
        }
        if (isset($malos['enlaces_rotos']) || isset($malos['rotos_internos'])) {
            $m[] = ['titulo' => 'Arreglar los enlaces rotos',
                    'pasos'  => 'Corrige o quita los enlaces que llevan a páginas que ya no existen. '
                              . 'Si una página se movió, pon una redirección desde la dirección vieja a la nueva.'];
        }
        if (isset($malos['lista_negra']) || isset($malos['familias']) || isset($malos['cloaking'])
            || isset($malos['redirige_movil']) || isset($malos['enlaces_ocultos']) || isset($malos['virustotal'])) {
            $m[] = ['titulo' => 'URGENTE: limpiar el sitio',
                    'pasos'  => 'Hay código metido por alguien. El orden importa: (1) copia de seguridad de lo '
                              . 'que hay ahora, para poder analizarlo; (2) cambiar TODAS las contraseñas: hosting, '
                              . 'base de datos, administradores y FTP; (3) limpiar o reinstalar desde archivos '
                              . 'limpios, actualizando plugins y plantilla; (4) solo entonces, pedir la revisión '
                              . 'a Google desde Search Console. Restaurar una copia anterior sin cerrar por dónde '
                              . 'entraron solo retrasa el problema unos días.'];
        }

        return $m;
    }

    /** Una línea con lo que trae el paquete. */
    private static function resumen(array $malos): string
    {
        $n = count($malos);
        if ($n === 0) { return 'No se encontró nada que corregir.'; }
        return $n === 1
            ? 'Un punto por corregir.'
            : $n . ' puntos por corregir. Empieza por el archivo .htaccess: es el que más arregla con menos trabajo.';
    }

    // =====================================================================
    //  Piezas
    // =====================================================================

    /** El nombre del negocio, sacado de lo que ya diga el sitio. */
    private static function nombreDelSitio(array $datos, string $host): string
    {
        $t = trim((string) ($datos['titulo'] ?? ''));
        if ($t !== '') {
            // "Colegio Modelo · Preprimaria y primaria" -> "Colegio Modelo"
            $corte = preg_split('~\s+[|·\-–—]\s+~u', $t) ?: [];
            $primero = trim((string) ($corte[0] ?? ''));
            if ($primero !== '' && mb_strlen($primero) <= 60) { return $primero; }
            return mb_substr($t, 0, 60);
        }
        return $host !== '' ? $host : 'Tu negocio';
    }

    private static function botonWhatsapp(): string
    {
        return <<<'HTML'
<!-- Botón flotante de WhatsApp. Cambia el número por el tuyo. -->
<a href="https://wa.me/50200000000?text=Hola,%20vengo%20de%20su%20p%C3%A1gina%20web"
   target="_blank" rel="noopener" aria-label="Escríbenos por WhatsApp"
   style="position:fixed;right:20px;bottom:20px;z-index:9999;width:58px;height:58px;
          border-radius:50%;background:#25D366;display:flex;align-items:center;
          justify-content:center;box-shadow:0 4px 14px rgba(0,0,0,.3)">
  <svg viewBox="0 0 24 24" width="30" height="30" fill="#fff" aria-hidden="true">
    <path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Zm5.5 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-1.7-.1-.4-.1-.9-.3-1.5-.6-2.7-1.2-4.4-3.9-4.6-4.1-.1-.2-1-1.4-1-2.6 0-1.2.6-1.8.9-2.1.2-.2.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.3 0 .5l-.4.5-.3.3c-.1.1-.2.3 0 .5.2.3.8 1.3 1.7 2.1 1.1 1 2 1.3 2.3 1.4.2.1.4.1.5-.1l.8-.9c.2-.2.3-.2.5-.1l2 .9c.2.1.4.2.4.3.1.1.1.5-.1 1.1Z"/>
  </svg>
</a>
HTML;
    }

    private static function fichaNegocio(string $nombre, string $raiz): string
    {
        $n = str_replace(['"', "\\"], '', $nombre);
        return <<<HTML
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "{$n}",
  "url": "{$raiz}/",
  "telephone": "+502 2222 3333",
  "email": "info@ejemplo.com",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "5a avenida 10-20, zona 10",
    "addressLocality": "Guatemala",
    "addressCountry": "GT"
  },
  "openingHoursSpecification": [{
    "@type": "OpeningHoursSpecification",
    "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday"],
    "opens": "08:00",
    "closes": "17:00"
  }],
  "sameAs": [
    "https://www.facebook.com/tupagina",
    "https://www.instagram.com/tupagina"
  ]
}
</script>
HTML;
    }

    private static function fichaFaq(): string
    {
        return <<<'HTML'
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "¿Cuánto cuesta?",
      "acceptedAnswer": { "@type": "Answer", "text": "Escribe aquí la respuesta de verdad." }
    },
    {
      "@type": "Question",
      "name": "¿Dónde están ubicados?",
      "acceptedAnswer": { "@type": "Answer", "text": "La dirección completa y cómo llegar." }
    },
    {
      "@type": "Question",
      "name": "¿Cuál es el horario?",
      "acceptedAnswer": { "@type": "Answer", "text": "De lunes a viernes de 8:00 a 17:00." }
    }
  ]
}
</script>
HTML;
    }

    // =====================================================================
    //  Descarga
    // =====================================================================

    /** Todo el paquete en un solo archivo de texto, listo para enviar. */
    public static function comoTexto(array $paquete, array $fila, string $marca): string
    {
        $l = [];
        $l[] = str_repeat('=', 74);
        $l[] = '  CORRECCIONES PARA ' . mb_strtoupper((string) $fila['host']);
        $l[] = '  Preparado por ' . $marca . ' · ' . date('d/m/Y');
        $l[] = str_repeat('=', 74);
        $l[] = '';
        $l[] = $paquete['resumen'];
        $l[] = '';

        if ($paquete['htaccess'] !== '') {
            $l[] = str_repeat('-', 74);
            $l[] = '  1. ARCHIVO .htaccess';
            $l[] = str_repeat('-', 74);
            $l[] = '';
            $l[] = $paquete['htaccess'];
            $l[] = '';
        }

        if ($paquete['bloques']) {
            $l[] = str_repeat('-', 74);
            $l[] = '  2. CÓDIGO PARA PEGAR EN LAS PÁGINAS';
            $l[] = str_repeat('-', 74);
            foreach ($paquete['bloques'] as $i => $b) {
                $l[] = '';
                $l[] = '### ' . ($i + 1) . '. ' . $b['titulo'];
                $l[] = 'Dónde va: ' . $b['donde'];
                $l[] = '';
                $l[] = $b['codigo'];
                if ($b['nota'] !== '') { $l[] = ''; $l[] = 'Nota: ' . $b['nota']; }
                $l[] = '';
            }
        }

        if ($paquete['manual']) {
            $l[] = str_repeat('-', 74);
            $l[] = '  3. LO QUE NO SE ARREGLA PEGANDO CÓDIGO';
            $l[] = str_repeat('-', 74);
            foreach ($paquete['manual'] as $i => $m) {
                $l[] = '';
                $l[] = '### ' . ($i + 1) . '. ' . $m['titulo'];
                $l[] = $m['pasos'];
            }
            $l[] = '';
        }

        $l[] = str_repeat('=', 74);
        $l[] = 'Haz una copia de seguridad antes de tocar nada.';
        $l[] = 'Generado con Kaptor a partir del análisis de ' . (string) $fila['host'] . '.';
        $l[] = str_repeat('=', 74);

        return implode("\r\n", $l) . "\r\n";
    }
}
