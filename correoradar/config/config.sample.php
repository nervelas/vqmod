<?php
/**
 * CorreoRadar - Plantilla de configuración.
 *
 * El instalador (install.php) genera automaticamente config/config.php a partir
 * de este archivo. Solo hay que editarlo a mano si se prefiere instalar sin el
 * asistente o si cambian los datos de la base de datos.
 */
declare(strict_types=1);

// --- Base de datos -----------------------------------------------------------
define('CR_DB_HOST',    '{{DB_HOST}}');
define('CR_DB_NOMBRE',  '{{DB_NOMBRE}}');
define('CR_DB_USUARIO', '{{DB_USUARIO}}');
define('CR_DB_CLAVE',   '{{DB_CLAVE}}');
define('CR_DB_PUERTO',  '{{DB_PUERTO}}');
define('CR_DB_CHARSET', 'utf8mb4');

// --- Claves de seguridad (generadas al instalar; no compartirlas) ------------
define('CR_CLAVE_APP', '{{CLAVE_APP}}');

// --- Entorno -----------------------------------------------------------------
// URL base publica. Vacio = se detecta sola (recomendado en hosting compartido).
define('CR_URL_BASE', '{{URL_BASE}}');

// Zona horaria usada en fechas e informes.
define('CR_ZONA_HORARIA', '{{ZONA_HORARIA}}');

// Modo depuracion: true muestra los errores en pantalla. Dejar en false en produccion.
define('CR_DEBUG', false);

// Proxy de salida opcional para las descargas (formato host:puerto). Normalmente vacio.
define('CR_PROXY', '');
