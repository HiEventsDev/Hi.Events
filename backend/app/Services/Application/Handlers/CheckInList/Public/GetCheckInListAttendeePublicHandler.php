<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\Generated\CheckInListDomainObjectAbstract;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Services\Domain\CheckInList\CheckInListDataService;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

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
        $this->validateCheckInListIsAuthorized($checkInList, $password);

        return $this->attendeeRepository->findFirstWhere([
            'public_id' => $attendeePublicId,
            'event_id' => $checkInList->getEventId(),
        ]);
    }

    private function validateCheckInListIsAuthorized(CheckInListDomainObject $checkInList, ?string $password): void
    {
        if ($checkInList->isPasswordProtected() && $checkInList->getPassword() !== $password) {
            throw new CannotCheckInException(__('Invalid password provided'));
        }
    }
}
