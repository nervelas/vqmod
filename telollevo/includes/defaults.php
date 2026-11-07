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
            'meta_title'       => 'Te Lo Llevo | Delivery y envíos en moto en Guatemala',
            'meta_description' => 'Servicio de delivery y envíos en moto. Rápido, seguro y con confirmación por WhatsApp. Cotiza y solicita tu envío en minutos.',
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
            'address'       => 'Ciudad de Guatemala, Guatemala',
            'hours'         => "Lunes a sábado: 7:00 a.m. – 8:00 p.m.\nDomingos y feriados: 8:00 a.m. – 4:00 p.m.",
            'facebook'      => '',
            'instagram'     => '',
            'tiktok'        => '',
            'youtube'       => '',
        ],
        'layout' => [
            'order'   => ['hero', 'stats', 'services', 'how', 'rates', 'why', 'plans', 'coverage', 'testimonials', 'faq', 'cta', 'contact'],
            'enabled' => [
                'hero' => true, 'stats' => true, 'services' => true, 'how' => true, 'rates' => true, 'why' => true,
                'plans' => true, 'coverage' => true, 'testimonials' => false, 'faq' => true, 'cta' => true, 'contact' => true,
            ],
        ],
        'nav' => [
            'cta_label' => 'Pedir ahora',
            'cta_href'  => '#contacto',
            'items' => [
                ['label' => 'Inicio',          'href' => '#inicio'],
                ['label' => 'Servicios',       'href' => '#servicios'],
                ['label' => 'Cómo funciona',   'href' => '#como-funciona'],
                ['label' => 'Tarifas',         'href' => '#tarifas'],
                ['label' => 'Cobertura',       'href' => '#cobertura'],
                ['label' => 'Preguntas',       'href' => '#preguntas'],
                ['label' => 'Contacto',        'href' => '#contacto'],
            ],
        ],
        'hero' => [
            'badge'        => 'Delivery y envíos en Guatemala',
            'title'        => 'Te lo *llevo*, donde estés y cuando lo necesites',
            'subtitle'     => 'Mensajería en moto rápida, segura y con confirmación por WhatsApp. Recogemos y entregamos tus paquetes, documentos, compras y pedidos de puerta a puerta.',
            'cta1_label'   => 'Solicitar un envío',
            'cta1_href'    => '#contacto',
            'cta2_label'   => 'Cotizar mi envío',
            'cta2_href'    => '#tarifas',
            'image'        => 'assets/img/courier.webp',
            'image_alt'    => 'Motorizado de Te Lo Llevo con su caja de entregas',
            'card1_icon'   => 'seal-check',
            'card1_title'  => 'Pedido entregado',
            'card1_text'   => 'Confirmación al instante',
            'card2_icon'   => 'lightning',
            'card2_title'  => 'Entrega express',
            'card2_text'   => 'Mismo día en zona urbana',
            'trust' => [
                ['icon' => 'shield-check',  'text' => 'Envíos protegidos'],
                ['icon' => 'whatsapp-logo', 'text' => 'Aviso por WhatsApp'],
                ['icon' => 'receipt',       'text' => 'Precios claros'],
            ],
        ],
        'stats' => [
            'items' => [
                ['icon' => 'timer',          'prefix' => '',  'number' => 30,  'suffix' => ' min', 'label' => 'Entrega express desde'],
                ['icon' => 'whatsapp-logo',  'prefix' => '',  'number' => 100, 'suffix' => '%',    'label' => 'Seguimiento por WhatsApp'],
                ['icon' => 'calendar-check', 'prefix' => '',  'number' => 7,   'suffix' => ' días', 'label' => 'Servicio de lunes a domingo'],
                ['icon' => 'coins',          'prefix' => 'Q', 'number' => 20,  'suffix' => '',     'label' => 'Tarifa desde por envío'],
            ],
        ],
        'services' => [
            'eyebrow'  => 'Nuestros servicios',
            'title'    => 'Todo lo que necesitas *enviar*, en un solo lugar',
            'subtitle' => 'Desde un documento urgente hasta las entregas diarias de tu negocio. Elige el servicio que mejor se adapta a ti.',
            'cta_label' => 'Solicitar este servicio',
            'items' => [
                ['icon' => 'moped',          'title' => 'Envíos Express',              'tag' => 'Mismo día',      'text' => 'Recogemos y entregamos en el menor tiempo posible. Ideal para urgencias, documentos y pedidos que no pueden esperar.'],
                ['icon' => 'calendar-check', 'title' => 'Envíos Programados',          'tag' => 'A tu horario',   'text' => 'Elige el día y la hora de recogida o entrega. Perfecto para regalos, cumpleaños y entregas planificadas.'],
                ['icon' => 'shopping-bag',   'title' => 'Mandados y Compras',          'tag' => 'Tú pides',       'text' => 'Compramos lo que necesitas y te lo llevamos. Tú nos dices qué y dónde, nosotros nos encargamos del resto.'],
                ['icon' => 'storefront',     'title' => 'Para Negocios y Emprendedores', 'tag' => 'Convenios',    'text' => 'Entregas para tiendas en línea, restaurantes y emprendimientos, con rutas diarias y tarifas especiales por volumen.'],
                ['icon' => 'file-text',      'title' => 'Documentos y Trámites',       'tag' => 'Con confirmación', 'text' => 'Contratos, facturas, cheques y papelería importante entregados en mano, con confirmación de recibido.'],
                ['icon' => 'hand-coins',     'title' => 'Pago Contra Entrega',         'tag' => 'Cobro seguro',   'text' => 'Cobramos al destinatario y te entregamos el dinero. La solución ideal para tus ventas por redes sociales.'],
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
            'note'      => 'Precios referenciales en quetzales. El valor final puede variar según la ruta, el tráfico y las condiciones del envío; siempre lo confirmamos antes de salir.',
            'cta_label' => 'Pedir por WhatsApp',
        ],
        'why' => [
            'eyebrow'  => '¿Por qué Te Lo Llevo?',
            'title'    => 'Un servicio en el que *puedes confiar*',
            'subtitle' => 'Cuidamos cada envío como si fuera nuestro. Por eso nuestros clientes vuelven.',
            'image'    => 'assets/img/courier.webp',
            'image_alt'=> 'Repartidor de Te Lo Llevo en moto',
            'badge_title' => 'Motorizados identificados',
            'badge_text'  => 'Uniforme, caja y logo oficial',
            'items' => [
                ['icon' => 'lightning',           'title' => 'Rápido de verdad',        'text' => 'Rutas optimizadas y motorizados cerca de ti para reducir al máximo el tiempo de entrega.'],
                ['icon' => 'shield-check',        'title' => 'Seguro y protegido',      'text' => 'Cajas cerradas que cuidan tus paquetes del polvo, la lluvia y los golpes.'],
                ['icon' => 'identification-card', 'title' => 'Personal identificado',   'text' => 'Repartidores uniformados, capacitados y con excelente trato al cliente.'],
                ['icon' => 'chats',               'title' => 'Comunicación constante',  'text' => 'Te avisamos por WhatsApp cuando recogemos y cuando entregamos tu paquete.'],
                ['icon' => 'receipt',             'title' => 'Precios claros',          'text' => 'Sabes cuánto pagas antes de enviar. Sin sorpresas ni cargos ocultos.'],
                ['icon' => 'heart',               'title' => 'Trato cercano',           'text' => 'Somos un equipo local que conoce la ciudad y está siempre a un mensaje de distancia.'],
            ],
        ],
        'plans' => [
            'eyebrow'  => 'Planes',
            'title'    => 'Una solución para *cada necesidad*',
            'subtitle' => 'Envía cuando quieras o ahorra con un plan para tu negocio.',
            'items' => [
                ['icon' => 'package',    'name' => 'Envío individual', 'price' => 'Desde Q20', 'period' => 'por envío',
                 'desc' => 'Para cuando lo necesites, sin compromisos ni mensualidades.',
                 'features' => "Recogida y entrega puerta a puerta\nConfirmación por WhatsApp\nPago en efectivo o transferencia\nCotización inmediata",
                 'featured' => false, 'cta_label' => 'Pedir ahora', 'cta_href' => '#contacto'],
                ['icon' => 'rocket',     'name' => 'Emprendedor', 'price' => 'Tarifa especial', 'period' => 'según tu volumen',
                 'desc' => 'Para tiendas en línea y ventas por redes sociales que envían con frecuencia.',
                 'features' => "Descuento por volumen de envíos\nRutas programadas\nPago contra entrega\nAtención prioritaria por WhatsApp",
                 'featured' => true, 'cta_label' => 'Quiero esta tarifa', 'cta_href' => '#contacto'],
                ['icon' => 'buildings',  'name' => 'Empresas', 'price' => 'A tu medida', 'period' => 'convenio mensual',
                 'desc' => 'Entregas diarias para restaurantes, comercios y empresas con rutas fijas.',
                 'features' => "Convenio con facturación mensual\nEjecutivo de cuenta asignado\nReporte de entregas\nRutas fijas y flexibles",
                 'featured' => false, 'cta_label' => 'Solicitar propuesta', 'cta_href' => '#contacto'],
            ],
        ],
        'coverage' => [
            'eyebrow'  => 'Cobertura',
            'title'    => 'Llegamos a toda la *ciudad y sus alrededores*',
            'subtitle' => 'Cubrimos la capital y los municipios cercanos. ¿Tu destino no está en la lista? Escríbenos y lo cotizamos.',
            'zones' => [
                ['name' => 'Ciudad de Guatemala (zonas 1 a 25)'],
                ['name' => 'Mixco'],
                ['name' => 'Villa Nueva'],
                ['name' => 'San Miguel Petapa'],
                ['name' => 'Santa Catarina Pinula'],
                ['name' => 'San José Pinula'],
                ['name' => 'Fraijanes'],
                ['name' => 'Villa Canales'],
                ['name' => 'Chinautla'],
                ['name' => 'Otras rutas con cotización previa'],
            ],
            'note'      => 'Horario de atención: lunes a sábado de 7:00 a.m. a 8:00 p.m.',
            'cta_label' => 'Consultar mi zona',
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
            'subtitle' => 'Lo que más nos preguntan antes de enviar por primera vez.',
            'items' => [
                ['q' => '¿Qué tipo de cosas pueden enviar?', 'a' => 'Documentos, paquetes pequeños y medianos, compras, regalos, pedidos de comida y productos de emprendimientos. Si tienes dudas sobre el tamaño o el peso, escríbenos y te confirmamos.'],
                ['q' => '¿Qué artículos no se pueden transportar?', 'a' => 'Por seguridad no transportamos sustancias ilegales, armas, materiales inflamables o peligrosos, grandes sumas de dinero en efectivo ni animales vivos.'],
                ['q' => '¿Cuánto tarda una entrega?', 'a' => 'Las entregas express en zona urbana pueden realizarse en menos de una hora, según la distancia y el tráfico. Te damos un tiempo estimado al confirmar tu pedido.'],
                ['q' => '¿Cómo se calcula el precio?', 'a' => 'Según la distancia entre el punto de recogida y el de entrega, el tamaño del paquete y la urgencia. Usa nuestro cotizador para obtener un estimado y confirma el valor final por WhatsApp.'],
                ['q' => '¿Cómo puedo pagar?', 'a' => 'Aceptamos efectivo y transferencia bancaria. También ofrecemos pago contra entrega, donde cobramos al destinatario por ti.'],
                ['q' => '¿Puedo programar un envío para otro día?', 'a' => 'Sí. Indícanos la fecha y la hora en el formulario o por WhatsApp y dejamos tu envío programado.'],
                ['q' => '¿Cómo sé que mi paquete fue entregado?', 'a' => 'Te enviamos una confirmación por WhatsApp apenas el paquete es entregado al destinatario.'],
                ['q' => '¿Qué pasa si el destinatario no está?', 'a' => 'Nos comunicamos contigo y con el destinatario. Si no es posible entregar en ese momento, coordinamos una nueva entrega o la devolución del paquete.'],
                ['q' => '¿Atienden a empresas y emprendedores?', 'a' => 'Claro. Tenemos tarifas especiales y rutas programadas para negocios que envían con frecuencia. Escríbenos y armamos una propuesta a tu medida.'],
            ],
        ],
        'cta' => [
            'title'          => '¿Tienes algo que enviar hoy? *Nosotros lo llevamos*',
            'text'           => 'Escríbenos por WhatsApp o llámanos y un motorizado pasa por tu paquete.',
            'whatsapp_label' => 'Escribir por WhatsApp',
            'call_label'     => 'Llamar ahora',
        ],
        'form' => [
            'eyebrow'   => 'Contacto',
            'title'     => 'Solicita tu *servicio*',
            'subtitle'  => 'Completa el formulario y te contactamos en minutos para confirmar tu envío. También puedes escribirnos directo por WhatsApp.',
            'services'  => "Envío express (mismo día)\nEnvío programado\nMandado o compra\nDocumentos y trámites\nPago contra entrega\nServicio para mi negocio\nOtro",
            'sizes'     => "Sobre o documento\nPaquete pequeño (hasta 3 kg)\nPaquete mediano (hasta 8 kg)\nPaquete grande (hasta 15 kg)\nNo estoy seguro",
            'button'    => 'Enviar solicitud',
            'success'   => '¡Gracias! Recibimos tu solicitud y te contactaremos en breve para confirmar tu envío.',
            'privacy'   => 'Al enviar aceptas que usemos tus datos únicamente para atender tu solicitud.',
        ],
        'footer' => [
            'about'     => 'Servicio de mensajería y delivery en moto. Rápido, seguro y siempre cerca de ti.',
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
