<?php
/**
 * EduPortal · Archivo de configuración de ejemplo.
 * El instalador web crea automáticamente config/config.php a partir de esta plantilla.
 * Nunca comparta este archivo ni lo suba a un repositorio público con datos reales.
 */
return [
    'db' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'NOMBRE_DE_LA_BASE',
        'user'     => 'USUARIO',
        'password' => 'CONTRASENA',
        'charset'  => 'utf8mb4',
    ],

    // Zona horaria por defecto (se puede cambiar luego desde el panel).
    'timezone' => 'America/Guatemala',

    // Debe permanecer en false en producción: los errores se registran en storage/logs.
    'debug' => false,

    // Minutos de inactividad antes de cerrar la sesión (en segundos).
    'session_timeout' => 1800,

    // Clave interna generada durante la instalación.
    'app_key' => '',
];
