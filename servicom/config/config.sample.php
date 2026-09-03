<?php
/**
 * Servicom — Archivo de configuracion unico.
 * Copie este archivo como config/config.php y edite los datos.
 * Requiere PHP 8.0 o superior.
 */
declare(strict_types=1);

// ---------------------------------------------------------------------------
// BASE DE DATOS
// ---------------------------------------------------------------------------
define('DB_DRIVER', 'mysql');          // 'mysql' (produccion) o 'sqlite' (pruebas locales)
define('DB_HOST',   'localhost');
define('DB_PORT',   '3306');
define('DB_NAME',   'servicom');
define('DB_USER',   'root');
define('DB_PASS',   '');
define('DB_CHARSET','utf8mb4');
define('DB_FILE',   '');               // solo para DB_DRIVER = sqlite

// ---------------------------------------------------------------------------
// SITIO
// ---------------------------------------------------------------------------
define('SITE_URL',  'https://servicom.gt');   // sin barra final
define('BASE_PATH', '');                      // subcarpeta, ej. '/sitio' (vacio si es la raiz)

// ---------------------------------------------------------------------------
// SEGURIDAD
// ---------------------------------------------------------------------------
define('APP_KEY',   'CAMBIA-ESTA-LLAVE-POR-UNA-ALEATORIA');
define('APP_DEBUG', false);            // false en produccion
define('SESSION_NAME', 'servicom_sess');

// ---------------------------------------------------------------------------
// CORREO (formulario de contacto)
// ---------------------------------------------------------------------------
define('MAIL_TO',   'info@servicom.gt');
define('MAIL_FROM', 'no-reply@servicom.gt');
