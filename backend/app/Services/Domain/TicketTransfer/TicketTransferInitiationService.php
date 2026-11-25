<?php

namespace HiEvents\Services\Domain\TicketTransfer;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\Enums\TicketTransferNotice;
use HiEvents\DomainObjects\Generated\TicketTransferDomainObjectAbstract;
use HiEvents\DomainObjects\Status\TicketTransferStatus;
use HiEvents\DomainObjects\TicketTransferDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\TicketTransferRepositoryInterface;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use HiEvents\Services\Domain\TicketTransfer\DTO\TicketTransferContextDTO;
use HiEvents\Services\Infrastructure\TokenGenerator\TokenGeneratorService;

class TicketTransferInitiationService
{
    public function __construct(
        private readonly TicketTransferRepositoryInterface $ticketTransferRepository,
        private readonly TicketTransferEligibilityService $eligibilityService,
        private readonly TicketTransferMailService $mailService,
        private readonly OrderAuditLogService $orderAuditLogService,
        private readonly TokenGeneratorService $tokenGeneratorService,
        private readonly TicketTransferContextService $contextService,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function start(
        TicketTransferContextDTO $context,
        string $senderEmail,
        string $recipientEmail,
        ?string $ipAddress,
        ?string $userAgent,
    ): TicketTransferDomainObject {
        $block = $this->eligibilityService->blockOnStarting($context);

        if ($block !== null) {
            throw new ResourceConflictException($block->message());
        }

        if (mb_strtolower(trim($recipientEmail)) === mb_strtolower(trim((string) $context->attendee->getEmail()))) {
            throw new ResourceConflictException(__('This ticket is already in that person\'s name.'));
        }

        $this->ticketTransferRepository->updateWhere(
            [TicketTransferDomainObjectAbstract::STATUS => TicketTransferStatus::EXPIRED->name],
            [
                TicketTransferDomainObjectAbstract::ATTENDEE_ID => $context->attendee->getId(),
                TicketTransferDomainObjectAbstract::STATUS => TicketTransferStatus::PENDING->name,
            ],
        );

        $token = $this->tokenGeneratorService->generateToken(length: 48, prefix: 'tt_');

        $transfer = $this->ticketTransferRepository->create([
            TicketTransferDomainObjectAbstract::EVENT_ID => $context->event->getId(),
            TicketTransferDomainObjectAbstract::ORDER_ID => $context->order->getId(),
            TicketTransferDomainObjectAbstract::ATTENDEE_ID => $context->attendee->getId(),
            TicketTransferDomainObjectAbstract::SENDER_EMAIL => mb_strtolower(trim($senderEmail)),
            TicketTransferDomainObjectAbstract::RECIPIENT_EMAIL => mb_strtolower(trim($recipientEmail)),
            TicketTransferDomainObjectAbstract::TOKEN_HASH => TicketTransferClaimService::hashToken($token),
            TicketTransferDomainObjectAbstract::STATUS => TicketTransferStatus::PENDING->name,
            TicketTransferDomainObjectAbstract::LOCALE => $context->attendee->getLocale(),
            TicketTransferDomainObjectAbstract::EXPIRES_AT => $this->eligibilityService->expiryForNewTransfer($context)->toDateTimeString(),
        ]);

        $this->orderAuditLogService->logTicketTransfer(
            OrderAuditAction::TICKET_TRANSFER_STARTED,
            $context->attendee,
            $transfer->getSenderEmail(),
            $transfer->getRecipientEmail(),
            $ipAddress,
            $userAgent,
        );

        $this->mailService->sendInvitation($context, $transfer, $token);

        return $transfer;
    }

    /**
     * @throws ResourceConflictException
     */
    public function cancel(TicketTransferContextDTO $context, ?string $ipAddress, ?string $userAgent): void
    {
        if (! $this->cancelPending($context, $ipAddress, $userAgent)) {
            throw new ResourceConflictException(__('There is no transfer waiting to be accepted for this ticket.'));
        }
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function cancelForHolderChange(int $attendeeId): void
    {
        $this->cancelPending($this->contextService->forAttendeeId($attendeeId), null, null);
    }

    private function cancelPending(TicketTransferContextDTO $context, ?string $ipAddress, ?string $userAgent): bool
    {
        $transfer = $context->pendingTransfer();

        if ($transfer === null) {
            return false;
        }

        $cancelled = $this->ticketTransferRepository->updateWhere(
            [TicketTransferDomainObjectAbstract::STATUS => TicketTransferStatus::CANCELLED->name],
            [
                TicketTransferDomainObjectAbstract::ID => $transfer->getId(),
                TicketTransferDomainObjectAbstract::STATUS => TicketTransferStatus::PENDING->name,
                [TicketTransferDomainObjectAbstract::EXPIRES_AT, '>', Carbon::now()->toDateTimeString()],
            ],
        );

        if ($cancelled === 0) {
            return false;
        }

        $this->orderAuditLogService->logTicketTransfer(
            OrderAuditAction::TICKET_TRANSFER_CANCELLED,
            $context->attendee,
            $transfer->getSenderEmail(),
            $transfer->getRecipientEmail(),
            $ipAddress,
            $userAgent,
        );

        $this->mailService->sendNotice(
            TicketTransferNotice::CANCELLED_FOR_RECIPIENT,
            $context,
            $transfer,
            to: $transfer->getRecipientEmail(),
            aboutEmail: $transfer->getSenderEmail(),
        );

        return true;
    }
}
