<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\Generated\CheckInListDomainObjectAbstract;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Services\Domain\CheckInList\CheckInListDataService;

class GetCheckInListAttendeePublicHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly CheckInListDataService      $checkInListDataService,
    )
    {
    }

    public function handle(string $shortId, string $attendeePublicId, ?string $password = null): AttendeeDomainObject
    {
        $checkInList = $this->checkInListDataService->getCheckInList($shortId);
        $this->validateCheckInListIsActiveAndAuthorized($checkInList, $password);

        return $this->attendeeRepository->findFirstWhere([
            'public_id' => $attendeePublicId,
            'event_id' => $checkInList->getEventId(),
        ]);
    }

    private function validateCheckInListIsActiveAndAuthorized(CheckInListDomainObject $checkInList, ?string $password): void
    {
        if ($checkInList->isPasswordProtected() && $checkInList->getPassword() !== $password) {
            throw new CannotCheckInException(__('Invalid password provided'));
        }

        if ($checkInList->getExpiresAt() && DateHelper::utcDateIsPast($checkInList->getExpiresAt())) {
            throw new CannotCheckInException(__('Check-in list has expired'));
        }

        if ($checkInList->getActivatesAt() && DateHelper::utcDateIsFuture($checkInList->getActivatesAt())) {
            throw new CannotCheckInException(__('Check-in list is not active yet'));
        }
    }
}
