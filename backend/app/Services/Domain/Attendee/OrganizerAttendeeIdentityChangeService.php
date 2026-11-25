<?php

namespace HiEvents\Services\Domain\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\TicketResaleListingDomainObjectAbstract;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\Exceptions\TicketListedForResaleException;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use HiEvents\Services\Domain\TicketTransfer\TicketTransferInitiationService;

class OrganizerAttendeeIdentityChangeService
{
    private const IDENTITY_FIELDS = [
        AttendeeDomainObjectAbstract::FIRST_NAME,
        AttendeeDomainObjectAbstract::LAST_NAME,
        AttendeeDomainObjectAbstract::EMAIL,
    ];

    public function __construct(
        private readonly AttendeeCredentialRotationService $credentialRotationService,
        private readonly AttendeeDetailsChangeNotificationService $changeNotificationService,
        private readonly OrderAuditLogService $orderAuditLogService,
        private readonly TicketTransferInitiationService $transferInitiationService,
        private readonly TicketResaleListingRepositoryInterface $listingRepository,
    ) {}

    /**
     * @return array<string, string>
     *
     * @throws TicketListedForResaleException
     */
    public function credentialAttributesFor(AttendeeDomainObject $attendee, ?string $newEmail): array
    {
        if ($newEmail === $attendee->getEmail()) {
            return [];
        }

        if ($this->listingRepository->countWhere([
            TicketResaleListingDomainObjectAbstract::ATTENDEE_ID => $attendee->getId(),
            TicketResaleListingDomainObjectAbstract::STATUS => TicketResaleListingStatus::LISTED->name,
        ]) > 0) {
            throw new TicketListedForResaleException(__('This ticket is listed for resale. Close the listing on the resale page before changing its email.'));
        }

        $credentials = $this->credentialRotationService->issueFor($attendee->getEventId());

        return [
            AttendeeDomainObjectAbstract::SHORT_ID => $credentials->short_id,
            AttendeeDomainObjectAbstract::PUBLIC_ID => $credentials->public_id,
        ];
    }

    public function record(AttendeeDomainObject $before, AttendeeDomainObject $after): bool
    {
        $previous = $before->toArray();
        $current = $after->toArray();

        $oldValues = [];
        $newValues = [];

        foreach (self::IDENTITY_FIELDS as $field) {
            if (($previous[$field] ?? null) !== ($current[$field] ?? null)) {
                $oldValues[$field] = $previous[$field] ?? null;
                $newValues[$field] = $current[$field] ?? null;
            }
        }

        if ($newValues === []) {
            return false;
        }

        $emailChanged = array_key_exists(AttendeeDomainObjectAbstract::EMAIL, $newValues);

        if ($emailChanged) {
            $this->transferInitiationService->cancelForHolderChange($after->getId());
            $this->changeNotificationService->notify(
                attendeeId: $after->getId(),
                eventId: $after->getEventId(),
                previousEmail: $before->getEmail(),
                emailChanged: true,
                oldValues: $oldValues,
                newValues: $newValues,
            );
        }

        $this->orderAuditLogService->logOrganizerAttendeeUpdate(
            attendee: $after,
            oldValues: $oldValues,
            newValues: $emailChanged
                ? [...$newValues, OrderAuditLogService::CREDENTIALS_REPLACED => true]
                : $newValues,
        );

        return true;
    }
}
