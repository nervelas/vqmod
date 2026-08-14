<?php
/**
 * Configuration sample.
 * The web installer (/install/) generates the real config/config.php from this template.
 * Do NOT commit real credentials. This file is a template only.
 */
return [
    // Database
    'db' => [
        'driver'   => 'mysql',          // mysql (production) | sqlite (local testing only)
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'fuentedevida',
        'user'     => 'root',
        'pass'     => '',
        'charset'  => 'utf8mb4',
        'sqlite_path' => '',            // used only when driver = sqlite
    ],

    // Application
    'app' => [
        'name'     => 'Centro Educativo Cristiano Fuente de Vida',
        'base_url' => '',               // e.g. https://fuentedevida.edu.gt  (empty = auto-detect)
        'env'      => 'production',      // production | development
        'timezone' => 'America/Guatemala',
    ],

    // Security
    'security' => [
        'session_name' => 'fdv_sess',
        // A random secret is written here by the installer.
        'app_key'      => 'CHANGE_ME',
    ],
];
