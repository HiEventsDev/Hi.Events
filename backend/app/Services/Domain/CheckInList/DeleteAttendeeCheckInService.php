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
        private readonly CheckInListDataService $checkInListDataService,
        private readonly CheckInListActivityValidator $checkInListActivityValidator,
    ) {}

    /**
     * @throws CannotCheckInException
     */
    public function deleteAttendeeCheckIn(
        string $checkInListShortId,
        string $checkInShortId,
    ): int {
        $checkInList = $this->checkInListDataService->getCheckInList($checkInListShortId);
        $this->checkInListActivityValidator->assertActive($checkInList);

        /** @var AttendeeCheckInDomainObject $checkIn */
        $checkIn = $this->attendeeCheckInRepository
            ->loadRelation(new Relationship(AttendeeDomainObject::class, name: 'attendee'))
            ->findFirstWhere([
                AttendeeCheckInDomainObjectAbstract::SHORT_ID => $checkInShortId,
                AttendeeCheckInDomainObjectAbstract::CHECK_IN_LIST_ID => $checkInList->getId(),
            ]);

        if ($checkIn === null) {
            throw new CannotCheckInException(__('This attendee is not checked in'));
        }

        $this->attendeeCheckInRepository->deleteById($checkIn->getId());

        return $checkIn->getId();
    }
}
