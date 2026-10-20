<?php

namespace HiEvents\Exceptions;

use Throwable;

class AccessRestrictedException extends CodedAccessException
{
    public const ERROR_CODE = 'ACCESS_RESTRICTED';

    public function __construct(?string $message = null, ?Throwable $previous = null)
    {
        parent::__construct($message ?? __("You don't have access to this."), $previous);
    }
}
