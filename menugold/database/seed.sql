-- MenúGold · datos base que necesita toda instalación.
SET NAMES utf8mb4;

INSERT INTO `plans` (`id`,`name`,`slug`,`price_month`,`max_products`,`max_tables`,`max_orders_month`,`max_users`,`features`,`is_active`,`sort`) VALUES
(1,'Básico','basico',249.00,40,8,600,3,'["Menú QR con fotografía","Pedido en mesa","1 pantalla de cocina","Reportes básicos"]',1,1),
(2,'Pro','pro',449.00,150,30,4000,10,'["Todo lo del Básico","Para llevar y domicilio","Modificadores y promociones","Reportes completos y exportación","Español e inglés"]',1,2),
(3,'Premium','premium',749.00,0,0,0,0,'["Todo lo del Pro","Sin límites","Dominio propio","Capacitación y soporte prioritario","Respaldo semanal"]',1,3)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `landing_plans` (`id`,`name`,`price`,`period`,`pitch`,`features`,`cta_text`,`wa_message`,`is_featured`,`is_active`,`sort`) VALUES
(1,'Básico','Q249','al mes','Para el que empieza con su primer menú digital.','Menú QR con fotografía\nHasta 40 platillos\n8 mesas con su código\nPedido en mesa\nPantalla de cocina\nReportes de ventas','Quiero el Básico','Hola, me interesa el plan Básico de MenúGold para mi restaurante.',0,1,1),
(2,'Pro','Q449','al mes','El que usan la mayoría de restaurantes con salón y domicilio.','Todo lo del Básico\nHasta 150 platillos y 30 mesas\nPara llevar y a domicilio con zonas\nModificadores, combos y promociones\nCupones y clientes\nReportes completos en PDF y Excel\nMenú en español e inglés','Quiero el Pro','Hola, me interesa el plan Pro de MenúGold para mi restaurante.',1,1,2),
(3,'Premium','Q749','al mes','Para grupos con varias sucursales y marca propia.','Todo lo del Pro\nSin límite de platillos, mesas ni pedidos\nTu propio dominio\nCapacitación al equipo\nRespaldo semanal automático\nSoporte prioritario','Quiero el Premium','Hola, me interesa el plan Premium de MenúGold.',0,1,3)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `testimonials` (`id`,`name`,`role`,`place`,`quote`,`rating`,`is_active`,`sort`) VALUES
(1,'Marcela Villagrán','Propietaria','Brasa Negra','Dejamos de imprimir menús cada vez que cambiaba el precio de la carne. Ahora lo cambio desde el teléfono y en dos segundos está en las doce mesas.',5,1,1),
(2,'Rodrigo Estrada','Gerente','Café Central','Los meseros ya no anotan en papel. La cocina ve el pedido con el término exacto y los extras. Bajamos los errores casi a cero.',5,1,2),
(3,'Ana Lucía Prado','Chef propietaria','Sal de Mar','La gente pide más cuando ve la foto del platillo a pantalla completa. Nuestro ticket promedio subió un tercio sin subir precios.',5,1,3)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `settings` (`skey`,`svalue`) VALUES
('version','1.0.0'),
('installed_at', NOW())
ON DUPLICATE KEY UPDATE `svalue` = VALUES(`svalue`);
