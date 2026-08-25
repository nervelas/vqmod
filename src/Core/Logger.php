<?php
declare(strict_types=1);

namespace Fel\Core;

/**
 * Bitacora en archivo. La SAT exige poder demostrar la trazabilidad de cada DTE,
 * por eso se registra toda peticion y respuesta del certificador.
 */
final class Logger
{
    public static function escribir(string $nivel, string $mensaje, array $contexto = []): void
    {
        $dir = (string) Config::get('rutas.logs', dirname(__DIR__, 2) . '/storage/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        $linea = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($nivel),
            $mensaje,
            $contexto === [] ? '' : json_encode(self::enmascarar($contexto), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        @file_put_contents($dir . '/fel-' . date('Y-m') . '.log', $linea, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $mensaje, array $contexto = []): void
    {
        self::escribir('info', $mensaje, $contexto);
    }

    public static function error(string $mensaje, array $contexto = []): void
    {
        self::escribir('error', $mensaje, $contexto);
    }

    /**
     * Nunca se escriben llaves ni tokens completos en la bitacora.
     *
     * @param array<string,mixed> $contexto
     * @return array<string,mixed>
     */
    private static function enmascarar(array $contexto): array
    {
        $sensibles = ['llave', 'llave_firma', 'clave', 'password', 'token', 'api_key', 'llave_api', 'authorization'];

        foreach ($contexto as $clave => $valor) {
            if (is_array($valor)) {
                $contexto[$clave] = self::enmascarar($valor);
                continue;
            }
            if (in_array(strtolower((string) $clave), $sensibles, true) && is_string($valor) && $valor !== '') {
                $contexto[$clave] = substr($valor, 0, 4) . '****';
            }
        }

        return $contexto;
    }
}
