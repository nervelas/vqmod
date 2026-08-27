<?php
declare(strict_types=1);

namespace App\Core;

final class Vista
{
    /** Datos compartidos con todas las vistas. */
    private static array $compartidos = [];

    public static function compartir(string $clave, mixed $valor): void
    {
        self::$compartidos[$clave] = $valor;
    }

    public static function render(string $vista, array $datos = [], ?string $layout = null): string
    {
        $archivo = RUTA_BASE . '/app/Views/' . str_replace(['..', '\\'], '', $vista) . '.php';
        if (!is_file($archivo)) {
            throw new \RuntimeException('Vista no encontrada: ' . $vista);
        }
        $datos = array_merge(self::$compartidos, $datos);
        $contenido = self::capturar($archivo, $datos);

        if ($layout === null) {
            return $contenido;
        }
        $archivoLayout = RUTA_BASE . '/app/Views/layouts/' . str_replace(['..', '\\', '/'], '', $layout) . '.php';
        if (!is_file($archivoLayout)) {
            return $contenido;
        }
        $datos['contenido'] = $contenido;
        return self::capturar($archivoLayout, $datos);
    }

    public static function parcial(string $vista, array $datos = []): string
    {
        return self::render($vista, $datos, null);
    }

    private static function capturar(string $archivo, array $datos): string
    {
        extract($datos, EXTR_SKIP);
        ob_start();
        try {
            include $archivo;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
