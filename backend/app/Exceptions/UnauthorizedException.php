<?php

namespace TicketKitten\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;
use TicketKitten\Http\ResponseCodes;

class UnauthorizedException extends AccessDeniedHttpException
{
    public function __construct(
        string $message = 'This action is unauthorized',
        Throwable $previous = null,
        int $code = ResponseCodes::HTTP_FORBIDDEN,
        array $headers = []
    )
    {
        parent::__construct($message, $previous, $code, $headers);
    }
}
