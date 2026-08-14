<?php
/**
 * Sample configuration. The installer writes the real app/config.php.
 * Do NOT store real credentials here.
 */
return [
    'db' => [
        'host'    => 'localhost',
        'port'    => '3306',
        'name'    => 'database_name',
        'user'    => 'database_user',
        'pass'    => 'database_password',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'installed'   => false,
        'base_url'    => '',        // e.g. https://midominio.com  (auto-detected if empty)
        'security_salt' => '',      // random string set by installer
    ],
];
