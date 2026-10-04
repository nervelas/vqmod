<?php
declare(strict_types=1);

namespace App\Controllers\Api;

/** Error de la API con su código HTTP y un código estable para programas. */
final class ApiError extends \RuntimeException
{
    public string $apiCode;
    public int $httpStatus;

    public function __construct(string $message, string $apiCode, int $httpStatus)
    {
        parent::__construct($message);
        $this->apiCode = $apiCode;
        $this->httpStatus = $httpStatus;
    }
}
