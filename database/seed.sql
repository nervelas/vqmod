-- =====================================================================
--  Seed data — Centro Educativo Cristiano Fuente de Vida
--  Imported by the installer after schema.sql. Idempotent-ish: uses
--  INSERT ... (fresh install expected). External URLs reflect the real
--  platforms and can be edited later from the admin panel.
-- =====================================================================
SET NAMES utf8mb4;

-- ------------------------- Settings ---------------------------------
INSERT INTO `settings` (`key`, `value`, `group_name`) VALUES
('site_name', 'Centro Educativo Cristiano Fuente de Vida', 'general'),
('site_short_name', 'Fuente de Vida', 'general'),
('tagline', 'Educación con excelencia y valores cristianos', 'general'),
('logo', 'assets/img/logo.svg', 'branding'),
('logo_light', 'assets/img/logo-white.svg', 'branding'),
('favicon', 'assets/img/favicon.svg', 'branding'),
('color_primary', '#0f5a3c', 'branding'),
('color_secondary', '#f6a800', 'branding'),
('color_dark', '#0b3d2a', 'branding'),
('phone', '2277 5656', 'contact'),
('phone_link', '50222775656', 'contact'),
('whatsapp_enabled', '1', 'contact'),
('whatsapp_number', '50222775656', 'contact'),
('whatsapp_message', 'Hola, deseo más información sobre el Centro Educativo Cristiano Fuente de Vida.', 'contact'),
('whatsapp_button_text', 'Escríbenos por WhatsApp', 'contact'),
('email', 'info@fuentedevida.edu.gt', 'contact'),
('address', '6ta. Av. 1-57 Zona 19, Colonia La Florida, Ciudad de Guatemala', 'contact'),
('map_embed', 'https://www.google.com/maps?q=6ta.%20Av.%201-57%20Zona%2019%20La%20Florida%20Guatemala&output=embed', 'contact'),
('facebook', 'https://www.facebook.com/colegiofuentedevida/', 'social'),
('instagram', '', 'social'),
('tiktok', 'https://www.tiktok.com/@colegiofuentedevida', 'social'),
('youtube', '', 'social'),
('copyright', 'Centro Educativo Cristiano Fuente de Vida. Todos los derechos reservados.', 'footer'),
('footer_about', 'Institución profesional que brinda una educación sólida y una formación científico-cultural sustentada en principios éticos y morales con convicción en Jesucristo, de manera activa, progresiva e innovadora.', 'footer'),
('seo_default_title', 'Centro Educativo Cristiano Fuente de Vida | Colegio en Guatemala', 'seo'),
('seo_default_description', 'Colegio cristiano en zona 19, Guatemala. Preprimaria, primaria y nivel medio con más de 15 carreras. Formación académica con valores. Admisiones abiertas.', 'seo'),
('seo_og_image', 'assets/img/og-default.jpg', 'seo'),
('seo_keywords', 'colegio Guatemala, Fuente de Vida, colegio cristiano, preprimaria, primaria, diversificado, admisiones', 'seo'),
('analytics_head', '', 'seo'),
('maintenance_mode', '0', 'general');

