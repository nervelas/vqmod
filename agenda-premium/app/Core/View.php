<?php
declare(strict_types=1);

namespace App\Core;

/** Vistas PHP con escape por defecto (usa e() en cada salida). */
final class View
{
    /** Datos compartidos con todas las vistas y layouts. */
    private static array $shared = [];

    public static function share(string $key, $value): void
    {
        self::$shared[$key] = $value;
    }

    /** Renderiza app/Views/{$tpl}.php. Si $layout no es null, envuelve el resultado en app/Views/{$layout}.php con $content. */
    public static function render(string $tpl, array $data = [], ?string $layout = null): string
    {
        $out = self::partial($tpl, $data);
        if ($layout !== null) {
            $out = self::partial($layout, array_merge($data, ['content' => $out]));
        }
        return $out;
    }

    public static function partial(string $tpl, array $data = []): string
    {
        if (!preg_match('#^[A-Za-z0-9_/\-]+$#', $tpl)) {
            throw new \InvalidArgumentException('Nombre de vista no válido.');
        }
        $file = APP_ROOT . '/app/Views/' . $tpl . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Vista no encontrada: ' . $tpl);
        }
        $vars = array_merge(self::$shared, $data);
        return (static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                include $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
            return (string) ob_get_clean();
        })($file, $vars);
    }
}
