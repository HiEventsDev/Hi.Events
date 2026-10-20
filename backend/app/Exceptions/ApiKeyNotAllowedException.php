<?php

namespace HiEvents\Exceptions;

use Throwable;

class ApiKeyNotAllowedException extends CodedAccessException
{
    public const ERROR_CODE = 'API_KEY_NOT_ALLOWED';

    public function __construct(?string $message = null, ?Throwable $previous = null)
    {
        parent::__construct($message ?? __('API keys can\'t be used for this request. Sign in to do this instead.'), $previous);
    }
}
