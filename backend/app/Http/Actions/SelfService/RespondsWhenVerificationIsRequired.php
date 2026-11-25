<?php

namespace HiEvents\Http\Actions\SelfService;

use HiEvents\Exceptions\SelfServiceVerificationRequiredException;
use HiEvents\Http\Actions\TicketTransfer\BaseTicketTransferPublicAction;
use HiEvents\Http\ResponseCodes;
use Illuminate\Http\JsonResponse;

trait RespondsWhenVerificationIsRequired
{
    protected function verificationRequired(SelfServiceVerificationRequiredException $exception): JsonResponse
    {
        return $this->jsonResponse([
            'message' => $exception->getMessage(),
            'error_code' => BaseTicketTransferPublicAction::VERIFICATION_REQUIRED,
        ], ResponseCodes::HTTP_FORBIDDEN);
    }
}
