<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\CheckInListDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetCheckInListAttendeePublicHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface    $attendeeRepository,
        private readonly CheckInListRepositoryInterface $checkInListRepository,
    )
    {
    }

    /**
     * @throws CannotCheckInException
     */
    public function handle(string $shortId, string $attendeePublicId): AttendeeDomainObject
    {
        $checkInList = $this->checkInListRepository
            ->loadRelation(ProductDomainObject::class)
            ->loadRelation(new Relationship(EventDomainObject::class, name: 'event'))
            ->findFirstWhere([
                CheckInListDomainObjectAbstract::SHORT_ID => $shortId,
            ]);

        if (!$checkInList) {
            throw new ResourceNotFoundException(__('Check-in list not found'));
        }

        $this->validateCheckInListIsActive($checkInList);

        return $this->attendeeRepository->findFirstWhere([
            'public_id' => $attendeePublicId,
            'event_id' => $checkInList->getEventId(),
        ]);
    }

    /**
     * @todo - Move this to its own service. It's used 3 times
     * @throws CannotCheckInException
     */
    private function validateCheckInListIsActive(CheckInListDomainObject $checkInList): void
    {
        if ($checkInList->getExpiresAt() && DateHelper::utcDateIsPast($checkInList->getExpiresAt())) {
            throw new CannotCheckInException(__('Check-in list has expired'));
        }

        if ($checkInList->getActivatesAt() && DateHelper::utcDateIsFuture($checkInList->getActivatesAt())) {
            throw new CannotCheckInException(__('Check-in list is not active yet'));
        }
    }
}
