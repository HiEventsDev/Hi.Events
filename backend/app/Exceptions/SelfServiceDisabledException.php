<?php

declare(strict_types=1);

namespace HiEvents\Exceptions;

use Exception;

class SelfServiceDisabledException extends Exception
{
    public function __construct(
        string $message = 'Self-service management is disabled for this event',
        int $code = 403,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
