<?php

namespace HiEvents\Services\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetCheckInListPublicHandler
{
    public function __construct(
        private readonly CheckInListRepositoryInterface $checkInListRepository,
    )
    {
    }

    public function handle(string $shortId): CheckInListDomainObject
    {
        $checkInList = $this->checkInListRepository
            ->loadRelation(new Relationship(domainObject: EventDomainObject::class, name: 'event'))
            ->loadRelation(TicketDomainObject::class)
            ->findFirstWhere([
                'short_id' => $shortId,
            ]);

        if ($checkInList === null) {
            throw new ResourceNotFoundException('Check-in list not found');
        }

        $attendeeCheckInCount = $this->checkInListRepository->getCheckedInAttendeeCountById($checkInList->getId());

        $checkInList->setCheckedInCount($attendeeCheckInCount->checkedInCount ?? 0);
        $checkInList->setTotalAttendeesCount($attendeeCheckInCount->totalAttendeesCount ?? 0);

        return $checkInList;
    }
}
