<?php

namespace App\Exceptions;

use Exception;

class TourCapacityExceededException extends Exception
{
    public function __construct(string $message = "Tour capacity exceeded", int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
