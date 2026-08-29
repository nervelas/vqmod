<?php
/**
 * Ejemplo de configuración. El instalador escribe config/config.php por ti;
 * este archivo solo sirve de referencia si prefieres hacerlo a mano.
 */
return array(
    'app' => array(
        'name'     => 'MenúGold',
        'url'      => '',                 // vacío = se detecta solo
        'debug'    => false,
        'locale'   => 'es',
        'timezone' => 'America/Guatemala',
    ),
    'db' => array(
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'nombre_de_tu_base',
        'user' => 'usuario',
        'pass' => 'contraseña',
    ),
    'security' => array(
        'app_key'     => 'cadena-larga-y-aleatoria-de-48-caracteres',
        'cron_token'  => 'otra-cadena-larga-para-el-cron',
        'session_ttl' => 7200,
    ),
    'mail' => array(
        'host' => '', 'port' => 587, 'user' => '', 'pass' => '',
        'secure' => 'tls', 'from' => '', 'from_name' => 'Mi restaurante',
    ),
    'uploads' => array('max_bytes' => 8388608),
);
