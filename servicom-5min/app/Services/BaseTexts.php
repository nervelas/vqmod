<?php
declare(strict_types=1);

namespace S5\Services;

/**
 * Textos de respaldo por rubro e idioma (es/en), SIN IA.
 *
 * Solo se apoyan en lo que el cliente dio: nombre del negocio, rubro, nombres
 * de servicios y sus propias frases (`frase`, `apoyo`, `quienes`,
 * `descripcion`). No afirman años, cifras, certificaciones, precios,
 * direcciones, teléfonos ni testimonios. Trato de "usted".
 */
class BaseTexts
{
    /** Límites de longitud del contrato §6. */
    public const LIMITES = [
        'hero_titulo' => 70, 'hero_subtitulo' => 170, 'hero_boton' => 26,
        'servicios_titulo' => 60, 'servicios_intro' => 220,
        'nosotros_titulo' => 60, 'nosotros_texto' => 900,
        'cta_titulo' => 70, 'cta_texto' => 200, 'cta_boton' => 26,
        'contacto_titulo' => 60, 'contacto_intro' => 220,
        'galeria_titulo' => 60,
        'tienda_titulo' => 60, 'tienda_intro' => 220,
    ];
    public const LIM_RESUMEN = 140;
    public const LIM_DESCRIPCION = 600;
    public const MAX_SERVICIOS = 500;

    private const RUBROS = ['abogado', 'clinica', 'taller', 'ropa', 'restaurante', 'transporte', 'contabilidad', 'importaciones', 'otro'];

    /**
     * Datos por rubro: etiqueta, iconos (FontAwesome 5 free), claves de fotos
     * de stock y tono.
     *
     * @return array<string,array{etiqueta:string,iconos:array,stock:array,tono:string}>
     */
    public static function industrias(): array
    {
        $stock = ['hero', 'nosotros', 'servicio-1', 'servicio-2', 'servicio-3', 'servicio-4', 'servicio-5', 'servicio-6', 'galeria-1', 'galeria-2', 'galeria-3'];
        $d = [
            'abogado' => ['Abogados y bufetes', ['fas fa-gavel', 'fas fa-balance-scale', 'fas fa-file-contract', 'fas fa-user-shield', 'fas fa-landmark', 'fas fa-handshake', 'fas fa-book', 'fas fa-briefcase'], 'Formal, sobrio y confiable; lenguaje claro sin tecnicismos innecesarios.'],
            'clinica' => ['Clínicas y salud', ['fas fa-stethoscope', 'fas fa-heartbeat', 'fas fa-tooth', 'fas fa-user-md', 'fas fa-syringe', 'fas fa-pills', 'fas fa-notes-medical', 'fas fa-clinic-medical'], 'Cercano, tranquilizador y respetuoso; sin promesas de resultados.'],
            'taller' => ['Talleres y servicio automotriz', ['fas fa-wrench', 'fas fa-car', 'fas fa-oil-can', 'fas fa-tools', 'fas fa-cogs', 'fas fa-car-battery', 'fas fa-tachometer-alt', 'fas fa-truck-pickup'], 'Directo, práctico y claro; enfocado en el servicio.'],
            'ropa' => ['Tiendas de ropa y moda', ['fas fa-tshirt', 'fas fa-shopping-bag', 'fas fa-gem', 'fas fa-socks', 'fas fa-user-tag', 'fas fa-tags', 'fas fa-heart', 'fas fa-gift'], 'Fresco, amable e inspirador; cuidado con el estilo.'],
            'restaurante' => ['Restaurantes y comida', ['fas fa-utensils', 'fas fa-pizza-slice', 'fas fa-coffee', 'fas fa-hamburger', 'fas fa-ice-cream', 'fas fa-wine-glass-alt', 'fas fa-concierge-bell', 'fas fa-birthday-cake'], 'Cálido, apetitoso y hospitalario.'],
            'transporte' => ['Transporte y logística', ['fas fa-truck', 'fas fa-shipping-fast', 'fas fa-route', 'fas fa-map-marked-alt', 'fas fa-bus', 'fas fa-box', 'fas fa-pallet', 'fas fa-warehouse'], 'Serio, puntual y orientado a la solución.'],
            'contabilidad' => ['Contabilidad y finanzas', ['fas fa-calculator', 'fas fa-file-invoice-dollar', 'fas fa-chart-line', 'fas fa-coins', 'fas fa-receipt', 'fas fa-file-alt', 'fas fa-percent', 'fas fa-money-check-alt'], 'Profesional, ordenado y transparente.'],
            'importaciones' => ['Importaciones y comercio', ['fas fa-ship', 'fas fa-boxes', 'fas fa-globe-americas', 'fas fa-plane', 'fas fa-dolly', 'fas fa-clipboard-check', 'fas fa-handshake', 'fas fa-warehouse'], 'Confiable, claro y orientado a resultados.'],
            'otro' => ['Otro tipo de negocio', ['fas fa-star', 'fas fa-check-circle', 'fas fa-cogs', 'fas fa-lightbulb', 'fas fa-users', 'fas fa-handshake', 'fas fa-thumbs-up', 'fas fa-clipboard-list'], 'Profesional y cercano.'],
        ];
        $o = [];
        foreach ($d as $k => $v) {
            $o[$k] = ['etiqueta' => $v[0], 'iconos' => $v[1], 'stock' => $stock, 'tono' => $v[2]];
        }
        return $o;
    }

