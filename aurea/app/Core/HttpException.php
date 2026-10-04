<?php
declare(strict_types=1);

namespace Aurea\Core;

/** Permite cortar la ejecución de un controlador devolviendo una respuesta (redirección, 403, 404). */
final class HttpException extends \RuntimeException
{
    public Response $response;

    public function __construct(Response $r)
    {
        parent::__construct('http');
        $this->response = $r;
    }
}
