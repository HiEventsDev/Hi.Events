<?php

namespace HiEvents\Exceptions;

use Exception;

class ResourceConflictException extends Exception
{
    public function __construct(
        string $message = 'Resource conflict',
        int $code = 409,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
