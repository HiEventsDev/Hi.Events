<?php

namespace HiEvents\Exceptions;

use Exception;
use HiEvents\DomainObjects\Enums\SsoErrorCode;
use Throwable;

class SsoException extends Exception
{
    public function __construct(
        public readonly SsoErrorCode $errorCode,
        string $message,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
