<?php

declare(strict_types=1);

namespace App\UseCases\Exceptions;

use RuntimeException;

class ModelNotFoundException extends RuntimeException
{
    public function __construct(string $message = "", int $code = 404, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
