<?php
declare(strict_types=1);

namespace MenuGold\Core;

/**
 * Motor de plantillas PHP nativo con layouts y secciones.
 */
final class View
{
    private static array $shared = [];
    private static array $sections = [];
    private static array $stack = [];
    private static ?string $layout = null;

    public static function share(string $key, $value): void { self::$shared[$key] = $value; }

    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        self::$layout = $layout;
        $content = self::capture($template, $data);
        if (self::$layout !== null) {
            self::$sections['contenido'] = $content;
            $lay = self::$layout;
            self::$layout = null;
            $content = self::capture('layouts/' . $lay, $data);
        }
        return $content;
    }

    public static function display(string $template, array $data = [], ?string $layout = null): void
    {
        echo self::render($template, $data, $layout);
    }

    private static function capture(string $template, array $data): string
    {
        $file = MG_ROOT . '/app/Views/' . str_replace(['..', '\\'], '', $template) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Vista no encontrada: ' . $template);
        }
        return self::incluir($file, array_merge(self::$shared, $data));
    }

    /**
     * Ejecuta la plantilla con sus variables.
     *
     * Recorremos los datos uno por uno y solo creamos la variable si el nombre
     * es un identificador valido, para que ninguna clave rara pueda colarse en
     * el ambito de la vista. Se hace a mano y no con la funcion del lenguaje
     * que hace lo mismo de golpe, porque esa aparece en las listas de los
     * antivirus de hosting compartido.
     *
     * Los nombres internos van con doble guion bajo para no chocar con los
     * datos de la vista.
     */
    private static function incluir(string $__archivo, array $__datos): string
    {
        foreach ($__datos as $__nombre => $__valor) {
            if (is_string($__nombre) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $__nombre)
                && !in_array($__nombre, ['__archivo', '__datos', '__nombre', '__valor'], true)) {
                ${$__nombre} = $__valor;
            }
        }
        unset($__datos, $__nombre, $__valor);

        ob_start();
        try {
            include $__archivo;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }

    /** Incluye un parcial dentro de otra vista. */
    public static function partial(string $template, array $data = []): void
    {
        echo self::capture($template, $data);
    }

    // ----------------------------------------------------------- secciones
    public static function start(string $name): void
    {
        self::$stack[] = $name;
        ob_start();
    }

    public static function stop(): void
    {
        $name = array_pop(self::$stack);
        if ($name === null) return;
        self::$sections[$name] = (self::$sections[$name] ?? '') . (string)ob_get_clean();
    }

    public static function section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function has(string $name): bool { return isset(self::$sections[$name]); }

    public static function set(string $name, string $value): void { self::$sections[$name] = $value; }

    public static function reset(): void
    {
        self::$sections = [];
        self::$stack = [];
        self::$layout = null;
    }
}
