<?php

namespace App\Exceptions;

use RuntimeException;

class PaidOrderUnfulfillableException extends RuntimeException
{
    public function __construct(string $message = 'The payment succeeded but the tickets are no longer available. A refund is required.')
    {
        parent::__construct($message);
    }
}
