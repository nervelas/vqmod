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
    /** Límites de los textos ampliados (CONTRATO-LUXE §2). */
    public const LIMITES_LUXE = [
        'hero_eyebrow' => 40, 'nosotros_lead' => 200, 'cita' => 140,
        'valores_titulo' => 60, 'proceso_titulo' => 60, 'faq_titulo' => 60,
        'seo_descripcion' => 158,
    ];
    public const LIM_VALOR_TITULO = 36;
    public const LIM_VALOR_TEXTO = 150;
    public const LIM_PASO_TITULO = 36;
    public const LIM_PASO_TEXTO = 150;
    public const LIM_FAQ_P = 110;
    public const LIM_FAQ_R = 330;
    /** Servicios típicos que se agregan cuando el cliente dejó menos de MIN_SERVICIOS, hasta OBJETIVO_SERVICIOS. */
    public const MIN_SERVICIOS = 4;
    public const OBJETIVO_SERVICIOS = 6;

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

    // ------------------------------------------------------------------
    // Ampliación LUXE (CONTRATO-LUXE §2): textos ricos por rubro, sin IA
    // ------------------------------------------------------------------

    private static function rubroDe(array $brief): string
    {
        $rubro = (string)($brief['negocio']['rubro'] ?? 'otro');
        return in_array($rubro, self::RUBROS, true) ? $rubro : 'otro';
    }

    /**
     * Catálogo de servicios típicos del rubro (8), para sugerir cuando el
     * cliente dio pocos.
     *
     * @return array<int,array{nombre:string,resumen:string,descripcion:string,icono:string}>
     */
    public static function catalogo(string $rubro, string $idioma = 'es'): array
    {
        $rubro = in_array($rubro, self::RUBROS, true) ? $rubro : 'otro';
        $out = [];
        if ($idioma === 'en') {
            $cola = ' Tell us what you need by WhatsApp, phone or the contact form and we will explain how we can help.';
            foreach (BaseTextsData::en()[$rubro]['cat'] as $i => $c) {
                $ic = BaseTextsData::es()[$rubro]['cat'][$i][3];
                $out[] = [
                    'nombre' => $c[0],
                    'resumen' => TextClean::limpiar($c[1], self::LIM_RESUMEN),
                    'descripcion' => TextClean::limpiar($c[1] . $cola, self::LIM_DESCRIPCION),
                    'icono' => $ic,
                ];
            }
            return $out;
        }
        foreach (BaseTextsData::es()[$rubro]['cat'] as $c) {
            $out[] = [
                'nombre' => $c[0],
                'resumen' => TextClean::limpiar($c[1], self::LIM_RESUMEN),
                'descripcion' => TextClean::limpiar($c[2], self::LIM_DESCRIPCION, true),
                'icono' => $c[3],
            ];
        }
        return $out;
    }

    /** Normaliza un nombre para compararlo (minúsculas, sin acentos ni signos). */
    public static function normalizar(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e']);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? '';
        return trim($s);
    }

    /** ¿Dos nombres de servicio son el mismo o muy parecidos? (evita sugerir duplicados) */
    public static function parecidos(string $a, string $b): bool
    {
        $na = self::normalizar($a);
        $nb = self::normalizar($b);
        if ($na === '' || $nb === '') {
            return false;
        }
        if ($na === $nb || strpos(' ' . $na . ' ', ' ' . $nb . ' ') !== false || strpos(' ' . $nb . ' ', ' ' . $na . ' ') !== false) {
            return true;
        }
        $comunes = ['servicio', 'servicios', 'service', 'services', 'derecho', 'asesoria', 'atencion', 'general', 'para', 'empresa', 'empresas', 'tramites', 'consulta', 'consultas', 'cuidado', 'productos', 'product', 'products', 'advisory', 'advice', 'care', 'law'];
        $ta = [];
        foreach (explode(' ', $na) as $w) {
            if (strlen($w) >= 5 && !in_array($w, $comunes, true)) {
                $ta[substr($w, 0, 5)] = true;
            }
        }
        foreach (explode(' ', $nb) as $w) {
            if (strlen($w) >= 5 && !in_array($w, $comunes, true) && isset($ta[substr($w, 0, 5)])) {
                return true;
            }
        }
        return false;
    }

    /** Icono (clave §1) para un servicio: el del catálogo si el nombre se parece; si no, uno del rubro. */
    public static function iconoServicio(string $nombre, string $rubro, int $i): string
    {
        $rubro = in_array($rubro, self::RUBROS, true) ? $rubro : 'otro';
        foreach (BaseTextsData::es()[$rubro]['cat'] as $c) {
            if (self::parecidos($nombre, $c[0])) {
                return $c[3];
            }
        }
        $ic = TextSchema::ICONOS_RUBRO[$rubro];
        return $ic[$i % count($ic)];
    }

    /**
     * Textos ampliados (CONTRATO-LUXE §2) solo con datos del cliente y
     * contenido genérico del rubro. Sin cifras, años, premios, etc.
     *
     * @return array<string,mixed> hero_eyebrow, nosotros_lead, cita, valores_titulo, valores,
     *                             proceso_titulo, proceso, faq_titulo, faq, seo_descripcion
     */
    public static function luxe(array $brief): array
    {
        $en = (($brief['negocio']['idioma'] ?? 'es') === 'en');
        $rubro = self::rubroDe($brief);
        $nombre = TextClean::limpiar($brief['negocio']['nombre'] ?? '', 80);
        $otro = TextClean::limpiar($brief['negocio']['rubro_otro'] ?? '', 60);
        $nom = $nombre !== '' ? $nombre : ($en ? 'Our business' : 'Nuestro negocio');
        $D = $en ? BaseTextsData::en()[$rubro] : BaseTextsData::es()[$rubro];
        $E = BaseTextsData::es()[$rubro];
        $c = is_array($brief['contenido'] ?? null) ? $brief['contenido'] : [];
        $frase = TextClean::limpiar($c['frase'] ?? '', 400);
        $apoyo = TextClean::limpiar($c['apoyo'] ?? '', 600);
        $quienes = TextClean::limpiar($c['quienes'] ?? '', 4000, true);
        foreach (['frase', 'apoyo', 'quienes'] as $kk) {
            if (${$kk} !== '' && TextSchema::pareceInstruccion(${$kk})) {
                ${$kk} = '';   // no se reutiliza como contenido destacado un texto que parece una orden a la IA
            }
        }

        // sobretítulo
        $eyebrow = $D['label'];
        if ($rubro === 'otro' && $otro !== '' && mb_strlen($otro, 'UTF-8') <= self::LIMITES_LUXE['hero_eyebrow']) {
            $eyebrow = mb_strtoupper(mb_substr($otro, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($otro, 1, null, 'UTF-8');
        }
        // frase destacada de «Nosotros»
        if ($quienes !== '') {
            $lead = self::primeraFrase($quienes, self::LIMITES_LUXE['nosotros_lead']);
        } else {
            $lead = sprintf($D['lead'], $nom);
        }
        // cita de impacto: la frase del cliente si cabe; si no, el apoyo; si no, la del rubro
        $cita = $D['cita'];
        if ($frase !== '' && mb_strlen($frase, 'UTF-8') <= self::LIMITES_LUXE['cita'] && mb_strlen($frase, 'UTF-8') >= 12) {
            $cita = $frase;
        } elseif ($apoyo !== '' && mb_strlen($apoyo, 'UTF-8') <= self::LIMITES_LUXE['cita'] && mb_strlen($apoyo, 'UTF-8') >= 12) {
            $cita = $apoyo;
        }

        $icR = TextSchema::ICONOS_RUBRO[$rubro];
        if ($en) {
            $valores = [
                ['Personal attention', 'We listen first and adapt to what you need.'],
                ['Clear communication', 'We explain things in plain words, step by step.'],
                ['Careful work', 'We pay attention to the details in everything we do.'],
                ['Respect for your time', 'We keep you informed and follow up with you.'],
                ['Honest service', 'Friendly, straightforward treatment from the first contact.'],
            ];
            $valores = array_map(fn($v, $i) => ['titulo' => $v[0], 'texto' => $v[1], 'icono' => $icR[$i % count($icR)]], $valores, array_keys($valores));
            $proceso = [
                ['titulo' => 'We talk', 'texto' => 'Tell us what you need and we will answer your questions.'],
                ['titulo' => 'Proposal', 'texto' => 'We explain how we can help you.'],
                ['titulo' => 'We get to work', 'texto' => 'We carry out the work with care and order.'],
                ['titulo' => 'Follow-up', 'texto' => 'We make sure you are happy with the result.'],
            ];
            $faq = [
                ['p' => 'How can I contact you?', 'r' => 'Write to us on WhatsApp, call us or use the contact form on this page. Our contact details and opening hours are in the contact section.'],
                ['p' => 'How do I get started?', 'r' => 'Send us a message telling us what you need. We will reply within our opening hours and explain the next steps.'],
                ['p' => 'Can I ask for a quote?', 'r' => 'Yes. Tell us what you need through the contact form or WhatsApp and we will get back to you with the details.'],
                ['p' => 'What are your opening hours?', 'r' => 'You will find our opening hours in the contact section of this page. Outside those hours, leave us a message and we will answer as soon as possible.'],
                ['p' => 'What if I do not see the service I need?', 'r' => 'Ask us anyway. Tell us what you are looking for and we will let you know how we can help.'],
            ];
            $titulos = ['Why choose us', 'How we work', 'Frequently asked questions'];
            $seo = $nom . ': ' . lcfirst($D['label']) . '. Learn about our services and contact us by WhatsApp or phone.';
        } else {
            $valores = [];
            foreach ($E['valores'] as $v) {
                $valores[] = ['titulo' => $v[0], 'texto' => $v[1], 'icono' => $v[2]];
            }
            $proceso = [];
            foreach ($E['proceso'] as $p) {
                $proceso[] = ['titulo' => $p[0], 'texto' => $p[1]];
            }
            $faq = [
                ['p' => $E['faq1'][0], 'r' => $E['faq1'][1]],
                ['p' => '¿Cuál es el horario de atención?', 'r' => 'Encontrará nuestro horario en la sección de contacto de esta página. Fuera de ese horario puede dejarnos un mensaje y le responderemos lo antes posible.'],
                ['p' => '¿Cómo empiezo?', 'r' => 'Envíenos un mensaje contándonos lo que necesita. Le responderemos dentro de nuestro horario y le explicaremos los siguientes pasos.'],
                ['p' => '¿Puedo pedir una cotización?', 'r' => 'Con gusto. Cuéntenos lo que necesita por WhatsApp o mediante el formulario de contacto y le daremos los detalles.'],
                ['p' => '¿Y si no veo el servicio que busco?', 'r' => 'Consúltenos de todos modos. Cuéntenos lo que necesita y le indicaremos cómo podemos ayudarle.'],
            ];
            $titulos = ['Por qué elegirnos', 'Cómo trabajamos', 'Preguntas frecuentes'];
            $corto = $rubro === 'otro' && $otro !== '' ? $otro : $D['label'];
            $seo = $nom . ': ' . mb_strtolower(mb_substr($corto, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($corto, 1, null, 'UTF-8') . '. Conozca nuestros servicios y contáctenos por WhatsApp o teléfono.';
            if ($apoyo !== '') {
                $seo = $nom . ': ' . $apoyo;
            }
        }
        $o = [
            'hero_eyebrow' => $eyebrow, 'nosotros_lead' => $lead, 'cita' => $cita,
            'valores_titulo' => $titulos[0], 'proceso_titulo' => $titulos[1], 'faq_titulo' => $titulos[2],
            'seo_descripcion' => $seo,
        ];
        foreach (self::LIMITES_LUXE as $k => $max) {
            $o[$k] = TextClean::limpiar($o[$k], $max);
        }
        $o['valores'] = array_map(fn($v) => [
            'titulo' => TextClean::limpiar($v['titulo'], self::LIM_VALOR_TITULO),
            'texto' => TextClean::limpiar($v['texto'], self::LIM_VALOR_TEXTO),
            'icono' => $v['icono'],
        ], $valores);
        $o['proceso'] = array_map(fn($v) => [
            'titulo' => TextClean::limpiar($v['titulo'], self::LIM_PASO_TITULO),
            'texto' => TextClean::limpiar($v['texto'], self::LIM_PASO_TEXTO),
        ], $proceso);
        $o['faq'] = array_map(fn($v) => [
            'p' => TextClean::limpiar($v['p'], self::LIM_FAQ_P),
            'r' => TextClean::limpiar($v['r'], self::LIM_FAQ_R),
        ], $faq);
        return $o;
    }

    /**
     * Textos base COMPLETOS: contrato §6 + ampliación LUXE. Cada servicio
     * incluye además `icono` (clave §1).
     *
     * @return array<string,mixed>
     */
    public static function textosCompletos(array $brief): array
    {
        $t = self::textos($brief);
        $rubro = self::rubroDe($brief);
        $lista = is_array($brief['contenido']['servicios'] ?? null) ? array_values($brief['contenido']['servicios']) : [];
        foreach ($t['servicios'] as $i => $s) {
            $n = TextClean::limpiar(is_array($lista[$i] ?? null) ? ($lista[$i]['nombre'] ?? '') : ($lista[$i] ?? ''), 80);
            $t['servicios'][$i]['icono'] = self::iconoServicio($n, $rubro, $i);
        }
        return $t + self::luxe($brief);
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
