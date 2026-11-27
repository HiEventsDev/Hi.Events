<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Badge;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CheckInListDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Services\Domain\CheckInList\CheckInListActivityValidator;

class CheckInListBadgeAccessService
{
    public function __construct(
        private readonly CheckInListRepositoryInterface $checkInListRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly BadgeEligibilityService $badgeEligibilityService,
        private readonly CheckInListActivityValidator $checkInListActivityValidator,
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws CannotCheckInException
     */
    public function findPrintableAttendee(string $checkInListShortId, string $attendeePublicId): AttendeeDomainObject
    {
        /** @var CheckInListDomainObject|null $checkInList */
        $checkInList = $this->checkInListRepository
            ->loadRelation(ProductDomainObject::class)
            ->findFirstWhere([CheckInListDomainObjectAbstract::SHORT_ID => $checkInListShortId]);

        if ($checkInList === null) {
            throw new ResourceNotFoundException(__('Check-in list not found'));
        }

        /** @var EventSettingDomainObject|null $eventSettings */
        $eventSettings = $this->eventSettingsRepository->findFirstWhere([
            EventSettingDomainObjectAbstract::EVENT_ID => $checkInList->getEventId(),
        ]);

        if ($eventSettings === null || ! $checkInList->isBadgePrintingActive($eventSettings)) {
            throw new ResourceNotFoundException(__('Check-in list not found'));
        }

        $this->checkInListActivityValidator->assertActive($checkInList);

        /** @var AttendeeDomainObject|null $attendee */
        $attendee = $this->attendeeRepository
            ->loadRelation(new Relationship(ProductDomainObject::class, name: 'product'))
            ->loadRelation(new Relationship(EventOccurrenceDomainObject::class, name: 'event_occurrence'))
            ->findFirstWhere([
                AttendeeDomainObjectAbstract::PUBLIC_ID => $attendeePublicId,
                AttendeeDomainObjectAbstract::EVENT_ID => $checkInList->getEventId(),
            ]);

        if ($attendee === null || ! $this->isPrintableFromList($checkInList, $attendee)) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }

        return $attendee;
    }

    private function isPrintableFromList(CheckInListDomainObject $checkInList, AttendeeDomainObject $attendee): bool
    {
        $allowedProductIds = $checkInList->getProducts()?->map(fn ($product) => $product->getId())->all() ?? [];

        if ($allowedProductIds !== [] && ! in_array($attendee->getProductId(), $allowedProductIds, true)) {
            return false;
        }

        if ($checkInList->getEventOccurrenceId() !== null
            && $attendee->getEventOccurrenceId() !== $checkInList->getEventOccurrenceId()) {
            return false;
        }

        if ($attendee->getStatus() === AttendeeStatus::ACTIVE->name) {
            return true;
        }

        return $attendee->getStatus() === AttendeeStatus::AWAITING_PAYMENT->name
            && $this->badgeEligibilityService->includesAwaitingPayment($checkInList->getEventId());
    }
}
