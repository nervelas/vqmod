<?php
declare(strict_types=1);

namespace Aurea\Core;

/** Plantillas PHP simples. Escapar SIEMPRE con e() en la salida. */
final class View
{
    public static string $title = '';
    public static string $description = '';
    public static string $robots = '';
    public static array $head = [];
    public static array $bodyClass = [];

    public static function render(string $tpl, array $data = []): string
    {
        $file = AUREA_ROOT . '/app/Views/' . $tpl . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Plantilla no encontrada: ' . $tpl);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }

    /** Renderiza una vista dentro de un layout; el contenido queda en $content. */
    public static function page(string $layout, string $tpl, array $data = []): string
    {
        $content = self::render($tpl, $data);
        return self::render('layout/' . $layout, $data + ['content' => $content]);
    }
}
