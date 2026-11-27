<?php

namespace HiEvents\Exceptions;

use Throwable;

class OAuthTokenMcpOnlyException extends CodedAccessException
{
    public const ERROR_CODE = 'OAUTH_TOKEN_MCP_ONLY';

    public function __construct(?string $message = null, ?Throwable $previous = null)
    {
        parent::__construct($message ?? __('This token was issued to a connected app and only works with the MCP server. Use an API key to call the API.'), $previous);
    }
}
