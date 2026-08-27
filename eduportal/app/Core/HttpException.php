<?php
declare(strict_types=1);

namespace App\Core;

final class HttpException extends \RuntimeException
{
    public function __construct(int $codigo, string $mensaje = '')
    {
        parent::__construct($mensaje, $codigo);
    }

    public function status(): int
    {
        $c = (int)$this->getCode();
        return ($c >= 400 && $c < 600) ? $c : 500;
    }
}
