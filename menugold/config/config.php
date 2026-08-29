<?php
/**
 * MenúGold · Carga de la configuración
 *
 * Este archivo NO se modifica nunca y no guarda ningún dato.
 * Los datos de tu instalación (base de datos, claves, moneda…) viven en
 * config/ajustes.json, que crea el instalador web en /install/.
 *
 * Se hace así a propósito: la aplicación nunca genera ni escribe código PHP.
 * Los antivirus de los hosting compartidos marcan como sospechoso cualquier
 * programa que escriba archivos .php mientras funciona, y con razón: es lo
 * que hacen los instaladores maliciosos. Aquí solo se escribe texto JSON.
 */
declare(strict_types=1);

$archivo = __DIR__ . '/ajustes.json';
if (!is_file($archivo)) {
    return [];
}

$datos = json_decode((string)@file_get_contents($archivo), true);

return is_array($datos) ? $datos : [];
