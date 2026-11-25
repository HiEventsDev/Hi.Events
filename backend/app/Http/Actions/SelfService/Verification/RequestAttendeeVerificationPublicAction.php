<?php

namespace HiEvents\Http\Actions\SelfService\Verification;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Services\Application\Handlers\SelfService\Verification\DTO\SelfServiceLinkDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RequestAttendeeVerificationPublicAction extends BaseSelfServiceVerificationAction
{
    /**
     * @throws ResourceNotFoundException
     * @throws ValidationException
     */
    public function __invoke(int $eventId, string $attendeeShortId): JsonResponse
    {
        return $this->sendCode(new SelfServiceLinkDTO(event_id: $eventId, attendee_short_id: $attendeeShortId));
    }
}
