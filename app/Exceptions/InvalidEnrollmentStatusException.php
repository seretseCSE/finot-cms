<?php

namespace App\Exceptions;

use Exception;

class InvalidEnrollmentStatusException extends Exception
{
    public function __construct(string $message = "Invalid enrollment status transition", int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
