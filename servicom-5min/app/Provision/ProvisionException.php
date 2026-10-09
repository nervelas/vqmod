<?php
declare(strict_types=1);
namespace S5\Provision;

/** Error de aprovisionamiento. $retry indica si vale la pena reintentar el paso. */
class ProvisionException extends \RuntimeException
{
    public bool $retry;

    public function __construct(string $message, bool $retry = false, ?\Throwable $prev = null)
    {
        parent::__construct($message, 0, $prev);
        $this->retry = $retry;
    }
}
