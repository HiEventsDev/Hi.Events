<?php

namespace HiEvents\Http\Actions\SelfService\Verification;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Services\Application\Handlers\SelfService\Verification\DTO\SelfServiceLinkDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RequestOrderVerificationPublicAction extends BaseSelfServiceVerificationAction
{
    /**
     * @throws ResourceNotFoundException
     * @throws ValidationException
     */
    public function __invoke(int $eventId, string $orderShortId): JsonResponse
    {
        return $this->sendCode(new SelfServiceLinkDTO(event_id: $eventId, order_short_id: $orderShortId));
    }
}
