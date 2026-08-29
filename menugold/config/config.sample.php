<?php
// Este archivo solo se carga desde el arranque de la aplicación.
if (!defined('MG_ROOT')) { http_response_code(404); exit; }
/**
 * MenúGold · configuración.
 * El asistente de /install/ genera este archivo como config/config.php.
 * No lo subas a ningún repositorio: contiene credenciales.
 */
return array(
    'app' => array(
        'name'     => 'MenúGold',
        'url'      => '',                    // vacío = se detecta solo (raíz, subcarpeta o subdominio)
        'debug'    => false,
        'locale'   => 'es',
        'timezone' => 'America/Guatemala',
    ),
    'db' => array(
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'menugold',
        'user' => 'usuario',
        'pass' => 'contrasena',
    ),
    'security' => array(
        'app_key'     => 'CAMBIA-ESTA-CLAVE',  // 64 caracteres aleatorios
        'cron_token'  => 'CAMBIA-ESTE-TOKEN',
        'session_ttl' => 7200,
    ),
    'mail' => array(
        'host'      => '',
        'port'      => 587,
        'user'      => '',
        'pass'      => '',
        'secure'    => 'tls',
        'from'      => '',
        'from_name' => 'MenúGold',
    ),
    'uploads' => array(
        'max_bytes' => 8388608,               // 8 MB por imagen
    ),
);
