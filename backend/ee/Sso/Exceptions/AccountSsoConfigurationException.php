<?php

namespace HiEvents\Enterprise\Sso\Exceptions;

use Exception;

class AccountSsoConfigurationException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $field = 'connection',
    ) {
        parent::__construct($message);
    }
}
