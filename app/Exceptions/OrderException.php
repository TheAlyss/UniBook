<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule stopped the action (e.g. book already booked). Safe to show to the user.
 * The code is the HTTP status the API should return (409 conflict by default, 403 for wrong role).
 */
class OrderException extends RuntimeException
{
    public function __construct(string $message, int $status = 409)
    {
        parent::__construct($message, $status);
    }

    public function status(): int
    {
        return $this->getCode() ?: 409;
    }
}
