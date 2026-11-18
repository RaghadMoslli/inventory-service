<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidOrderTransitionException extends RuntimeException
{
    public function __construct(public readonly string $from, public readonly string $to)
    {
        parent::__construct("Order cannot move from {$from} to {$to}");
    }
}
