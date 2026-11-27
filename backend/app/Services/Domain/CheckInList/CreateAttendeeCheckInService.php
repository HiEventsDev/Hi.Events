<?php

namespace HiEvents\Services\Domain\CheckInList;

use Exception;
use HiEvents\DataTransferObjects\ErrorBagDTO;
use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\Enums\AttendeeCheckInActionType;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeCheckInDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\AttendeeCheckInRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\CheckInList\Public\DTO\AttendeeAndActionDTO;
use HiEvents\Services\Domain\CheckInList\DTO\CheckInResultDTO;
use HiEvents\Services\Domain\CheckInList\DTO\CreateAttendeeCheckInsResponseDTO;
use HiEvents\Services\Domain\Order\MarkOrderAsPaidService;
use HiEvents\Services\Domain\TicketResale\TicketResaleClosureService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Throwable;

class CreateAttendeeCheckInService
{
    public function __construct(
        private readonly AttendeeCheckInRepositoryInterface $attendeeCheckInRepository,
        private readonly CheckInListDataService $checkInListDataService,
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
        private readonly ConnectionInterface $db,
        private readonly MarkOrderAsPaidService $markOrderAsPaidService,
        private readonly CheckInListActivityValidator $checkInListActivityValidator,
        private readonly TicketResaleClosureService $resaleClosureService,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    /**
     * @param  Collection<int, AttendeeAndActionDTO>  $attendeesAndActions
     *
     * @throws CannotCheckInException
     * @throws Exception|Throwable
     */
    public function checkInAttendees(
        string $checkInListUuid,
        string $checkInUserIpAddress,
        Collection $attendeesAndActions
    ): CreateAttendeeCheckInsResponseDTO {
        $checkInList = $this->checkInListDataService->getCheckInList($checkInListUuid);
        $this->checkInListActivityValidator->assertActive($checkInList);

        $attendees = $this->fetchAttendees($checkInList, $attendeesAndActions);
        $eventSettings = $this->fetchEventSettings($checkInList->getEventId());
        $existingCheckIns = $this->fetchExistingCheckIns($attendees, $checkInList);
        $offlinePaymentOrderIds = $this->fetchOfflinePaymentOrderIds($attendees);

        return $this->processAttendeeCheckIns(
            $attendees,
            $attendeesAndActions,
            $checkInList,
            $eventSettings,
            $existingCheckIns,
            $offlinePaymentOrderIds,
            $checkInUserIpAddress
        );
    }

    /**
     * @param  Collection<int, AttendeeAndActionDTO>  $attendeesAndActions
     * @return Collection<int, AttendeeDomainObject>
     *
     * @throws CannotCheckInException
     */
    private function fetchAttendees(CheckInListDomainObject $checkInList, Collection $attendeesAndActions): Collection
    {
        $publicIds = $attendeesAndActions->map(
            fn (AttendeeAndActionDTO $attendeeAndAction) => $attendeeAndAction->public_id
        );

        return $this->checkInListDataService->getAttendees($checkInList, $publicIds);
    }

    private function fetchEventSettings(int $eventId): EventSettingDomainObject
    {
        return $this->eventSettingsRepository->findFirstWhere([
            'event_id' => $eventId,
        ]);
    }

    /**
     * @param  Collection<int, AttendeeDomainObject>  $attendees
     *
     * @throws Exception
     */
    private function fetchExistingCheckIns(Collection $attendees, CheckInListDomainObject $checkInList): Collection
    {
        $attendeeIds = $attendees->map(fn (AttendeeDomainObject $attendee) => $attendee->getId())->toArray();

        return $this->attendeeCheckInRepository->findWhereIn(
            field: AttendeeCheckInDomainObjectAbstract::ATTENDEE_ID,
            values: $attendeeIds,
            additionalWhere: [
                AttendeeCheckInDomainObjectAbstract::EVENT_ID => $checkInList->getEventId(),
                AttendeeCheckInDomainObjectAbstract::CHECK_IN_LIST_ID => $checkInList->getId(),
            ],
        );
    }

    /**
     * @param  Collection<int, AttendeeDomainObject>  $attendees
     * @return Collection<int, int>
     */
    private function fetchOfflinePaymentOrderIds(Collection $attendees): Collection
    {
        $awaitingPaymentOrderIds = $attendees
            ->filter(fn (AttendeeDomainObject $attendee) => $attendee->getStatus() === AttendeeStatus::AWAITING_PAYMENT->name)
            ->map(fn (AttendeeDomainObject $attendee) => $attendee->getOrderId())
            ->unique()
            ->values();

        if ($awaitingPaymentOrderIds->isEmpty()) {
            return new Collection;
        }

        return $this->orderRepository
            ->findWhereIn('id', $awaitingPaymentOrderIds->all(), ['status' => OrderStatus::AWAITING_OFFLINE_PAYMENT->name])
            ->map(fn ($order) => $order->getId())
            ->values();
    }

    /**
     * @throws Throwable
     * @throws CannotCheckInException
     */
    private function processAttendeeCheckIns(
        Collection $attendees,
        Collection $attendeesAndActions,
        CheckInListDomainObject $checkInList,
        EventSettingDomainObject $eventSettings,
        Collection $existingCheckIns,
        Collection $offlinePaymentOrderIds,
        string $checkInUserIpAddress
    ): CreateAttendeeCheckInsResponseDTO {
        $errors = new ErrorBagDTO;
        $checkIns = new Collection;

        foreach ($attendees as $attendee) {
            $result = $this->processIndividualCheckIn(
                $attendee,
                $attendeesAndActions,
                $checkInList,
                $eventSettings,
                $existingCheckIns,
                $offlinePaymentOrderIds,
                $checkInUserIpAddress,
            );

            if ($result->checkIn) {
                $checkIns->push($result->checkIn);
            }
            if ($result->error) {
                $errors->addError($attendee->getPublicId(), $result->error);
            }
        }

        return new CreateAttendeeCheckInsResponseDTO(
            attendeeCheckIns: $checkIns,
            errors: $errors,
        );
    }

    /**
     * @throws Throwable
     * @throws CannotCheckInException
     */
    private function processIndividualCheckIn(
        AttendeeDomainObject $attendee,
        Collection $attendeesAndActions,
        CheckInListDomainObject $checkInList,
        EventSettingDomainObject $eventSettings,
        Collection $existingCheckIns,
        Collection $offlinePaymentOrderIds,
        string $checkInUserIpAddress,
    ): CheckInResultDTO {
        $this->checkInListDataService->verifyAttendeeBelongsToCheckInList($checkInList, $attendee);

        $attendeeAction = $attendeesAndActions->first(
            fn (AttendeeAndActionDTO $action) => $action->public_id === $attendee->getPublicId()
        );
        $checkInAction = $attendeeAction->action;

        if ($existingCheckIn = $this->getExistingCheckIn($existingCheckIns, $attendee)) {
            return new CheckInResultDTO(
                checkIn: $existingCheckIn,
                error: __('Attendee :attendee_name is already checked in', [
                    'attendee_name' => $attendee->getFullName(),
                ])
            );
        }

        $orderAwaitsOfflinePayment = $offlinePaymentOrderIds->contains($attendee->getOrderId());

        if ($error = $this->validateAttendeeStatus($attendee, $checkInAction, $eventSettings, $orderAwaitsOfflinePayment)) {
            return new CheckInResultDTO(error: $error);
        }

        try {
            return $this->db->transaction(function () use ($attendee, $checkInList, $checkInAction, $checkInUserIpAddress, $eventSettings, $orderAwaitsOfflinePayment) {
                $lockedAttendee = $this->resaleClosureService->lockForCheckIn($attendee->getId());

                if ($error = $this->validateAttendeeStatus($lockedAttendee, $checkInAction, $eventSettings, $orderAwaitsOfflinePayment)) {
                    return new CheckInResultDTO(error: $error);
                }

                $this->resaleClosureService->releaseForCheckIn($attendee->getId());

                $checkIn = $this->createCheckIn($attendee, $checkInList, $checkInUserIpAddress);

                if ($checkInAction->value === AttendeeCheckInActionType::CHECK_IN_AND_MARK_ORDER_AS_PAID->value
                    && $lockedAttendee->getStatus() === AttendeeStatus::AWAITING_PAYMENT->name
                ) {
                    $this->markOrderAsPaidService->markOrderAsPaid(
                        orderId: $attendee->getOrderId(),
                        eventId: $attendee->getEventId(),
                    );
                }

                return new CheckInResultDTO(checkIn: $checkIn);
            });
        } catch (ResourceConflictException) {
            return new CheckInResultDTO(error: __('Attendee :attendee_name\'s ticket is listed for resale', [
                'attendee_name' => $attendee->getFullName(),
            ]));
        } catch (UniqueConstraintViolationException) {
            return new CheckInResultDTO(
                checkIn: $this->attendeeCheckInRepository->findFirstWhere([
                    AttendeeCheckInDomainObjectAbstract::ATTENDEE_ID => $attendee->getId(),
                    AttendeeCheckInDomainObjectAbstract::CHECK_IN_LIST_ID => $checkInList->getId(),
                ]),
                error: __('Attendee :attendee_name is already checked in', [
                    'attendee_name' => $attendee->getFullName(),
                ])
            );
        }
    }

    private function getExistingCheckIn(Collection $existingCheckIns, AttendeeDomainObject $attendee): ?object
    {
        return $existingCheckIns->first(
            fn ($checkIn) => $checkIn->getAttendeeId() === $attendee->getId()
        );
    }

    private function validateAttendeeStatus(
        AttendeeDomainObject $attendee,
        AttendeeCheckInActionType $checkInAction,
        EventSettingDomainObject $eventSettings,
        bool $orderAwaitsOfflinePayment,
    ): ?string {
        $allowAttendeesAwaitingPaymentToCheckIn = $eventSettings->getAllowOrdersAwaitingOfflinePaymentToCheckIn();

        if ($attendee->getStatus() === AttendeeStatus::CANCELLED->name) {
            return __('Attendee :attendee_name\'s ticket is cancelled', [
                'attendee_name' => $attendee->getFullName(),
            ]);
        }

        if ($allowAttendeesAwaitingPaymentToCheckIn
            && $attendee->getStatus() === AttendeeStatus::AWAITING_PAYMENT->name
            && ! $orderAwaitsOfflinePayment
        ) {
            return __('Unable to check in as attendee :attendee_name\'s order is awaiting payment', [
                'attendee_name' => $attendee->getFullName(),
            ]);
        }

        if (! $allowAttendeesAwaitingPaymentToCheckIn) {
            if ($checkInAction->value === AttendeeCheckInActionType::CHECK_IN->value
                && $attendee->getStatus() === AttendeeStatus::AWAITING_PAYMENT->name
            ) {
                return __('Unable to check in as attendee :attendee_name\'s order is awaiting payment', [
                    'attendee_name' => $attendee->getFullName(),
                ]);
            }

            if ($checkInAction->value === AttendeeCheckInActionType::CHECK_IN_AND_MARK_ORDER_AS_PAID->value) {
                return __('Attendee :attendee_name\'s order cannot be marked as paid. Please check your event settings', [
                    'attendee_name' => $attendee->getFullName(),
                ]);
            }
        }

        return null;
    }

    private function createCheckIn(
        AttendeeDomainObject $attendee,
        CheckInListDomainObject $checkInList,
        string $checkInUserIpAddress
    ): AttendeeCheckInDomainObject {
        return $this->attendeeCheckInRepository->create([
            AttendeeCheckInDomainObjectAbstract::ORDER_ID => $attendee->getOrderId(),
            AttendeeCheckInDomainObjectAbstract::ATTENDEE_ID => $attendee->getId(),
            AttendeeCheckInDomainObjectAbstract::CHECK_IN_LIST_ID => $checkInList->getId(),
            AttendeeCheckInDomainObjectAbstract::IP_ADDRESS => $checkInUserIpAddress,
            AttendeeCheckInDomainObjectAbstract::PRODUCT_ID => $attendee->getProductId(),
            AttendeeCheckInDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::CHECK_IN_PREFIX),
            AttendeeCheckInDomainObjectAbstract::EVENT_ID => $checkInList->getEventId(),
            AttendeeCheckInDomainObjectAbstract::EVENT_OCCURRENCE_ID => $attendee->getEventOccurrenceId(),
        ]);
    }
}
