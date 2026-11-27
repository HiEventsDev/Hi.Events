<?php

namespace HiEvents\Services\Application\Handlers\SelfService\Verification;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SelfServiceVerificationException;
use HiEvents\Services\Application\Handlers\SelfService\Verification\DTO\SelfServiceLinkDTO;
use HiEvents\Services\Domain\SelfService\DTO\SelfServiceSessionDTO;
use HiEvents\Services\Domain\SelfService\SelfServiceSubjectResolver;
use HiEvents\Services\Domain\SelfService\SelfServiceVerificationService;

class ConfirmSelfServiceVerificationHandler
{
    public function __construct(
        private readonly SelfServiceSubjectResolver $subjectResolver,
        private readonly SelfServiceVerificationService $verificationService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws SelfServiceVerificationException
     */
    public function handle(SelfServiceLinkDTO $link): SelfServiceSessionDTO
    {
        $subject = $link->attendee_short_id !== null
            ? $this->subjectResolver->forAttendee($link->event_id, $link->attendee_short_id)
            : $this->subjectResolver->forOrder($link->event_id, (string) $link->order_short_id);

        return $this->verificationService->confirmCode($subject->email, (string) $link->code);
    }
}