    /**
     * Genera los textos de la web (contrato §6) solo con datos del cliente.
     *
     * @param array $brief Brief canónico (contrato §4); se toleran claves ausentes.
     * @return array<string,mixed>
     */
    public static function textos(array $brief): array
    {
        $en = (($brief['negocio']['idioma'] ?? 'es') === 'en');
        $L = $en ? 'en' : 'es';
        $rubro = (string)($brief['negocio']['rubro'] ?? 'otro');
        if (!in_array($rubro, self::RUBROS, true)) {
            $rubro = 'otro';
        }
        $nombre = TextClean::limpiar($brief['negocio']['nombre'] ?? '', 80);
        $otro = TextClean::limpiar($brief['negocio']['rubro_otro'] ?? '', 60);
        $nom = $nombre !== '' ? $nombre : ($en ? 'our business' : 'nuestro negocio');

        $T = self::plantilla($rubro, $L);
        $corto = $T['corto'];
        if ($rubro === 'otro' && $otro !== '') {
            $corto = $otro;
        }

        $c = is_array($brief['contenido'] ?? null) ? $brief['contenido'] : [];
        $frase = TextClean::limpiar($c['frase'] ?? '', 400);
        $apoyo = TextClean::limpiar($c['apoyo'] ?? '', 600);
        $quienes = TextClean::limpiar($c['quienes'] ?? '', 4000, true);

        // Servicios (nombre + descripción tal cual los escribió el cliente).
        $servs = [];
        $lista = is_array($c['servicios'] ?? null) ? array_values($c['servicios']) : [];
        $lista = array_slice($lista, 0, self::MAX_SERVICIOS);
        foreach ($lista as $i => $s) {
            $n = TextClean::limpiar(is_array($s) ? ($s['nombre'] ?? '') : $s, 80);
            $d = is_array($s) ? TextClean::limpiar($s['descripcion'] ?? '', 2000, true) : '';
            $servs[] = [$n !== '' ? $n : ($en ? 'Service ' : 'Servicio ') . ($i + 1), $d, $n !== ''];
        }

        // Hero
        $tit = '';
        $sub = '';
        if ($frase !== '') {
            if (mb_strlen($frase, 'UTF-8') <= 70) {
                $tit = $frase;
            } else {
                $sub = $frase;
            }
        }
        if ($tit === '') {
            $tit = $nombre !== '' ? $nombre : $T['hero_sin_nombre'];
            if ($nombre !== '' && $corto !== '' && mb_strlen($nombre . ' | ' . $corto, 'UTF-8') <= 70) {
                $tit = $nombre . ' | ' . $corto;
            }
        }
        if ($apoyo !== '') {
            $sub = $apoyo;
        } elseif ($sub === '') {
            $sub = sprintf($T['hero_sub'], $nom);
        }

        // Nosotros
        if ($quienes !== '') {
            $nos = $quienes;
        } else {
            $nos = sprintf($T['nos_1'], $nom, $corto !== '' ? $corto : $T['corto']);
            $nombresServ = [];
            foreach ($servs as $s) {
                if ($s[2]) {
                    $nombresServ[] = $s[0];
                }
                if (count($nombresServ) >= 5) {
                    break;
                }
            }
            if ($nombresServ) {
                $nos .= ' ' . sprintf($T['nos_2'], self::unir($nombresServ, $en));
            }
            $nos .= ' ' . $T['nos_3'];
        }

        $t = [
            'hero_titulo' => $tit,
            'hero_subtitulo' => $sub,
            'hero_boton' => $T['hero_boton'],
            'servicios_titulo' => $T['servicios_titulo'],
            'servicios_intro' => $T['servicios_intro'],
            'nosotros_titulo' => $T['nosotros_titulo'],
            'nosotros_texto' => $nos,
            'cta_titulo' => $T['cta_titulo'],
            'cta_texto' => $T['cta_texto'],
            'cta_boton' => $T['cta_boton'],
            'contacto_titulo' => $T['contacto_titulo'],
            'contacto_intro' => $T['contacto_intro'],
            'galeria_titulo' => $T['galeria_titulo'],
        ];
        if (($brief['plan'] ?? 'info') === 'tienda') {
            $t['tienda_titulo'] = $T['tienda_titulo'];
            $t['tienda_intro'] = $T['tienda_intro'];
        }
        foreach ($t as $k => $v) {
            $t[$k] = TextClean::limpiar($v, self::LIMITES[$k], $k === 'nosotros_texto');
        }

        $sv = [];
        foreach ($servs as $s) {
            $sv[] = self::servicio($s[0], $s[1], $nom, $T);
        }
        $t['servicios'] = $sv;
        return $t;
    }

