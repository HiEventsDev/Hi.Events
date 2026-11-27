<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Exceptions;

use Exception;

class InvalidPlaceholderException extends Exception
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
