<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function shared(): array
    {
        return self::$shared;
    }

    public static function render(string $template, array $data = [], string $layout = 'layout/base'): void
    {
        Security::headers();
        header('Content-Type: text/html; charset=utf-8');
        echo self::capture($template, $data, $layout);
        Flash::clearOld();
    }

    public static function capture(string $template, array $data = [], ?string $layout = null): string
    {
        $data = array_merge(self::$shared, $data);
        $content = self::partial($template, $data);
        if ($layout === null) {
            return $content;
        }
        $data['content'] = $content;
        return self::partial($layout, $data);
    }

    public static function partial(string $template, array $data = []): string
    {
        $file = APP_PATH . '/Views/' . str_replace(['..', "\0"], '', $template) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Vista no encontrada: ' . $template);
        }
        extract(array_merge(self::$shared, $data), EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}
