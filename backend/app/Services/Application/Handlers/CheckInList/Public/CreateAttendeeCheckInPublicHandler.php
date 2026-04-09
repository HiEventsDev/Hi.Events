<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Services\Application\Handlers\CheckInList\Public\DTO\CreateAttendeeCheckInPublicDTO;
use HiEvents\Services\Domain\CheckInList\CheckInListDataService;
use HiEvents\Services\Domain\CheckInList\CreateAttendeeCheckInService;
use HiEvents\Services\Domain\CheckInList\DTO\CreateAttendeeCheckInsResponseDTO;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\CheckinEvent;
use Psr\Log\LoggerInterface;
use Throwable;

class CreateAttendeeCheckInPublicHandler
{
    public function __construct(
        private readonly CreateAttendeeCheckInService $createAttendeeCheckInService,
        private readonly LoggerInterface              $logger,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
        private readonly CheckInListDataService       $checkInListDataService,
    )
    {
    }

    /**
     * @throws CannotCheckInException|Throwable
     */
    public function handle(CreateAttendeeCheckInPublicDTO $checkInData): CreateAttendeeCheckInsResponseDTO
    {
        $checkInList = $this->checkInListDataService->getCheckInList($checkInData->checkInListUuid);
        $this->validateCheckInListIsAuthorized($checkInList, $checkInData->password);

        $checkIns = $this->createAttendeeCheckInService->checkInAttendees(
            $checkInData->checkInListUuid,
            $checkInData->checkInUserIpAddress,
            $checkInData->attendeesAndActions,
        );

        $this->logger->info('Attendee check-ins created', [
            'attendee_ids' => $checkIns->attendeeCheckIns
                ->map(fn(AttendeeCheckInDomainObject $checkIn) => $checkIn->getAttendeeId())->toArray(),
            'check_in_list_uuid' => $checkInData->checkInListUuid,
            'ip_address' => $checkInData->checkInUserIpAddress,
        ]);

        /** @var AttendeeCheckInDomainObject $checkIn */
        foreach ($checkIns->attendeeCheckIns as $checkIn) {
            $this->domainEventDispatcherService->dispatch(
                new CheckinEvent(
                    type: DomainEventType::CHECKIN_CREATED,
                    attendeeCheckinId: $checkIn->getId(),
                )
            );
        }

        return $checkIns;
    }

    /**
     * @throws CannotCheckInException
     */
    private function validateCheckInListIsAuthorized(CheckInListDomainObject $checkInList, ?string $password): void
    {
        if ($checkInList->isPasswordProtected() && $checkInList->getPassword() !== $password) {
            throw new CannotCheckInException(__('Invalid password provided'));
        }
    }
}
