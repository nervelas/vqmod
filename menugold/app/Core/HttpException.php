<?php
declare(strict_types=1);

namespace MenuGold\Core;

class HttpException extends \RuntimeException
{
    public static function notFound(string $msg = 'La página que buscas no existe.'): self
    {
        return new self($msg, 404);
    }
    public static function forbidden(string $msg = 'No tienes permiso para ver esta sección.'): self
    {
        return new self($msg, 403);
    }
    public static function badRequest(string $msg = 'Solicitud inválida.'): self
    {
        return new self($msg, 400);
    }
    public static function tooMany(string $msg = 'Demasiados intentos. Espera un momento.'): self
    {
        return new self($msg, 429);
    }
}
