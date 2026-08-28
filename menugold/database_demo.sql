-- =====================================================================
--  MenuGold - Base de datos de DEMOSTRACION
--  Estructura completa + datos de prueba:
--    - Restaurante 1: "La Terraza Gold"  (6 categorias, 37 platillos,
--      12 mesas con QR, 4 usuarios, 40 pedidos historicos, 3 promociones,
--      2 cupones, modificadores y zonas de entrega)
--    - Restaurante 2: "Cafe Central"     (sirve para comprobar el
--      aislamiento total de datos entre restaurantes)
--
--  USO: solo para pruebas. Importar sobre una base de datos VACIA;
--       borra y recrea todas las tablas.
--       Para una instalacion real use /install/ (importa database.sql).
--
--  Accesos de demostracion:
--    superadmin  admin@plataforma.gt / Admin2026!
--    dueno       dueno@laterraza.gt  / Terraza2026!
--    cocina      cocina1             / Cocina2026!
--    mesero      mesero1             / Mesero2026!
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

--
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `usuario` varchar(120) NOT NULL DEFAULT '',
  `accion` varchar(60) NOT NULL,
  `entidad` varchar(60) NOT NULL DEFAULT '',
  `entidad_id` int(10) unsigned NOT NULL DEFAULT 0,
  `antes` text DEFAULT NULL,
  `despues` text DEFAULT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `agente` varchar(255) NOT NULL DEFAULT '',
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_audit_rest` (`restaurant_id`,`creado`),
  KEY `ix_audit_accion` (`accion`)
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
INSERT INTO `audit_log` (`id`, `restaurant_id`, `user_id`, `usuario`, `accion`, `entidad`, `entidad_id`, `antes`, `despues`, `ip`, `agente`, `creado`) VALUES (1,1,3,'Cocina · Estación caliente','ingreso','users',3,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:43:46'),
(2,1,3,'Cocina · Estación caliente','pedido.estado','orders',59,'{\"estado\":\"nuevo\"}','{\"estado\":\"preparando\"}','127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:44:38'),
(3,1,3,'Cocina · Estación caliente','pedido.estado','orders',59,'{\"estado\":\"preparando\"}','{\"estado\":\"listo\"}','127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:44:38'),
(4,1,3,'Cocina · Estación caliente','ingreso','users',3,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:46:28'),
(5,1,3,'Cocina · Estación caliente','pedido.estado','orders',60,'{\"estado\":\"nuevo\"}','{\"estado\":\"preparando\"}','127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:47:21'),
(6,1,3,'Cocina · Estación caliente','ingreso','users',3,NULL,NULL,'127.0.0.1','curl/8.5.0','2026-08-28 01:47:33'),
(7,1,3,'Cocina · Estación caliente','ingreso','users',3,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:49:06'),
(8,1,3,'Cocina · Estación caliente','pedido.estado','orders',61,'{\"estado\":\"nuevo\"}','{\"estado\":\"preparando\"}','127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:49:32'),
(9,1,3,'Cocina · Estación caliente','pedido.estado','orders',60,'{\"estado\":\"preparando\"}','{\"estado\":\"listo\"}','127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:49:32'),
(10,1,4,'Diego Ramírez','ingreso','users',4,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:49:49'),
(11,1,3,'Cocina · Estación caliente','ingreso','users',3,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:53:02'),
(12,1,3,'Cocina · Estación caliente','pedido.estado','orders',62,'{\"estado\":\"nuevo\"}','{\"estado\":\"preparando\"}','127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:53:28'),
(13,1,3,'Cocina · Estación caliente','pedido.estado','orders',62,'{\"estado\":\"preparando\"}','{\"estado\":\"listo\"}','127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:53:29'),
(14,1,4,'Diego Ramírez','ingreso','users',4,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:53:48'),
(15,1,4,'Diego Ramírez','descuento','orders',62,'{\"total\":459.8}','{\"descuento\":41.8,\"total\":418,\"cupon\":\"\"}','127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:54:30'),
(16,1,4,'Diego Ramírez','pedido.cobrar','orders',62,NULL,'{\"total\":418,\"metodo\":\"efectivo\"}','127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:54:33'),
(17,1,4,'Diego Ramírez','mesa.cerrar','tables',3,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:54:33'),
(18,1,3,'Cocina · Estación caliente','ingreso','users',3,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:55:13'),
(19,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:56:45'),
(20,NULL,8,'Administrador de plataforma','ingreso','users',8,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 01:57:32'),
(21,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','curl/8.5.0','2026-08-28 02:00:10'),
(22,2,6,'Roberto Ixcot','ingreso','users',6,NULL,NULL,'127.0.0.1','curl/8.5.0','2026-08-28 02:00:10'),
(23,1,3,'Cocina · Estación caliente','ingreso','users',3,NULL,NULL,'127.0.0.1','curl/8.5.0','2026-08-28 02:00:11'),
(24,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','curl/8.5.0','2026-08-28 02:02:10'),
(25,2,6,'Roberto Ixcot','ingreso','users',6,NULL,NULL,'127.0.0.1','curl/8.5.0','2026-08-28 02:02:10'),
(26,1,3,'Cocina · Estación caliente','ingreso','users',3,NULL,NULL,'127.0.0.1','curl/8.5.0','2026-08-28 02:02:10'),
(27,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','curl/8.5.0','2026-08-28 02:02:35'),
(28,2,6,'Roberto Ixcot','ingreso','users',6,NULL,NULL,'127.0.0.1','curl/8.5.0','2026-08-28 02:02:35'),
(29,1,3,'Cocina · Estación caliente','ingreso','users',3,NULL,NULL,'127.0.0.1','curl/8.5.0','2026-08-28 02:02:36'),
(30,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','curl/8.5.0','2026-08-28 02:02:55'),
(31,2,6,'Roberto Ixcot','ingreso','users',6,NULL,NULL,'127.0.0.1','curl/8.5.0','2026-08-28 02:02:55'),
(32,1,3,'Cocina · Estación caliente','ingreso','users',3,NULL,NULL,'127.0.0.1','curl/8.5.0','2026-08-28 02:02:55'),
(33,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:06:15'),
(34,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:13:06'),
(35,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:18:58'),
(36,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:24:58'),
(37,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:32:39'),
(38,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:36:58'),
(39,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:37:40'),
(40,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:42:50'),
(41,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:44:40'),
(42,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','curl/8.5.0','2026-08-28 02:46:44'),
(43,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:47:06'),
(44,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:47:15'),
(45,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:48:02'),
(46,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:48:49'),
(47,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:49:30'),
(48,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:49:46'),
(49,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:50:02'),
(50,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:50:18'),
(51,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:50:34'),
(52,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:50:37'),
(53,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:50:41'),
(54,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:50:45'),
(55,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36','2026-08-28 02:51:08'),
(56,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:51:41'),
(57,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:52:12'),
(58,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:53:05'),
(59,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:53:25'),
(60,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:54:31'),
(61,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:55:18'),
(62,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:56:18'),
(63,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:57:18'),
(64,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:58:18'),
(65,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 02:59:18'),
(66,1,3,'Cocina · Estación caliente','ingreso','users',3,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 03:00:19'),
(67,1,3,'Cocina · Estación caliente','ingreso','users',3,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 03:04:44'),
(68,1,3,'Cocina · Estación caliente','ingreso','users',3,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 03:05:06'),
(69,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 03:05:18'),
(70,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 03:07:51'),
(71,1,2,'Mariana Solís','ingreso','users',2,NULL,NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36','2026-08-28 03:08:07');
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` int(10) unsigned NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `nombre_en` varchar(120) NOT NULL DEFAULT '',
  `descripcion` varchar(255) NOT NULL DEFAULT '',
  `descripcion_en` varchar(255) NOT NULL DEFAULT '',
  `imagen` varchar(190) NOT NULL DEFAULT '',
  `icono` varchar(30) NOT NULL DEFAULT 'utensils',
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `dias` varchar(30) NOT NULL DEFAULT '',
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_cat_rest` (`restaurant_id`,`activo`,`orden`),
  CONSTRAINT `fk_cat_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` (`id`, `restaurant_id`, `nombre`, `nombre_en`, `descripcion`, `descripcion_en`, `imagen`, `icono`, `orden`, `activo`, `hora_inicio`, `hora_fin`, `dias`, `creado`, `actualizado`) VALUES (1,1,'Desayunos de la casa','Breakfast','Servidos hasta las 11:00 de la mañana','','','sun',0,1,'06:00:00','11:00:00','','2026-08-28 01:39:47',NULL),
(2,1,'Entradas','Starters','Para empezar, algo que se comparte','','','sparkles',1,1,NULL,NULL,'','2026-08-28 01:39:47',NULL),
(3,1,'Sopas y ensaladas','Soups & salads','Frescura del huerto y caldos de fuego lento','','','leaf',2,1,NULL,NULL,'','2026-08-28 01:39:47',NULL),
(4,1,'Platos fuertes','Main courses','Cocinados a fuego vivo, como debe ser','','','fire',3,1,NULL,NULL,'','2026-08-28 01:39:47',NULL),
(5,1,'Postres','Desserts','El final que se recuerda','','','cake',4,1,NULL,NULL,'','2026-08-28 01:39:47',NULL),
(6,1,'Bebidas y coctelería','Drinks & cocktails','Coctelería de autor con destilados de la región','','','bar',5,1,NULL,NULL,'','2026-08-28 01:39:47',NULL),
(7,2,'Cafetería','','','','','utensils',0,1,NULL,NULL,'','2026-08-28 01:39:48',NULL),
(8,2,'Panadería','','','','','cake',1,1,NULL,NULL,'','2026-08-28 01:39:48',NULL),
(9,2,'Desayunos','','','','','chef',2,1,NULL,NULL,'','2026-08-28 01:39:48',NULL);
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `telefono` varchar(30) NOT NULL DEFAULT '',
  `restaurante` varchar(120) NOT NULL DEFAULT '',
  `plan` varchar(60) NOT NULL DEFAULT '',
  `mensaje` text NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `leido` tinyint(1) NOT NULL DEFAULT 0,
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_cm_leido` (`leido`,`creado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_messages`
--

LOCK TABLES `contact_messages` WRITE;
/*!40000 ALTER TABLE `contact_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` int(10) unsigned NOT NULL,
  `codigo` varchar(40) NOT NULL,
  `descripcion` varchar(190) NOT NULL DEFAULT '',
  `tipo` enum('porcentaje','monto','envio_gratis') NOT NULL DEFAULT 'porcentaje',
  `valor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_compra` decimal(10,2) NOT NULL DEFAULT 0.00,
  `usos_max` int(11) NOT NULL DEFAULT 0,
  `usos` int(11) NOT NULL DEFAULT 0,
  `desde` date DEFAULT NULL,
  `hasta` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cup_codigo` (`restaurant_id`,`codigo`),
  CONSTRAINT `fk_cup_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupons`
--

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
INSERT INTO `coupons` (`id`, `restaurant_id`, `codigo`, `descripcion`, `tipo`, `valor`, `min_compra`, `usos_max`, `usos`, `desde`, `hasta`, `activo`, `creado`) VALUES (1,1,'BIENVENIDO10','10% de descuento en tu primer pedido','porcentaje',10.00,100.00,0,8,'2026-07-29','2026-12-26',1,'2026-08-28 01:39:47'),
(2,1,'ENVIOGRATIS','Envío gratis en pedidos a domicilio','envio_gratis',0.00,250.00,100,12,'2026-08-13','2026-10-27',1,'2026-08-28 01:39:47'),
(3,2,'CAFE15','15% en cafetería','porcentaje',15.00,50.00,200,31,'2026-08-18','2026-10-07',1,'2026-08-28 01:39:48');
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` int(10) unsigned NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `telefono` varchar(30) NOT NULL,
  `email` varchar(190) NOT NULL DEFAULT '',
  `direccion` varchar(255) NOT NULL DEFAULT '',
  `referencia` varchar(255) NOT NULL DEFAULT '',
  `zone_id` int(10) unsigned DEFAULT NULL,
  `puntos` int(11) NOT NULL DEFAULT 0,
  `pedidos` int(11) NOT NULL DEFAULT 0,
  `total_gastado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notas` varchar(255) NOT NULL DEFAULT '',
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  `ultimo_pedido` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cli_tel` (`restaurant_id`,`telefono`),
  KEY `ix_cli_rest` (`restaurant_id`),
  CONSTRAINT `fk_cli_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` (`id`, `restaurant_id`, `nombre`, `telefono`, `email`, `direccion`, `referencia`, `zone_id`, `puntos`, `pedidos`, `total_gastado`, `notas`, `creado`, `ultimo_pedido`) VALUES (1,1,'Ana Gutiérrez','50255512340','','Calle del Arco 22, Antigua Guatemala','Portón verde',1,107,0,0.00,'','2026-03-26 01:39:47',NULL),
(2,1,'Carlos Méndez','50255512341','','3a Calle Poniente 8, Antigua','',NULL,190,2,1245.20,'','2026-05-08 01:39:47','2026-08-27 12:09:00'),
(3,1,'Sofía Ramírez','50255512342','','Residencial El Panorama, casa 14','',NULL,277,2,1558.00,'','2026-04-08 01:39:47','2026-08-17 13:43:00'),
(4,1,'Julio Estrada','50255512343','','6a Avenida Sur 40, Antigua','',NULL,126,0,0.00,'','2026-05-27 01:39:47',NULL),
(5,1,'Paola Cifuentes','50255512344','','Ciudad Vieja, km 4.5','',NULL,129,0,0.00,'','2026-06-01 01:39:47',NULL),
(6,2,'Carlos Méndez','50255512341','','','',NULL,0,0,0.00,'','2026-08-28 01:58:42',NULL);
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_zones`
--

DROP TABLE IF EXISTS `delivery_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_zones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` int(10) unsigned NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `costo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `minimo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tiempo_min` int(11) NOT NULL DEFAULT 30,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_dz_rest` (`restaurant_id`,`activo`),
  CONSTRAINT `fk_dz_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_zones`
--

LOCK TABLES `delivery_zones` WRITE;
/*!40000 ALTER TABLE `delivery_zones` DISABLE KEYS */;
INSERT INTO `delivery_zones` (`id`, `restaurant_id`, `nombre`, `costo`, `minimo`, `tiempo_min`, `activo`, `orden`) VALUES (1,1,'Centro de Antigua',20.00,100.00,25,1,0),
(2,1,'San Pedro El Panorama',35.00,150.00,40,1,1),
(3,1,'Ciudad Vieja',45.00,200.00,50,1,2);
/*!40000 ALTER TABLE `delivery_zones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modifier_groups`
--

DROP TABLE IF EXISTS `modifier_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `modifier_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` int(10) unsigned NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `nombre_en` varchar(120) NOT NULL DEFAULT '',
  `tipo` enum('unico','multiple') NOT NULL DEFAULT 'unico',
  `obligatorio` tinyint(1) NOT NULL DEFAULT 0,
  `min_sel` int(11) NOT NULL DEFAULT 0,
  `max_sel` int(11) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_mg_rest` (`restaurant_id`,`orden`),
  CONSTRAINT `fk_mg_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modifier_groups`
--

LOCK TABLES `modifier_groups` WRITE;
/*!40000 ALTER TABLE `modifier_groups` DISABLE KEYS */;
INSERT INTO `modifier_groups` (`id`, `restaurant_id`, `nombre`, `nombre_en`, `tipo`, `obligatorio`, `min_sel`, `max_sel`, `orden`, `activo`, `creado`) VALUES (1,1,'Término de la carne','Cooking preference','unico',1,1,1,0,1,'2026-08-28 01:39:47'),
(2,1,'Tamaño','Size','unico',1,1,1,1,1,'2026-08-28 01:39:47'),
(3,1,'Extras','Add-ons','multiple',0,0,4,2,1,'2026-08-28 01:39:47'),
(4,1,'Quitar ingredientes','Remove','multiple',0,0,5,3,1,'2026-08-28 01:39:47'),
(5,1,'Tipo de leche','Milk','unico',0,0,1,4,1,'2026-08-28 01:39:47');
/*!40000 ALTER TABLE `modifier_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modifier_options`
--

DROP TABLE IF EXISTS `modifier_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `modifier_options` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `restaurant_id` int(10) unsigned NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `nombre_en` varchar(120) NOT NULL DEFAULT '',
  `precio_extra` decimal(10,2) NOT NULL DEFAULT 0.00,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `agotado` tinyint(1) NOT NULL DEFAULT 0,
  `predeterminado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_mo_group` (`group_id`,`orden`),
  KEY `ix_mo_rest` (`restaurant_id`),
  CONSTRAINT `fk_mo_group` FOREIGN KEY (`group_id`) REFERENCES `modifier_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modifier_options`
--

LOCK TABLES `modifier_options` WRITE;
/*!40000 ALTER TABLE `modifier_options` DISABLE KEYS */;
INSERT INTO `modifier_options` (`id`, `group_id`, `restaurant_id`, `nombre`, `nombre_en`, `precio_extra`, `orden`, `activo`, `agotado`, `predeterminado`) VALUES (1,1,1,'Término medio','',0.00,0,1,0,1),
(2,1,1,'Tres cuartos','',0.00,1,1,0,0),
(3,1,1,'Bien cocido','',0.00,2,1,0,0),
(4,1,1,'Rojo inglés','',0.00,3,1,0,0),
(5,2,1,'Individual','',0.00,0,1,0,1),
(6,2,1,'Para compartir','',65.00,1,1,0,0),
(7,3,1,'Queso de Zacapa','',22.00,0,1,0,0),
(8,3,1,'Aguacate hass','',18.00,1,1,0,0),
(9,3,1,'Tocino artesanal','',25.00,2,1,0,0),
(10,3,1,'Huevo de campo','',15.00,3,1,0,0),
(11,3,1,'Chile cobanero','',8.00,4,1,0,0),
(12,4,1,'Sin cebolla','',0.00,0,1,0,0),
(13,4,1,'Sin cilantro','',0.00,1,1,0,0),
(14,4,1,'Sin picante','',0.00,2,1,0,0),
(15,4,1,'Sin lácteos','',0.00,3,1,0,0),
(16,4,1,'Sin ajo','',0.00,4,1,0,0),
(17,5,1,'Entera','',0.00,0,1,0,1),
(18,5,1,'Deslactosada','',6.00,1,1,0,0),
(19,5,1,'De almendra','',12.00,2,1,0,0),
(20,5,1,'De avena','',12.00,3,1,0,0);
/*!40000 ALTER TABLE `modifier_options` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_events`
--

DROP TABLE IF EXISTS `order_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `estado` varchar(20) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `usuario` varchar(120) NOT NULL DEFAULT '',
  `nota` varchar(255) NOT NULL DEFAULT '',
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_oe_order` (`order_id`,`creado`),
  CONSTRAINT `fk_oe_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_events`
--

LOCK TABLES `order_events` WRITE;
/*!40000 ALTER TABLE `order_events` DISABLE KEYS */;
INSERT INTO `order_events` (`id`, `order_id`, `estado`, `user_id`, `usuario`, `nota`, `creado`) VALUES (5,3,'nuevo',NULL,'cliente','','2026-08-27 12:09:00'),
(6,3,'pagado',NULL,'sistema','','2026-08-27 13:04:00'),
(7,4,'nuevo',NULL,'cliente','','2026-08-26 19:54:00'),
(8,4,'pagado',NULL,'sistema','','2026-08-26 20:53:00'),
(9,5,'nuevo',NULL,'cliente','','2026-08-26 20:35:00'),
(10,5,'pagado',NULL,'sistema','','2026-08-26 21:36:00'),
(11,6,'nuevo',NULL,'cliente','','2026-08-25 08:23:00'),
(12,6,'pagado',NULL,'sistema','','2026-08-25 09:13:00'),
(13,7,'nuevo',NULL,'cliente','','2026-08-24 13:45:00'),
(14,7,'pagado',NULL,'sistema','','2026-08-24 14:35:00'),
(15,8,'nuevo',NULL,'cliente','','2026-08-23 12:05:00'),
(16,8,'pagado',NULL,'sistema','','2026-08-23 13:19:00'),
(17,9,'nuevo',NULL,'cliente','','2026-08-23 13:47:00'),
(18,9,'pagado',NULL,'sistema','','2026-08-23 14:48:00'),
(19,10,'nuevo',NULL,'cliente','','2026-08-22 21:02:00'),
(20,10,'pagado',NULL,'sistema','','2026-08-22 22:12:00'),
(21,11,'nuevo',NULL,'cliente','','2026-08-21 14:37:00'),
(22,11,'pagado',NULL,'sistema','','2026-08-21 15:31:00'),
(23,12,'nuevo',NULL,'cliente','','2026-08-21 19:26:00'),
(24,13,'nuevo',NULL,'cliente','','2026-08-20 20:48:00'),
(25,13,'pagado',NULL,'sistema','','2026-08-20 21:58:00'),
(26,14,'nuevo',NULL,'cliente','','2026-08-19 19:59:00'),
(27,14,'pagado',NULL,'sistema','','2026-08-19 20:50:00'),
(28,15,'nuevo',NULL,'cliente','','2026-08-18 19:35:00'),
(29,15,'pagado',NULL,'sistema','','2026-08-18 20:33:00'),
(30,16,'nuevo',NULL,'cliente','','2026-08-18 19:09:00'),
(31,16,'pagado',NULL,'sistema','','2026-08-18 20:13:00'),
(32,17,'nuevo',NULL,'cliente','','2026-08-17 13:43:00'),
(33,17,'pagado',NULL,'sistema','','2026-08-17 14:55:00'),
(34,18,'nuevo',NULL,'cliente','','2026-08-16 09:33:00'),
(35,18,'pagado',NULL,'sistema','','2026-08-16 10:24:00'),
(36,19,'nuevo',NULL,'cliente','','2026-08-15 20:41:00'),
(37,19,'pagado',NULL,'sistema','','2026-08-15 21:36:00'),
(38,20,'nuevo',NULL,'cliente','','2026-08-15 13:41:00'),
(39,20,'pagado',NULL,'sistema','','2026-08-15 14:34:00'),
(40,21,'nuevo',NULL,'cliente','','2026-08-14 21:52:00'),
(41,21,'pagado',NULL,'sistema','','2026-08-14 22:50:00'),
(42,22,'nuevo',NULL,'cliente','','2026-08-13 20:22:00'),
(43,22,'pagado',NULL,'sistema','','2026-08-13 21:24:00'),
(44,23,'nuevo',NULL,'cliente','','2026-08-13 20:32:00'),
(45,23,'pagado',NULL,'sistema','','2026-08-13 21:24:00'),
(46,24,'nuevo',NULL,'cliente','','2026-08-12 20:59:00'),
(47,24,'pagado',NULL,'sistema','','2026-08-12 21:51:00'),
(48,25,'nuevo',NULL,'cliente','','2026-08-11 13:23:00'),
(49,25,'pagado',NULL,'sistema','','2026-08-11 14:19:00'),
(50,26,'nuevo',NULL,'cliente','','2026-08-10 09:29:00'),
(51,27,'nuevo',NULL,'cliente','','2026-08-10 19:25:00'),
(52,27,'pagado',NULL,'sistema','','2026-08-10 20:33:00'),
(53,28,'nuevo',NULL,'cliente','','2026-08-09 12:08:00'),
(54,28,'pagado',NULL,'sistema','','2026-08-09 13:03:00'),
(55,29,'nuevo',NULL,'cliente','','2026-08-08 19:24:00'),
(56,29,'pagado',NULL,'sistema','','2026-08-08 20:24:00'),
(57,30,'nuevo',NULL,'cliente','','2026-08-07 08:34:00'),
(58,30,'pagado',NULL,'sistema','','2026-08-07 09:30:00'),
(59,31,'nuevo',NULL,'cliente','','2026-08-07 21:56:00'),
(60,31,'pagado',NULL,'sistema','','2026-08-07 22:55:00'),
(61,32,'nuevo',NULL,'cliente','','2026-08-06 19:21:00'),
(62,32,'pagado',NULL,'sistema','','2026-08-06 20:22:00'),
(63,33,'nuevo',NULL,'cliente','','2026-08-05 19:11:00'),
(64,33,'pagado',NULL,'sistema','','2026-08-05 20:09:00'),
(65,34,'nuevo',NULL,'cliente','','2026-08-05 13:54:00'),
(66,34,'pagado',NULL,'sistema','','2026-08-05 14:46:00'),
(67,35,'nuevo',NULL,'cliente','','2026-08-04 08:07:00'),
(68,35,'pagado',NULL,'sistema','','2026-08-04 09:09:00'),
(69,36,'nuevo',NULL,'cliente','','2026-08-03 19:42:00'),
(70,36,'pagado',NULL,'sistema','','2026-08-03 20:31:00'),
(71,37,'nuevo',NULL,'cliente','','2026-08-02 19:29:00'),
(72,37,'pagado',NULL,'sistema','','2026-08-02 20:31:00'),
(73,38,'nuevo',NULL,'cliente','','2026-08-02 19:56:00'),
(74,38,'pagado',NULL,'sistema','','2026-08-02 20:51:00'),
(75,39,'nuevo',NULL,'cliente','','2026-08-01 09:31:00'),
(76,39,'pagado',NULL,'sistema','','2026-08-01 10:44:00'),
(77,40,'nuevo',NULL,'cliente','','2026-07-31 20:25:00'),
(78,40,'pagado',NULL,'sistema','','2026-07-31 21:21:00'),
(81,42,'nuevo',NULL,'cliente','','2026-08-27 14:29:00'),
(82,42,'pagado',NULL,'sistema','','2026-08-27 15:18:00'),
(83,43,'nuevo',NULL,'cliente','','2026-08-25 08:53:00'),
(84,43,'pagado',NULL,'sistema','','2026-08-25 09:43:00'),
(85,44,'nuevo',NULL,'cliente','','2026-08-24 19:21:00'),
(86,44,'pagado',NULL,'sistema','','2026-08-24 20:19:00'),
(87,45,'nuevo',NULL,'cliente','','2026-08-22 13:09:00'),
(88,45,'pagado',NULL,'sistema','','2026-08-22 14:22:00'),
(89,46,'nuevo',NULL,'cliente','','2026-08-20 19:13:00'),
(90,46,'pagado',NULL,'sistema','','2026-08-20 20:19:00'),
(91,47,'nuevo',NULL,'cliente','','2026-08-19 21:22:00'),
(92,47,'pagado',NULL,'sistema','','2026-08-19 22:20:00'),
(93,48,'nuevo',NULL,'cliente','','2026-08-17 13:48:00'),
(94,48,'pagado',NULL,'sistema','','2026-08-17 14:50:00'),
(95,49,'nuevo',NULL,'cliente','','2026-08-16 21:49:00'),
(96,49,'pagado',NULL,'sistema','','2026-08-16 22:57:00'),
(97,50,'nuevo',NULL,'cliente','','2026-08-14 19:17:00'),
(98,50,'pagado',NULL,'sistema','','2026-08-14 20:06:00'),
(99,51,'nuevo',NULL,'cliente','','2026-08-12 08:26:00'),
(100,51,'pagado',NULL,'sistema','','2026-08-12 09:27:00'),
(101,52,'nuevo',NULL,'cliente','','2026-08-11 08:25:00'),
(102,52,'pagado',NULL,'sistema','','2026-08-11 09:14:00'),
(103,53,'nuevo',NULL,'cliente','','2026-08-09 13:38:00'),
(104,53,'pagado',NULL,'sistema','','2026-08-09 14:32:00'),
(105,54,'nuevo',NULL,'cliente','','2026-08-08 14:50:00'),
(106,54,'pagado',NULL,'sistema','','2026-08-08 15:59:00'),
(107,55,'nuevo',NULL,'cliente','','2026-08-06 14:37:00'),
(108,55,'pagado',NULL,'sistema','','2026-08-06 15:35:00'),
(109,56,'nuevo',NULL,'cliente','','2026-08-04 12:10:00'),
(110,56,'pagado',NULL,'sistema','','2026-08-04 13:13:00'),
(111,57,'nuevo',NULL,'cliente','','2026-08-03 13:01:00'),
(112,57,'pagado',NULL,'sistema','','2026-08-03 14:00:00'),
(113,58,'nuevo',NULL,'cliente','','2026-08-01 20:35:00'),
(114,58,'pagado',NULL,'sistema','','2026-08-01 21:27:00'),
(123,62,'nuevo',NULL,'cliente','Pedido creado','2026-08-28 01:52:36'),
(124,62,'preparando',3,'Cocina · Estación caliente','Desde la pantalla de cocina','2026-08-28 01:53:28'),
(125,62,'listo',3,'Cocina · Estación caliente','Desde la pantalla de cocina','2026-08-28 01:53:29'),
(126,62,'pagado',4,'Diego Ramírez','Cobrado por Diego Ramírez','2026-08-28 01:54:33'),
(127,63,'nuevo',NULL,'cliente','Pedido creado','2026-08-28 01:58:34'),
(128,64,'nuevo',NULL,'cliente','Pedido creado','2026-08-28 01:58:42');
/*!40000 ALTER TABLE `order_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `restaurant_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `nombre` varchar(180) NOT NULL,
  `precio_unit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `extra_unit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `modificadores` text DEFAULT NULL,
  `notas` varchar(255) NOT NULL DEFAULT '',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estacion` enum('cocina','bar','postres') NOT NULL DEFAULT 'cocina',
  `estado` enum('pendiente','preparando','listo','entregado','anulado') NOT NULL DEFAULT 'pendiente',
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_oi_order` (`order_id`),
  KEY `ix_oi_prod` (`product_id`),
  KEY `ix_oi_rest` (`restaurant_id`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=151 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` (`id`, `order_id`, `restaurant_id`, `product_id`, `nombre`, `precio_unit`, `extra_unit`, `cantidad`, `modificadores`, `notas`, `subtotal`, `estacion`, `estado`, `creado`) VALUES (4,3,1,11,'Hongos silvestres al ajillo',96.00,0.00,1,'[]','',96.00,'cocina','entregado','2026-08-27 12:09:00'),
(5,3,1,35,'Margarita de rosa de jamaica',88.00,0.00,2,'[]','',176.00,'bar','entregado','2026-08-27 12:09:00'),
(6,3,1,3,'Tostada de aguacate y salmón',118.00,0.00,2,'[]','',236.00,'cocina','entregado','2026-08-27 12:09:00'),
(7,3,1,37,'Copa de vino tinto',78.00,0.00,2,'[]','',156.00,'bar','entregado','2026-08-27 12:09:00'),
(8,4,1,22,'Costilla de cerdo glaseada',215.00,0.00,1,'[]','',215.00,'cocina','entregado','2026-08-26 19:54:00'),
(9,4,1,4,'Pancakes de banano y cardamomo',74.00,0.00,1,'[]','',74.00,'cocina','entregado','2026-08-26 19:54:00'),
(10,4,1,34,'Old Fashioned de ron añejo',95.00,0.00,2,'[]','',190.00,'bar','entregado','2026-08-26 19:54:00'),
(11,5,1,26,'Camarones al ajillo',245.00,0.00,1,'[]','',245.00,'cocina','entregado','2026-08-26 20:35:00'),
(12,5,1,20,'Pollo al carbón con recado',178.00,0.00,3,'[]','',534.00,'cocina','entregado','2026-08-26 20:35:00'),
(13,5,1,28,'Crème brûlée de vainilla',82.00,0.00,1,'[]','',82.00,'postres','entregado','2026-08-26 20:35:00'),
(14,6,1,12,'Ostras de la bahía',185.00,0.00,1,'[]','',185.00,'cocina','entregado','2026-08-25 08:23:00'),
(15,6,1,9,'Tostadas de tuétano',128.00,0.00,2,'[]','',256.00,'cocina','entregado','2026-08-25 08:23:00'),
(16,7,1,1,'Huevos rancheros de la abuela',78.00,0.00,1,'[]','',78.00,'cocina','entregado','2026-08-24 13:45:00'),
(17,7,1,34,'Old Fashioned de ron añejo',95.00,0.00,3,'[]','',285.00,'bar','entregado','2026-08-24 13:45:00'),
(18,7,1,31,'Helado artesanal',58.00,0.00,2,'[]','',116.00,'postres','entregado','2026-08-24 13:45:00'),
(19,8,1,26,'Camarones al ajillo',245.00,0.00,1,'[]','',245.00,'cocina','entregado','2026-08-23 12:05:00'),
(20,8,1,1,'Huevos rancheros de la abuela',78.00,0.00,3,'[]','',234.00,'cocina','entregado','2026-08-23 12:05:00'),
(21,8,1,12,'Ostras de la bahía',185.00,0.00,3,'[]','',555.00,'cocina','entregado','2026-08-23 12:05:00'),
(22,8,1,28,'Crème brûlée de vainilla',82.00,0.00,3,'[]','',246.00,'postres','entregado','2026-08-23 12:05:00'),
(23,9,1,13,'Kak\'ik de pavo',132.00,0.00,3,'[]','',396.00,'cocina','entregado','2026-08-23 13:47:00'),
(24,9,1,24,'Pepián de res de la casa',168.00,0.00,2,'[]','',336.00,'cocina','entregado','2026-08-23 13:47:00'),
(25,9,1,33,'Café de Antigua',32.00,0.00,1,'[]','',32.00,'bar','entregado','2026-08-23 13:47:00'),
(26,10,1,19,'Cordero en cocción lenta',298.00,0.00,2,'[]','',596.00,'cocina','entregado','2026-08-22 21:02:00'),
(27,10,1,34,'Old Fashioned de ron añejo',95.00,0.00,2,'[]','',190.00,'bar','entregado','2026-08-22 21:02:00'),
(28,10,1,26,'Camarones al ajillo',245.00,0.00,3,'[]','',735.00,'cocina','entregado','2026-08-22 21:02:00'),
(29,10,1,12,'Ostras de la bahía',185.00,0.00,2,'[]','',370.00,'cocina','entregado','2026-08-22 21:02:00'),
(30,11,1,17,'Caprese de queso fresco',94.00,0.00,2,'[]','',188.00,'cocina','entregado','2026-08-21 14:37:00'),
(31,12,1,34,'Old Fashioned de ron añejo',95.00,0.00,2,'[]','',190.00,'bar','anulado','2026-08-21 19:26:00'),
(32,12,1,7,'Tártar de atún aleta amarilla',165.00,0.00,3,'[]','',495.00,'cocina','anulado','2026-08-21 19:26:00'),
(33,13,1,5,'Bowl de frutas y granola',68.00,0.00,1,'[]','',68.00,'cocina','entregado','2026-08-20 20:48:00'),
(34,14,1,9,'Tostadas de tuétano',128.00,0.00,3,'[]','',384.00,'cocina','entregado','2026-08-19 19:59:00'),
(35,14,1,13,'Kak\'ik de pavo',132.00,0.00,3,'[]','',396.00,'cocina','entregado','2026-08-19 19:59:00'),
(36,14,1,31,'Helado artesanal',58.00,0.00,3,'[]','',174.00,'postres','entregado','2026-08-19 19:59:00'),
(37,15,1,11,'Hongos silvestres al ajillo',96.00,0.00,3,'[]','',288.00,'cocina','entregado','2026-08-18 19:35:00'),
(38,15,1,15,'Ensalada de la terraza',86.00,0.00,1,'[]','',86.00,'cocina','entregado','2026-08-18 19:35:00'),
(39,16,1,21,'Pescado del día a la talla',265.00,0.00,3,'[]','',795.00,'cocina','entregado','2026-08-18 19:09:00'),
(40,17,1,5,'Bowl de frutas y granola',68.00,0.00,2,'[]','',136.00,'cocina','entregado','2026-08-17 13:43:00'),
(41,17,1,37,'Copa de vino tinto',78.00,0.00,3,'[]','',234.00,'bar','entregado','2026-08-17 13:43:00'),
(42,17,1,25,'Ravioles de ayote y salvia',172.00,0.00,1,'[]','',172.00,'cocina','entregado','2026-08-17 13:43:00'),
(43,18,1,6,'Ceviche del chef',145.00,0.00,1,'[]','',145.00,'cocina','entregado','2026-08-16 09:33:00'),
(44,18,1,25,'Ravioles de ayote y salvia',172.00,0.00,2,'[]','',344.00,'cocina','entregado','2026-08-16 09:33:00'),
(45,18,1,12,'Ostras de la bahía',185.00,0.00,3,'[]','',555.00,'cocina','entregado','2026-08-16 09:33:00'),
(46,19,1,17,'Caprese de queso fresco',94.00,0.00,3,'[]','',282.00,'cocina','entregado','2026-08-15 20:41:00'),
(47,19,1,32,'Tarta de limón persa',74.00,0.00,1,'[]','',74.00,'postres','entregado','2026-08-15 20:41:00'),
(48,19,1,34,'Old Fashioned de ron añejo',95.00,0.00,1,'[]','',95.00,'bar','entregado','2026-08-15 20:41:00'),
(49,19,1,35,'Margarita de rosa de jamaica',88.00,0.00,1,'[]','',88.00,'bar','entregado','2026-08-15 20:41:00'),
(50,20,1,1,'Huevos rancheros de la abuela',78.00,0.00,1,'[]','',78.00,'cocina','entregado','2026-08-15 13:41:00'),
(51,20,1,34,'Old Fashioned de ron añejo',95.00,0.00,3,'[]','',285.00,'bar','entregado','2026-08-15 13:41:00'),
(52,21,1,8,'Carpaccio de res angus',138.00,0.00,1,'[]','',138.00,'cocina','entregado','2026-08-14 21:52:00'),
(53,21,1,3,'Tostada de aguacate y salmón',118.00,0.00,3,'[]','',354.00,'cocina','entregado','2026-08-14 21:52:00'),
(54,22,1,12,'Ostras de la bahía',185.00,0.00,3,'[]','',555.00,'cocina','entregado','2026-08-13 20:22:00'),
(55,22,1,23,'Risotto de hongos y trufa',195.00,0.00,1,'[]','',195.00,'cocina','entregado','2026-08-13 20:22:00'),
(56,22,1,11,'Hongos silvestres al ajillo',96.00,0.00,1,'[]','',96.00,'cocina','entregado','2026-08-13 20:22:00'),
(57,23,1,36,'Limonada con hierbabuena',38.00,0.00,3,'[]','',114.00,'bar','entregado','2026-08-13 20:32:00'),
(58,24,1,2,'Plato chapín completo',92.00,0.00,1,'[]','',92.00,'cocina','entregado','2026-08-12 20:59:00'),
(59,24,1,20,'Pollo al carbón con recado',178.00,0.00,1,'[]','',178.00,'cocina','entregado','2026-08-12 20:59:00'),
(60,24,1,27,'Hamburguesa Gold',158.00,0.00,2,'[]','',316.00,'cocina','entregado','2026-08-12 20:59:00'),
(61,24,1,23,'Risotto de hongos y trufa',195.00,0.00,2,'[]','',390.00,'cocina','entregado','2026-08-12 20:59:00'),
(62,25,1,25,'Ravioles de ayote y salvia',172.00,0.00,3,'[]','',516.00,'cocina','entregado','2026-08-11 13:23:00'),
(63,25,1,19,'Cordero en cocción lenta',298.00,0.00,2,'[]','',596.00,'cocina','entregado','2026-08-11 13:23:00'),
(64,25,1,34,'Old Fashioned de ron añejo',95.00,0.00,1,'[]','',95.00,'bar','entregado','2026-08-11 13:23:00'),
(65,26,1,17,'Caprese de queso fresco',94.00,0.00,2,'[]','',188.00,'cocina','anulado','2026-08-10 09:29:00'),
(66,26,1,2,'Plato chapín completo',92.00,0.00,1,'[]','',92.00,'cocina','anulado','2026-08-10 09:29:00'),
(67,26,1,22,'Costilla de cerdo glaseada',215.00,0.00,1,'[]','',215.00,'cocina','anulado','2026-08-10 09:29:00'),
(68,27,1,8,'Carpaccio de res angus',138.00,0.00,2,'[]','',276.00,'cocina','entregado','2026-08-10 19:25:00'),
(69,28,1,34,'Old Fashioned de ron añejo',95.00,0.00,1,'[]','',95.00,'bar','entregado','2026-08-09 12:08:00'),
(70,29,1,9,'Tostadas de tuétano',128.00,0.00,2,'[]','',256.00,'cocina','entregado','2026-08-08 19:24:00'),
(71,29,1,8,'Carpaccio de res angus',138.00,0.00,3,'[]','',414.00,'cocina','entregado','2026-08-08 19:24:00'),
(72,30,1,13,'Kak\'ik de pavo',132.00,0.00,1,'[]','',132.00,'cocina','entregado','2026-08-07 08:34:00'),
(73,30,1,28,'Crème brûlée de vainilla',82.00,0.00,1,'[]','',82.00,'postres','entregado','2026-08-07 08:34:00'),
(74,30,1,18,'Lomito Wellington',325.00,0.00,1,'[]','',325.00,'cocina','entregado','2026-08-07 08:34:00'),
(75,31,1,35,'Margarita de rosa de jamaica',88.00,0.00,3,'[]','',264.00,'bar','entregado','2026-08-07 21:56:00'),
(76,31,1,30,'Tres leches de la casa',76.00,0.00,3,'[]','',228.00,'postres','entregado','2026-08-07 21:56:00'),
(77,31,1,16,'Ensalada César con pollo al carbón',108.00,0.00,3,'[]','',324.00,'cocina','entregado','2026-08-07 21:56:00'),
(78,31,1,5,'Bowl de frutas y granola',68.00,0.00,3,'[]','',204.00,'cocina','entregado','2026-08-07 21:56:00'),
(79,32,1,23,'Risotto de hongos y trufa',195.00,0.00,1,'[]','',195.00,'cocina','entregado','2026-08-06 19:21:00'),
(80,33,1,6,'Ceviche del chef',145.00,0.00,3,'[]','',435.00,'cocina','entregado','2026-08-05 19:11:00'),
(81,33,1,14,'Crema de güisquil y cardamomo',78.00,0.00,3,'[]','',234.00,'cocina','entregado','2026-08-05 19:11:00'),
(82,34,1,2,'Plato chapín completo',92.00,0.00,2,'[]','',184.00,'cocina','entregado','2026-08-05 13:54:00'),
(83,35,1,24,'Pepián de res de la casa',168.00,0.00,2,'[]','',336.00,'cocina','entregado','2026-08-04 08:07:00'),
(84,36,1,15,'Ensalada de la terraza',86.00,0.00,3,'[]','',258.00,'cocina','entregado','2026-08-03 19:42:00'),
(85,37,1,33,'Café de Antigua',32.00,0.00,3,'[]','',96.00,'bar','entregado','2026-08-02 19:29:00'),
(86,37,1,18,'Lomito Wellington',325.00,0.00,1,'[]','',325.00,'cocina','entregado','2026-08-02 19:29:00'),
(87,38,1,15,'Ensalada de la terraza',86.00,0.00,1,'[]','',86.00,'cocina','entregado','2026-08-02 19:56:00'),
(88,38,1,1,'Huevos rancheros de la abuela',78.00,0.00,3,'[]','',234.00,'cocina','entregado','2026-08-02 19:56:00'),
(89,39,1,27,'Hamburguesa Gold',158.00,0.00,3,'[]','',474.00,'cocina','entregado','2026-08-01 09:31:00'),
(90,39,1,20,'Pollo al carbón con recado',178.00,0.00,2,'[]','',356.00,'cocina','entregado','2026-08-01 09:31:00'),
(91,39,1,14,'Crema de güisquil y cardamomo',78.00,0.00,1,'[]','',78.00,'cocina','entregado','2026-08-01 09:31:00'),
(92,40,1,15,'Ensalada de la terraza',86.00,0.00,2,'[]','',172.00,'cocina','entregado','2026-07-31 20:25:00'),
(93,40,1,21,'Pescado del día a la talla',265.00,0.00,3,'[]','',795.00,'cocina','entregado','2026-07-31 20:25:00'),
(98,42,2,47,'Desayuno chapín',58.00,0.00,3,'[]','',174.00,'cocina','entregado','2026-08-27 14:29:00'),
(99,42,2,49,'Granola de la casa',48.00,0.00,1,'[]','',48.00,'cocina','entregado','2026-08-27 14:29:00'),
(100,43,2,43,'Concha de masa madre',16.00,0.00,3,'[]','',48.00,'postres','entregado','2026-08-25 08:53:00'),
(101,43,2,42,'Chocolate de metate',32.00,0.00,2,'[]','',64.00,'bar','entregado','2026-08-25 08:53:00'),
(102,43,2,41,'Cold brew 12 h',28.00,0.00,1,'[]','',28.00,'bar','entregado','2026-08-25 08:53:00'),
(103,43,2,44,'Croissant de mantequilla',22.00,0.00,1,'[]','',22.00,'postres','entregado','2026-08-25 08:53:00'),
(104,44,2,43,'Concha de masa madre',16.00,0.00,3,'[]','',48.00,'postres','entregado','2026-08-24 19:21:00'),
(105,45,2,41,'Cold brew 12 h',28.00,0.00,2,'[]','',56.00,'bar','entregado','2026-08-22 13:09:00'),
(106,45,2,47,'Desayuno chapín',58.00,0.00,2,'[]','',116.00,'cocina','entregado','2026-08-22 13:09:00'),
(107,45,2,39,'Cappuccino',26.00,0.00,3,'[]','',78.00,'bar','entregado','2026-08-22 13:09:00'),
(108,45,2,44,'Croissant de mantequilla',22.00,0.00,3,'[]','',66.00,'postres','entregado','2026-08-22 13:09:00'),
(109,46,2,42,'Chocolate de metate',32.00,0.00,2,'[]','',64.00,'bar','entregado','2026-08-20 19:13:00'),
(110,46,2,43,'Concha de masa madre',16.00,0.00,1,'[]','',16.00,'postres','entregado','2026-08-20 19:13:00'),
(111,46,2,46,'Cardamomo roll',28.00,0.00,1,'[]','',28.00,'postres','entregado','2026-08-20 19:13:00'),
(112,46,2,48,'Tostada de aguacate',62.00,0.00,2,'[]','',124.00,'cocina','entregado','2026-08-20 19:13:00'),
(113,47,2,49,'Granola de la casa',48.00,0.00,1,'[]','',48.00,'cocina','entregado','2026-08-19 21:22:00'),
(114,48,2,44,'Croissant de mantequilla',22.00,0.00,3,'[]','',66.00,'postres','entregado','2026-08-17 13:48:00'),
(115,48,2,43,'Concha de masa madre',16.00,0.00,1,'[]','',16.00,'postres','entregado','2026-08-17 13:48:00'),
(116,48,2,40,'Latte de vainilla',30.00,0.00,2,'[]','',60.00,'bar','entregado','2026-08-17 13:48:00'),
(117,49,2,45,'Pan de banano y nuez',24.00,0.00,2,'[]','',48.00,'postres','entregado','2026-08-16 21:49:00'),
(118,50,2,38,'Espresso doble',18.00,0.00,3,'[]','',54.00,'bar','entregado','2026-08-14 19:17:00'),
(119,51,2,40,'Latte de vainilla',30.00,0.00,1,'[]','',30.00,'bar','entregado','2026-08-12 08:26:00'),
(120,51,2,41,'Cold brew 12 h',28.00,0.00,2,'[]','',56.00,'bar','entregado','2026-08-12 08:26:00'),
(121,51,2,39,'Cappuccino',26.00,0.00,1,'[]','',26.00,'bar','entregado','2026-08-12 08:26:00'),
(122,52,2,41,'Cold brew 12 h',28.00,0.00,2,'[]','',56.00,'bar','entregado','2026-08-11 08:25:00'),
(123,52,2,45,'Pan de banano y nuez',24.00,0.00,2,'[]','',48.00,'postres','entregado','2026-08-11 08:25:00'),
(124,53,2,48,'Tostada de aguacate',62.00,0.00,3,'[]','',186.00,'cocina','entregado','2026-08-09 13:38:00'),
(125,53,2,46,'Cardamomo roll',28.00,0.00,3,'[]','',84.00,'postres','entregado','2026-08-09 13:38:00'),
(126,53,2,39,'Cappuccino',26.00,0.00,3,'[]','',78.00,'bar','entregado','2026-08-09 13:38:00'),
(127,54,2,43,'Concha de masa madre',16.00,0.00,3,'[]','',48.00,'postres','entregado','2026-08-08 14:50:00'),
(128,55,2,44,'Croissant de mantequilla',22.00,0.00,3,'[]','',66.00,'postres','entregado','2026-08-06 14:37:00'),
(129,55,2,45,'Pan de banano y nuez',24.00,0.00,3,'[]','',72.00,'postres','entregado','2026-08-06 14:37:00'),
(130,56,2,45,'Pan de banano y nuez',24.00,0.00,2,'[]','',48.00,'postres','entregado','2026-08-04 12:10:00'),
(131,56,2,39,'Cappuccino',26.00,0.00,2,'[]','',52.00,'bar','entregado','2026-08-04 12:10:00'),
(132,56,2,49,'Granola de la casa',48.00,0.00,2,'[]','',96.00,'cocina','entregado','2026-08-04 12:10:00'),
(133,56,2,42,'Chocolate de metate',32.00,0.00,1,'[]','',32.00,'bar','entregado','2026-08-04 12:10:00'),
(134,57,2,46,'Cardamomo roll',28.00,0.00,1,'[]','',28.00,'postres','entregado','2026-08-03 13:01:00'),
(135,57,2,39,'Cappuccino',26.00,0.00,3,'[]','',78.00,'bar','entregado','2026-08-03 13:01:00'),
(136,57,2,44,'Croissant de mantequilla',22.00,0.00,1,'[]','',22.00,'postres','entregado','2026-08-03 13:01:00'),
(137,58,2,39,'Cappuccino',26.00,0.00,1,'[]','',26.00,'bar','entregado','2026-08-01 20:35:00'),
(138,58,2,43,'Concha de masa madre',16.00,0.00,2,'[]','',32.00,'postres','entregado','2026-08-01 20:35:00'),
(139,58,2,48,'Tostada de aguacate',62.00,0.00,2,'[]','',124.00,'cocina','entregado','2026-08-01 20:35:00'),
(146,62,1,6,'Ceviche del chef',145.00,0.00,2,'[{\"grupo\":\"Tamaño\",\"opcion\":\"Individual\",\"precio\":0,\"id\":5},{\"grupo\":\"Quitar ingredientes\",\"opcion\":\"Sin cebolla\",\"precio\":0,\"id\":12}]','Sin cilantro, por favor',290.00,'cocina','entregado','2026-08-28 01:52:36'),
(147,62,1,9,'Tostadas de tuétano',128.00,0.00,1,'[{\"grupo\":\"Tamaño\",\"opcion\":\"Individual\",\"precio\":0,\"id\":5}]','',128.00,'cocina','entregado','2026-08-28 01:52:36'),
(148,63,1,6,'Ceviche del chef',145.00,0.00,1,'[{\"grupo\":\"Tamaño\",\"opcion\":\"Individual\",\"precio\":0,\"id\":5}]','',145.00,'cocina','anulado','2026-08-28 01:58:34'),
(149,63,1,8,'Carpaccio de res angus',138.00,0.00,1,'[{\"grupo\":\"Tamaño\",\"opcion\":\"Individual\",\"precio\":0,\"id\":5}]','',138.00,'cocina','anulado','2026-08-28 01:58:34'),
(150,64,2,38,'Espresso doble',18.00,0.00,1,'[]','',18.00,'bar','anulado','2026-08-28 01:58:42');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` int(10) unsigned NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `table_id` int(10) unsigned DEFAULT NULL,
  `mesa_nombre` varchar(40) NOT NULL DEFAULT '',
  `modo` enum('mesa','llevar','delivery','whatsapp') NOT NULL DEFAULT 'mesa',
  `estado` enum('nuevo','preparando','listo','entregado','pagado','anulado') NOT NULL DEFAULT 'nuevo',
  `customer_id` int(10) unsigned DEFAULT NULL,
  `cliente_nombre` varchar(120) NOT NULL DEFAULT '',
  `cliente_telefono` varchar(30) NOT NULL DEFAULT '',
  `cliente_direccion` varchar(255) NOT NULL DEFAULT '',
  `cliente_referencia` varchar(255) NOT NULL DEFAULT '',
  `delivery_zone_id` int(10) unsigned DEFAULT NULL,
  `costo_envio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cupon_codigo` varchar(40) NOT NULL DEFAULT '',
  `impuesto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `propina` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pagado_con` decimal(12,2) DEFAULT NULL,
  `metodo_pago` varchar(30) NOT NULL DEFAULT '',
  `notas` varchar(500) NOT NULL DEFAULT '',
  `motivo_anulacion` varchar(255) NOT NULL DEFAULT '',
  `user_id` int(10) unsigned DEFAULT NULL,
  `creado_por` enum('cliente','mesero','admin') NOT NULL DEFAULT 'cliente',
  `session_token` char(32) NOT NULL DEFAULT '',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `minutos_prep` int(11) DEFAULT NULL,
  `calificacion` tinyint(4) DEFAULT NULL,
  `comentario` varchar(500) NOT NULL DEFAULT '',
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado` datetime DEFAULT NULL,
  `listo_en` datetime DEFAULT NULL,
  `entregado_en` datetime DEFAULT NULL,
  `pagado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ord_codigo` (`restaurant_id`,`codigo`),
  KEY `ix_ord_rest_estado` (`restaurant_id`,`estado`,`creado`),
  KEY `ix_ord_mesa` (`table_id`,`estado`),
  KEY `ix_ord_creado` (`restaurant_id`,`creado`),
  KEY `ix_ord_token` (`session_token`),
  KEY `fk_ord_cli` (`customer_id`),
  CONSTRAINT `fk_ord_cli` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ord_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ord_tbl` FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` (`id`, `restaurant_id`, `codigo`, `table_id`, `mesa_nombre`, `modo`, `estado`, `customer_id`, `cliente_nombre`, `cliente_telefono`, `cliente_direccion`, `cliente_referencia`, `delivery_zone_id`, `costo_envio`, `subtotal`, `descuento`, `cupon_codigo`, `impuesto`, `propina`, `total`, `pagado_con`, `metodo_pago`, `notas`, `motivo_anulacion`, `user_id`, `creado_por`, `session_token`, `ip`, `minutos_prep`, `calificacion`, `comentario`, `creado`, `actualizado`, `listo_en`, `entregado_en`, `pagado_en`) VALUES (3,1,'0827-1002',NULL,'','delivery','pagado',2,'Ana Gutiérrez','55516529','Calle del Arco 23, Antigua','',NULL,20.00,664.00,0.00,'',71.14,0.00,684.00,NULL,'tarjeta','','',5,'mesero','','',15,5,'','2026-08-27 12:09:00',NULL,'2026-08-27 12:24:00','2026-08-27 12:28:00','2026-08-27 13:04:00'),
(4,1,'0826-1003',2,'Mesa 2','mesa','pagado',NULL,'','','','',NULL,0.00,479.00,0.00,'',51.32,47.90,526.90,NULL,'transferencia','','',4,'mesero','','',19,NULL,'','2026-08-26 19:54:00',NULL,'2026-08-26 20:13:00','2026-08-26 20:17:00','2026-08-26 20:53:00'),
(5,1,'0826-1004',NULL,'','llevar','pagado',NULL,'Carlos Méndez','55514948','','',NULL,0.00,861.00,0.00,'',92.25,86.10,947.10,NULL,'transferencia','','',5,'cliente','','',21,NULL,'','2026-08-26 20:35:00',NULL,'2026-08-26 20:56:00','2026-08-26 21:00:00','2026-08-26 21:36:00'),
(6,1,'0825-1005',4,'Mesa 4','mesa','pagado',NULL,'','','','',NULL,0.00,441.00,0.00,'',47.25,0.00,441.00,NULL,'efectivo','','',5,'cliente','','',10,NULL,'','2026-08-25 08:23:00',NULL,'2026-08-25 08:33:00','2026-08-25 08:37:00','2026-08-25 09:13:00'),
(7,1,'0824-1006',NULL,'','llevar','pagado',NULL,'Carlos Méndez','55514251','','',NULL,0.00,479.00,0.00,'',51.32,47.90,526.90,NULL,'efectivo','','',5,'cliente','','',10,NULL,'','2026-08-24 13:45:00',NULL,'2026-08-24 13:55:00','2026-08-24 13:59:00','2026-08-24 14:35:00'),
(8,1,'0823-1007',5,'Mesa 5','mesa','pagado',NULL,'','','','',NULL,0.00,1280.00,0.00,'',137.14,0.00,1280.00,NULL,'tarjeta','','',4,'mesero','','',34,NULL,'','2026-08-23 12:05:00',NULL,'2026-08-23 12:39:00','2026-08-23 12:43:00','2026-08-23 13:19:00'),
(9,1,'0823-1008',7,'Mesa 7','mesa','pagado',NULL,'','','','',NULL,0.00,764.00,0.00,'',81.86,0.00,764.00,NULL,'transferencia','','',5,'cliente','','',21,4,'','2026-08-23 13:47:00',NULL,'2026-08-23 14:08:00','2026-08-23 14:12:00','2026-08-23 14:48:00'),
(10,1,'0822-1009',2,'Mesa 2','mesa','pagado',NULL,'','','','',NULL,0.00,1891.00,0.00,'',202.61,0.00,1891.00,NULL,'transferencia','','',5,'mesero','','',30,NULL,'','2026-08-22 21:02:00',NULL,'2026-08-22 21:32:00','2026-08-22 21:36:00','2026-08-22 22:12:00'),
(11,1,'0821-1010',6,'Mesa 6','mesa','pagado',NULL,'','','','',NULL,0.00,188.00,0.00,'',20.14,0.00,188.00,NULL,'transferencia','','',5,'mesero','','',14,4,'','2026-08-21 14:37:00',NULL,'2026-08-21 14:51:00','2026-08-21 14:55:00','2026-08-21 15:31:00'),
(12,1,'0821-1011',10,'Mesa 10','mesa','anulado',NULL,'','','','',NULL,0.00,685.00,0.00,'',73.39,0.00,685.00,NULL,'tarjeta','','El cliente cambió de opinión',4,'mesero','','',NULL,NULL,'','2026-08-21 19:26:00',NULL,NULL,NULL,NULL),
(13,1,'0820-1012',NULL,'','llevar','pagado',NULL,'Carlos Méndez','55517424','','',NULL,0.00,68.00,0.00,'',7.29,6.80,74.80,NULL,'tarjeta','','',5,'mesero','','',30,4,'','2026-08-20 20:48:00',NULL,'2026-08-20 21:18:00','2026-08-20 21:22:00','2026-08-20 21:58:00'),
(14,1,'0819-1013',3,'Mesa 3','mesa','pagado',NULL,'','','','',NULL,0.00,954.00,0.00,'',102.21,0.00,954.00,NULL,'transferencia','','',4,'cliente','','',11,NULL,'','2026-08-19 19:59:00',NULL,'2026-08-19 20:10:00','2026-08-19 20:14:00','2026-08-19 20:50:00'),
(15,1,'0818-1014',NULL,'','llevar','pagado',NULL,'Sofía Ramírez','55514524','','',NULL,0.00,374.00,0.00,'',40.07,0.00,374.00,NULL,'efectivo','','',4,'cliente','','',18,NULL,'','2026-08-18 19:35:00',NULL,'2026-08-18 19:53:00','2026-08-18 19:57:00','2026-08-18 20:33:00'),
(16,1,'0818-1015',NULL,'','llevar','pagado',NULL,'Ana Gutiérrez','55519284','','',NULL,0.00,795.00,0.00,'',85.18,0.00,795.00,NULL,'tarjeta','','',4,'cliente','','',24,NULL,'','2026-08-18 19:09:00',NULL,'2026-08-18 19:33:00','2026-08-18 19:37:00','2026-08-18 20:13:00'),
(17,1,'0817-1016',NULL,'','delivery','pagado',3,'Julio Estrada','55519054','Calle del Arco 41, Antigua','',NULL,20.00,542.00,0.00,'',58.07,0.00,562.00,NULL,'transferencia','','',5,'mesero','','',32,5,'','2026-08-17 13:43:00',NULL,'2026-08-17 14:15:00','2026-08-17 14:19:00','2026-08-17 14:55:00'),
(18,1,'0816-1017',6,'Mesa 6','mesa','pagado',NULL,'','','','',NULL,0.00,1044.00,0.00,'',111.86,0.00,1044.00,NULL,'transferencia','','',5,'cliente','','',11,NULL,'','2026-08-16 09:33:00',NULL,'2026-08-16 09:44:00','2026-08-16 09:48:00','2026-08-16 10:24:00'),
(19,1,'0815-1018',10,'Mesa 10','mesa','pagado',NULL,'','','','',NULL,0.00,539.00,0.00,'',57.75,0.00,539.00,NULL,'transferencia','','',5,'cliente','','',15,NULL,'','2026-08-15 20:41:00',NULL,'2026-08-15 20:56:00','2026-08-15 21:00:00','2026-08-15 21:36:00'),
(20,1,'0815-1019',NULL,'','llevar','pagado',NULL,'Carlos Méndez','55512866','','',NULL,0.00,363.00,0.00,'',38.89,0.00,363.00,NULL,'transferencia','','',5,'mesero','','',13,NULL,'','2026-08-15 13:41:00',NULL,'2026-08-15 13:54:00','2026-08-15 13:58:00','2026-08-15 14:34:00'),
(21,1,'0814-1020',NULL,'','delivery','pagado',2,'Julio Estrada','55519619','Calle del Arco 41, Antigua','',NULL,20.00,492.00,0.00,'',52.71,49.20,561.20,NULL,'tarjeta','','',5,'mesero','','',18,5,'','2026-08-14 21:52:00',NULL,'2026-08-14 22:10:00','2026-08-14 22:14:00','2026-08-14 22:50:00'),
(22,1,'0813-1021',11,'Mesa 11','mesa','pagado',NULL,'','','','',NULL,0.00,846.00,0.00,'',90.64,84.60,930.60,NULL,'tarjeta','','',4,'mesero','','',22,5,'','2026-08-13 20:22:00',NULL,'2026-08-13 20:44:00','2026-08-13 20:48:00','2026-08-13 21:24:00'),
(23,1,'0813-1022',2,'Mesa 2','mesa','pagado',NULL,'','','','',NULL,0.00,114.00,0.00,'',12.21,11.40,125.40,NULL,'transferencia','','',4,'cliente','','',12,5,'','2026-08-13 20:32:00',NULL,'2026-08-13 20:44:00','2026-08-13 20:48:00','2026-08-13 21:24:00'),
(24,1,'0812-1023',NULL,'','delivery','pagado',3,'Sofía Ramírez','55512560','Calle del Arco 26, Antigua','',NULL,20.00,976.00,0.00,'',104.57,0.00,996.00,NULL,'efectivo','','',5,'cliente','','',12,4,'','2026-08-12 20:59:00',NULL,'2026-08-12 21:11:00','2026-08-12 21:15:00','2026-08-12 21:51:00'),
(25,1,'0811-1024',NULL,'','llevar','pagado',NULL,'Ana Gutiérrez','55512927','','',NULL,0.00,1207.00,0.00,'',129.32,0.00,1207.00,NULL,'transferencia','','',4,'cliente','','',16,NULL,'','2026-08-11 13:23:00',NULL,'2026-08-11 13:39:00','2026-08-11 13:43:00','2026-08-11 14:19:00'),
(26,1,'0810-1025',12,'Mesa 12','mesa','anulado',NULL,'','','','',NULL,0.00,495.00,0.00,'',53.04,0.00,495.00,NULL,'efectivo','','El cliente cambió de opinión',5,'mesero','','',NULL,NULL,'','2026-08-10 09:29:00',NULL,NULL,NULL,NULL),
(27,1,'0810-1026',5,'Mesa 5','mesa','pagado',NULL,'','','','',NULL,0.00,276.00,0.00,'',29.57,27.60,303.60,NULL,'efectivo','','',4,'mesero','','',28,4,'','2026-08-10 19:25:00',NULL,'2026-08-10 19:53:00','2026-08-10 19:57:00','2026-08-10 20:33:00'),
(28,1,'0809-1027',4,'Mesa 4','mesa','pagado',NULL,'','','','',NULL,0.00,95.00,0.00,'',10.18,9.50,104.50,NULL,'efectivo','','',5,'mesero','','',15,4,'','2026-08-09 12:08:00',NULL,'2026-08-09 12:23:00','2026-08-09 12:27:00','2026-08-09 13:03:00'),
(29,1,'0808-1028',3,'Mesa 3','mesa','pagado',NULL,'','','','',NULL,0.00,670.00,0.00,'',71.79,0.00,670.00,NULL,'transferencia','','',5,'cliente','','',20,NULL,'','2026-08-08 19:24:00',NULL,'2026-08-08 19:44:00','2026-08-08 19:48:00','2026-08-08 20:24:00'),
(30,1,'0807-1029',5,'Mesa 5','mesa','pagado',NULL,'','','','',NULL,0.00,539.00,0.00,'',57.75,0.00,539.00,NULL,'transferencia','','',4,'cliente','','',16,NULL,'','2026-08-07 08:34:00',NULL,'2026-08-07 08:50:00','2026-08-07 08:54:00','2026-08-07 09:30:00'),
(31,1,'0807-1030',9,'Mesa 9','mesa','pagado',NULL,'','','','',NULL,0.00,1020.00,0.00,'',109.29,102.00,1122.00,NULL,'transferencia','','',5,'mesero','','',19,4,'','2026-08-07 21:56:00',NULL,'2026-08-07 22:15:00','2026-08-07 22:19:00','2026-08-07 22:55:00'),
(32,1,'0806-1031',11,'Mesa 11','mesa','pagado',NULL,'','','','',NULL,0.00,195.00,0.00,'',20.89,19.50,214.50,NULL,'transferencia','','',5,'mesero','','',21,NULL,'','2026-08-06 19:21:00',NULL,'2026-08-06 19:42:00','2026-08-06 19:46:00','2026-08-06 20:22:00'),
(33,1,'0805-1032',7,'Mesa 7','mesa','pagado',NULL,'','','','',NULL,0.00,669.00,0.00,'',71.68,66.90,735.90,NULL,'efectivo','','',5,'mesero','','',18,5,'','2026-08-05 19:11:00',NULL,'2026-08-05 19:29:00','2026-08-05 19:33:00','2026-08-05 20:09:00'),
(34,1,'0805-1033',5,'Mesa 5','mesa','pagado',NULL,'','','','',NULL,0.00,184.00,0.00,'',19.71,18.40,202.40,NULL,'efectivo','','',4,'mesero','','',12,NULL,'','2026-08-05 13:54:00',NULL,'2026-08-05 14:06:00','2026-08-05 14:10:00','2026-08-05 14:46:00'),
(35,1,'0804-1034',7,'Mesa 7','mesa','pagado',NULL,'','','','',NULL,0.00,336.00,0.00,'',36.00,0.00,336.00,NULL,'tarjeta','','',5,'mesero','','',22,NULL,'','2026-08-04 08:07:00',NULL,'2026-08-04 08:29:00','2026-08-04 08:33:00','2026-08-04 09:09:00'),
(36,1,'0803-1035',4,'Mesa 4','mesa','pagado',NULL,'','','','',NULL,0.00,258.00,0.00,'',27.64,25.80,283.80,NULL,'efectivo','','',5,'cliente','','',9,NULL,'','2026-08-03 19:42:00',NULL,'2026-08-03 19:51:00','2026-08-03 19:55:00','2026-08-03 20:31:00'),
(37,1,'0802-1036',10,'Mesa 10','mesa','pagado',NULL,'','','','',NULL,0.00,421.00,0.00,'',45.11,0.00,421.00,NULL,'efectivo','','',4,'mesero','','',22,NULL,'','2026-08-02 19:29:00',NULL,'2026-08-02 19:51:00','2026-08-02 19:55:00','2026-08-02 20:31:00'),
(38,1,'0802-1037',4,'Mesa 4','mesa','pagado',NULL,'','','','',NULL,0.00,320.00,0.00,'',34.29,32.00,352.00,NULL,'transferencia','','',4,'mesero','','',15,NULL,'','2026-08-02 19:56:00',NULL,'2026-08-02 20:11:00','2026-08-02 20:15:00','2026-08-02 20:51:00'),
(39,1,'0801-1038',6,'Mesa 6','mesa','pagado',NULL,'','','','',NULL,0.00,908.00,0.00,'',97.29,0.00,908.00,NULL,'transferencia','','',5,'mesero','','',33,NULL,'','2026-08-01 09:31:00',NULL,'2026-08-01 10:04:00','2026-08-01 10:08:00','2026-08-01 10:44:00'),
(40,1,'0731-1039',3,'Mesa 3','mesa','pagado',NULL,'','','','',NULL,0.00,967.00,0.00,'',103.61,96.70,1063.70,NULL,'tarjeta','','',4,'cliente','','',16,NULL,'','2026-07-31 20:25:00',NULL,'2026-07-31 20:41:00','2026-07-31 20:45:00','2026-07-31 21:21:00'),
(42,2,'0827-1001',14,'Mesa 2','mesa','pagado',NULL,'','','','',NULL,0.00,222.00,0.00,'',23.79,0.00,222.00,NULL,'efectivo','','',7,'mesero','','',9,NULL,'','2026-08-27 14:29:00',NULL,'2026-08-27 14:38:00','2026-08-27 14:42:00','2026-08-27 15:18:00'),
(43,2,'0825-1002',15,'Mesa 3','mesa','pagado',NULL,'','','','',NULL,0.00,162.00,0.00,'',17.36,16.20,178.20,NULL,'tarjeta','','',7,'cliente','','',10,NULL,'','2026-08-25 08:53:00',NULL,'2026-08-25 09:03:00','2026-08-25 09:07:00','2026-08-25 09:43:00'),
(44,2,'0824-1003',13,'Mesa 1','mesa','pagado',NULL,'','','','',NULL,0.00,48.00,0.00,'',5.14,0.00,48.00,NULL,'tarjeta','','',7,'cliente','','',18,NULL,'','2026-08-24 19:21:00',NULL,'2026-08-24 19:39:00','2026-08-24 19:43:00','2026-08-24 20:19:00'),
(45,2,'0822-1004',16,'Mesa 4','mesa','pagado',NULL,'','','','',NULL,0.00,316.00,0.00,'',33.86,0.00,316.00,NULL,'transferencia','','',7,'mesero','','',33,5,'','2026-08-22 13:09:00',NULL,'2026-08-22 13:42:00','2026-08-22 13:46:00','2026-08-22 14:22:00'),
(46,2,'0820-1005',13,'Mesa 1','mesa','pagado',NULL,'','','','',NULL,0.00,232.00,0.00,'',24.86,0.00,232.00,NULL,'transferencia','','',7,'cliente','','',26,5,'','2026-08-20 19:13:00',NULL,'2026-08-20 19:39:00','2026-08-20 19:43:00','2026-08-20 20:19:00'),
(47,2,'0819-1006',NULL,'','llevar','pagado',NULL,'Sofía Ramírez','55517578','','',NULL,0.00,48.00,0.00,'',5.14,4.80,52.80,NULL,'transferencia','','',7,'mesero','','',18,NULL,'','2026-08-19 21:22:00',NULL,'2026-08-19 21:40:00','2026-08-19 21:44:00','2026-08-19 22:20:00'),
(48,2,'0817-1007',17,'Mesa 5','mesa','pagado',NULL,'','','','',NULL,0.00,142.00,0.00,'',15.21,0.00,142.00,NULL,'efectivo','','',7,'mesero','','',22,NULL,'','2026-08-17 13:48:00',NULL,'2026-08-17 14:10:00','2026-08-17 14:14:00','2026-08-17 14:50:00'),
(49,2,'0816-1008',NULL,'','llevar','pagado',NULL,'Carlos Méndez','55511969','','',NULL,0.00,48.00,0.00,'',5.14,4.80,52.80,NULL,'transferencia','','',7,'mesero','','',28,NULL,'','2026-08-16 21:49:00',NULL,'2026-08-16 22:17:00','2026-08-16 22:21:00','2026-08-16 22:57:00'),
(50,2,'0814-1009',15,'Mesa 3','mesa','pagado',NULL,'','','','',NULL,0.00,54.00,0.00,'',5.79,0.00,54.00,NULL,'transferencia','','',7,'mesero','','',9,NULL,'','2026-08-14 19:17:00',NULL,'2026-08-14 19:26:00','2026-08-14 19:30:00','2026-08-14 20:06:00'),
(51,2,'0812-1010',13,'Mesa 1','mesa','pagado',NULL,'','','','',NULL,0.00,112.00,0.00,'',12.00,11.20,123.20,NULL,'efectivo','','',7,'mesero','','',21,NULL,'','2026-08-12 08:26:00',NULL,'2026-08-12 08:47:00','2026-08-12 08:51:00','2026-08-12 09:27:00'),
(52,2,'0811-1011',NULL,'','llevar','pagado',NULL,'Sofía Ramírez','55514057','','',NULL,0.00,104.00,0.00,'',11.14,0.00,104.00,NULL,'tarjeta','','',7,'cliente','','',9,NULL,'','2026-08-11 08:25:00',NULL,'2026-08-11 08:34:00','2026-08-11 08:38:00','2026-08-11 09:14:00'),
(53,2,'0809-1012',17,'Mesa 5','mesa','pagado',NULL,'','','','',NULL,0.00,348.00,0.00,'',37.29,34.80,382.80,NULL,'transferencia','','',7,'mesero','','',14,NULL,'','2026-08-09 13:38:00',NULL,'2026-08-09 13:52:00','2026-08-09 13:56:00','2026-08-09 14:32:00'),
(54,2,'0808-1013',NULL,'','llevar','pagado',NULL,'Ana Gutiérrez','55516125','','',NULL,0.00,48.00,0.00,'',5.14,0.00,48.00,NULL,'tarjeta','','',7,'mesero','','',29,NULL,'','2026-08-08 14:50:00',NULL,'2026-08-08 15:19:00','2026-08-08 15:23:00','2026-08-08 15:59:00'),
(55,2,'0806-1014',17,'Mesa 5','mesa','pagado',NULL,'','','','',NULL,0.00,138.00,0.00,'',14.79,0.00,138.00,NULL,'efectivo','','',7,'mesero','','',18,NULL,'','2026-08-06 14:37:00',NULL,'2026-08-06 14:55:00','2026-08-06 14:59:00','2026-08-06 15:35:00'),
(56,2,'0804-1015',14,'Mesa 2','mesa','pagado',NULL,'','','','',NULL,0.00,228.00,0.00,'',24.43,22.80,250.80,NULL,'transferencia','','',7,'mesero','','',23,5,'','2026-08-04 12:10:00',NULL,'2026-08-04 12:33:00','2026-08-04 12:37:00','2026-08-04 13:13:00'),
(57,2,'0803-1016',NULL,'','llevar','pagado',NULL,'Ana Gutiérrez','55513585','','',NULL,0.00,128.00,0.00,'',13.71,0.00,128.00,NULL,'efectivo','','',7,'cliente','','',19,NULL,'','2026-08-03 13:01:00',NULL,'2026-08-03 13:20:00','2026-08-03 13:24:00','2026-08-03 14:00:00'),
(58,2,'0801-1017',14,'Mesa 2','mesa','pagado',NULL,'','','','',NULL,0.00,182.00,0.00,'',19.50,18.20,200.20,NULL,'tarjeta','','',7,'cliente','','',12,NULL,'','2026-08-01 20:35:00',NULL,'2026-08-01 20:47:00','2026-08-01 20:51:00','2026-08-01 21:27:00'),
(62,1,'0828-2899',3,'Mesa 3','mesa','pagado',NULL,'','','','',NULL,0.00,418.00,41.80,'',40.31,41.80,418.00,600.00,'efectivo','','',4,'cliente','0eefa2efd40d271e740e003522ec4ec6','127.0.0.1',0,NULL,'','2026-08-28 01:52:36','2026-08-28 01:54:33','2026-08-28 01:53:29','2026-08-28 01:54:33','2026-08-28 01:54:33'),
(63,1,'0828-5006',NULL,'','delivery','anulado',1,'Ana Gutiérrez','50255512340','Calle del Arco 22, Antigua Guatemala','Portón verde',1,20.00,283.00,28.30,'BIENVENIDO10',27.29,0.00,274.70,NULL,'efectivo','','Anulado automáticamente por inactividad',NULL,'cliente','12c086034c231dc2ae4176a51324f861','127.0.0.1',NULL,NULL,'','2026-08-28 01:58:34','2026-08-28 08:00:11',NULL,NULL,NULL),
(64,2,'0828-0525',NULL,'','whatsapp','anulado',6,'Carlos Méndez','50255512341','','',NULL,0.00,18.00,0.00,'',1.93,0.00,18.00,NULL,'efectivo','','Anulado automáticamente por inactividad',NULL,'cliente','4c2e6dd6f1b46980ee23f78258cc0a28','127.0.0.1',NULL,NULL,'','2026-08-28 01:58:42','2026-08-28 08:00:11',NULL,NULL,NULL);
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plans`
--

DROP TABLE IF EXISTS `plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `descripcion` varchar(255) NOT NULL DEFAULT '',
  `precio_mensual` decimal(10,2) NOT NULL DEFAULT 0.00,
  `precio_anual` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_productos` int(11) NOT NULL DEFAULT 50,
  `max_mesas` int(11) NOT NULL DEFAULT 10,
  `max_sucursales` int(11) NOT NULL DEFAULT 1,
  `max_usuarios` int(11) NOT NULL DEFAULT 3,
  `caracteristicas` text DEFAULT NULL,
  `destacado` tinyint(1) NOT NULL DEFAULT 0,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plans`
--

LOCK TABLES `plans` WRITE;
/*!40000 ALTER TABLE `plans` DISABLE KEYS */;
INSERT INTO `plans` (`id`, `nombre`, `slug`, `descripcion`, `precio_mensual`, `precio_anual`, `max_productos`, `max_mesas`, `max_sucursales`, `max_usuarios`, `caracteristicas`, `destacado`, `orden`, `activo`, `creado`) VALUES (1,'Básico','basico','Ideal para cafeterías y comedores que quieren su menú digital.',149.00,1490.00,60,12,1,3,'[\"Menú digital ilimitado en visitas\",\"QR general y por mesa\",\"Fotos y descripciones\",\"Pedidos por WhatsApp\",\"1 sucursal\",\"Soporte por correo\"]',0,1,1,'2026-08-28 07:39:46'),
(2,'Pro','pro','El favorito: pedidos en mesa que llegan directo a la cocina.',299.00,2990.00,250,40,1,10,'[\"Todo lo del plan Básico\",\"Pedidos en mesa en tiempo real\",\"Pantalla de cocina (KDS)\",\"Panel de mesero y caja\",\"Reportes y gráficas\",\"Cupones y promociones\",\"Pedidos para llevar\"]',1,2,1,'2026-08-28 07:39:46'),
(3,'Premium','premium','Para restaurantes con varias sucursales y operación completa.',549.00,5490.00,0,0,5,0,'[\"Todo lo del plan Pro\",\"Productos y mesas ilimitados\",\"Hasta 5 sucursales\",\"Delivery con zonas y costos\",\"Programa de puntos\",\"Dominio propio\",\"Respaldos automáticos\",\"Soporte prioritario\"]',0,3,1,'2026-08-28 07:39:46');
/*!40000 ALTER TABLE `plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `platform_settings`
--

DROP TABLE IF EXISTS `platform_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_settings` (
  `clave` varchar(60) NOT NULL,
  `valor` mediumtext DEFAULT NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `platform_settings`
--

LOCK TABLES `platform_settings` WRITE;
/*!40000 ALTER TABLE `platform_settings` DISABLE KEYS */;
INSERT INTO `platform_settings` (`clave`, `valor`) VALUES ('aviso_vencimiento_dias','7'),
('backup_semanal','1'),
('cta_texto','Quiero mi menú digital'),
('demo_slug','la-terraza-gold'),
('descripcion','El menú digital que hace que tu restaurante se vea como lo que es: una experiencia de alta cocina. Tus clientes escanean, piden y tú lo ves al instante.'),
('direccion','Ciudad de Guatemala'),
('email_contacto','admin@plataforma.gt'),
('eslogan','Menús QR de lujo con pedidos en tiempo real'),
('hero_subtitulo','Menú QR elegante, pedidos que llegan solos a la cocina y control total desde tu celular.'),
('hero_titulo','Tu carta, convertida en experiencia'),
('nombre_plataforma','MenúGold'),
('telefono',''),
('whatsapp','');
/*!40000 ALTER TABLE `platform_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_modifiers`
--

DROP TABLE IF EXISTS `product_modifiers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_modifiers` (
  `product_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`product_id`,`group_id`),
  KEY `ix_pm_group` (`group_id`),
  CONSTRAINT `fk_pm_group` FOREIGN KEY (`group_id`) REFERENCES `modifier_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pm_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_modifiers`
--

LOCK TABLES `product_modifiers` WRITE;
/*!40000 ALTER TABLE `product_modifiers` DISABLE KEYS */;
INSERT INTO `product_modifiers` (`product_id`, `group_id`, `orden`) VALUES (1,3,0),
(1,4,1),
(2,3,0),
(2,4,1),
(3,3,0),
(3,4,1),
(4,3,0),
(4,4,1),
(5,3,0),
(5,4,1),
(6,2,0),
(6,4,1),
(7,2,0),
(7,4,1),
(8,2,0),
(8,4,1),
(9,2,0),
(9,4,1),
(10,2,0),
(10,4,1),
(11,2,0),
(11,4,1),
(12,2,0),
(12,4,1),
(33,5,0),
(34,5,0),
(35,5,0),
(36,5,0),
(37,5,0);
/*!40000 ALTER TABLE `product_modifiers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `nombre` varchar(160) NOT NULL,
  `nombre_en` varchar(160) NOT NULL DEFAULT '',
  `descripcion` text DEFAULT NULL,
  `descripcion_en` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `precio_promo` decimal(10,2) DEFAULT NULL,
  `costo` decimal(10,2) DEFAULT NULL,
  `imagen` varchar(190) NOT NULL DEFAULT '',
  `imagenes` text DEFAULT NULL,
  `sku` varchar(40) NOT NULL DEFAULT '',
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `agotado` tinyint(1) NOT NULL DEFAULT 0,
  `destacado` tinyint(1) NOT NULL DEFAULT 0,
  `tiempo_prep` int(11) NOT NULL DEFAULT 15,
  `calorias` int(11) DEFAULT NULL,
  `etiquetas` varchar(190) NOT NULL DEFAULT '',
  `alergenos` varchar(255) NOT NULL DEFAULT '',
  `estacion` enum('cocina','bar','postres') NOT NULL DEFAULT 'cocina',
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `dias` varchar(30) NOT NULL DEFAULT '',
  `es_combo` tinyint(1) NOT NULL DEFAULT 0,
  `combo_items` text DEFAULT NULL,
  `vendidos` int(10) unsigned NOT NULL DEFAULT 0,
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_prod_rest` (`restaurant_id`,`activo`,`orden`),
  KEY `ix_prod_cat` (`category_id`),
  KEY `ix_prod_destacado` (`restaurant_id`,`destacado`),
  CONSTRAINT `fk_prod_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_prod_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` (`id`, `restaurant_id`, `category_id`, `nombre`, `nombre_en`, `descripcion`, `descripcion_en`, `precio`, `precio_promo`, `costo`, `imagen`, `imagenes`, `sku`, `orden`, `activo`, `agotado`, `destacado`, `tiempo_prep`, `calorias`, `etiquetas`, `alergenos`, `estacion`, `hora_inicio`, `hora_fin`, `dias`, `es_combo`, `combo_items`, `vendidos`, `creado`, `actualizado`) VALUES (1,1,1,'Huevos rancheros de la abuela','Grandma\'s ranch eggs','Huevos de campo sobre tortilla de maíz criollo, salsa de chile guaque y queso fresco de Zacapa.',NULL,78.00,NULL,NULL,'demo/huevos-rancheros-de-la-abuela.jpg',NULL,'',0,1,0,1,14,520,'popular','huevo,lacteos','cocina',NULL,NULL,'',0,NULL,105,'2026-08-28 01:39:47',NULL),
(2,1,1,'Plato chapín completo','Full Guatemalan breakfast','Frijol volteado, plátano frito, crema, queso, huevos al gusto y tortillas hechas a mano.',NULL,92.00,NULL,NULL,'demo/plato-chapin-completo.jpg',NULL,'',1,1,0,0,16,740,'popular','huevo,lacteos','cocina',NULL,NULL,'',0,NULL,120,'2026-08-28 01:39:47',NULL),
(3,1,1,'Tostada de aguacate y salmón','Avocado & salmon toast','Masa madre de la casa, aguacate hass, salmón curado y eneldo.',NULL,118.00,NULL,NULL,'demo/tostada-de-aguacate-y-salmon.jpg',NULL,'',2,1,0,1,12,480,'nuevo','gluten,pescado','cocina',NULL,NULL,'',0,NULL,54,'2026-08-28 01:39:47',NULL),
(4,1,1,'Pancakes de banano y cardamomo','Banana pancakes','Tres pancakes esponjosos con miel de caña y nuez caramelizada.',NULL,74.00,NULL,NULL,'demo/pancakes-de-banano-y-cardamomo.jpg',NULL,'',3,1,0,0,15,620,'vegetariano','gluten,huevo,lacteos','cocina',NULL,NULL,'',0,NULL,77,'2026-08-28 01:39:47',NULL),
(5,1,1,'Bowl de frutas y granola','Fruit & granola bowl','Frutas de temporada, yogurt griego, granola artesanal y miel de abeja.',NULL,68.00,NULL,NULL,'demo/bowl-de-frutas-y-granola.jpg',NULL,'',4,1,0,0,6,340,'vegetariano,sin_gluten','lacteos,frutos secos','cocina',NULL,NULL,'',0,NULL,54,'2026-08-28 01:39:47',NULL),
(6,1,2,'Ceviche del chef','Chef\'s ceviche','Corvina fresca del Pacífico, leche de tigre de chile cobanero, camote y cilantro criollo.',NULL,145.00,NULL,NULL,'demo/ceviche-del-chef.jpg',NULL,'',0,1,0,1,12,280,'nuevo,popular','pescado','cocina',NULL,NULL,'',0,NULL,188,'2026-08-28 01:39:47',NULL),
(7,1,2,'Tártar de atún aleta amarilla','Yellowfin tuna tartare','Atún curado, aguacate, ajonjolí tostado y aceite de cilantro.',NULL,165.00,NULL,NULL,'demo/tartar-de-atun-aleta-amarilla.jpg',NULL,'',1,1,0,1,10,310,'popular','pescado,sesamo','cocina',NULL,NULL,'',0,NULL,114,'2026-08-28 01:39:47',NULL),
(8,1,2,'Carpaccio de res angus','Angus beef carpaccio','Láminas finas, alcaparras, rúcula y lascas de parmesano curado 24 meses.',NULL,138.00,NULL,NULL,'demo/carpaccio-de-res-angus.jpg',NULL,'',2,1,0,0,9,290,'','lacteos','cocina',NULL,NULL,'',0,NULL,171,'2026-08-28 01:39:47',NULL),
(9,1,2,'Tostadas de tuétano','Bone marrow toast','Tuétano asado al fuego, chimichurri de hierbas y sal de gusano.',NULL,128.00,NULL,NULL,'demo/tostadas-de-tuetano.jpg',NULL,'',3,1,0,0,18,460,'nuevo','gluten','cocina',NULL,NULL,'',0,NULL,91,'2026-08-28 01:39:47',NULL),
(10,1,2,'Croquetas de plátano y queso','Plantain croquettes','Plátano macho relleno de queso de capas, sobre crema agria de la casa.',NULL,88.00,NULL,NULL,'demo/croquetas-de-platano-y-queso.jpg',NULL,'',4,1,0,0,12,420,'vegetariano,popular','gluten,lacteos,huevo','cocina',NULL,NULL,'',0,NULL,33,'2026-08-28 01:39:47',NULL),
(11,1,2,'Hongos silvestres al ajillo','Wild mushrooms','Hongos de temporada de San Juan Sacatepéquez, ajo confitado y perejil.',NULL,96.00,NULL,NULL,'demo/hongos-silvestres-al-ajillo.jpg',NULL,'',5,1,0,0,11,210,'vegano','','cocina',NULL,NULL,'',0,NULL,178,'2026-08-28 01:39:47',NULL),
(12,1,2,'Ostras de la bahía','Fresh oysters','Media docena con mignonette de vinagre de jamaica.',NULL,185.00,NULL,NULL,'demo/ostras-de-la-bahia.jpg',NULL,'',6,1,1,0,8,90,'','mariscos','cocina',NULL,NULL,'',0,NULL,26,'2026-08-28 01:39:47',NULL),
(13,1,3,'Kak\'ik de pavo','Traditional turkey broth','Caldo ceremonial q\'eqchi\' de pavo criollo, achiote y chile cobanero. Nuestra receta de siempre.',NULL,132.00,NULL,NULL,'demo/kak-ik-de-pavo.jpg',NULL,'',0,1,0,1,20,380,'popular,picante','','cocina',NULL,NULL,'',0,NULL,28,'2026-08-28 01:39:47',NULL),
(14,1,3,'Crema de güisquil y cardamomo','Chayote cream soup','Aterciopelada, con aceite de semilla de ayote tostada.',NULL,78.00,NULL,NULL,'demo/crema-de-guisquil-y-cardamomo.jpg',NULL,'',1,1,0,0,12,240,'vegetariano','lacteos','cocina',NULL,NULL,'',0,NULL,144,'2026-08-28 01:39:47',NULL),
(15,1,3,'Ensalada de la terraza','House garden salad','Hojas del huerto, tomate riñón, aguacate, semillas y vinagreta de limón persa.',NULL,86.00,NULL,NULL,'demo/ensalada-de-la-terraza.jpg',NULL,'',2,1,0,0,8,190,'vegano,sin_gluten','','cocina',NULL,NULL,'',0,NULL,126,'2026-08-28 01:39:47',NULL),
(16,1,3,'Ensalada César con pollo al carbón','Caesar salad','Lechuga romana, aderezo clásico, crotones de masa madre y parmesano.',NULL,108.00,NULL,NULL,'demo/ensalada-cesar-con-pollo-al-carbon.jpg',NULL,'',3,1,0,0,12,430,'popular','gluten,lacteos,huevo,pescado','cocina',NULL,NULL,'',0,NULL,145,'2026-08-28 01:39:47',NULL),
(17,1,3,'Caprese de queso fresco','Caprese salad','Tomates heirloom, queso fresco de Zacapa, albahaca y aceite de oliva.',NULL,94.00,NULL,NULL,'demo/caprese-de-queso-fresco.jpg',NULL,'',4,1,0,0,7,260,'vegetariano,sin_gluten','lacteos','cocina',NULL,NULL,'',0,NULL,129,'2026-08-28 01:39:47',NULL),
(18,1,4,'Lomito Wellington','Beef Wellington','Res premium en hojaldre de mantequilla, duxelles de hongos y salsa de vino tinto. 350 g.',NULL,325.00,285.00,NULL,'demo/lomito-wellington.jpg',NULL,'',0,1,0,1,32,890,'popular','gluten,huevo,lacteos','cocina',NULL,NULL,'',0,NULL,117,'2026-08-28 01:39:47',NULL),
(19,1,4,'Cordero en cocción lenta','Slow-cooked lamb','Ocho horas al horno, puré de camote y jugo de romero.',NULL,298.00,NULL,NULL,'demo/cordero-en-coccion-lenta.jpg',NULL,'',1,1,0,1,28,780,'','lacteos','cocina',NULL,NULL,'',0,NULL,24,'2026-08-28 01:39:47',NULL),
(20,1,4,'Pollo al carbón con recado','Charcoal chicken','Medio pollo criollo marinado 24 horas, recado rojo y verduras al fuego.',NULL,178.00,NULL,NULL,'demo/pollo-al-carbon-con-recado.jpg',NULL,'',2,1,0,0,26,690,'popular','','cocina',NULL,NULL,'',0,NULL,166,'2026-08-28 01:39:47',NULL),
(21,1,4,'Pescado del día a la talla','Catch of the day','Según la pesca: abierto, untado de adobo y terminado a las brasas.',NULL,265.00,NULL,NULL,'demo/pescado-del-dia-a-la-talla.jpg',NULL,'',3,1,0,0,24,520,'','pescado','cocina',NULL,NULL,'',0,NULL,22,'2026-08-28 01:39:47',NULL),
(22,1,4,'Costilla de cerdo glaseada','Glazed pork ribs','Glaseada con miel de caña y chile pasa, sobre puré de frijol.',NULL,215.00,NULL,NULL,'demo/costilla-de-cerdo-glaseada.jpg',NULL,'',4,1,0,0,30,830,'picante','','cocina',NULL,NULL,'',0,NULL,78,'2026-08-28 01:39:47',NULL),
(23,1,4,'Risotto de hongos y trufa','Truffle mushroom risotto','Arroz carnaroli, hongos silvestres y aceite de trufa negra.',NULL,195.00,NULL,NULL,'demo/risotto-de-hongos-y-trufa.jpg',NULL,'',5,1,0,1,25,610,'vegetariano,popular','lacteos','cocina',NULL,NULL,'',0,NULL,135,'2026-08-28 01:39:47',NULL),
(24,1,4,'Pepián de res de la casa','Traditional beef pepián','El clásico guatemalteco con carne de res, arroz blanco y tortillas.',NULL,168.00,NULL,NULL,'demo/pepian-de-res-de-la-casa.jpg',NULL,'',6,1,0,0,24,640,'popular','sesamo','cocina',NULL,NULL,'',0,NULL,63,'2026-08-28 01:39:47',NULL),
(25,1,4,'Ravioles de ayote y salvia','Pumpkin ravioli','Pasta fresca hecha en casa, mantequilla noisette y avellana.',NULL,172.00,NULL,NULL,'demo/ravioles-de-ayote-y-salvia.jpg',NULL,'',7,1,0,0,20,570,'vegetariano','gluten,huevo,lacteos,frutos secos','cocina',NULL,NULL,'',0,NULL,78,'2026-08-28 01:39:47',NULL),
(26,1,4,'Camarones al ajillo','Garlic shrimp','Camarón jumbo, ajo confitado, chile guaque y pan de la casa.',NULL,245.00,NULL,NULL,'demo/camarones-al-ajillo.jpg',NULL,'',8,1,0,0,18,460,'','mariscos,gluten','cocina',NULL,NULL,'',0,NULL,89,'2026-08-28 01:39:47',NULL),
(27,1,4,'Hamburguesa Gold','Gold burger','Blend de res 200 g, queso de capas, tocino artesanal y pan brioche.',NULL,158.00,NULL,NULL,'demo/hamburguesa-gold.jpg',NULL,'',9,1,0,0,18,920,'popular','gluten,lacteos,huevo','cocina',NULL,NULL,'',0,NULL,63,'2026-08-28 01:39:47',NULL),
(28,1,5,'Crème brûlée de vainilla','Vanilla crème brûlée','Vainilla de Alta Verapaz y azúcar quemada al momento.',NULL,82.00,NULL,NULL,'demo/creme-brulee-de-vainilla.jpg',NULL,'',0,1,0,1,8,390,'popular','lacteos,huevo','postres',NULL,NULL,'',0,NULL,155,'2026-08-28 01:39:47',NULL),
(29,1,5,'Volcán de chocolate 70%','Chocolate lava cake','Cacao guatemalteco, centro líquido y helado de canela.',NULL,88.00,NULL,NULL,'demo/volcan-de-chocolate-70.jpg',NULL,'',1,1,0,0,12,520,'popular','gluten,lacteos,huevo','postres',NULL,NULL,'',0,NULL,113,'2026-08-28 01:39:47',NULL),
(30,1,5,'Tres leches de la casa','Tres leches cake','Esponjoso, con merengue tostado y fresas de Sacatepéquez.',NULL,76.00,NULL,NULL,'demo/tres-leches-de-la-casa.jpg',NULL,'',2,1,0,0,6,480,'','gluten,lacteos,huevo','postres',NULL,NULL,'',0,NULL,180,'2026-08-28 01:39:47',NULL),
(31,1,5,'Helado artesanal','Artisanal ice cream','Tres bolas: vainilla, cardamomo o cacao. Hecho en casa.',NULL,58.00,NULL,NULL,'demo/helado-artesanal.jpg',NULL,'',3,1,0,0,3,280,'sin_gluten','lacteos','postres',NULL,NULL,'',0,NULL,166,'2026-08-28 01:39:47',NULL),
(32,1,5,'Tarta de limón persa','Persian lime tart','Base de galleta de mantequilla y merengue italiano.',NULL,74.00,NULL,NULL,'demo/tarta-de-limon-persa.jpg',NULL,'',4,1,0,0,6,410,'','gluten,lacteos,huevo','postres',NULL,NULL,'',0,NULL,15,'2026-08-28 01:39:47',NULL),
(33,1,6,'Café de Antigua','Antigua coffee','Tostado de la semana, preparado en prensa francesa.',NULL,32.00,NULL,NULL,'demo/cafe-de-antigua.jpg',NULL,'',0,1,0,0,5,5,'popular','','bar',NULL,NULL,'',0,NULL,87,'2026-08-28 01:39:47',NULL),
(34,1,6,'Old Fashioned de ron añejo','Aged rum old fashioned','Ron guatemalteco 12 años, amargo de cacao y naranja quemada.',NULL,95.00,NULL,NULL,'demo/old-fashioned-de-ron-anejo.jpg',NULL,'',1,1,0,1,6,210,'popular','','bar',NULL,NULL,'',0,NULL,142,'2026-08-28 01:39:47',NULL),
(35,1,6,'Margarita de rosa de jamaica','Hibiscus margarita','Tequila, jamaica de la casa y sal de gusano en el borde.',NULL,88.00,NULL,NULL,'demo/margarita-de-rosa-de-jamaica.jpg',NULL,'',2,1,0,0,5,190,'nuevo','','bar',NULL,NULL,'',0,NULL,36,'2026-08-28 01:39:47',NULL),
(36,1,6,'Limonada con hierbabuena','Mint lemonade','Limón persa, hierbabuena del huerto y hielo frappé.',NULL,38.00,NULL,NULL,'demo/limonada-con-hierbabuena.jpg',NULL,'',3,1,0,0,4,90,'vegano','','bar',NULL,NULL,'',0,NULL,29,'2026-08-28 01:39:47',NULL),
(37,1,6,'Copa de vino tinto','Glass of red wine','Selección del sommelier, consulta la etiqueta del día.',NULL,78.00,NULL,NULL,'demo/copa-de-vino-tinto.jpg',NULL,'',4,1,0,0,2,125,'','sulfitos','bar',NULL,NULL,'',0,NULL,83,'2026-08-28 01:39:47',NULL),
(38,2,7,'Espresso doble','','Huehuetenango lavado, notas de panela y cacao.',NULL,18.00,NULL,NULL,'demo/espresso-doble.jpg',NULL,'',0,1,0,1,3,NULL,'popular','','bar',NULL,NULL,'',0,NULL,196,'2026-08-28 01:39:48',NULL),
(39,2,7,'Cappuccino','','Leche texturizada y arte latte de la casa.',NULL,26.00,NULL,NULL,'demo/cappuccino.jpg',NULL,'',1,1,0,1,5,NULL,'','','bar',NULL,NULL,'',0,NULL,85,'2026-08-28 01:39:48',NULL),
(40,2,7,'Latte de vainilla','','Vainilla natural de Alta Verapaz.',NULL,30.00,NULL,NULL,'demo/latte-de-vainilla.jpg',NULL,'',2,1,0,0,5,NULL,'nuevo','','bar',NULL,NULL,'',0,NULL,169,'2026-08-28 01:39:48',NULL),
(41,2,7,'Cold brew 12 h','','Extracción en frío, servido sobre hielo.',NULL,28.00,NULL,NULL,'demo/cold-brew-12-h.jpg',NULL,'',3,1,0,0,2,NULL,'popular','','bar',NULL,NULL,'',0,NULL,81,'2026-08-28 01:39:48',NULL),
(42,2,7,'Chocolate de metate','','Cacao guatemalteco molido en piedra.',NULL,32.00,NULL,NULL,'demo/chocolate-de-metate.jpg',NULL,'',4,1,0,0,6,NULL,'','','bar',NULL,NULL,'',0,NULL,96,'2026-08-28 01:39:48',NULL),
(43,2,8,'Concha de masa madre','','Fermentación de 24 horas, cubierta de vainilla.',NULL,16.00,NULL,NULL,'demo/concha-de-masa-madre.jpg',NULL,'',0,1,0,1,1,NULL,'popular','','postres',NULL,NULL,'',0,NULL,12,'2026-08-28 01:39:48',NULL),
(44,2,8,'Croissant de mantequilla','','Hojaldre de 27 capas, horneado cada mañana.',NULL,22.00,NULL,NULL,'demo/croissant-de-mantequilla.jpg',NULL,'',1,1,0,1,1,NULL,'','','postres',NULL,NULL,'',0,NULL,53,'2026-08-28 01:39:48',NULL),
(45,2,8,'Pan de banano y nuez','','Con plátano maduro de la costa sur.',NULL,24.00,NULL,NULL,'demo/pan-de-banano-y-nuez.jpg',NULL,'',2,1,0,0,1,NULL,'sin_gluten','','postres',NULL,NULL,'',0,NULL,174,'2026-08-28 01:39:48',NULL),
(46,2,8,'Cardamomo roll','','Nuestro pan más pedido los domingos.',NULL,28.00,NULL,NULL,'demo/cardamomo-roll.jpg',NULL,'',3,1,0,0,1,NULL,'nuevo,popular','','postres',NULL,NULL,'',0,NULL,11,'2026-08-28 01:39:48',NULL),
(47,2,9,'Desayuno chapín','','Huevos al gusto, frijol volteado, plátano y queso fresco.',NULL,58.00,NULL,NULL,'demo/desayuno-chapin.jpg',NULL,'',0,1,0,1,12,NULL,'popular','','cocina',NULL,NULL,'',0,NULL,99,'2026-08-28 01:39:48',NULL),
(48,2,9,'Tostada de aguacate','','Masa madre, aguacate hass, huevo pochado y chile cobanero.',NULL,62.00,NULL,NULL,'demo/tostada-de-aguacate.jpg',NULL,'',1,1,0,1,10,NULL,'vegetariano','','cocina',NULL,NULL,'',0,NULL,134,'2026-08-28 01:39:48',NULL),
(49,2,9,'Granola de la casa','','Yogurt natural, frutas de temporada y miel de abeja.',NULL,48.00,NULL,NULL,'demo/granola-de-la-casa.jpg',NULL,'',2,1,0,0,4,NULL,'vegetariano','','cocina',NULL,NULL,'',0,NULL,108,'2026-08-28 01:39:48',NULL);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotions`
--

DROP TABLE IF EXISTS `promotions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` int(10) unsigned NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) NOT NULL DEFAULT '',
  `tipo` enum('descuento','2x1','combo','precio_fijo') NOT NULL DEFAULT 'descuento',
  `valor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `product_ids` varchar(500) NOT NULL DEFAULT '',
  `category_ids` varchar(500) NOT NULL DEFAULT '',
  `imagen` varchar(190) NOT NULL DEFAULT '',
  `desde` date DEFAULT NULL,
  `hasta` date DEFAULT NULL,
  `dias` varchar(30) NOT NULL DEFAULT '',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_promo_rest` (`restaurant_id`,`activo`),
  CONSTRAINT `fk_promo_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotions`
--

LOCK TABLES `promotions` WRITE;
/*!40000 ALTER TABLE `promotions` DISABLE KEYS */;
INSERT INTO `promotions` (`id`, `restaurant_id`, `nombre`, `descripcion`, `tipo`, `valor`, `product_ids`, `category_ids`, `imagen`, `desde`, `hasta`, `dias`, `activo`, `orden`, `creado`) VALUES (1,1,'Martes de 2x1 en cócteles','Pide un cóctel de autor y el segundo va por nuestra cuenta.','2x1',0.00,'','','','2026-08-08','2026-10-27','2',1,0,'2026-08-28 01:39:47'),
(2,1,'Menú del día · 20% en entradas','De lunes a viernes antes de las 3:00 pm.','descuento',20.00,'','2','','2026-08-18','2026-11-26','1,2,3,4,5',1,1,'2026-08-28 01:39:47'),
(3,1,'Postre de cortesía','En pedidos mayores a Q400, el postre va incluido.','combo',0.00,'','','','2026-08-23','2026-10-12','',1,2,'2026-08-28 01:39:47');
/*!40000 ALTER TABLE `promotions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `restaurant_settings`
--

DROP TABLE IF EXISTS `restaurant_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurant_settings` (
  `restaurant_id` int(10) unsigned NOT NULL,
  `clave` varchar(60) NOT NULL,
  `valor` mediumtext DEFAULT NULL,
  PRIMARY KEY (`restaurant_id`,`clave`),
  CONSTRAINT `fk_rs_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `restaurant_settings`
--

LOCK TABLES `restaurant_settings` WRITE;
/*!40000 ALTER TABLE `restaurant_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `restaurant_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `restaurants`
--

DROP TABLE IF EXISTS `restaurants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurants` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `eslogan` varchar(180) NOT NULL DEFAULT '',
  `descripcion` text DEFAULT NULL,
  `plan_id` int(10) unsigned DEFAULT NULL,
  `estado` enum('activo','suspendido','prueba') NOT NULL DEFAULT 'prueba',
  `vence_el` date DEFAULT NULL,
  `dominio` varchar(190) DEFAULT NULL,
  `logo` varchar(190) NOT NULL DEFAULT '',
  `portada` varchar(190) NOT NULL DEFAULT '',
  `tema` varchar(30) NOT NULL DEFAULT 'negro-oro',
  `color_primario` varchar(9) NOT NULL DEFAULT '#D4AF37',
  `color_fondo` varchar(9) NOT NULL DEFAULT '#141414',
  `tipografia` enum('clasica','moderna','editorial') NOT NULL DEFAULT 'clasica',
  `moneda` varchar(6) NOT NULL DEFAULT 'GTQ',
  `simbolo` varchar(4) NOT NULL DEFAULT 'Q',
  `impuesto_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `impuesto_incluido` tinyint(1) NOT NULL DEFAULT 1,
  `propina_sugerida` varchar(60) NOT NULL DEFAULT '[0,10,15]',
  `telefono` varchar(30) NOT NULL DEFAULT '',
  `whatsapp` varchar(30) NOT NULL DEFAULT '',
  `email` varchar(190) NOT NULL DEFAULT '',
  `direccion` varchar(255) NOT NULL DEFAULT '',
  `mapa_lat` decimal(10,7) DEFAULT NULL,
  `mapa_lng` decimal(10,7) DEFAULT NULL,
  `facebook` varchar(190) NOT NULL DEFAULT '',
  `instagram` varchar(190) NOT NULL DEFAULT '',
  `tiktok` varchar(190) NOT NULL DEFAULT '',
  `google_reviews` varchar(255) NOT NULL DEFAULT '',
  `link_pago` varchar(255) NOT NULL DEFAULT '',
  `datos_bancarios` text DEFAULT NULL,
  `modos_pedido` varchar(120) NOT NULL DEFAULT 'consulta,mesa',
  `metodos_pago` varchar(120) NOT NULL DEFAULT 'efectivo,tarjeta',
  `idioma` varchar(5) NOT NULL DEFAULT 'es',
  `idiomas` varchar(30) NOT NULL DEFAULT 'es',
  `abierto_modo` enum('auto','abierto','cerrado') NOT NULL DEFAULT 'auto',
  `mensaje_bienvenida` varchar(255) NOT NULL DEFAULT '',
  `mensaje_pie` varchar(255) NOT NULL DEFAULT '',
  `seo_title` varchar(190) NOT NULL DEFAULT '',
  `seo_desc` varchar(255) NOT NULL DEFAULT '',
  `og_image` varchar(190) NOT NULL DEFAULT '',
  `tiempo_prep_min` int(11) NOT NULL DEFAULT 20,
  `pedido_minimo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notas_activas` tinyint(1) NOT NULL DEFAULT 1,
  `demo` tinyint(1) NOT NULL DEFAULT 0,
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rest_slug` (`slug`),
  UNIQUE KEY `uq_rest_dominio` (`dominio`),
  KEY `ix_rest_estado` (`estado`),
  KEY `ix_rest_plan` (`plan_id`),
  CONSTRAINT `fk_rest_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `restaurants`
--

LOCK TABLES `restaurants` WRITE;
/*!40000 ALTER TABLE `restaurants` DISABLE KEYS */;
INSERT INTO `restaurants` (`id`, `slug`, `nombre`, `eslogan`, `descripcion`, `plan_id`, `estado`, `vence_el`, `dominio`, `logo`, `portada`, `tema`, `color_primario`, `color_fondo`, `tipografia`, `moneda`, `simbolo`, `impuesto_pct`, `impuesto_incluido`, `propina_sugerida`, `telefono`, `whatsapp`, `email`, `direccion`, `mapa_lat`, `mapa_lng`, `facebook`, `instagram`, `tiktok`, `google_reviews`, `link_pago`, `datos_bancarios`, `modos_pedido`, `metodos_pago`, `idioma`, `idiomas`, `abierto_modo`, `mensaje_bienvenida`, `mensaje_pie`, `seo_title`, `seo_desc`, `og_image`, `tiempo_prep_min`, `pedido_minimo`, `notas_activas`, `demo`, `creado`, `actualizado`) VALUES (1,'la-terraza-gold','La Terraza Gold','Cocina de autor · Antigua Guatemala','Una terraza con vista al volcán donde la cocina guatemalteca se sirve con técnica contemporánea. Producto local, fuego vivo y una carta que cambia con la estación.',3,'activo','2027-07-28',NULL,'demo/logo-la-terraza-gold.jpg','demo/portada-la-terraza-gold.jpg','negro-oro','#D4AF37','#141414','clasica','GTQ','Q',12.00,1,'[0,10,15,20]','+502 7832 4500','50278324500','reservas@laterrazagold.gt','5a Avenida Norte #12, Antigua Guatemala',14.5619000,-90.7343000,'https://facebook.com/laterrazagold','https://instagram.com/laterrazagold','','https://g.page/r/laterrazagold/review','','Banco Industrial\nCuenta monetaria 123-456789-0\nLa Terraza Gold, S.A.\nNIT 1234567-8','consulta,mesa,llevar,delivery','efectivo,tarjeta,transferencia','es','es,en','abierto','Bienvenido a nuestra mesa. Tómate tu tiempo: aquí todo se cocina al momento.','Gracias por acompañarnos. Que vuelvas pronto.','La Terraza Gold · Menú de alta cocina en Antigua Guatemala','Descubre la carta de La Terraza Gold: cocina de autor, fuego vivo y producto local en el corazón de Antigua. Pide desde tu mesa escaneando el QR.','demo/portada-la-terraza-gold.jpg',22,0.00,1,1,'2026-08-28 01:39:46',NULL),
(2,'cafe-central','Café Central','Tostaduría & panadería · Ciudad de Guatemala','Café de origen guatemalteco tostado cada semana, pan de masa madre y desayunos todo el día.',2,'activo','2027-01-28',NULL,'demo/logo-cafe-central.jpg','demo/portada-cafe-central.jpg','marfil','#8C7A3F','#F7F3EA','moderna','GTQ','Q',12.00,1,'[0,10,15]','+502 2360 8877','50223608877','hola@cafecentral.gt','Zona 4, 4 Grados Norte, Ciudad de Guatemala',NULL,NULL,'','https://instagram.com/cafecentralgt','','','','Banrural\nCuenta monetaria 987-654321-0\nCafé Central','consulta,mesa,llevar,whatsapp','efectivo,tarjeta','es','es','abierto','Buenos días. El café de hoy es un Huehuetenango lavado.','','Café Central · Café de origen y panadería en Ciudad de Guatemala','Café de origen guatemalteco, pan de masa madre y desayunos todo el día en 4 Grados Norte.','demo/portada-cafe-central.jpg',10,0.00,1,0,'2026-08-28 01:39:47',NULL);
/*!40000 ALTER TABLE `restaurants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedules`
--

DROP TABLE IF EXISTS `schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` int(10) unsigned NOT NULL,
  `dia` tinyint(4) NOT NULL,
  `abre` time NOT NULL DEFAULT '08:00:00',
  `cierra` time NOT NULL DEFAULT '22:00:00',
  `cerrado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_horario` (`restaurant_id`,`dia`),
  CONSTRAINT `fk_hor_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedules`
--

LOCK TABLES `schedules` WRITE;
/*!40000 ALTER TABLE `schedules` DISABLE KEYS */;
INSERT INTO `schedules` (`id`, `restaurant_id`, `dia`, `abre`, `cierra`, `cerrado`) VALUES (1,1,0,'07:00:00','22:00:00',0),
(2,1,1,'12:00:00','22:00:00',0),
(3,1,2,'07:00:00','22:00:00',0),
(4,1,3,'07:00:00','22:00:00',0),
(5,1,4,'07:00:00','22:00:00',0),
(6,1,5,'07:00:00','23:30:00',0),
(7,1,6,'07:00:00','23:30:00',0),
(8,2,0,'06:30:00','15:00:00',0),
(9,2,1,'06:30:00','20:00:00',0),
(10,2,2,'06:30:00','20:00:00',0),
(11,2,3,'06:30:00','20:00:00',0),
(12,2,4,'06:30:00','20:00:00',0),
(13,2,5,'06:30:00','20:00:00',0),
(14,2,6,'06:30:00','20:00:00',0);
/*!40000 ALTER TABLE `schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tables`
--

DROP TABLE IF EXISTS `tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tables` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` int(10) unsigned NOT NULL,
  `zone_id` int(10) unsigned DEFAULT NULL,
  `nombre` varchar(40) NOT NULL,
  `capacidad` int(11) NOT NULL DEFAULT 4,
  `estado` enum('libre','ocupada','cuenta','llamada') NOT NULL DEFAULT 'libre',
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `abierta_desde` datetime DEFAULT NULL,
  `mesero_id` int(10) unsigned DEFAULT NULL,
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_tbl_rest` (`restaurant_id`,`activo`,`orden`),
  KEY `fk_tbl_zone` (`zone_id`),
  CONSTRAINT `fk_tbl_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tbl_zone` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tables`
--

LOCK TABLES `tables` WRITE;
/*!40000 ALTER TABLE `tables` DISABLE KEYS */;
INSERT INTO `tables` (`id`, `restaurant_id`, `zone_id`, `nombre`, `capacidad`, `estado`, `orden`, `activo`, `abierta_desde`, `mesero_id`, `creado`) VALUES (1,1,1,'Mesa 1',4,'libre',1,1,NULL,NULL,'2026-08-28 01:39:47'),
(2,1,1,'Mesa 2',4,'libre',2,1,NULL,NULL,'2026-08-28 01:39:47'),
(3,1,1,'Mesa 3',4,'libre',3,1,NULL,NULL,'2026-08-28 01:39:47'),
(4,1,1,'Mesa 4',4,'libre',4,1,NULL,NULL,'2026-08-28 01:39:47'),
(5,1,1,'Mesa 5',4,'libre',5,1,NULL,NULL,'2026-08-28 01:39:47'),
(6,1,2,'Mesa 6',6,'libre',6,1,NULL,NULL,'2026-08-28 01:39:47'),
(7,1,2,'Mesa 7',6,'libre',7,1,NULL,NULL,'2026-08-28 01:39:47'),
(8,1,2,'Mesa 8',6,'libre',8,1,NULL,NULL,'2026-08-28 01:39:47'),
(9,1,2,'Mesa 9',6,'libre',9,1,NULL,NULL,'2026-08-28 01:39:47'),
(10,1,2,'Mesa 10',6,'libre',10,1,NULL,NULL,'2026-08-28 01:39:47'),
(11,1,3,'Mesa 11',2,'libre',11,1,NULL,NULL,'2026-08-28 01:39:47'),
(12,1,3,'Mesa 12',2,'libre',12,1,NULL,NULL,'2026-08-28 01:39:47'),
(13,2,4,'Mesa 1',2,'libre',1,1,NULL,NULL,'2026-08-28 01:39:48'),
(14,2,4,'Mesa 2',2,'libre',2,1,NULL,NULL,'2026-08-28 01:39:48'),
(15,2,4,'Mesa 3',2,'libre',3,1,NULL,NULL,'2026-08-28 01:39:48'),
(16,2,4,'Mesa 4',2,'libre',4,1,NULL,NULL,'2026-08-28 01:39:48'),
(17,2,4,'Mesa 5',2,'libre',5,1,NULL,NULL,'2026-08-28 01:39:48'),
(18,2,4,'Mesa 6',2,'libre',6,1,NULL,NULL,'2026-08-28 01:39:48');
/*!40000 ALTER TABLE `tables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` int(10) unsigned DEFAULT NULL,
  `nombre` varchar(120) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `usuario` varchar(60) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('superadmin','dueno','admin','cocina','mesero') NOT NULL DEFAULT 'mesero',
  `telefono` varchar(30) NOT NULL DEFAULT '',
  `avatar` varchar(190) NOT NULL DEFAULT '',
  `tema_panel` enum('claro','oscuro','auto') NOT NULL DEFAULT 'auto',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `onboarding` tinyint(1) NOT NULL DEFAULT 0,
  `ultimo_acceso` datetime DEFAULT NULL,
  `ultima_ip` varchar(45) NOT NULL DEFAULT '',
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_email` (`email`),
  UNIQUE KEY `uq_user_usuario` (`usuario`),
  KEY `ix_user_rest` (`restaurant_id`),
  CONSTRAINT `fk_user_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`, `restaurant_id`, `nombre`, `email`, `usuario`, `password_hash`, `rol`, `telefono`, `avatar`, `tema_panel`, `activo`, `onboarding`, `ultimo_acceso`, `ultima_ip`, `creado`) VALUES (2,1,'Mariana Solís','dueno@laterraza.gt','mariana','$argon2id$v=19$m=65536,t=4,p=2$SUVieUNZak04RFNvY0ZzcA$+qfDPdYMv00a3NkOntH/jwN3M5VxYzmdZlJOayJ+QwY','dueno','+502 5544 1122','','auto',1,1,'2026-08-28 03:08:07','127.0.0.1','2026-08-28 01:39:46'),
(3,1,'Cocina · Estación caliente','cocina@laterraza.gt','cocina1','$argon2id$v=19$m=65536,t=4,p=2$RkdyY01lcUxOVko1RkM3MQ$CsZ6SEgk6WuMQc6WgY+8iIEGcLo6pG7t47vGgT7GLrk','cocina','','','auto',1,1,'2026-08-28 03:05:06','127.0.0.1','2026-08-28 01:39:46'),
(4,1,'Diego Ramírez','mesero1@laterraza.gt','mesero1','$argon2id$v=19$m=65536,t=4,p=2$ejRad2tpYXNrRy83NGJERQ$5PxgVWr6Rpfje/EKIDbG1ATTQklxHT6uo4fxeYLW6FM','mesero','','','auto',1,1,'2026-08-28 01:53:48','127.0.0.1','2026-08-28 01:39:46'),
(5,1,'Lucía Pérez','mesero2@laterraza.gt','mesero2','$argon2id$v=19$m=65536,t=4,p=2$SDNCcnN0clA0bngvaktzWg$DbAJHZtoD/jzZYgGiaLF3lqOsYCmpzzOF0kA5pmi5Kw','mesero','','','auto',1,1,NULL,'','2026-08-28 01:39:47'),
(6,2,'Roberto Ixcot','dueno@cafecentral.gt','roberto','$argon2id$v=19$m=65536,t=4,p=2$L0s4VU5PVi8vMEc4QmNJRw$32nlR/392kDryVuYsf7Jdc5FsWDi0vqjxR90TFoV4No','dueno','','','auto',1,1,'2026-08-28 02:02:55','127.0.0.1','2026-08-28 01:39:48'),
(7,2,'Karla Xuyá','barra@cafecentral.gt','karla','$argon2id$v=19$m=65536,t=4,p=2$L0YwUFV1SEYxaldQL2VKSw$4gnQOI+23AY8xF3UDaFY+UC6Xw/mxshP+tVaX/mRe/s','mesero','','','auto',1,1,NULL,'','2026-08-28 01:39:48'),
(8,NULL,'Administrador de plataforma','admin@plataforma.gt','superadmin','$argon2id$v=19$m=65536,t=4,p=2$Z1RNYnNvTy9QaW53RWU5Tg$dqGQSBvhRO3r9yk9wT9TJaZtBCf/g5ALxqTac4UlJ9Q','superadmin','','','auto',1,1,'2026-08-28 01:57:32','127.0.0.1','2026-08-28 07:39:46');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `waiter_calls`
--

DROP TABLE IF EXISTS `waiter_calls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `waiter_calls` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` int(10) unsigned NOT NULL,
  `table_id` int(10) unsigned DEFAULT NULL,
  `mesa_nombre` varchar(40) NOT NULL DEFAULT '',
  `tipo` enum('mesero','cuenta') NOT NULL DEFAULT 'mesero',
  `estado` enum('pendiente','atendida') NOT NULL DEFAULT 'pendiente',
  `nota` varchar(190) NOT NULL DEFAULT '',
  `user_id` int(10) unsigned DEFAULT NULL,
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  `atendida_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_wc_rest` (`restaurant_id`,`estado`,`creado`),
  CONSTRAINT `fk_wc_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `waiter_calls`
--

LOCK TABLES `waiter_calls` WRITE;
/*!40000 ALTER TABLE `waiter_calls` DISABLE KEYS */;
INSERT INTO `waiter_calls` (`id`, `restaurant_id`, `table_id`, `mesa_nombre`, `tipo`, `estado`, `nota`, `user_id`, `creado`, `atendida_en`) VALUES (3,1,3,'Mesa 3','mesero','atendida','',4,'2026-08-28 01:53:33','2026-08-28 01:54:15'),
(4,1,3,'Mesa 3','cuenta','atendida','',NULL,'2026-08-28 01:53:34','2026-08-28 07:54:33');
/*!40000 ALTER TABLE `waiter_calls` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zones`
--

DROP TABLE IF EXISTS `zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `zones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` int(10) unsigned NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_zone_rest` (`restaurant_id`,`orden`),
  CONSTRAINT `fk_zone_rest` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zones`
--

LOCK TABLES `zones` WRITE;
/*!40000 ALTER TABLE `zones` DISABLE KEYS */;
INSERT INTO `zones` (`id`, `restaurant_id`, `nombre`, `orden`) VALUES (1,1,'Terraza',0),
(2,1,'Salón principal',1),
(3,1,'Barra',2),
(4,2,'Salón',0);
/*!40000 ALTER TABLE `zones` ENABLE KEYS */;
UNLOCK TABLES;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

--
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `rate_limits`
--

DROP TABLE IF EXISTS `rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rate_limits` (
  `clave` varchar(40) NOT NULL,
  `contador` int(11) NOT NULL DEFAULT 0,
  `ventana_inicio` datetime NOT NULL,
  `bloqueado_hasta` datetime DEFAULT NULL,
  PRIMARY KEY (`clave`),
  KEY `ix_rl_ventana` (`ventana_inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `remember_tokens`
--

DROP TABLE IF EXISTS `remember_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `remember_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expira` datetime NOT NULL,
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_rt_user` (`user_id`,`token_hash`),
  CONSTRAINT `fk_rt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expira` datetime NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT 0,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_pr_token` (`token_hash`),
  KEY `fk_pr_user` (`user_id`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

SET FOREIGN_KEY_CHECKS = 1;
