<?php

namespace HiEvents\Exceptions;

use Throwable;

class ApiKeyReadOnlyException extends CodedAccessException
{
    public const ERROR_CODE = 'API_KEY_READ_ONLY';

    public function __construct(?string $message = null, ?Throwable $previous = null)
    {
        parent::__construct($message ?? __('This API key is read-only. Create a key with read and write access to make changes.'), $previous);
    }
}