    /**
     * Texto de un servicio (resumen + descripción).
     *
     * @return array{resumen:string,descripcion:string}
     */
    public static function servicio(string $nombre, string $descripcionCliente, string $negocio, array $T): array
    {
        $desc = TextClean::limpiar($descripcionCliente, 0, true);
        if ($desc !== '') {
            $resumen = self::primeraFrase($desc, self::LIM_RESUMEN);
            return [
                'resumen' => TextClean::limpiar($resumen, self::LIM_RESUMEN),
                'descripcion' => TextClean::limpiar($desc, self::LIM_DESCRIPCION, true),
            ];
        }
        return [
            'resumen' => TextClean::limpiar(sprintf($T['srv_res'], $nombre), self::LIM_RESUMEN),
            'descripcion' => TextClean::limpiar(sprintf($T['srv_desc'], $nombre, $negocio), self::LIM_DESCRIPCION),
        ];
    }

    private static function primeraFrase(string $s, int $max): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
        if (preg_match('/^(.{10,}?[.!?…])(\s|$)/us', $s, $m) && mb_strlen($m[1], 'UTF-8') <= $max) {
            return $m[1];
        }
        return TextClean::recortar($s, $max);
    }

    /** @param string[] $xs */
    private static function unir(array $xs, bool $en): string
    {
        $n = count($xs);
        if ($n <= 1) {
            return $xs[0] ?? '';
        }
        $y = $en ? ' and ' : ' y ';
        return implode(', ', array_slice($xs, 0, $n - 1)) . $y . $xs[$n - 1];
    }

    /** @return array<string,string> */
    private static function plantilla(string $rubro, string $L): array
    {
        $es = $L === 'es';
        $b = $es ? [
            'corto' => 'servicios',
            'hero_sin_nombre' => 'Bienvenido',
            'hero_sub' => 'Conozca los servicios de %s y póngase en contacto con nosotros.',
            'hero_boton' => 'Contáctenos',
            'servicios_titulo' => 'Nuestros servicios',
            'servicios_intro' => 'Conozca lo que podemos hacer por usted. Si tiene dudas, con gusto le atendemos.',
            'nosotros_titulo' => 'Quiénes somos',
            'nos_1' => '%s es un negocio dedicado a %s.',
            'nos_2' => 'Entre lo que ofrecemos: %s.',
            'nos_3' => 'Contáctenos para conocer más y resolver sus dudas.',
            'cta_titulo' => '¿Quiere saber más?',
            'cta_texto' => 'Escríbanos o llámenos y con gusto le atenderemos.',
            'cta_boton' => 'Contáctenos',
            'contacto_titulo' => 'Contáctenos',
            'contacto_intro' => 'Déjenos su mensaje o utilice nuestros datos de contacto. Le responderemos lo antes posible.',
            'galeria_titulo' => 'Galería',
            'tienda_titulo' => 'Nuestra tienda',
            'tienda_intro' => 'Explore nuestros productos y haga su pedido de forma sencilla.',
            'srv_res' => 'Consúltenos sobre %s.',
            'srv_desc' => '%s es uno de los servicios que ofrece %s. Contáctenos para recibir más información y resolver sus dudas.',
        ] : [
            'corto' => 'services',
            'hero_sin_nombre' => 'Welcome',
            'hero_sub' => 'Learn about the services of %s and get in touch with us.',
            'hero_boton' => 'Contact us',
            'servicios_titulo' => 'Our services',
            'servicios_intro' => 'See what we can do for you. If you have any questions, we are happy to help.',
            'nosotros_titulo' => 'About us',
            'nos_1' => '%s is a business dedicated to %s.',
            'nos_2' => 'What we offer includes: %s.',
            'nos_3' => 'Contact us to learn more and get your questions answered.',
            'cta_titulo' => 'Want to know more?',
            'cta_texto' => 'Write or call us and we will gladly assist you.',
            'cta_boton' => 'Contact us',
            'contacto_titulo' => 'Contact us',
            'contacto_intro' => 'Leave us a message or use our contact details. We will reply as soon as possible.',
            'galeria_titulo' => 'Gallery',
            'tienda_titulo' => 'Our store',
            'tienda_intro' => 'Browse our products and place your order easily.',
            'srv_res' => 'Ask us about %s.',
            'srv_desc' => '%s is one of the services offered by %s. Contact us for more information and to answer your questions.',
        ];
        $o = [
            'abogado' => $es ? [
                'corto' => 'servicios legales', 'hero_sub' => 'Atención legal de %s. Cuéntenos su caso y le orientaremos.',
                'hero_boton' => 'Solicitar consulta', 'servicios_titulo' => 'Áreas de atención',
                'servicios_intro' => 'Estas son las áreas legales en las que le podemos atender.',
                'cta_titulo' => '¿Necesita asesoría legal?', 'cta_texto' => 'Cuéntenos su situación y le indicaremos cómo podemos ayudarle.', 'cta_boton' => 'Solicitar consulta',
                'contacto_intro' => 'Escríbanos para plantear su caso. Le responderemos lo antes posible.',
            ] : [
                'corto' => 'legal services', 'hero_sub' => 'Legal assistance from %s. Tell us about your case and we will guide you.',
                'hero_boton' => 'Request a consultation', 'servicios_titulo' => 'Practice areas',
                'servicios_intro' => 'These are the legal areas in which we can assist you.',
                'cta_titulo' => 'Need legal advice?', 'cta_texto' => 'Tell us about your situation and we will explain how we can help.', 'cta_boton' => 'Request consultation',
                'contacto_intro' => 'Write to us to present your case. We will reply as soon as possible.',
            ],
            'clinica' => $es ? [
                'corto' => 'servicios de salud', 'hero_sub' => 'Atención de salud en %s. Consulte por nuestros servicios.',
                'hero_boton' => 'Agendar cita', 'servicios_intro' => 'Estos son los servicios de salud que ofrecemos. Consulte por la atención que necesita.',
                'cta_titulo' => '¿Desea agendar una cita?', 'cta_texto' => 'Comuníquese con nosotros y le indicaremos cómo programar su atención.', 'cta_boton' => 'Agendar cita',
                'contacto_intro' => 'Escríbanos o llámenos para consultar por una cita.',
            ] : [
                'corto' => 'health services', 'hero_sub' => 'Health care at %s. Ask about our services.',
                'hero_boton' => 'Book an appointment', 'servicios_intro' => 'These are the health services we offer. Ask about the care you need.',
                'cta_titulo' => 'Want to book an appointment?', 'cta_texto' => 'Get in touch and we will explain how to schedule your visit.', 'cta_boton' => 'Book appointment',
                'contacto_intro' => 'Write or call us to ask about an appointment.',
            ],
            'taller' => $es ? [
                'corto' => 'servicio automotriz', 'hero_sub' => 'Servicio para su vehículo en %s. Consúltenos.',
                'hero_boton' => 'Pedir cotización', 'servicios_titulo' => 'Servicios para su vehículo',
                'servicios_intro' => 'Estos son los servicios que realizamos. Consúltenos por el que necesita.',
                'cta_titulo' => '¿Necesita servicio para su vehículo?', 'cta_texto' => 'Cuéntenos qué necesita y le damos más información.', 'cta_boton' => 'Pedir cotización',
            ] : [
                'corto' => 'auto repair', 'hero_sub' => 'Vehicle service at %s. Ask us.',
                'hero_boton' => 'Request a quote', 'servicios_titulo' => 'Services for your vehicle',
                'servicios_intro' => 'These are the services we perform. Ask us about the one you need.',
                'cta_titulo' => 'Need service for your vehicle?', 'cta_texto' => 'Tell us what you need and we will give you more information.', 'cta_boton' => 'Request a quote',
            ],
            'ropa' => $es ? [
                'corto' => 'venta de ropa', 'hero_sub' => 'Conozca la propuesta de %s y encuentre su estilo.',
                'hero_boton' => 'Escríbanos', 'servicios_titulo' => 'Lo que ofrecemos',
                'servicios_intro' => 'Conozca lo que tenemos para usted. Escríbanos para más detalles.',
                'cta_titulo' => '¿Busca algo en especial?', 'cta_texto' => 'Escríbanos y con gusto le ayudamos a encontrarlo.', 'cta_boton' => 'Escríbanos',
            ] : [
                'corto' => 'clothing and fashion', 'hero_sub' => 'Discover what %s has for you and find your style.',
                'hero_boton' => 'Write to us', 'servicios_titulo' => 'What we offer',
                'servicios_intro' => 'See what we have for you. Write to us for more details.',
                'cta_titulo' => 'Looking for something special?', 'cta_texto' => 'Write to us and we will gladly help you find it.', 'cta_boton' => 'Write to us',
            ],
            'restaurante' => $es ? [
                'corto' => 'alimentos y bebidas', 'hero_sub' => 'Conozca la oferta de %s y visítenos.',
                'hero_boton' => 'Contáctenos', 'servicios_titulo' => 'Nuestra oferta',
                'servicios_intro' => 'Conozca lo que tenemos para ofrecerle.',
                'cta_titulo' => '¿Desea visitarnos o hacer una consulta?', 'cta_texto' => 'Escríbanos o llámenos y con gusto le atendemos.', 'cta_boton' => 'Contáctenos',
            ] : [
                'corto' => 'food and drinks', 'hero_sub' => 'Discover what %s offers and come visit us.',
                'hero_boton' => 'Contact us', 'servicios_titulo' => 'What we offer',
                'servicios_intro' => 'See what we have to offer.',
                'cta_titulo' => 'Want to visit us or ask a question?', 'cta_texto' => 'Write or call us and we will gladly assist you.', 'cta_boton' => 'Contact us',
            ],
            'transporte' => $es ? [
                'corto' => 'transporte y logística', 'hero_sub' => 'Servicios de transporte de %s. Consulte por su envío.',
                'hero_boton' => 'Pedir cotización', 'servicios_intro' => 'Estos son los servicios de transporte que ofrecemos.',
                'cta_titulo' => '¿Necesita transportar algo?', 'cta_texto' => 'Cuéntenos qué necesita mover y le damos más información.', 'cta_boton' => 'Pedir cotización',
            ] : [
                'corto' => 'transport and logistics', 'hero_sub' => 'Transport services by %s. Ask about your shipment.',
                'hero_boton' => 'Request a quote', 'servicios_intro' => 'These are the transport services we offer.',
                'cta_titulo' => 'Need to move something?', 'cta_texto' => 'Tell us what you need to move and we will give you more information.', 'cta_boton' => 'Request a quote',
            ],
            'contabilidad' => $es ? [
                'corto' => 'contabilidad y finanzas', 'hero_sub' => 'Servicios contables de %s. Consúltenos.',
                'hero_boton' => 'Solicitar asesoría', 'servicios_titulo' => 'Servicios contables',
                'servicios_intro' => 'Estos son los servicios contables y financieros que ofrecemos.',
                'cta_titulo' => '¿Necesita apoyo contable?', 'cta_texto' => 'Cuéntenos su necesidad y le indicaremos cómo ayudarle.', 'cta_boton' => 'Solicitar asesoría',
            ] : [
                'corto' => 'accounting and finance', 'hero_sub' => 'Accounting services by %s. Ask us.',
                'hero_boton' => 'Request advice', 'servicios_titulo' => 'Accounting services',
                'servicios_intro' => 'These are the accounting and financial services we offer.',
                'cta_titulo' => 'Need accounting support?', 'cta_texto' => 'Tell us what you need and we will explain how we can help.', 'cta_boton' => 'Request advice',
            ],
            'importaciones' => $es ? [
                'corto' => 'importaciones', 'hero_sub' => 'Servicios de importación de %s. Consulte por su pedido.',
                'hero_boton' => 'Pedir cotización', 'servicios_intro' => 'Estos son los servicios de importación que ofrecemos.',
                'cta_titulo' => '¿Desea cotizar una importación?', 'cta_texto' => 'Cuéntenos qué necesita y le damos más información.', 'cta_boton' => 'Pedir cotización',
            ] : [
                'corto' => 'imports', 'hero_sub' => 'Import services by %s. Ask about your order.',
                'hero_boton' => 'Request a quote', 'servicios_intro' => 'These are the import services we offer.',
                'cta_titulo' => 'Want to quote an import?', 'cta_texto' => 'Tell us what you need and we will give you more information.', 'cta_boton' => 'Request a quote',
            ],
            'otro' => [],
        ];
        return array_merge($b, $o[$rubro] ?? []);
    }
}
