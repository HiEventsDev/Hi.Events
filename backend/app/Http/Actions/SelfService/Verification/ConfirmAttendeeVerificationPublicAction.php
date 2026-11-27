<?php

namespace HiEvents\Http\Actions\SelfService\Verification;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Request\SelfService\ConfirmSelfServiceVerificationRequest;
use HiEvents\Services\Application\Handlers\SelfService\Verification\DTO\SelfServiceLinkDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ConfirmAttendeeVerificationPublicAction extends BaseSelfServiceVerificationAction
{
    /**
     * @throws ResourceNotFoundException
     * @throws ValidationException
     */
    public function __invoke(ConfirmSelfServiceVerificationRequest $request, int $eventId, string $attendeeShortId): JsonResponse
    {
        return $this->confirmCode(new SelfServiceLinkDTO(event_id: $eventId, attendee_short_id: $attendeeShortId, code: $request->validated('code')));
    }
}
