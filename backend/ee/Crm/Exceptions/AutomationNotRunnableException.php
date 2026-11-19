<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Exceptions;

use Exception;

class AutomationNotRunnableException extends Exception
{
    public function __construct(
        public readonly string $reasonCode,
    ) {
        parent::__construct($reasonCode);
    }
}
