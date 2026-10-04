<?php
declare(strict_types=1);

namespace App\Core;

final class HttpException extends \RuntimeException
{
    public int $status;

    public function __construct(int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
        $this->status = $status;
    }

    private static function defaultMessage(int $s): string
    {
        $m = [403 => 'No tienes permiso para ver esta página.', 404 => 'No encontramos lo que buscas.', 405 => 'Método no permitido.', 419 => 'Tu sesión caducó. Recarga la página e inténtalo de nuevo.', 429 => 'Demasiadas solicitudes. Espera un momento.'];
        return $m[$s] ?? 'Ocurrió un problema.';
    }
}
