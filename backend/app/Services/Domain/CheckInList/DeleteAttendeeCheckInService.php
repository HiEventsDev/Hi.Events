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
    ): void
    {
        /** @var AttendeeCheckInDomainObject $checkIn */
        $checkIn = $this->attendeeCheckInRepository
            ->loadRelation(new Relationship(AttendeeDomainObject::class, name: 'attendee'))
            ->findFirstWhere([
                AttendeeCheckInDomainObjectAbstract::SHORT_ID => $checkInShortId,
            ]);

        if ($checkIn === null) {
            throw new CannotCheckInException(__('This attendee is not checked in'));
        }

        $checkInList = $this->checkInListDataService->getCheckInList($checkInListShortId);

        if ($checkInList->getId() !== $checkIn->getCheckInListId()) {
            throw new CannotCheckInException(__('Attendee does not belong to this check-in list'));
        }

        $this->attendeeCheckInRepository->deleteById($checkIn->getId());
    }
}
