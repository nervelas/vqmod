<?php
/**
 * Reintenta los documentos que quedaron en contingencia.
 *
 * Programelo en el Cron de cPanel. Para cada 10 minutos, el cron lleva
 * "asterisco barra 10" en el campo de minutos:
 *
 *   [min] * * * * /usr/local/bin/php /home/USUARIO/fel/bin/reintentar_pendientes.php >> /home/USUARIO/fel/storage/logs/cron.log 2>&1
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Fel\Core\Config;
use Fel\Servicio\ContingenciaService;

if (PHP_SAPI !== 'cli') {
    exit('Este script solo se ejecuta desde la linea de comandos.');
}

Config::cargar();
date_default_timezone_set((string) Config::get('zona_horaria', 'America/Guatemala'));

$resultado = (new ContingenciaService())->procesarPendientes(
    (int) ($argv[1] ?? 50)
);

printf(
    "[%s] pendientes procesados: %d | certificados: %d | siguen fallando: %d\n",
    date('Y-m-d H:i:s'),
    $resultado['procesados'],
    $resultado['certificados'],
    $resultado['fallidos']
);

foreach ($resultado['detalle'] as $linea) {
    echo '  ', $linea, "\n";
}

exit($resultado['fallidos'] > 0 ? 1 : 0);
