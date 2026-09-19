<?php
/**
 * Kaptor - Extraer páginas web de un texto.
 *
 * Es la misma herramienta que depurar.php, pero con su propia dirección y su
 * propia entrada en el menú: "Extraer correos" y "Extraer dominios" son dos
 * trabajos distintos y cada uno merece su sitio.
 *
 * Todo el motor vive en depurar.php; aquí solo se fija el modo.
 */
declare(strict_types=1);

$_GET['modo'] = 'webs';
require __DIR__ . '/depurar.php';
