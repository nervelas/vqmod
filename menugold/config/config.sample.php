<?php
/**
 * MenúGold · Configuración
 *
 * Este archivo lo genera el instalador web (/install/).
 * Puedes editarlo a mano si cambias de servidor o de base de datos.
 */
return [
    // --- Base de datos ---
    'db_host'    => 'localhost',
    'db_port'    => 3306,
    'db_name'    => 'NOMBRE_BD',
    'db_user'    => 'USUARIO_BD',
    'db_pass'    => 'CONTRASENA_BD',
    'db_charset' => 'utf8mb4',
    'db_socket'  => '',

    // --- Aplicación ---
    'app_nombre'   => 'MenúGold',
    'zona_horaria' => 'America/Guatemala',
    'moneda'       => 'GTQ',
    'simbolo'      => 'Q',
    'version'      => '1.0.0',

    // Ponlo en true SOLO para diagnosticar un problema. En producción: false.
    'debug'        => false,

    // Clave secreta única de esta instalación (firma tokens y QR de mesa).
    // Si la cambias, los QR de mesa ya impresos dejarán de validar.
    'app_key'      => 'CAMBIA_ESTA_CLAVE',

    // Token del cron. Se usa en:
    //   */10 * * * * curl -s "https://TUDOMINIO/cron/run.php?token=TU_TOKEN"
    'cron_token'   => 'CAMBIA_ESTE_TOKEN',
];