-- ------------------------- Pages ------------------------------------
INSERT INTO `pages` (`slug`,`title`,`template`,`h1`,`intro`,`is_active`,`show_in_menu`,`sort`,`seo_title`,`seo_description`,`created_at`,`updated_at`) VALUES
('inicio','Inicio','home','Centro Educativo Cristiano Fuente de Vida','Educación con excelencia académica y valores cristianos.',1,1,1,'Centro Educativo Cristiano Fuente de Vida | Colegio en Guatemala','Colegio cristiano en zona 19, Guatemala. Preprimaria, primaria y nivel medio con más de 15 carreras.',NOW(),NOW()),
('nosotros','Nosotros','page','Quiénes somos','Conoce nuestra historia, misión, visión y valores.',1,1,2,'Nosotros | Fuente de Vida','Conoce la misión, visión y valores del Centro Educativo Cristiano Fuente de Vida.',NOW(),NOW()),
('preprimaria','Preprimaria','level','Nivel Preprimaria','Formación integral desde los 4 años.',1,1,3,'Preprimaria | Fuente de Vida','Nivel preprimaria a partir de los 4 años con metodología activa e integral.',NOW(),NOW()),
('primaria','Primaria','level','Nivel Primaria','Una formación común que desarrolla las capacidades individuales.',1,1,4,'Primaria | Fuente de Vida','Nivel primario que desarrolla las capacidades motrices, de equilibrio personal y social.',NOW(),NOW()),
('nivel-medio','Nivel Medio','level','Nivel Medio','Ciclo Básico y Ciclo Diversificado con más de 15 carreras.',1,1,5,'Nivel Medio | Fuente de Vida','Ciclo Básico y Diversificado con más de 15 carreras a elección del estudiante.',NOW(),NOW()),
('admisiones','Admisiones','admissions','Admisiones','Únete a la familia Fuente de Vida. Solicita tu primer ingreso.',1,1,6,'Admisiones | Fuente de Vida','Proceso de admisión y solicitud de primer ingreso al Centro Educativo Cristiano Fuente de Vida.',NOW(),NOW()),
('galeria','Galería','gallery','Galería','Momentos y actividades especiales de nuestra comunidad educativa.',1,1,7,'Galería | Fuente de Vida','Fotografías de actividades, logros y experiencias de la comunidad Fuente de Vida.',NOW(),NOW()),
('contactenos','Contáctenos','contact','Contáctenos','Estamos para atenderte. Escríbenos o visítanos.',1,1,8,'Contáctenos | Fuente de Vida','Contacta al Centro Educativo Cristiano Fuente de Vida. Dirección, teléfono y formulario.',NOW(),NOW());

-- ------------------------- Home sections ----------------------------
INSERT INTO `sections` (`page_id`,`block_key`,`type`,`title`,`subtitle`,`body`,`image`,`background`,`icon`,`button_text`,`button_url`,`button_target`,`is_active`,`sort`)
SELECT p.id,'hero','hero','Formamos líderes con excelencia y valores','Centro Educativo Cristiano Fuente de Vida',
 'Una educación sólida y una formación científico-cultural sustentada en principios éticos y morales, con convicción en Jesucristo.',
 '', 'assets/img/hero.svg', '', 'Conoce las admisiones 2026', 'admisiones', '_self', 1, 1
FROM pages p WHERE p.slug='inicio';

INSERT INTO `sections` (`page_id`,`block_key`,`type`,`title`,`subtitle`,`body`,`image`,`icon`,`button_text`,`button_url`,`is_active`,`sort`)
SELECT p.id,'welcome','text','Bienvenidos a Fuente de Vida','Nuestra institución',
 'Somos una institución profesional que brinda una educación sólida y una formación científico-cultural, sustentada en principios éticos y morales con convicción en Jesucristo, implementada de manera activa, progresiva e innovadora. Contamos con instalaciones equipadas y laboratorios modernos que garantizan un excelente desarrollo académico.',
 'assets/img/welcome.svg','', 'Conócenos más', 'nosotros', 1, 2
FROM pages p WHERE p.slug='inicio';

INSERT INTO `sections` (`page_id`,`block_key`,`type`,`title`,`subtitle`,`is_active`,`sort`)
SELECT p.id,'levels_header','heading','Nuestros niveles educativos','Desde los 4 años hasta el diversificado',1,3
FROM pages p WHERE p.slug='inicio';

INSERT INTO `sections` (`page_id`,`block_key`,`type`,`title`,`body`,`image`,`icon`,`button_text`,`button_url`,`is_active`,`sort`)
SELECT p.id,'level_pre','card','Preprimaria',
 'A partir de los 4 años. Acciones físicas, mentales y emocionales que promueven nuevos aprendizajes para el desarrollo integral.',
 'assets/img/nivel-preprimaria.svg','child','Ver más','preprimaria',1,4
FROM pages p WHERE p.slug='inicio';

INSERT INTO `sections` (`page_id`,`block_key`,`type`,`title`,`body`,`image`,`icon`,`button_text`,`button_url`,`is_active`,`sort`)
SELECT p.id,'level_pri','card','Primaria',
 'Una formación común que desarrolla las capacidades individuales motrices, de equilibrio personal, de relación y de actuación social.',
 'assets/img/nivel-primaria.svg','book','Ver más','primaria',1,5
FROM pages p WHERE p.slug='inicio';

INSERT INTO `sections` (`page_id`,`block_key`,`type`,`title`,`body`,`image`,`icon`,`button_text`,`button_url`,`is_active`,`sort`)
SELECT p.id,'level_medio','card','Nivel Medio',
 'Ciclo Básico y Ciclo Diversificado con más de 15 carreras a elección del estudiante para su futuro profesional.',
 'assets/img/nivel-medio.svg','graduation','Ver más','nivel-medio',1,6
