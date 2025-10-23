<?php

namespace HiEvents\Services\Domain\CheckInList;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeCheckInDomainObjectAbstract;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeCheckInRepositoryInterface;

class DeleteAttendeeCheckInService
{
    public function __construct(
        private readonly AttendeeCheckInRepositoryInterface $attendeeCheckInRepository,
        private readonly CheckInListDataService             $checkInListDataService,
    )
    {
    }

    /**
     * @throws CannotCheckInException
     */
    public function deleteAttendeeCheckIn(
        string $checkInListShortId,
        string $checkInShortId,
    ): int
    {
        /** @var AttendeeCheckInDomainObject $checkIn */
        $checkIn = $this->attendeeCheckInRepository
            ->loadRelation(new Relationship(AttendeeDomainObject::class, name: 'attendee'))
            ->findFirstWhere([
                AttendeeCheckInDomainObjectAbstract::SHORT_ID => $checkInShortId,
                AttendeeCheckInDomainObjectAbstract::CHECK_IN_LIST_ID => $this
                    ->checkInListDataService
                    ->getCheckInList($checkInListShortId)->getId(),
            ]);

        if ($checkIn === null) {
            throw new CannotCheckInException(__('This attendee is not checked in'));
        }

        $checkInList = $this->checkInListDataService->getCheckInList($checkInListShortId);

        if ($checkInList->getId() !== $checkIn->getCheckInListId()) {
            // For now, let's allow this, as someone could delete the check-in list and be unable to delete the check-in
            // It should be safe as to check someone out you need to know the check-in list and check in short id
            //throw new CannotCheckInException(__('Attendee does not belong to this check-in list'));
        }

        $this->attendeeCheckInRepository->deleteById($checkIn->getId());

        return $checkIn->getId();
    }
}
