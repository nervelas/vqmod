<?php
/**
 * Contenido inicial del sitio Te Lo Llevo.
 * Todo es editable desde el panel de administración.
 */
if (!defined('TLL_BOOT')) { http_response_code(403); exit; }

// Hash bcrypt de la contraseña inicial (la contraseña NO se guarda en texto plano).
const TLL_DEFAULT_ADMIN_USER = 'Admin-TLL';
const TLL_DEFAULT_ADMIN_HASH = '$2y$12$WYX9032HP.WkpL6wFIX7Wukt2qCr82HnPzxRcYZuDNmdHjxOVx63O';

function default_settings(): array {
    return [
        'mail_to'   => 'info@telollevogt.com',
        'mail_from' => '',
        'smtp' => [
            'enabled' => false,
            'host'    => '',
            'port'    => 587,
            'secure'  => 'tls',   // tls | ssl | none
            'user'    => '',
            'pass'    => '',
        ],
    ];
}

function default_content(): array {
    return [
        'site' => [
            'brand'            => 'Te Lo Llevo',
            'tagline'          => 'Delivery y envíos',
            'logo'             => 'assets/img/logo-mark.png',
            'meta_title'       => 'Te Lo Llevo | Delivery y envíos en toda Guatemala',
            'meta_description' => 'Delivery y envíos a nivel nacional, en toda Guatemala. Rápido, seguro y con confirmación por WhatsApp. Cotiza y solicita tu envío en minutos.',
            'share_image'      => 'assets/img/og-image.jpg',
            'floating'         => true,
        ],
        'theme' => [
            'blue'   => '#0176D3',
            'navy'   => '#062B5F',
            'yellow' => '#FFC914',
        ],
        'contact' => [
            'phone_display' => '3789 9709',
            'phone_digits'  => '50237899709',
            'whatsapp'      => '50237899709',
            'whatsapp_msg'  => 'Hola Te Lo Llevo 👋 quisiera solicitar un servicio de envío.',
            'email'         => 'info@telollevogt.com',
            'address'       => 'Servicio a nivel nacional, en toda Guatemala',
            'hours'         => "Lunes a sábado: 7:00 a.m. – 8:00 p.m.\nDomingos y feriados: 8:00 a.m. – 4:00 p.m.",
            'facebook'      => '',
            'instagram'     => '',
            'tiktok'        => '',
            'youtube'       => '',
        ],
        'layout' => [
            'order'   => ['hero', 'stats', 'services', 'about', 'why', 'coverage', 'testimonials', 'faq', 'cta', 'contact', 'how', 'rates'],
            'enabled' => [
                'hero' => true, 'stats' => true, 'services' => true, 'about' => true, 'how' => false, 'rates' => false, 'why' => true, 'coverage' => true, 'testimonials' => false, 'faq' => true, 'cta' => true, 'contact' => true,
            ],
        ],
        'nav' => [
            'cta_label' => 'Cotizar ahora',
            'cta_href'  => '#contacto',
            'items' => [
                ['label' => 'Inicio',          'href' => '#inicio'],
                ['label' => 'Servicios',       'href' => '#servicios'],
                ['label' => 'Misión, Visión y Propósito', 'href' => '#mision'],
                ['label' => 'Cobertura',       'href' => '#cobertura'],
                ['label' => 'Preguntas',       'href' => '#preguntas'],
                ['label' => 'Contacto',        'href' => '#contacto'],
            ],
        ],
        'hero' => [
            'badge'        => 'Piloto+motocicleta+operación diaria',
            'title'        => 'Soluciones *logísticas* de reparto',
            'subtitle'     => 'Asignamos un motorista exclusivo, motocicleta y operación completa por día. Su empresa se enfoca en crecer, nosotros garantizamos que cada envío se ejecute con puntualidad, eficiencia y total compromiso.',
            'cta1_label'   => 'Cotización',
            'cta1_href'    => '#contacto',
            'cta2_label'   => '',
            'cta2_href'    => '',
            'image'        => 'assets/img/equipo.webp',
            'image_alt'    => 'Equipo de motoristas uniformados de Te Lo Llevo',
            'card1_icon'   => 'seal-check',
            'card1_title'  => '',
            'card1_text'   => '',
            'card2_icon'   => 'lightning',
            'card2_title'  => '',
            'card2_text'   => '',
            'trust' => [
                ['icon' => 'medal',        'text' => 'Personal calificado'],
                ['icon' => 'lightning',    'text' => 'Atención inmediata'],
                ['icon' => 'shield-check', 'text' => 'Servicio garantizado'],
            ],
        ],
        'stats' => [
            'items' => [
                ['icon' => 'timer',          'prefix' => '', 'number' => 'Jornada completa', 'suffix' => '', 'label' => ''],
                ['icon' => 'whatsapp-logo',  'prefix' => '', 'number' => '100', 'suffix' => '%',     'label' => 'Seguimiento por WhatsApp'],
                ['icon' => 'calendar-check', 'prefix' => '', 'number' => '7',   'suffix' => ' días', 'label' => 'Servicio de lunes a domingo'],
            ],
        ],
        'services' => [
            'eyebrow'  => 'Nuestros servicios',
            'title'    => 'Todo lo que su empresa necesita *repartir*, en un solo lugar',
            'subtitle' => 'Desde un documento urgente hasta la operación diaria de su negocio. Elija el servicio que mejor se adapte a su operación.',
            'cta_label' => 'Solicitar este servicio',
            'items' => [
                ['icon' => 'moped',          'title' => 'Motorista por Jornada',     'tag' => 'Exclusivo',         'text' => 'Un piloto asignado exclusivamente a su empresa por 8 horas, con motocicleta y operación completa incluida.'],
                ['icon' => 'calendar-check', 'title' => 'Reparto Programado',        'tag' => 'A su horario',      'text' => 'Programe rutas diarias, semanales o por temporada. Perfecto para entregas recurrentes y planificadas.'],
                ['icon' => 'buildings',      'title' => 'Mensajería Empresarial',    'tag' => 'Corporativo',       'text' => 'Recogemos y entregamos documentos, paquetes y pedidos de su empresa con registro (Ruta diaria)'],
                ['icon' => 'fork-knife',     'title' => 'Reparto para Restaurantes', 'tag' => 'Para restaurantes', 'text' => 'Motorista exclusivo para entregar sus pedidos calientes y a tiempo. Su restaurante se enfoca en cocinar, nosotros en que cada pedido llegue puntual a sus clientes.'],
            ],
        ],
        'about' => [
            'eyebrow'    => 'Nosotros',
            'title'      => 'Misión, Visión y *Propósito*',
            'image'      => 'assets/img/motorista-uniforme.webp',
            'image_alt'  => 'Motorista uniformado de Te Lo Llevo',
            'banner'     => 'assets/img/equipo-motos.webp',
            'banner_alt' => 'Equipo de motoristas de Te Lo Llevo con sus motocicletas',
            'items' => [
                ['icon' => 'target', 'title' => 'Misión',    'text' => 'Proveer soluciones logísticas de reparto y mensajería, mediante motoristas exclusivos, procesos eficientes y comunicación transparente, garantizando entregas puntuales, seguras y con trazabilidad en cada operación.'],
                ['icon' => 'eye',    'title' => 'Visión',    'text' => 'Ser una empresa líder en servicios de reparto, reconocida por su excelencia operativa, confiabilidad y compromiso con el crecimiento sostenible de las empresas que confían en nuestros servicios.'],
                ['icon' => 'flag',   'title' => 'Propósito', 'text' => 'Optimizar la operación logística de nuestros clientes, permitiéndoles delegar sus entregas en un aliado estratégico que garantiza puntualidad, control y calidad en cada jornada.'],
            ],
            'values' => [
                ['name' => 'Puntualidad'], ['name' => 'Confianza'], ['name' => 'Transparencia'], ['name' => 'Excelencia Operativa'], ['name' => 'Compromiso Empresarial'],
            ],
        ],
        'how' => [
            'eyebrow'  => 'Cómo funciona',
            'title'    => 'Enviar con nosotros es *fácil y rápido*',
            'subtitle' => 'En cuatro pasos tu paquete sale de donde está y llega a donde debe estar.',
            'items' => [
                ['icon' => 'chat-circle-dots', 'title' => 'Solicita tu envío',     'text' => 'Escríbenos por WhatsApp, llámanos o llena el formulario con los datos de recogida y entrega.'],
                ['icon' => 'map-pin-line',     'title' => 'Recogemos tu paquete',  'text' => 'Un motorizado uniformado llega al punto de recogida y verifica el paquete contigo.'],
                ['icon' => 'moped',            'title' => 'Va en camino',          'text' => 'Tu envío viaja en caja protegida y te mantenemos informado durante el trayecto.'],
                ['icon' => 'seal-check',       'title' => 'Entregado',             'text' => 'Entregamos en la puerta del destinatario y te confirmamos. Así de simple.'],
            ],
        ],
        'rates' => [
            'eyebrow'   => 'Tarifas y cotizador',
            'title'     => 'Calcula tu envío *al instante*',
            'subtitle'  => 'Elige la distancia, el tamaño y la urgencia. Te mostramos un precio estimado y lo confirmas con un mensaje.',
            'currency'  => 'Q',
            'distances' => [
                ['label' => 'Hasta 3 km',   'price' => 20],
                ['label' => 'De 3 a 6 km',  'price' => 30],
                ['label' => 'De 6 a 10 km', 'price' => 40],
                ['label' => 'De 10 a 15 km', 'price' => 55],
                ['label' => 'De 15 a 25 km', 'price' => 75],
            ],
            'sizes' => [
                ['label' => 'Sobre o documento',            'extra' => 0],
                ['label' => 'Paquete pequeño (hasta 3 kg)', 'extra' => 0],
                ['label' => 'Paquete mediano (hasta 8 kg)', 'extra' => 10],
                ['label' => 'Paquete grande (hasta 15 kg)', 'extra' => 20],
            ],
            'speeds' => [
                ['label' => 'Estándar (hasta 3 horas)',  'extra' => 0],
                ['label' => 'Express (lo antes posible)', 'extra' => 15],
                ['label' => 'Programado (día y hora a elegir)', 'extra' => 0],
            ],
            'note'      => 'Precios referenciales en quetzales para envíos dentro de la ciudad y alrededores; para otros departamentos cotizamos según el destino. El valor final puede variar según la ruta, el tráfico y las condiciones del envío; siempre lo confirmamos antes de salir.',
            'cta_label' => 'Pedir por WhatsApp',
        ],
        'why' => [
            'eyebrow'  => '¿Por qué Te Lo Llevo?',
            'title'    => 'Un servicio en el que *puedes confiar*',
            'subtitle' => 'Cuidamos cada envío como si fuera nuestro. Por eso nuestros clientes vuelven.',
            'image'    => 'assets/img/motorista-caja.webp',
            'image_alt'=> 'Motorista de Te Lo Llevo con su caja de entregas',
            'badge_title' => 'Motorizados identificados',
            'badge_text'  => 'Uniforme, caja y logo oficial',
            'items' => [
                ['icon' => 'lightning',           'title' => 'Rápido de verdad',        'text' => 'Rutas optimizadas y motorizados cerca de ti para reducir al máximo el tiempo de entrega.'],
                ['icon' => 'shield-check',        'title' => 'Seguro y protegido',      'text' => 'Cajas cerradas que cuidan tus paquetes del polvo, la lluvia y los golpes.'],
                ['icon' => 'identification-card', 'title' => 'Personal identificado',   'text' => 'Repartidores uniformados, capacitados y con excelente trato al cliente.'],
                ['icon' => 'chats',               'title' => 'Comunicación constante',  'text' => 'Te avisamos por WhatsApp cuando recogemos y cuando entregamos tu paquete.'],
                ['icon' => 'heart',               'title' => 'Trato cercano',           'text' => 'Somos un equipo local que conoce la ciudad y está siempre a un mensaje de distancia.'],
            ],
        ],
        'coverage' => [
            'eyebrow'  => 'Cobertura',
            'title'    => 'Servicio en *toda Guatemala*',
            'subtitle' => 'Brindamos servicio de reparto, mensajería y distribución por jornada para empresas en todo el país. Un motorista exclusivo asignado a su operación por 8 horas, con motocicleta, combustible y gestión de rutas incluida. Escríbanos con su zona de trabajo y le enviamos una cotización al instante, sin compromiso.',
            'zones' => [
                ['name' => 'Guatemala'],
                ['name' => 'Sacatepéquez'],
                ['name' => 'Chimaltenango'],
                ['name' => 'Escuintla'],
                ['name' => 'Santa Rosa'],
                ['name' => 'Sololá'],
                ['name' => 'Totonicapán'],
                ['name' => 'Quetzaltenango'],
                ['name' => 'Suchitepéquez'],
                ['name' => 'Retalhuleu'],
                ['name' => 'San Marcos'],
                ['name' => 'Huehuetenango'],
                ['name' => 'Quiché'],
                ['name' => 'Baja Verapaz'],
                ['name' => 'Alta Verapaz'],
                ['name' => 'Petén'],
                ['name' => 'Izabal'],
                ['name' => 'Zacapa'],
                ['name' => 'Chiquimula'],
                ['name' => 'Jalapa'],
                ['name' => 'Jutiapa'],
                ['name' => 'El Progreso'],
            ],
            'note'      => "Escríbanos por WhatsApp con su zona y el tipo de operación que necesita. Le respondemos con una propuesta clara.\nTrabajamos con convenios mensuales, quincenales, semanales y por turno diario, y servicios por día, comunicación directa con su motorista durante toda la jornada.",
            'cta_label' => 'Cotizar',
        ],
        'testimonials' => [
            'eyebrow'  => 'Opiniones',
            'title'    => 'Lo que dicen *nuestros clientes*',
            'subtitle' => 'Personas y negocios que ya confían en nosotros.',
            'items' => [
                ['name' => 'Nombre del cliente', 'role' => 'Negocio o cargo', 'rating' => 5, 'text' => 'Reemplaza este texto con el comentario real de un cliente. Actívalo desde Panel → Secciones.'],
                ['name' => 'Nombre del cliente', 'role' => 'Negocio o cargo', 'rating' => 5, 'text' => 'Reemplaza este texto con el comentario real de un cliente.'],
                ['name' => 'Nombre del cliente', 'role' => 'Negocio o cargo', 'rating' => 5, 'text' => 'Reemplaza este texto con el comentario real de un cliente.'],
            ],
        ],
        'faq' => [
            'eyebrow'  => 'Preguntas frecuentes',
            'title'    => 'Resolvemos *tus dudas*',
            'subtitle' => 'Lo que más nos preguntan las empresas antes de contratar su motorista por jornada.',
            'items' => [
                ['q' => '¿En qué zonas prestan el servicio?', 'a' => 'Tenemos cobertura en todo el país.'],
                ['q' => '¿Qué incluye la jornada de 8 horas?', 'a' => 'Motorista exclusivo, motocicleta, combustible, uniforme corporativo y gestión de rutas durante 8 horas para su empresa. Usted decide los pedidos y nosotros ejecutamos las entregas.'],
                ['q' => '¿Qué tipo de cosas pueden entregar?', 'a' => 'Documentos, paquetes, pedidos de tienda en línea, comida de restaurantes, facturas, cheques y papelería empresarial.'],
                ['q' => '¿Qué artículos no se pueden transportar?', 'a' => 'No transportamos armas, drogas, objetos ilegales, valores en efectivo altos sin previo acuerdo, ni artículos peligrosos o prohibidos por la ley.'],
                ['q' => '¿Cómo se calcula el precio?', 'a' => 'Por jornada completa de 8 horas, no por entrega. El precio varía según la zona y el volumen de operación. Le enviamos cotización personalizada.'],
                ['q' => '¿Cómo puedo pagar?', 'a' => 'Transferencia bancaria o depósito contra entrega de reporte. Para empresas con convenio mensual, semanal o por turno diario.'],
                ['q' => '¿Puedo programar un servicio para otro día?', 'a' => 'Sí. Puede reservar su motorista con anticipación por WhatsApp y programar servicios recurrentes de lunes a domingo.'],
                ['q' => '¿Qué pasa si el destinatario no está?', 'a' => 'Nos comunicamos con usted de inmediato, intentamos coordinar una nueva entrega dentro de la misma jornada y dejamos constancia del intento.'],
                ['q' => '¿Trabajan con empresas y restaurantes?', 'a' => 'Sí. Es nuestro servicio principal. Trabajamos con restaurantes, tiendas en línea, emprendimientos y empresas con convenios mensuales y motoristas exclusivos.'],
            ],
        ],
        'cta' => [
            'title'          => '¿Necesitás un motorista por día? *Nosotros te lo ponemos.*',
            'text'           => 'Te asignamos piloto, moto y gasolina por jornada completa. Trabaja solo para tu empresa.',
            'whatsapp_label' => 'Cotizar motorista por WhatsApp',
            'call_label'     => 'Llamar ahora',
            'chips' => [
                ['icon' => 'motorcycle',       'text' => 'Piloto + Moto + Gasolina incluido'],
                ['icon' => 'clock',            'text' => 'Jornada de 8am a 6pm'],
                ['icon' => 'arrows-clockwise', 'text' => 'Reemplazo inmediato si falta'],
            ],
        ],
        'form' => [
            'eyebrow'   => 'Contacto',
            'title'     => 'Solicita tu *servicio*',
            'subtitle'  => 'Completa el formulario y te contactamos en minutos para confirmar tu envío. También puedes escribirnos directo por WhatsApp.',
            'services'  => "Motorista por Jornada - 8 horas\nReparto para Restaurantes\nReparto Programado\nMensajería Empresarial\nDocumentos y Trámites",
            'button'    => 'Enviar solicitud',
            'success'   => '¡Gracias! Recibimos tu solicitud y te contactaremos en breve para confirmar tu envío.',
            'privacy'   => 'Al enviar aceptas que usemos tus datos únicamente para atender tu solicitud.',
        ],
        'footer' => [
            'about'     => 'Servicio de mensajería y delivery a nivel nacional, en toda Guatemala. Rápido, seguro y siempre cerca de ti.',
            'copyright' => 'Te Lo Llevo. Todos los derechos reservados.',
        ],
        'legal' => [
            'privacy_title' => 'Política de privacidad',
            'privacy' => "En Te Lo Llevo respetamos tu privacidad. Esta política explica qué datos recopilamos y cómo los usamos.\n\nDatos que recopilamos\nCuando completas el formulario de contacto recopilamos tu nombre, teléfono, correo electrónico, direcciones de recogida y entrega y los detalles de tu envío. Estos datos son los mínimos necesarios para prestarte el servicio.\n\nCómo usamos tus datos\nUsamos tu información únicamente para contactarte, confirmar y realizar tu envío, y mejorar nuestra atención. No vendemos ni compartimos tus datos personales con terceros, salvo cuando sea necesario para completar la entrega o cuando la ley lo exija.\n\nConservación y seguridad\nConservamos tus datos solo el tiempo necesario y aplicamos medidas razonables para protegerlos contra accesos no autorizados.\n\nTus derechos\nPuedes solicitar acceso, corrección o eliminación de tus datos escribiéndonos a nuestro correo de contacto.\n\nContacto\nPara cualquier consulta sobre esta política escríbenos al correo indicado en la sección de contacto del sitio.",
            'terms_title' => 'Términos del servicio',
            'terms' => "Al solicitar un servicio de Te Lo Llevo aceptas los siguientes términos.\n\nServicio\nOfrecemos recogida y entrega de documentos, paquetes y pedidos mediante motorizados dentro de nuestra zona de cobertura. Los tiempos de entrega son estimados y pueden variar por tráfico, clima o dirección incompleta.\n\nTarifas\nLas tarifas publicadas son referenciales. El valor final se confirma antes de realizar el servicio.\n\nContenido del envío\nEl cliente declara que el contenido de su envío es legal y está correctamente empacado. No transportamos sustancias ilegales, armas, materiales peligrosos o inflamables, ni animales vivos.\n\nDirecciones y datos de contacto\nEl cliente es responsable de proporcionar direcciones y teléfonos correctos. Si no es posible entregar por datos incorrectos o ausencia del destinatario, se coordinará una nueva entrega, lo cual podría tener un costo adicional.\n\nResponsabilidad\nNos esforzamos por cuidar cada envío. Para artículos de alto valor, consúltanos antes para acordar condiciones especiales.\n\nCambios\nPodemos actualizar estos términos en cualquier momento. La versión vigente es la publicada en este sitio.",
        ],
    ];
}
