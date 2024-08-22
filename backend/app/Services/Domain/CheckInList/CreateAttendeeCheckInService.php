<?php

namespace HiEvents\Services\Domain\CheckInList;

use Exception;
use HiEvents\DataTransferObjects\ErrorBagDTO;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeCheckInDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Helper\DateHelper;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\AttendeeCheckInRepositoryInterface;
use HiEvents\Services\Domain\CheckInList\DTO\CreateAttendeeCheckInsResponseDTO;
use Illuminate\Support\Collection;

class CreateAttendeeCheckInService
{
    public function __construct(
        private readonly AttendeeCheckInRepositoryInterface $attendeeCheckInRepository,
        private readonly CheckInListDataService             $checkInListDataService,
    )
    {
    }

    /**
     * @throws CannotCheckInException
     * @throws Exception
     */
    public function checkInAttendees(
        string $checkInListUuid,
        string $checkInUserIpAddress,
        array  $attendeePublicIds
    ): CreateAttendeeCheckInsResponseDTO
    {
        $attendees = $this->checkInListDataService->getAttendees($attendeePublicIds);
        $checkInList = $this->checkInListDataService->getCheckInList($checkInListUuid);

        $this->validateCheckInListIsActive($checkInList);

        $existingCheckIns = $this->attendeeCheckInRepository->findWhereIn(
            field: AttendeeCheckInDomainObjectAbstract::ATTENDEE_ID,
            values: $attendees->filter(
                fn(AttendeeDomainObject $attendee) => in_array($attendee->getPublicId(), $attendeePublicIds, true)
            )->map(
                fn(AttendeeDomainObject $attendee) => $attendee->getId()
            )->toArray(),
            additionalWhere: [
                AttendeeCheckInDomainObjectAbstract::EVENT_ID => $checkInList->getEventId(),
            ],
        );

        $errors = new ErrorBagDTO();
        $checkIns = new Collection();

        foreach ($attendees as $attendee) {
            $this->checkInListDataService->verifyAttendeeBelongsToCheckInList($checkInList, $attendee);

            $existingCheckIn = $existingCheckIns->first(
                fn($checkIn) => $checkIn->getAttendeeId() === $attendee->getId()
            );

            if ($attendee->getStatus() === AttendeeStatus::CANCELLED->name) {
                $errors->addError(
                    key: $attendee->getPublicId(),
                    message: __('Attendee :attendee_name\'s ticket is cancelled', [
                        'attendee_name' => $attendee->getFullName(),
                    ])
                );
                continue;
            }

            if ($existingCheckIn) {
                $checkIns->push($existingCheckIn);
                $errors->addError(
                    key: $attendee->getPublicId(),
                    message: __('Attendee :attendee_name is already checked in', [
                        'attendee_name' => $attendee->getFullName(),
                    ])
                );
                continue;
            }

            $checkIns->push(
                $this->attendeeCheckInRepository->create([
                    AttendeeCheckInDomainObjectAbstract::ATTENDEE_ID => $attendee->getId(),
                    AttendeeCheckInDomainObjectAbstract::CHECK_IN_LIST_ID => $checkInList->getId(),
                    AttendeeCheckInDomainObjectAbstract::IP_ADDRESS => $checkInUserIpAddress,
                    AttendeeCheckInDomainObjectAbstract::TICKET_ID => $attendee->getTicketId(),
                    AttendeeCheckInDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::CHECK_IN_PREFIX),
                    AttendeeCheckInDomainObjectAbstract::EVENT_ID => $checkInList->getEventId(),
                ])
            );
        }

        return new CreateAttendeeCheckInsResponseDTO(
            attendeeCheckIns: $checkIns,
            errors: $errors,
        );
    }

    /**
     * @throws CannotCheckInException
     */
    private function validateCheckInListIsActive(CheckInListDomainObject $checkInList): void
    {
        if ($checkInList->getExpiresAt() && DateHelper::utcDateIsPast($checkInList->getExpiresAt())) {
            throw new CannotCheckInException(__('Check-in list has expired'));
        }

        if ($checkInList->getActivatesAt() && DateHelper::utcDateIsFuture($checkInList->getActivatesAt())) {
            throw new CannotCheckInException(__('Check-in list is not active yes'));
        }
    }
}
