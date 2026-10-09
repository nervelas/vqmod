<?php
declare(strict_types=1);
namespace S5\Core;

final class View
{
    /** Renderiza app/views/<name>.php con variables aisladas; devuelve el HTML. */
    public static function capture(string $name, array $vars = []): string
    {
        $file = S5_ROOT . '/app/views/' . $name . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Vista no encontrada: ' . $name);
        }
        $fn = static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            include $__file;
            return (string) ob_get_clean();
        };
        return $fn($file, $vars);
    }

    /** Vista dentro de un layout (layout.php o admin/layout.php). */
    public static function render(string $name, array $vars = [], string $layout = 'layout'): void
    {
        $nonce = Security::nonce();
        $vars += ['nonce' => $nonce];
        $content = self::capture($name, $vars);
        $vars['content'] = $content;
        echo self::capture($layout, $vars);
    }
}
