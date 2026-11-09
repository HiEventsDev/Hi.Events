<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Services\Domain\Badge\BadgeDesignService;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetCheckInListPublicHandler
{
    public function __construct(
        private readonly CheckInListRepositoryInterface $checkInListRepository,
        private readonly BadgeDesignService $badgeDesignService,
    ) {}

    public function handle(string $shortId): CheckInListDomainObject
    {
        $checkInList = $this->checkInListRepository
            ->loadRelation((new Relationship(domainObject: EventDomainObject::class, nested: [
                new Relationship(domainObject: EventSettingDomainObject::class, name: 'event_settings'),
                new Relationship(domainObject: EventOccurrenceDomainObject::class, name: 'event_occurrences'),
                new Relationship(domainObject: ImageDomainObject::class, name: 'images'),
            ], name: 'event')))
            ->loadRelation(ProductDomainObject::class)
            ->loadRelation(new Relationship(EventOccurrenceDomainObject::class, name: 'event_occurrence'))
            ->findFirstWhere([
                'short_id' => $shortId,
            ]);

        if ($checkInList === null) {
            throw new ResourceNotFoundException('Check-in list not found');
        }

        $attendeeCheckInCount = $this->checkInListRepository->getCheckedInAttendeeCountById($checkInList->getId());

        $checkInList->setCheckedInCount($attendeeCheckInCount->checkedInCount ?? 0);

        $eventSettings = $checkInList->getEvent()?->getEventSettings();
        if ($eventSettings !== null && $checkInList->isBadgePrintingActive($eventSettings)) {
            $checkInList->setBadgeDesign($this->badgeDesignService->getDesign($checkInList->getEventId()));
        }

        $checkInList->setTotalAttendeesCount($attendeeCheckInCount->totalAttendeesCount ?? 0);

        return $checkInList;
    }
}
