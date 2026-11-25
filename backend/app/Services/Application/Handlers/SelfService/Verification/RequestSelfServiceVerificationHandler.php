<?php

namespace HiEvents\Services\Application\Handlers\SelfService\Verification;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SelfServiceVerificationException;
use HiEvents\Helper\EmailMasker;
use HiEvents\Mail\SelfService\SelfServiceVerificationCodeMail;
use HiEvents\Services\Application\Handlers\SelfService\Verification\DTO\SelfServiceLinkDTO;
use HiEvents\Services\Domain\SelfService\SelfServiceSubjectResolver;
use HiEvents\Services\Domain\SelfService\SelfServiceVerificationService;
use Illuminate\Contracts\Mail\Mailer;

class RequestSelfServiceVerificationHandler
{
    public function __construct(
        private readonly SelfServiceSubjectResolver $subjectResolver,
        private readonly SelfServiceVerificationService $verificationService,
        private readonly Mailer $mailer,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws SelfServiceVerificationException
     */
    public function handle(SelfServiceLinkDTO $link): string
    {
        $subject = $link->attendee_short_id !== null
            ? $this->subjectResolver->forAttendee($link->event_id, $link->attendee_short_id)
            : $this->subjectResolver->forOrder($link->event_id, (string) $link->order_short_id);

        $this->mailer
            ->to($subject->email)
            ->locale($subject->locale)
            ->send(new SelfServiceVerificationCodeMail(
                code: $this->verificationService->issueCode($subject->email),
                validForMinutes: SelfServiceVerificationService::CODE_TTL_MINUTES,
            ));

        return EmailMasker::mask($subject->email);
    }
}
