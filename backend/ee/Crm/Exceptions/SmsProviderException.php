<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Exceptions;

use Exception;

class SmsProviderException extends Exception
{
    public function __construct(
        string $message,
        public readonly bool $isPermanent = false,
        public readonly bool $recipientOptedOut = false,
        public readonly bool $mayHaveBeenAccepted = false,
        public readonly bool $connectionRefused = false,
    ) {
        parent::__construct($message);
    }
}
