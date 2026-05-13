<?php

namespace App\Exceptions;

use Exception;

class InsufficientFundsException extends Exception
{
    public function __construct(string $message = "Insufficient funds for this operation", int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
