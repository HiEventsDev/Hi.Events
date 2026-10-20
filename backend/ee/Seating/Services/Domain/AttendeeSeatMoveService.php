<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Exceptions\SeatsUnavailableException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;
use Illuminate\Database\DatabaseManager;
use Throwable;

class AttendeeSeatMoveService
{
    public function __construct(
        private readonly SeatClaimService $seatClaimService,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly OrderAuditLogService $orderAuditLogService,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
        private readonly EventSeatMapLookupService $eventSeatMapLookup,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws SeatsUnavailableException
     * @throws SeatSelectionInvalidException
     * @throws Throwable
     */
    public function move(
        AttendeeDomainObject $attendee,
        string $seatUid,
        bool $isOrganizer,
        string $ipAddress,
        ?string $userAgent,
    ): AttendeeDomainObject {
        return $this->databaseManager->transaction(function () use ($attendee, $seatUid, $isOrganizer, $ipAddress, $userAgent) {
            $this->seatClaimService->move(
                $attendee,
                $seatUid,
                allowBlocked: $isOrganizer,
                requireSameBand: ! $isOrganizer,
                enforceSelectionRules: ! $isOrganizer,
            );

            $moved = $this->attendeeRepository->findById($attendee->getId());

            $previousBand = $attendee->getSeatUid() === null
                ? null
                : $this->eventSeatMapLookup->bandOf($attendee->getEventId(), $attendee->getSeatUid());
            $newBand = $this->eventSeatMapLookup->bandOf($attendee->getEventId(), $seatUid);

            $this->orderAuditLogService->logAttendeeUpdate(
                attendee: $attendee,
                oldValues: array_filter([
                    'seat_label' => $attendee->getSeatLabel(),
                    'band_key' => $previousBand === $newBand ? null : $previousBand,
                ], static fn ($value) => $value !== null),
                newValues: array_filter([
                    'seat_label' => $moved->getSeatLabel(),
                    'band_key' => $previousBand === $newBand ? null : $newBand,
                ], static fn ($value) => $value !== null),
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );

            $this->domainEventDispatcherService->dispatch(new AttendeeEvent(
                type: DomainEventType::ATTENDEE_UPDATED,
                attendeeId: $attendee->getId(),
            ));

            return $moved;
        });
    }
}