FROM pages p WHERE p.slug='inicio';

INSERT INTO `sections` (`page_id`,`block_key`,`type`,`title`,`subtitle`,`is_active`,`sort`)
SELECT p.id,'platforms_header','heading','Accesos y plataformas','Todo lo que necesitas, en un solo lugar',1,7
FROM pages p WHERE p.slug='inicio';

INSERT INTO `sections` (`page_id`,`block_key`,`type`,`title`,`subtitle`,`body`,`background`,`button_text`,`button_url`,`is_active`,`sort`)
SELECT p.id,'cta_admisiones','cta','Admisiones 2026 abiertas','Únete a la familia Fuente de Vida',
 'Descubre nuevas opciones de carrera y actividades. Inicia hoy tu proceso de primer ingreso.',
 'assets/img/cta-bg.svg','Solicitar información','admisiones',1,8
FROM pages p WHERE p.slug='inicio';

INSERT INTO `sections` (`page_id`,`block_key`,`type`,`title`,`subtitle`,`is_active`,`sort`)
SELECT p.id,'gallery_header','heading','Nuestra galería','Momentos que nos definen',1,9
FROM pages p WHERE p.slug='inicio';

-- ------------------------- Nosotros sections ------------------------
INSERT INTO `sections` (`page_id`,`block_key`,`type`,`title`,`body`,`image`,`is_active`,`sort`)
SELECT p.id,'historia','text','Nuestra institución',
 'El Centro Educativo Cristiano Fuente de Vida es una institución profesional que brinda una educación sólida y una formación científico-cultural sustentada en principios éticos y morales con convicción en Jesucristo, implementada de manera activa, progresiva e innovadora. Implementamos metodologías de aprendizaje activo que promueven una actitud motivacional positiva y fortalecen las competencias de nuestros estudiantes.',
 'assets/img/welcome.svg',1,1
FROM pages p WHERE p.slug='nosotros';

INSERT INTO `sections` (`page_id`,`block_key`,`type`,`title`,`body`,`icon`,`is_active`,`sort`)
SELECT p.id,'mision','feature','Misión',
 'Formar estudiantes íntegros con excelencia académica y valores cristianos, capaces de enfrentar los retos de la sociedad con principios éticos y morales.',
 'target',1,2
FROM pages p WHERE p.slug='nosotros';

INSERT INTO `sections` (`page_id`,`block_key`,`type`,`title`,`body`,`icon`,`is_active`,`sort`)
SELECT p.id,'vision','feature','Visión',
 'Ser una institución educativa líder, reconocida por su calidad académica y por la formación de personas comprometidas con Dios, la familia y la sociedad.',
 'eye',1,3
FROM pages p WHERE p.slug='nosotros';

INSERT INTO `sections` (`page_id`,`block_key`,`type`,`title`,`body`,`icon`,`is_active`,`sort`)
SELECT p.id,'valores','feature','Valores',
 'Fe, respeto, responsabilidad, honestidad, solidaridad y excelencia guían el actuar diario de nuestra comunidad educativa.',
 'heart',1,4
FROM pages p WHERE p.slug='nosotros';

-- ------------------------- Level pages sections ---------------------
INSERT INTO `sections` (`page_id`,`block_key`,`type`,`title`,`body`,`image`,`is_active`,`sort`)
SELECT p.id,'intro','text','Preprimaria',
 'Nuestro nivel preprimario atiende a estudiantes a partir de los 4 años. La metodología se lleva a cabo a través de acciones físicas, mentales y emocionales que promueven en los estudiantes la creación de nuevos aprendizajes que contribuyen a su desarrollo integral, en un ambiente seguro, afectivo y estimulante.',
 'assets/img/nivel-preprimaria.svg',1,1
FROM pages p WHERE p.slug='preprimaria';

INSERT INTO `sections` (`page_id`,`block_key`,`type`,`title`,`body`,`image`,`is_active`,`sort`)
SELECT p.id,'intro','text','Primaria',
 'En el nivel primario, la finalidad es proporcionar a todos los alumnos una formación común que haga posible el desarrollo de las capacidades individuales motrices, de equilibrio personal, de relación y de actuación social, con acompañamiento docente cercano y programas de refuerzo.',
 'assets/img/nivel-primaria.svg',1,1
