<?php

namespace HiEvents\Services\Domain\SelfService;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Generated\TicketResaleListingDomainObjectAbstract;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\Exceptions\TicketListedForResaleException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use HiEvents\Services\Domain\Attendee\AttendeeCredentialRotationService;
use HiEvents\Services\Domain\Attendee\AttendeeDetailsChangeNotificationService;
use HiEvents\Services\Domain\Badge\BadgeReprintService;
use HiEvents\Services\Domain\SelfService\DTO\EditAttendeeResultDTO;
use HiEvents\Services\Domain\TicketTransfer\TicketTransferInitiationService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;

class SelfServiceEditAttendeeService
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly OrderAuditLogService $orderAuditLogService,
        private readonly AttendeeCredentialRotationService $credentialRotationService,
        private readonly AttendeeDetailsChangeNotificationService $changeNotificationService,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
        private readonly TicketTransferInitiationService $transferInitiationService,
        private readonly TicketResaleListingRepositoryInterface $listingRepository,
        private readonly BadgeReprintService $badgeReprintService,
    ) {}

    /**
     * @throws TicketListedForResaleException
     */
    public function editAttendee(
        AttendeeDomainObject $attendee,
        ?string $firstName,
        ?string $lastName,
        ?string $email,
        string $ipAddress,
        ?string $userAgent
    ): EditAttendeeResultDTO {
        $oldValues = [];
        $newValues = [];
        $updateData = [];

        if ($firstName !== null && $firstName !== $attendee->getFirstName()) {
            $oldValues['first_name'] = $attendee->getFirstName();
            $newValues['first_name'] = $firstName;
            $updateData['first_name'] = $firstName;
        }

        if ($lastName !== null && $lastName !== $attendee->getLastName()) {
            $oldValues['last_name'] = $attendee->getLastName();
            $newValues['last_name'] = $lastName;
            $updateData['last_name'] = $lastName;
        }

        $emailChanged = $email !== null && $email !== $attendee->getEmail();

        if ($emailChanged && $this->listingRepository->countWhere([
            TicketResaleListingDomainObjectAbstract::ATTENDEE_ID => $attendee->getId(),
            TicketResaleListingDomainObjectAbstract::STATUS => TicketResaleListingStatus::LISTED->name,
        ]) > 0) {
            throw new TicketListedForResaleException(__('This ticket is listed for resale. Withdraw the listing before changing its email.'));
        }

        if ($emailChanged) {
            $oldValues['email'] = $attendee->getEmail();
            $newValues['email'] = $email;
            $updateData['email'] = $email;
        }

        $newShortId = null;

        if (! empty($updateData)) {
            if ($emailChanged) {
                $credentials = $this->credentialRotationService->issueFor($attendee->getEventId());
                $updateData['short_id'] = $credentials->short_id;
                $updateData['public_id'] = $credentials->public_id;
                $newShortId = $credentials->short_id;
            }

            $this->attendeeRepository->updateWhere(
                attributes: $updateData,
                where: ['id' => $attendee->getId()]
            );

            $this->badgeReprintService->flagForReprint([$attendee->getId()]);

            if ($emailChanged) {
                $this->transferInitiationService->cancelForHolderChange($attendee->getId());
            }

            $this->changeNotificationService->notify(
                attendeeId: $attendee->getId(),
                eventId: $attendee->getEventId(),
                previousEmail: $attendee->getEmail(),
                emailChanged: $emailChanged,
                oldValues: $oldValues,
                newValues: $newValues,
            );

            $this->orderAuditLogService->logAttendeeUpdate(
                attendee: $attendee,
                oldValues: $oldValues,
                newValues: $emailChanged
                    ? [...$newValues, OrderAuditLogService::CREDENTIALS_REPLACED => true]
                    : $newValues,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->domainEventDispatcherService->dispatch(
                new AttendeeEvent(
                    type: DomainEventType::ATTENDEE_UPDATED,
                    attendeeId: $attendee->getId(),
                )
            );
        }

        return new EditAttendeeResultDTO(
            success: true,
            shortIdChanged: $emailChanged,
            newShortId: $newShortId,
            emailChanged: $emailChanged,
        );
    }
}
