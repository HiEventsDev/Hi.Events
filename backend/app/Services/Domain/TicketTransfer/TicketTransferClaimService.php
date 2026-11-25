<?php

namespace HiEvents\Services\Domain\TicketTransfer;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\Enums\TicketTransferNotice;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\TicketTransferDomainObjectAbstract;
use HiEvents\DomainObjects\Status\TicketTransferStatus;
use HiEvents\DomainObjects\TicketTransferDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketTransferRepositoryInterface;
use HiEvents\Services\Domain\Attendee\AttendeeCredentialRotationService;
use HiEvents\Services\Domain\Attendee\SendAttendeeTicketService;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use HiEvents\Services\Domain\SelfService\TicketControlService;
use HiEvents\Services\Domain\TicketTransfer\DTO\TicketTransferContextDTO;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;
use Illuminate\Database\DatabaseManager;
use Throwable;

class TicketTransferClaimService
{
    public function __construct(
        private readonly TicketTransferRepositoryInterface $ticketTransferRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly TicketTransferContextService $contextService,
        private readonly TicketTransferEligibilityService $eligibilityService,
        private readonly TicketTransferAnswerService $answerService,
        private readonly TicketTransferMailService $mailService,
        private readonly AttendeeCredentialRotationService $credentialRotationService,
        private readonly SendAttendeeTicketService $sendAttendeeTicketService,
        private readonly OrderAuditLogService $orderAuditLogService,
        private readonly TicketControlService $ticketControlService,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function findByToken(string $token): TicketTransferDomainObject
    {
        $transfer = $this->ticketTransferRepository->findFirstWhere([
            TicketTransferDomainObjectAbstract::TOKEN_HASH => self::hashToken($token),
        ]);

        if ($transfer === null) {
            throw new ResourceNotFoundException(__('This ticket transfer could not be found.'));
        }

        return $transfer;
    }

    /**
     * @param  array<int, array{question_id: int|string, response: mixed}>  $questions
     *
     * @throws ResourceConflictException
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function accept(
        string $token,
        string $firstName,
        string $lastName,
        array $questions,
        ?string $ipAddress,
        ?string $userAgent,
    ): AttendeeDomainObject {
        return $this->databaseManager->transaction(function () use ($token, $firstName, $lastName, $questions, $ipAddress, $userAgent) {
            $transfer = $this->lockAwaitingTransfer($token);
            $this->attendeeRepository->findByIdLocked($transfer->getAttendeeId());
            $context = $this->contextService->forAttendeeId($transfer->getAttendeeId());

            $block = $this->eligibilityService->blockOnHolderChange($context);

            if ($block !== null) {
                throw new ResourceConflictException(__('This ticket is no longer available to accept.'));
            }

            $credentials = $this->credentialRotationService->issueFor($context->event->getId());

            $this->attendeeRepository->updateWhere(
                [
                    AttendeeDomainObjectAbstract::FIRST_NAME => $firstName,
                    AttendeeDomainObjectAbstract::LAST_NAME => $lastName,
                    AttendeeDomainObjectAbstract::EMAIL => $transfer->getRecipientEmail(),
                    AttendeeDomainObjectAbstract::SHORT_ID => $credentials->short_id,
                    AttendeeDomainObjectAbstract::PUBLIC_ID => $credentials->public_id,
                    AttendeeDomainObjectAbstract::TRANSFERRED_AT => Carbon::now()->toDateTimeString(),
                ],
                [AttendeeDomainObjectAbstract::ID => $context->attendee->getId()],
            );

            $this->answerService->replaceAnswers($context->attendee, $questions);

            $this->ticketTransferRepository->updateWhere(
                [
                    TicketTransferDomainObjectAbstract::STATUS => TicketTransferStatus::ACCEPTED->name,
                    TicketTransferDomainObjectAbstract::ACCEPTED_AT => Carbon::now()->toDateTimeString(),
                ],
                [TicketTransferDomainObjectAbstract::ID => $transfer->getId()],
            );

            $this->orderAuditLogService->logTicketTransfer(
                OrderAuditAction::TICKET_TRANSFER_ACCEPTED,
                $context->attendee,
                $transfer->getSenderEmail(),
                $transfer->getRecipientEmail(),
                $ipAddress,
                $userAgent,
            );

            $newHolder = $this->contextService->forAttendeeId($context->attendee->getId());

            $this->notifyOfAcceptance($newHolder, $transfer);

            foreach ([DomainEventType::ATTENDEE_UPDATED, DomainEventType::ATTENDEE_TRANSFERRED] as $eventType) {
                $this->domainEventDispatcherService->dispatch(
                    new AttendeeEvent(type: $eventType, attendeeId: $newHolder->attendee->getId())
                );
            }

            return $newHolder->attendee;
        });
    }

    /**
     * @throws ResourceConflictException
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function decline(string $token, ?string $ipAddress, ?string $userAgent): void
    {
        $this->databaseManager->transaction(function () use ($token, $ipAddress, $userAgent) {
            $transfer = $this->lockAwaitingTransfer($token);
            $context = $this->contextService->forAttendeeId($transfer->getAttendeeId());

            $this->ticketTransferRepository->updateWhere(
                [TicketTransferDomainObjectAbstract::STATUS => TicketTransferStatus::DECLINED->name],
                [TicketTransferDomainObjectAbstract::ID => $transfer->getId()],
            );

            $this->orderAuditLogService->logTicketTransfer(
                OrderAuditAction::TICKET_TRANSFER_DECLINED,
                $context->attendee,
                $transfer->getSenderEmail(),
                $transfer->getRecipientEmail(),
                $ipAddress,
                $userAgent,
            );

            $this->mailService->sendNotice(
                TicketTransferNotice::DECLINED_FOR_SENDER,
                $context,
                $transfer,
                to: $transfer->getSenderEmail(),
                aboutEmail: $transfer->getRecipientEmail(),
            );
        });
    }

    /**
     * @throws ResourceConflictException
     * @throws ResourceNotFoundException
     */
    private function lockAwaitingTransfer(string $token): TicketTransferDomainObject
    {
        $transfer = $this->ticketTransferRepository->findByTokenHashLocked(self::hashToken($token));

        if ($transfer === null) {
            throw new ResourceNotFoundException(__('This ticket transfer could not be found.'));
        }

        if (! $transfer->isAwaitingRecipient()) {
            throw new ResourceConflictException(__('This ticket is no longer available to accept.'));
        }

        return $transfer;
    }

    private function notifyOfAcceptance(TicketTransferContextDTO $newHolder, TicketTransferDomainObject $transfer): void
    {
        $this->sendAttendeeTicketService->send(
            order: $newHolder->order,
            attendee: $newHolder->attendee,
            event: $newHolder->event,
            eventSettings: $newHolder->event->getEventSettings(),
            organizer: $newHolder->event->getOrganizer(),
        );

        $this->mailService->sendNotice(
            TicketTransferNotice::ACCEPTED_FOR_SENDER,
            $newHolder,
            $transfer,
            to: $transfer->getSenderEmail(),
            aboutEmail: $transfer->getRecipientEmail(),
        );

        $buyerEmail = $newHolder->order->getEmail();
        $buyerIsAlreadyInformed = $this->ticketControlService->controlsOrder($transfer->getSenderEmail(), $newHolder->order)
            || $this->ticketControlService->controlsOrder($transfer->getRecipientEmail(), $newHolder->order);

        if ($buyerEmail !== null && ! $buyerIsAlreadyInformed) {
            $this->mailService->sendNotice(
                TicketTransferNotice::ACCEPTED_FOR_BUYER,
                $newHolder,
                $transfer,
                to: $buyerEmail,
                aboutEmail: $transfer->getRecipientEmail(),
            );
        }
    }
}
