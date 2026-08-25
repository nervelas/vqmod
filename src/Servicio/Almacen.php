<?php
declare(strict_types=1);

namespace Fel\Servicio;

use Fel\Core\Config;

/**
 * Guarda los XML en disco ademas de la base de datos.
 *
 * SAT exige conservar los DTE durante el plazo de prescripcion (4 anios,
 * ampliable). Tener el XML tambien como archivo facilita respaldos,
 * entregas al contador y auditorias.
 */
final class Almacen
{
    public static function guardarXml(string $contenido, string $tipo, string $identificador, string $sufijo = ''): string
    {
        $base = (string) Config::get('rutas.xml', dirname(__DIR__, 2) . '/storage/xml');
        $dir  = $base . '/' . date('Y') . '/' . date('m');

        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            return '';
        }

        $nombre = sprintf(
            '%s-%s%s.xml',
            strtoupper($tipo),
            preg_replace('/[^A-Za-z0-9_-]/', '', $identificador),
            $sufijo === '' ? '' : '-' . $sufijo
        );

        $ruta = $dir . '/' . $nombre;

        return @file_put_contents($ruta, $contenido) === false ? '' : $ruta;
    }
}
