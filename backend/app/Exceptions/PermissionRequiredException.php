<?php

namespace HiEvents\Exceptions;

use Throwable;

class PermissionRequiredException extends CodedAccessException
{
    public const ERROR_CODE = 'PERMISSION_REQUIRED';

    public function __construct(?string $message = null, ?Throwable $previous = null)
    {
        parent::__construct($message ?? __("You don't have permission to do this."), $previous);
    }
}
