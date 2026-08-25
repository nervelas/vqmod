<?php
declare(strict_types=1);

namespace Fel\Web;

/**
 * Render de plantillas PHP con layout.
 */
final class Vista
{
    /** @param array<string,mixed> $datos */
    public static function render(string $plantilla, array $datos = [], string $titulo = ''): string
    {
        $contenido = self::parcial($plantilla, $datos);

        return self::parcial('layout', [
            'contenido' => $contenido,
            'titulo'    => $titulo,
            'mensajes'  => Flash::consumir(),
        ]);
    }

    /** @param array<string,mixed> $datos */
    public static function parcial(string $plantilla, array $datos = []): string
    {
        $ruta = dirname(__DIR__, 2) . '/public/views/' . $plantilla . '.php';

        if (!is_file($ruta)) {
            throw new \RuntimeException("Vista no encontrada: {$plantilla}");
        }

        extract($datos, EXTR_SKIP);
        ob_start();
        require $ruta;

        return (string) ob_get_clean();
    }

    public static function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }

    public static function moneda(mixed $valor, string $simbolo = 'Q'): string
    {
        return $simbolo . number_format((float) $valor, 2, '.', ',');
    }

    public static function fecha(string $iso): string
    {
        if (trim($iso) === '') {
            return '—';
        }

        try {
            return (new \DateTimeImmutable($iso))->format('d/m/Y H:i');
        } catch (\Exception) {
            return $iso;
        }
    }
}
