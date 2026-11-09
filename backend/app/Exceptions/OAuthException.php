<?php

declare(strict_types=1);

namespace HiEvents\Exceptions;

use HiEvents\DomainObjects\Enums\OAuthErrorCode;
use HiEvents\Http\ResponseCodes;

class OAuthException extends BaseException
{
    public function __construct(
        public readonly OAuthErrorCode $errorCode,
        string $description,
        public readonly int $status = ResponseCodes::HTTP_BAD_REQUEST,
    ) {
        parent::__construct($description);
    }
}
