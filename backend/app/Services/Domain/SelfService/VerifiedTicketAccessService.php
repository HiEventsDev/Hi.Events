<?php

namespace HiEvents\Services\Domain\SelfService;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Exceptions\SelfServiceVerificationRequiredException;

class VerifiedTicketAccessService
{
    public function __construct(
        private readonly SelfServiceVerificationService $verificationService,
        private readonly TicketControlService $ticketControlService,
    ) {}

    /**
     * @throws SelfServiceVerificationRequiredException
     */
    public function assertControlsTicket(?string $sessionToken, AttendeeDomainObject $attendee, OrderDomainObject $order): string
    {
        $verifiedEmail = $this->verificationService->emailForSession($sessionToken);

        if (! $this->ticketControlService->controlsTicket($verifiedEmail, $attendee, $order)) {
            throw new SelfServiceVerificationRequiredException(__('Confirm it is you with the code we email you before changing this ticket.'));
        }

        return $verifiedEmail;
    }

    /**
     * @throws SelfServiceVerificationRequiredException
     */
    public function assertControlsOrder(?string $sessionToken, OrderDomainObject $order): void
    {
        $verifiedEmail = $this->verificationService->emailForSession($sessionToken);

        if (! $this->ticketControlService->controlsOrder($verifiedEmail, $order)) {
            throw new SelfServiceVerificationRequiredException(__('Confirm it is you with the code we email you before changing this order.'));
        }
    }
}
