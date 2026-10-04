<?php
declare(strict_types=1);

namespace App\Services;

/** Error de reserva con mensaje amable en español y un código para el cliente. */
final class BookingException extends \RuntimeException
{
    public string $errorCode;

    public function __construct(string $message, string $errorCode = 'validation')
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
    }
}
