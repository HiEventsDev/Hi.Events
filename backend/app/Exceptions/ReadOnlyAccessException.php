<?php

namespace HiEvents\Exceptions;

use Throwable;

class ReadOnlyAccessException extends CodedAccessException
{
    public const ERROR_CODE = 'READ_ONLY_ROLE';

    public function __construct(?string $message = null, ?Throwable $previous = null)
    {
        parent::__construct($message ?? __('You have view-only access.'), $previous);
    }
}
