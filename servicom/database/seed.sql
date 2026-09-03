-- =============================================================================
-- Servicom — Contenido inicial
-- Usuario administrador por defecto:  admin  /  Servicom2026*
-- IMPORTANTE: cambie la contrasena desde el panel al iniciar sesion.
-- =============================================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------- Usuario -------
INSERT INTO `users` (`name`, `username`, `email`, `password`, `role`, `status`, `created_at`) VALUES
('Administrador', 'admin', 'info@servicom.gt', '$2y$12$6JGCYtQZr76KFvfk0HivheIltk0lrlKT22Es5teIe8/bBXE97YYzK', 'admin', 1, '2026-01-01 08:00:00');

-- ------------------------------------------------------------ Ajustes --------
INSERT INTO `settings` (`key`, `value`, `group_name`) VALUES
('site_name', 'Servicom', 'general'),
('site_tagline', 'Diseño de Páginas Web en Guatemala', 'general'),
('logo', 'assets/img/logo.svg', 'general'),
('logo_light', 'assets/img/logo-light.svg', 'general'),
('favicon', 'assets/img/favicon.svg', 'general'),
('phone', '+502 3204 0756', 'contacto'),
('phone_alt', '', 'contacto'),
('whatsapp', '+502 3204 0756', 'contacto'),
('whatsapp_message', 'Hola Servicom, quiero información sobre el diseño de mi página web.', 'contacto'),
('email', 'info@servicom.gt', 'contacto'),
('address_city', 'Ciudad de Guatemala', 'contacto'),
('address_region', 'Guatemala', 'contacto'),
('address_line', 'Ciudad de Guatemala, Guatemala', 'contacto'),
('schedule', 'Lunes a viernes de 8:00 a 18:00 h', 'contacto'),
('map_embed', '', 'contacto'),
('social_facebook', 'https://www.facebook.com/SERVICOM.GT/', 'redes'),
('social_instagram', '', 'redes'),
('social_linkedin', '', 'redes'),
('social_youtube', '', 'redes'),
('social_tiktok', '', 'redes'),
('social_x', '', 'redes'),
('theme_active', 'obsidiana', 'tema'),
('theme_allow_visitor_switch', '1', 'tema'),
('fx_cursor', '1', 'tema'),
('fx_grain', '1', 'tema'),
('fx_parallax', '1', 'tema'),
('fx_reveal', '1', 'tema'),
('fx_preloader', '1', 'tema'),
('slider_autoplay', '1', 'tema'),
('slider_interval', '6500', 'tema'),
('seo_default_title', 'Diseño de Páginas Web en Guatemala | Servicom', 'seo'),
('seo_default_description', 'Diseño de páginas web en Guatemala con más de 16 años de experiencia. Sitios claros y adaptables, tiendas virtuales y soporte directo.', 'seo'),
('seo_default_keywords', 'diseño de páginas web Guatemala, páginas web Guatemala, tiendas virtuales Guatemala, diseño web profesional, desarrollo web Guatemala, Servicom', 'seo'),
('seo_separator', '|', 'seo'),
('seo_og_image', 'assets/img/og-default.svg', 'seo'),
('seo_robots', 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1', 'seo'),
('seo_geo_region', 'GT', 'seo'),
('schema_type', 'ProfessionalService', 'seo'),
('schema_price_range', '$$', 'seo'),
('schema_hours', 'Mo-Fr 08:00-18:00', 'seo'),
('schema_lat', '', 'seo'),
('schema_lng', '', 'seo'),
('google_analytics', '', 'seo'),
('google_verification', '', 'seo'),
('footer_text', 'Servicom diseña y desarrolla páginas web profesionales y tiendas virtuales en Guatemala, con atención directa y acompañamiento en cada etapa del proyecto.', 'general'),
('copyright', 'Servicom. Todos los derechos reservados.', 'general'),
('cta_bar_text', '¿Listo para tener una página web que sí venda?', 'general'),
('cta_bar_btn', 'Solicitar cotización', 'general'),
('maintenance', '0', 'general'),
('form_success', '¡Gracias! Recibimos su mensaje y le responderemos a la brevedad.', 'general'),
('form_error', 'Revise los campos marcados e intente nuevamente.', 'general');

-- -------------------------------------------------------------- Temas -------
INSERT INTO `themes` (`theme_key`, `name`, `mode`, `description`, `palette`, `fonts`, `sort_order`) VALUES
('obsidiana', 'Obsidiana', 'dark', 'Negro profundo con turquesa electrico. Tecnologico, nitido y de alto contraste.', '{"bg":"#05070a","bg-alt":"#090d13","surface":"#0e131b","surface-2":"#141b25","text":"#eef3f8","muted":"#8d9bad","border":"rgba(255,255,255,.09)","accent":"#22e5c7","accent-2":"#7c5cff","accent-ink":"#03110e","glow":"rgba(34,229,199,.32)","grain":".05","radius":"20px","display-tracking":"-.035em"}', '{"display":"''Space Grotesk''","body":"''Inter''","display_fallback":"system-ui,-apple-system,''Segoe UI'',sans-serif","body_fallback":"system-ui,-apple-system,''Segoe UI'',sans-serif","google":"Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700"}', 1),
('medianoche', 'Medianoche', 'dark', 'Azul noche con oro suave. Elegancia editorial de alta gama.', '{"bg":"#070c1b","bg-alt":"#0a1124","surface":"#0e1730","surface-2":"#131f3d","text":"#f2f0e9","muted":"#97a3c0","border":"rgba(232,200,122,.16)","accent":"#e8c87a","accent-2":"#6f8fd6","accent-ink":"#1a1305","glow":"rgba(232,200,122,.28)","grain":".045","radius":"6px","display-tracking":"-.015em"}', '{"display":"''Playfair Display''","body":"''Jost''","display_fallback":"Georgia,''Times New Roman'',serif","body_fallback":"system-ui,-apple-system,''Segoe UI'',sans-serif","google":"Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600"}', 2),
('carbon', 'Carbon', 'dark', 'Grafito con lima neon. Brutalismo premium, tipografia de gran peso.', '{"bg":"#0c0c0d","bg-alt":"#111113","surface":"#161618","surface-2":"#1e1e21","text":"#f5f5f2","muted":"#9b9b9b","border":"rgba(255,255,255,.12)","accent":"#c8f31d","accent-2":"#ff5c35","accent-ink":"#11140a","glow":"rgba(200,243,29,.3)","grain":".07","radius":"2px","display-tracking":"-.045em"}', '{"display":"''Archivo''","body":"''Inter Tight''","display_fallback":"''Arial Black'',system-ui,sans-serif","body_fallback":"system-ui,-apple-system,''Segoe UI'',sans-serif","google":"Archivo:wght@500;600;700;800;900&family=Inter+Tight:wght@300;400;500;600"}', 3),
('nebulosa', 'Nebulosa', 'dark', 'Violeta profundo con magenta y cian. Futurista, luminoso y envolvente.', '{"bg":"#0b0616","bg-alt":"#100a20","surface":"#16102b","surface-2":"#1e1738","text":"#f4eefc","muted":"#a294c2","border":"rgba(255,255,255,.1)","accent":"#ff5fa2","accent-2":"#46e0ff","accent-ink":"#22030f","glow":"rgba(255,95,162,.32)","grain":".05","radius":"28px","display-tracking":"-.03em"}', '{"display":"''Outfit''","body":"''Manrope''","display_fallback":"system-ui,-apple-system,''Segoe UI'',sans-serif","body_fallback":"system-ui,-apple-system,''Segoe UI'',sans-serif","google":"Outfit:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700"}', 4),
('alabastro', 'Alabastro', 'light', 'Blanco calido con indigo intenso. Precision suiza y aire limpio.', '{"bg":"#f7f7f5","bg-alt":"#efefec","surface":"#ffffff","surface-2":"#f2f2ef","text":"#111214","muted":"#5e6470","border":"rgba(17,18,20,.1)","accent":"#3b2fe0","accent-2":"#ff7a2f","accent-ink":"#ffffff","glow":"rgba(59,47,224,.22)","grain":".035","radius":"16px","display-tracking":"-.03em"}', '{"display":"''Sora''","body":"''Inter''","display_fallback":"system-ui,-apple-system,''Segoe UI'',sans-serif","body_fallback":"system-ui,-apple-system,''Segoe UI'',sans-serif","google":"Sora:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700"}', 5),
('arena', 'Arena', 'light', 'Crema arena con terracota. Calido, artesanal y editorial.', '{"bg":"#faf4ec","bg-alt":"#f2e9dc","surface":"#fffaf3","surface-2":"#f6ede0","text":"#2b1d14","muted":"#7a6555","border":"rgba(43,29,20,.14)","accent":"#a84522","accent-2":"#1a6350","accent-ink":"#fff6ef","glow":"rgba(168,69,34,.2)","grain":".06","radius":"14px","display-tracking":"-.02em"}', '{"display":"''Fraunces''","body":"''Karla''","display_fallback":"Georgia,''Times New Roman'',serif","body_fallback":"system-ui,-apple-system,''Segoe UI'',sans-serif","google":"Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400&family=Karla:wght@300;400;500;600;700"}', 6),
('menta', 'Menta', 'light', 'Verde menta con esmeralda. Fresco, confiable y corporativo.', '{"bg":"#f2f7f4","bg-alt":"#e7f0eb","surface":"#ffffff","surface-2":"#edf5f0","text":"#0f2019","muted":"#5b7268","border":"rgba(15,32,25,.11)","accent":"#0e7c5a","accent-2":"#d8a02a","accent-ink":"#ffffff","glow":"rgba(14,124,90,.22)","grain":".03","radius":"22px","display-tracking":"-.015em"}', '{"display":"''DM Serif Display''","body":"''DM Sans''","display_fallback":"Georgia,''Times New Roman'',serif","body_fallback":"system-ui,-apple-system,''Segoe UI'',sans-serif","google":"DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,700"}', 7),
('perla', 'Perla', 'light', 'Perla fria con azul real y coral. Moderno, financiero y luminoso.', '{"bg":"#f4f6fb","bg-alt":"#e9edf7","surface":"#ffffff","surface-2":"#eef1f9","text":"#0d1220","muted":"#5a6480","border":"rgba(13,18,32,.1)","accent":"#1f4fe0","accent-2":"#ff6b4a","accent-ink":"#ffffff","glow":"rgba(31,79,224,.22)","grain":".03","radius":"10px","display-tracking":"-.04em"}', '{"display":"''Bricolage Grotesque''","body":"''Public Sans''","display_fallback":"system-ui,-apple-system,''Segoe UI'',sans-serif","body_fallback":"system-ui,-apple-system,''Segoe UI'',sans-serif","google":"Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,700;12..96,800&family=Public+Sans:wght@300;400;500;600;700"}', 8);

-- ------------------------------------------------ Menu de navegacion ---------
INSERT INTO `menu_items` (`label`, `url`, `icon`, `location`, `target`, `is_button`, `status`, `sort_order`) VALUES
('Inicio', '/', 'inicio', 'header', '_self', 0, 1, 1),
('Servicios', '/servicios/', 'servicios', 'header', '_self', 0, 1, 2),
('Nosotros', '/nosotros/', 'nosotros', 'header', '_self', 0, 1, 3),
('Portafolio', '/portafolio/', 'portafolio', 'header', '_self', 0, 1, 4),
('Actualidad Web', '/actualidad-web/', 'blog', 'header', '_self', 0, 1, 5),
('Contacto', '/contacto/', 'contacto', 'header', '_self', 0, 1, 6),
('Cotizar ahora', '/contacto/', 'cotizar', 'header', '_self', 1, 1, 7),
('Inicio', '/', 'inicio', 'footer', '_self', 0, 1, 1),
('Nosotros', '/nosotros/', 'nosotros', 'footer', '_self', 0, 1, 2),
('Portafolio', '/portafolio/', 'portafolio', 'footer', '_self', 0, 1, 3),
('Planes', '/#seccion-planes', 'planes', 'footer', '_self', 0, 1, 4),
('Actualidad Web', '/actualidad-web/', 'blog', 'footer', '_self', 0, 1, 5),
('Contacto', '/contacto/', 'contacto', 'footer', '_self', 0, 1, 6);

-- -------------------------------------------------- Slider principal ---------
INSERT INTO `slides` (`eyebrow`, `title`, `highlight`, `subtitle`, `image`, `image_alt`, `align`, `btn1_text`, `btn1_url`, `btn1_icon`, `btn2_text`, `btn2_url`, `btn2_icon`, `status`, `sort_order`) VALUES
('Más de 16 años diseñando en Guatemala', 'Diseño de páginas web que atraen', 'clientes reales', 'Sitios profesionales, claros y rápidos, que se adaptan a celulares, tablets y computadoras. Con formulario de contacto y enlace directo a WhatsApp para que sus clientes le escriban en un clic.', 'assets/img/slide-1.svg', 'Diseño de páginas web profesionales en Guatemala', 'left', 'Solicitar cotización', '/contacto/', 'cotizar', 'Ver servicios', '/servicios/', 'servicios', 1, 1),
('Comercio electrónico', 'Tiendas virtuales exclusivas para', 'su marca', 'Plataformas de comercio electrónico totalmente personalizables: diseño atractivo, métodos de pago seguros, opciones de envío y una administración sencilla de su inventario y sus pedidos.', 'assets/img/slide-2.svg', 'Diseño de tiendas virtuales en Guatemala', 'left', 'Quiero mi tienda', '/servicios/tiendas-virtuales-guatemala/', 'tienda', 'Hablar por WhatsApp', 'whatsapp', 'whatsapp', 1, 2),
('Acompañamiento completo', 'Dominio, hosting, correos y', 'redes sociales', 'Le asesoramos para elegir dominio y hosting, o trabajamos con los servicios que ya tenga. También gestionamos su presencia en Facebook e Instagram con contenido y campañas.', 'assets/img/slide-3.svg', 'Servicios de dominio, hosting, correos corporativos y redes sociales', 'left', 'Ver todos los servicios', '/servicios/', 'servicios', 'Llamar ahora', 'tel', 'telefono', 1, 3);

-- ------------------------------------------------------------ Servicios ------
INSERT INTO `services` (`slug`, `title`, `short_title`, `icon`, `excerpt`, `body`, `features`, `image`, `image_alt`, `price_text`, `btn_text`, `meta_title`, `meta_description`, `meta_keywords`, `featured`, `status`, `sort_order`) VALUES
('paginas-web-guatemala', 'Páginas web profesionales', 'Páginas web', 'web',
 'Diseño y creación de páginas web para todo tipo de empresas y negocios, con una presentación clara y profesional que se entiende rápido.',
 'Diseñamos y desarrollamos páginas web para todo tipo de empresas y negocios en Guatemala. El servicio incluye diseño visual profesional, una estructura de secciones claras, adaptación a celulares, tablets y computadoras, formulario de contacto y enlace a WhatsApp para facilitar la comunicación con sus clientes.

Recibimos la información básica de su negocio —servicios, datos de contacto, logotipo y contenido— y organizamos la página por secciones claras, para que su servicio se entienda rápido y el cliente pueda contactarle fácil.

El tiempo de desarrollo varía según el proyecto, pero normalmente se entrega en pocos días una vez recibida toda la información.',
 'Diseño visual profesional
Estructura de secciones claras
Adaptación a celular, tablet y computadora
Formulario de contacto
Enlace directo a WhatsApp
Entrega en pocos días',
 'assets/img/servicio-web.svg', 'Diseño de páginas web profesionales en Guatemala', 'Planes accesibles según su negocio', 'Cotizar mi página web',
 'Diseño de Páginas Web en Guatemala', 'Diseño de páginas web profesionales en Guatemala: secciones claras, adaptación a celular y computadora, formulario de contacto y enlace a WhatsApp. Más de 16 años de experiencia.',
 'diseño de páginas web Guatemala, páginas web profesionales, desarrollo web Guatemala, diseño web empresas', 1, 1, 1),

('tiendas-virtuales-guatemala', 'Tiendas virtuales', 'Tiendas virtuales', 'tienda',
 'Creamos tiendas virtuales que no solo son visualmente atractivas, sino también altamente funcionales, para que destaque en el comercio electrónico.',
 'Nos enfocamos en crear tiendas virtuales que combinan un diseño atractivo con funcionalidad avanzada, para ofrecer una experiencia de compra excepcional que facilite la navegación y la compra en línea.

Cada tienda virtual es completamente personalizable: puede destacar frente a la competencia con un diseño exclusivo y funcionalidades adaptadas a su modelo de negocio, reflejando la calidad y el profesionalismo de su marca.

Nos encargamos de la integración de métodos de pago seguros y de la configuración de opciones de envío y devoluciones. Además, la plataforma está diseñada para administrarse con facilidad: actualice su inventario, procese pedidos y analice el rendimiento de sus ventas sin complicaciones.',
 'Diseño exclusivo y personalizable
Métodos de pago seguros
Configuración de envíos y devoluciones
Administración sencilla del inventario
Procesamiento de pedidos
Análisis del rendimiento de ventas',
 'assets/img/servicio-tienda.svg', 'Diseño de tiendas virtuales y comercio electrónico en Guatemala', 'Según catálogo y funcionalidades', 'Cotizar mi tienda virtual',
 'Tiendas Virtuales en Guatemala | Comercio Electrónico', 'Diseño de tiendas virtuales en Guatemala: diseño exclusivo, pagos seguros, envíos, administración de inventario y análisis de ventas. Plataformas personalizables para su negocio.',
 'tiendas virtuales Guatemala, tienda en línea Guatemala, comercio electrónico Guatemala, ecommerce Guatemala', 1, 1, 2),

('dominio-hosting-correos', 'Dominio, hosting y correos corporativos', 'Dominio y hosting', 'correo',
 'Le asesoramos para elegir dominio y hosting, o trabajamos con los servicios que ya tenga, incluidos sus correos corporativos.',
 'Podemos asesorarle para elegir el dominio y el hosting adecuados para su proyecto, o trabajar directamente con los servicios que ya tenga contratados. También le acompañamos con dominios nacionales y con la gestión de su dominio.

Su página web puede integrarse con cuentas de correo corporativas con el nombre de su empresa, para que cada mensaje que envíe refuerce su imagen profesional.',
 'Asesoría para elegir dominio y hosting
Trabajamos con los servicios que ya tenga
Apoyo con dominios nacionales
Gestión de su dominio
Cuentas de correo con su propio dominio',
 'assets/img/servicio-hosting.svg', 'Asesoría en dominio, hosting y correos corporativos en Guatemala', 'Asesoría incluida en su proyecto', 'Consultar disponibilidad',
 'Dominio, Hosting y Correos Corporativos en Guatemala', 'Asesoría en dominio y hosting en Guatemala, apoyo con dominios nacionales y cuentas de correo corporativo con el nombre de su empresa.',
 'dominio Guatemala, hosting Guatemala, correos corporativos Guatemala, dominio .gt', 1, 1, 3),

('redes-sociales', 'Manejo de redes sociales', 'Redes sociales', 'redes',
 'Promovemos su negocio en Facebook e Instagram con contenido que conecta con sus seguidores y campañas publicitarias.',
 'Ofrecemos servicios de mercadeo en redes sociales para promover su negocio en plataformas como Facebook e Instagram.

Creamos contenido atractivo que conecta con sus seguidores y administramos campañas publicitarias en Facebook e Instagram para mejorar su presencia y llegar a más clientes potenciales.',
 'Presencia en Facebook e Instagram
Contenido atractivo para sus seguidores
Administración de campañas publicitarias
Mejora de la presencia en redes sociales',
 'assets/img/servicio-redes.svg', 'Manejo de redes sociales Facebook e Instagram en Guatemala', 'Según alcance de la campaña', 'Cotizar manejo de redes',
 'Manejo de Redes Sociales en Guatemala | Facebook e Instagram', 'Manejo de redes sociales en Guatemala: contenido para Facebook e Instagram y administración de campañas publicitarias para mejorar la presencia de su negocio.',
 'manejo de redes sociales Guatemala, Facebook ads Guatemala, Instagram Guatemala, community manager Guatemala', 1, 1, 4);

-- --------------------------------------------- Bloques de contenido ----------
INSERT INTO `blocks` (`key`, `area`, `label`, `eyebrow`, `title`, `subtitle`, `body`, `image`, `icon`, `btn_text`, `btn_url`, `btn2_text`, `btn2_url`, `extra`, `status`, `sort_order`) VALUES
('marquee', 'inicio', 'Cinta animada', NULL, NULL, NULL, 'Páginas web profesionales · Tiendas virtuales · Dominio y hosting · Correos corporativos · Redes sociales · Diseño adaptable · Soporte directo', NULL, 'chispa', NULL, NULL, NULL, NULL, NULL, 1, 1),

('servicios', 'inicio', 'Sección Servicios', 'Lo que hacemos', 'Servicios pensados para que su negocio se vea y se venda mejor', 'Diseñamos la presencia digital completa de su empresa: la página que la presenta, la tienda que vende y las redes que la mantienen viva.', NULL, NULL, 'servicios', 'Ver todos los servicios', '/servicios/', NULL, NULL, NULL, 1, 2),

('nosotros', 'inicio', 'Sección Nosotros', 'Quiénes somos', 'Más de 16 años diseñando páginas web en Guatemala', 'Atención directa con quien desarrolla su sitio, sin intermediarios.', 'Servicom diseña páginas web en Guatemala que atraen clientes reales. Trabajamos con empresas y negocios de todo tipo, con una idea muy simple: que su servicio se entienda rápido y que el cliente pueda contactarle fácil.

Recibimos la información básica de su negocio —servicios, datos de contacto, logotipo y contenido— y organizamos la página por secciones claras. Todas nuestras páginas web se adaptan correctamente a celulares, tablets y computadoras.

El tiempo de desarrollo varía según el proyecto, pero normalmente entregamos en pocos días una vez recibida toda la información.', 'assets/img/nosotros.svg', 'nosotros', 'Conozca más', '/nosotros/', 'Solicitar cotización', '/contacto/', NULL, 1, 3),

('stats', 'inicio', 'Sección Indicadores', 'En números', 'Experiencia que se nota en cada proyecto', NULL, NULL, NULL, 'grafica', NULL, NULL, NULL, NULL, NULL, 1, 4),

('proceso', 'inicio', 'Sección Proceso', 'Cómo trabajamos', 'Un proceso simple, claro y sin sorpresas', 'Desde la cotización por escrito hasta la publicación de su sitio, usted siempre sabe en qué etapa va su proyecto.', NULL, NULL, 'engranaje', NULL, NULL, NULL, NULL, NULL, 1, 5),

('portafolio', 'inicio', 'Sección Portafolio', 'Portafolio', 'Sitios para negocios reales de Guatemala', 'Trabajamos con empresas, comercios y profesionales de distintos sectores. Cada proyecto se diseña a la medida del negocio.', NULL, NULL, 'portafolio', 'Ver portafolio', '/portafolio/', NULL, NULL, NULL, 1, 6),

('testimonios', 'inicio', 'Sección Testimonios', 'Testimonios', 'Lo que dicen nuestros clientes', 'Agregue aquí los comentarios reales de sus clientes desde el panel de administración.', NULL, NULL, 'comilla', NULL, NULL, NULL, NULL, NULL, 1, 7),

('planes', 'inicio', 'Sección Planes', 'Planes', 'Planes accesibles según las necesidades de cada negocio', 'El costo depende del tipo de sitio y de la cantidad de secciones. Solicite su cotización por escrito, sin compromiso.', NULL, NULL, 'planes', 'Solicitar cotización por escrito', '/contacto/', NULL, NULL, NULL, 1, 8),

('faq', 'inicio', 'Sección Preguntas frecuentes', 'Preguntas frecuentes', 'Resolvemos las dudas más comunes', 'Si su pregunta no está aquí, escríbanos por WhatsApp y con gusto le respondemos.', NULL, NULL, 'documento', NULL, NULL, NULL, NULL, NULL, 1, 9),

('blog', 'inicio', 'Sección Actualidad Web', 'Actualidad Web', 'Ideas y novedades sobre diseño web', 'Contenido útil para entender cómo una buena página web hace crecer su negocio.', NULL, NULL, 'blog', 'Ver todas las publicaciones', '/actualidad-web/', NULL, NULL, NULL, 1, 10),

('cta', 'inicio', 'Sección Llamado final', 'Empecemos', 'Su próxima página web puede estar lista en pocos días', 'Cuéntenos sobre su negocio y le enviamos una cotización por escrito, sin compromiso.', NULL, 'assets/img/cta.svg', 'cohete', 'Solicitar cotización', '/contacto/', 'Escribir por WhatsApp', 'whatsapp', NULL, 1, 11),

('contacto', 'contacto', 'Sección Contacto', 'Hablemos', 'Solicite su cotización por escrito', 'Complete el formulario y le responderemos con una propuesta clara. También puede llamarnos o escribirnos por WhatsApp.', NULL, NULL, 'contacto', NULL, NULL, NULL, NULL, NULL, 1, 12);

-- ---------------------------------------------------------- Indicadores ------
INSERT INTO `stats` (`value`, `prefix`, `suffix`, `label`, `icon`, `status`, `sort_order`) VALUES
('16', NULL, '+', 'Años diseñando páginas web en Guatemala', 'reloj', 1, 1),
('100', NULL, '%', 'Sitios adaptables a celular, tablet y computadora', 'movil', 1, 2),
('4', NULL, NULL, 'Servicios digitales para hacer crecer su negocio', 'servicios', 1, 3),
('1', NULL, ':1', 'Atención directa con quien desarrolla su sitio', 'usuarios', 1, 4);

-- ------------------------------------------------------------- Proceso -------
INSERT INTO `process_steps` (`title`, `body`, `icon`, `status`, `sort_order`) VALUES
('Cotización por escrito', 'Nos cuenta qué necesita y le enviamos una cotización clara por escrito, según el tipo de sitio y la cantidad de secciones.', 'cotizar', 1, 1),
('Recopilación de información', 'Recibimos la información básica de su negocio: servicios, datos de contacto, logotipo y contenido.', 'documento', 1, 2),
('Diseño y estructura', 'Organizamos la página por secciones claras, con diseño visual profesional y adaptación a todos los dispositivos.', 'diseno', 1, 3),
('Revisión y ajustes', 'Le mostramos el avance y aplicamos los ajustes necesarios hasta que el sitio refleje lo que su negocio necesita.', 'engranaje', 1, 4),
('Publicación y entrega', 'Publicamos su sitio con formulario de contacto y enlace a WhatsApp. Normalmente se entrega en pocos días.', 'cohete', 1, 5);

-- --------------------------------------------------- Preguntas frecuentes ----
INSERT INTO `faqs` (`question`, `answer`, `status`, `sort_order`) VALUES
('¿Cuánto cuesta una página web en Guatemala?', 'El costo depende del tipo de sitio y de la cantidad de secciones. Ofrecemos planes accesibles según las necesidades de cada negocio. Solicite su cotización por escrito y le enviamos una propuesta clara, sin compromiso.', 1, 1),
('¿Cuánto tiempo toma desarrollar mi sitio?', 'El tiempo de desarrollo varía según el proyecto, pero normalmente se entrega en pocos días una vez recibida toda la información.', 1, 2),
('¿Mi página se verá bien en celulares?', 'Sí. Todas nuestras páginas web se adaptan correctamente a celulares, tablets y computadoras.', 1, 3),
('¿Qué información necesitan de mi negocio?', 'La información básica: los servicios que ofrece, sus datos de contacto, el logotipo y el contenido que quiera mostrar. Con eso organizamos la página por secciones claras.', 1, 4),
('¿Me ayudan con el dominio y el hosting?', 'Sí. Podemos asesorarle para elegir dominio y hosting, o trabajar con los servicios que ya tenga contratados. También le apoyamos con dominios nacionales.', 1, 5),
('¿La página incluye formulario de contacto y WhatsApp?', 'Sí. El servicio incluye formulario de contacto y enlace a WhatsApp para facilitar la comunicación con sus clientes.', 1, 6),
('¿También hacen tiendas en línea?', 'Sí. Creamos tiendas virtuales completamente personalizables, con métodos de pago seguros, configuración de envíos y devoluciones, y administración sencilla de inventario y pedidos.', 1, 7),
('¿Manejan redes sociales?', 'Sí. Promovemos su negocio en Facebook e Instagram con contenido atractivo y administración de campañas publicitarias.', 1, 8);

-- --------------------------------------------------------------- Planes ------
INSERT INTO `plans` (`name`, `tagline`, `price_text`, `features`, `btn_text`, `btn_url`, `featured`, `icon`, `status`, `sort_order`) VALUES
('Página web profesional', 'Para negocios que necesitan presentarse en internet con claridad.', 'Cotización a la medida',
'Diseño visual profesional
Secciones claras según su servicio
Adaptable a celular, tablet y PC
Formulario de contacto
Enlace directo a WhatsApp
Entrega en pocos días', 'Solicitar cotización', '/contacto/', 0, 'web', 1, 1),
('Tienda virtual', 'Para vender en línea con una plataforma exclusiva y fácil de administrar.', 'Según catálogo y funciones',
'Todo lo del plan anterior
Diseño exclusivo y personalizable
Métodos de pago seguros
Envíos y devoluciones configurados
Administración de inventario
Análisis de rendimiento de ventas', 'Cotizar mi tienda', '/contacto/', 1, 'tienda', 1, 2),
('Presencia digital completa', 'Su sitio, su dominio y sus redes trabajando juntos.', 'Plan combinado',
'Página web o tienda virtual
Asesoría de dominio y hosting
Correos con su propio dominio
Contenido para Facebook e Instagram
Administración de campañas
Acompañamiento directo', 'Hablar con Servicom', '/contacto/', 0, 'infinito', 1, 3);

-- ---------------------------------------------------------- Portafolio -------
-- Tarjetas por tipo de proyecto. Reemplácelas desde el panel por sus proyectos
-- reales (imagen, nombre del cliente y enlace) cuando lo desee.
INSERT INTO `projects` (`title`, `category`, `description`, `image`, `image_alt`, `url`, `status`, `sort_order`) VALUES
('Sitio corporativo', 'Empresas y servicios', 'Presentación clara de la empresa, sus servicios y sus datos de contacto, con formulario y enlace a WhatsApp.', 'assets/img/proyecto-1.svg', 'Ejemplo de sitio web corporativo diseñado por Servicom', '', 1, 1),
('Tienda en línea', 'Comercio electrónico', 'Catálogo de productos, pagos seguros, envíos configurados y administración sencilla del inventario.', 'assets/img/proyecto-2.svg', 'Ejemplo de tienda virtual diseñada por Servicom', '', 1, 2),
('Sitio de profesionales', 'Consultorios y despachos', 'Perfil profesional, servicios, horarios y agenda de citas por WhatsApp en una estructura simple.', 'assets/img/proyecto-3.svg', 'Ejemplo de sitio web para profesionales diseñado por Servicom', '', 1, 3),
('Catálogo de productos', 'Distribuidores y ferreterías', 'Listado de productos por categoría con fichas claras y solicitud de cotización directa.', 'assets/img/proyecto-4.svg', 'Ejemplo de catálogo de productos en línea diseñado por Servicom', '', 1, 4),
('Sitio para restaurantes', 'Restaurantes y cafeterías', 'Menú digital, galería de platillos, ubicación y pedidos por WhatsApp desde el celular.', 'assets/img/proyecto-5.svg', 'Ejemplo de sitio web para restaurantes diseñado por Servicom', '', 1, 5),
('Landing de campaña', 'Publicidad y promociones', 'Página enfocada en una sola acción, ideal para acompañar campañas en Facebook e Instagram.', 'assets/img/proyecto-6.svg', 'Ejemplo de landing page para campañas publicitarias', '', 1, 6);

-- --------------------------------------------------------- Testimonios -------
-- Ejemplos DESACTIVADOS. Sustitúyalos por comentarios reales de sus clientes
-- desde el panel (Contenido > Testimonios) y actívelos.
INSERT INTO `testimonials` (`name`, `role`, `body`, `rating`, `avatar`, `status`, `sort_order`) VALUES
('Nombre del cliente', 'Cargo · Empresa', 'Escriba aquí el comentario real de su cliente. Este ejemplo está desactivado y no se muestra en el sitio hasta que usted lo edite y lo active desde el panel.', 5, '', 0, 1),
('Nombre del cliente', 'Cargo · Empresa', 'Segundo espacio disponible para un testimonio real. Actívelo desde el panel cuando tenga el comentario de su cliente.', 5, '', 0, 2),
('Nombre del cliente', 'Cargo · Empresa', 'Tercer espacio disponible para un testimonio real. Actívelo desde el panel cuando tenga el comentario de su cliente.', 5, '', 0, 3);

-- --------------------------------------------------------------- Blog --------
INSERT INTO `posts` (`slug`, `title`, `excerpt`, `body`, `image`, `image_alt`, `author`, `meta_title`, `meta_description`, `meta_keywords`, `published_at`, `status`) VALUES
('que-debe-tener-una-pagina-web-para-un-negocio-en-guatemala',
 '¿Qué debe tener una página web para un negocio en Guatemala?',
 'Una buena página web no es la que tiene más efectos, sino la que hace que el cliente entienda su servicio y le escriba. Estos son los elementos que no pueden faltar.',
 'Muchos negocios en Guatemala invierten en una página web y luego no reciben ni una sola consulta. El problema casi nunca es la tecnología: es que la página no responde las preguntas que el cliente se hace en los primeros segundos.

Qué hace usted, para quién y cómo contactarle. Eso es lo primero.

Antes de pensar en animaciones o colores, la página tiene que dejar claro a qué se dedica el negocio, a quién le sirve y cómo se le puede contactar. Si un visitante entra desde su celular y en diez segundos no encuentra esa información, se va con la competencia.

Secciones claras, no un muro de texto.

Organizar el contenido por secciones —servicios, sobre el negocio, preguntas frecuentes, contacto— ayuda a que la información se entienda rápido. Cada sección debe tener un solo propósito y un camino claro hacia el siguiente paso.

Que se vea bien en el celular.

En Guatemala la mayoría del tráfico web llega desde teléfonos. Una página que se ve desordenada en un celular pierde clientes todos los días. Por eso todas nuestras páginas se adaptan correctamente a celulares, tablets y computadoras.

Formulario de contacto y WhatsApp.

El cliente debe poder escribirle sin fricción. Un formulario de contacto sencillo y un enlace directo a WhatsApp convierten una visita en una conversación, que es donde realmente se cierran las ventas.

Contenido real del negocio.

Fotos propias, servicios descritos con las palabras que usa su cliente y datos de contacto correctos valen más que cualquier plantilla llamativa. Con la información básica de su negocio —servicios, datos de contacto, logotipo y contenido— ya se puede armar una página que funcione.',
 'assets/img/blog-1.svg', 'Elementos que debe tener una página web para un negocio en Guatemala', 'Servicom',
 '¿Qué debe tener una página web para un negocio en Guatemala?',
 'Los elementos que no pueden faltar en la página web de un negocio en Guatemala: secciones claras, diseño adaptable a celular, formulario de contacto y enlace a WhatsApp.',
 'página web para negocio Guatemala, qué debe tener una página web, diseño web Guatemala', '2026-01-20 09:00:00', 1),

('cuanto-cuesta-una-pagina-web-en-guatemala',
 '¿Cuánto cuesta una página web en Guatemala?',
 'La respuesta honesta es: depende. Pero se puede explicar con claridad qué es lo que hace que un proyecto cueste más o menos.',
 'Es la primera pregunta de casi todos los clientes, y merece una respuesta directa: el costo depende del tipo de sitio y de la cantidad de secciones.

El tipo de sitio.

No cuesta lo mismo una página que presenta a una empresa y sus servicios que una tienda virtual con catálogo, métodos de pago, envíos y administración de inventario. La segunda tiene más piezas funcionando, y por lo tanto más trabajo detrás.

La cantidad de secciones.

Cada sección adicional implica estructura, diseño y contenido. Un sitio de cinco secciones bien resueltas suele funcionar mejor que uno de veinte secciones a medias, y además cuesta menos.

El contenido.

Cuando el negocio ya tiene su logotipo, sus textos y sus fotografías, el proyecto avanza más rápido. Cuando hay que construir ese material desde cero, el tiempo aumenta.

Lo que sí debería ser fijo: la claridad.

Pida siempre una cotización por escrito, con el alcance detallado: cuántas secciones incluye, si el sitio será adaptable a celulares, si incluye formulario de contacto y enlace a WhatsApp, y qué pasa con el dominio y el hosting.

En Servicom manejamos planes accesibles según las necesidades de cada negocio, y entregamos la cotización por escrito para que usted sepa exactamente qué está contratando.',
 'assets/img/blog-2.svg', 'Cuánto cuesta una página web en Guatemala', 'Servicom',
 '¿Cuánto cuesta una página web en Guatemala? | Precios y factores',
 'Qué determina el precio de una página web en Guatemala: el tipo de sitio, la cantidad de secciones y el contenido disponible. Pida siempre su cotización por escrito.',
 'cuánto cuesta una página web Guatemala, precio página web Guatemala, cotización diseño web', '2026-02-10 09:00:00', 1),

('tienda-virtual-o-redes-sociales-donde-vender',
 'Tienda virtual o redes sociales: ¿dónde conviene vender?',
 'Las redes sociales atraen. La tienda virtual convierte. Lo ideal no es elegir, sino entender qué hace cada una.',
 'Muchos negocios en Guatemala empiezan vendiendo por Facebook e Instagram, y funciona: es rápido, es gratis y la gente ya está ahí. El problema aparece cuando el volumen crece.

Lo que hacen bien las redes sociales.

Las redes sociales sirven para que la gente lo descubra. El contenido atractivo y las campañas publicitarias en Facebook e Instagram ponen su negocio frente a clientes potenciales que no lo conocían.

Lo que no resuelven.

Un catálogo en publicaciones es difícil de ordenar, los precios se pierden entre comentarios, y cada pedido implica responder mensajes uno por uno. Además, el alcance depende de un algoritmo que usted no controla.

Lo que aporta una tienda virtual.

Una tienda propia le da un catálogo ordenado, métodos de pago seguros, opciones de envío y devoluciones configuradas, y la posibilidad de procesar pedidos y analizar el rendimiento de sus ventas sin complicaciones. Y es suya: nadie puede cambiar las reglas de un día para otro.

La combinación que funciona.

Las redes atraen y la tienda cierra la venta. Use Facebook e Instagram para generar interés y dirija ese interés a una tienda virtual bien construida, con un diseño exclusivo que refleje la calidad de su marca.',
 'assets/img/blog-3.svg', 'Tienda virtual o redes sociales para vender en Guatemala', 'Servicom',
 'Tienda virtual o redes sociales: ¿dónde conviene vender en Guatemala?',
 'Comparación entre vender por redes sociales y tener una tienda virtual propia en Guatemala: alcance, catálogo, pagos seguros y control del negocio.',
 'tienda virtual Guatemala, vender por redes sociales, comercio electrónico Guatemala', '2026-03-05 09:00:00', 1);

-- -------------------------------------------------------------- Paginas ------
INSERT INTO `pages` (`slug`, `title`, `subtitle`, `template`, `body`, `image`, `meta_title`, `meta_description`, `meta_keywords`, `og_image`, `robots`, `is_system`, `show_in_sitemap`, `priority`, `status`, `sort_order`, `updated_at`) VALUES
('inicio', 'Inicio', 'Diseño de páginas web en Guatemala', 'home', NULL, NULL,
 'Diseño de Páginas Web en Guatemala',
 'Diseño de páginas web en Guatemala con más de 16 años de experiencia. Sitios claros y adaptables, tiendas virtuales, dominio, hosting y manejo de redes sociales.',
 'diseño de páginas web Guatemala, páginas web Guatemala, diseño web profesional Guatemala, tiendas virtuales Guatemala, Servicom',
 'assets/img/og-default.svg', NULL, 1, 1, '1.0', 1, 1, '2026-01-01 08:00:00'),

('servicios', 'Servicios', 'Todo lo que su negocio necesita para vender en internet', 'services', NULL, NULL,
 'Servicios de Diseño Web en Guatemala',
 'Páginas web profesionales, tiendas virtuales, asesoría de dominio y hosting, correos corporativos y manejo de redes sociales en Guatemala.',
 'servicios diseño web Guatemala, páginas web, tiendas virtuales, hosting, redes sociales',
 NULL, NULL, 1, 1, '0.9', 1, 2, '2026-01-01 08:00:00'),

('nosotros', 'Nosotros', 'Más de 16 años diseñando páginas web en Guatemala', 'about',
 'Servicom diseña páginas web en Guatemala que atraen clientes reales. Desde hace más de 16 años trabajamos con empresas, comercios y profesionales que necesitan mostrar su servicio en internet de forma clara y profesional.

Nuestro enfoque es simple: recibimos la información básica de su negocio —servicios, datos de contacto, logotipo y contenido— y organizamos la página por secciones claras, para que su servicio se entienda rápido y el cliente pueda contactarle fácil.

Todas nuestras páginas web se adaptan correctamente a celulares, tablets y computadoras, e incluyen formulario de contacto y enlace a WhatsApp para facilitar la comunicación con sus clientes.

Además del diseño y desarrollo de páginas web, creamos tiendas virtuales completamente personalizables, le asesoramos con dominio y hosting, y manejamos su presencia en Facebook e Instagram.

El tiempo de desarrollo varía según el proyecto, pero normalmente entregamos en pocos días una vez recibida toda la información. Y siempre con atención directa: usted habla con quien desarrolla su sitio.',
 'assets/img/nosotros.svg',
 'Nosotros | Diseño Web en Guatemala con más de 16 años de experiencia',
 'Conozca Servicom: más de 16 años diseñando páginas web en Guatemala, con atención directa, sitios adaptables a todos los dispositivos y entrega en pocos días.',
 'Servicom Guatemala, empresa diseño web Guatemala, quiénes somos',
 NULL, NULL, 1, 1, '0.8', 1, 3, '2026-01-01 08:00:00'),

('portafolio', 'Portafolio', 'Sitios diseñados para negocios reales', 'portfolio', NULL, NULL,
 'Portafolio de Diseño Web | Servicom Guatemala',
 'Ejemplos de los tipos de sitios que desarrollamos en Guatemala: sitios corporativos, tiendas virtuales, catálogos de productos y landings de campaña.',
 'portafolio diseño web Guatemala, ejemplos páginas web Guatemala',
 NULL, NULL, 1, 1, '0.7', 1, 4, '2026-01-01 08:00:00'),

('actualidad-web', 'Actualidad Web', 'Ideas y novedades sobre diseño web', 'blog', NULL, NULL,
 'Actualidad Web | Blog de Diseño Web en Guatemala',
 'Artículos sobre diseño web, tiendas virtuales y presencia digital para negocios en Guatemala.',
 'blog diseño web Guatemala, actualidad web, novedades diseño web',
 NULL, NULL, 1, 1, '0.6', 1, 5, '2026-01-01 08:00:00'),

('contacto', 'Contacto', 'Solicite su cotización por escrito', 'contact', NULL, NULL,
 'Contacto | Cotice su Página Web en Guatemala',
 'Solicite su cotización por escrito para el diseño de su página web o tienda virtual en Guatemala. Teléfono y WhatsApp: +502 3204 0756.',
 'contacto Servicom, cotizar página web Guatemala, diseño web Guatemala teléfono',
 NULL, NULL, 1, 1, '0.9', 1, 6, '2026-01-01 08:00:00'),

('aviso-legal', 'Aviso legal y privacidad', 'Cómo tratamos su información', 'page',
 'Este sitio pertenece a Servicom, empresa dedicada al diseño y desarrollo de páginas web en Guatemala.

Datos que recopilamos

Cuando usted completa el formulario de contacto, guardamos únicamente los datos que nos proporciona (nombre, correo electrónico, teléfono, servicio de interés y mensaje) con el fin de responder a su solicitud y elaborar la cotización que nos pide.

Uso de la información

Sus datos se utilizan exclusivamente para atender su consulta comercial. No los vendemos, alquilamos ni compartimos con terceros ajenos a la prestación del servicio.

Conservación y eliminación

Puede solicitar en cualquier momento la corrección o eliminación de sus datos escribiéndonos a nuestro correo de contacto.

Cookies

Este sitio utiliza únicamente el almacenamiento necesario para el funcionamiento de la página, como la preferencia de tema visual y la sesión del formulario de contacto.

Propiedad intelectual

Los textos, imágenes y elementos gráficos de este sitio pertenecen a Servicom, salvo indicación expresa en contrario.',
 NULL,
 'Aviso legal y política de privacidad | Servicom',
 'Aviso legal y política de privacidad del sitio de Servicom: qué datos recopilamos a través del formulario de contacto y cómo los utilizamos.',
 NULL, NULL, 'index, follow', 0, 1, '0.3', 1, 20, '2026-01-01 08:00:00');