FROM pages p WHERE p.slug='primaria';

INSERT INTO `sections` (`page_id`,`block_key`,`type`,`title`,`body`,`image`,`is_active`,`sort`)
SELECT p.id,'intro','text','Nivel Medio',
 'El nivel medio comprende el Ciclo Básico y el Ciclo Diversificado, con más de 15 carreras a elección del estudiante. Preparamos a los jóvenes con una base académica sólida y orientación vocacional para que descubran nuevas opciones de carrera y actividades hacia su futuro profesional.',
 'assets/img/nivel-medio.svg',1,1
FROM pages p WHERE p.slug='nivel-medio';

-- ------------------------- Admisiones sections ----------------------
INSERT INTO `sections` (`page_id`,`block_key`,`type`,`title`,`body`,`button_text`,`button_url`,`button_target`,`is_active`,`sort`)
SELECT p.id,'intro','text','Proceso de admisión',
 'Estamos felices de que consideres a Fuente de Vida para la educación de tu hijo. Completa la solicitud de primer ingreso con tus datos y uno de nuestros asesores te contactará para continuar con el proceso. También puedes ingresar a nuestro portal en línea para dar seguimiento a tu solicitud.',
 'Ir al portal en línea','https://e-fuentedevida.net/login_efuente/','_blank',1,1
FROM pages p WHERE p.slug='admisiones';

-- ------------------------- Navigation menu --------------------------
INSERT INTO `menu_items` (`label`,`url`,`target`,`sort`,`is_active`) VALUES
('Inicio','inicio','_self',1,1),
('Nosotros','nosotros','_self',2,1),
('Preprimaria','preprimaria','_self',3,1),
('Primaria','primaria','_self',4,1),
('Nivel Medio','nivel-medio','_self',5,1),
('Admisiones','admisiones','_self',6,1),
('Galería','galeria','_self',7,1),
('Contáctenos','contactenos','_self',8,1);

-- ------------------------- Platforms / quick access -----------------
INSERT INTO `platforms` (`name`,`description`,`icon`,`url`,`target`,`is_active`,`sort`) VALUES
('Portal Académico','Consulta notas, tareas e información académica.','portal','https://e-fuentedevida.net/login_efuente/','_blank',1,1),
('Pagos en Línea','Realiza y consulta tus pagos y estados de cuenta.','card','https://e-fuentedevida.net/login_efuente/','_blank',1,2),
('Admisiones','Solicitud de primer ingreso.','admissions','admisiones','_self',1,3),
('Solicitud de Empleo','Trabaja con nosotros.','briefcase','contactenos','_self',1,4),
('Servicio de Bus','Información del servicio de transporte escolar.','bus','contactenos','_self',1,5),
('Galería','Fotografías de nuestras actividades.','image','galeria','_self',1,6),
('Fuente de Vida Radio','Escucha nuestra radio en vivo.','radio','https://www.radiofuentedevida.net/','_blank',1,7);

-- ------------------------- Sample gallery album ---------------------
INSERT INTO `albums` (`title`,`slug`,`description`,`cover_image`,`event_date`,`is_active`,`sort`,`created_at`) VALUES
('Actividades escolares','actividades-escolares','Momentos especiales que han sido parte de las experiencias y logros de nuestros estudiantes.','assets/img/gallery/g1.svg',CURDATE(),1,1,NOW());

INSERT INTO `photos` (`album_id`,`image`,`caption`,`sort`)
SELECT a.id, 'assets/img/gallery/g1.svg','Acto cívico',1 FROM albums a WHERE a.slug='actividades-escolares';
INSERT INTO `photos` (`album_id`,`image`,`caption`,`sort`)
SELECT a.id, 'assets/img/gallery/g2.svg','Actividad deportiva',2 FROM albums a WHERE a.slug='actividades-escolares';
INSERT INTO `photos` (`album_id`,`image`,`caption`,`sort`)
SELECT a.id, 'assets/img/gallery/g3.svg','Clausura',3 FROM albums a WHERE a.slug='actividades-escolares';
INSERT INTO `photos` (`album_id`,`image`,`caption`,`sort`)
SELECT a.id, 'assets/img/gallery/g4.svg','Actividad cultural',4 FROM albums a WHERE a.slug='actividades-escolares';
