<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionScopeDTO;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Services\Domain\CheckInList\CheckInListActivityValidator;
use Illuminate\Validation\ValidationException;

class BoxOfficeSessionScopeService
{
    public function __construct(
        private readonly BoxOfficeRepositoryInterface $boxOfficeRepository,
        private readonly CheckInListRepositoryInterface $checkInListRepository,
        private readonly CheckInListActivityValidator $checkInListActivityValidator,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function loadBoxOffice(string $shortId): BoxOfficeDomainObject
    {
        $boxOffice = $this->boxOfficeRepository
            ->loadRelation(new Relationship(domainObject: EventDomainObject::class, nested: [
                new Relationship(domainObject: EventSettingDomainObject::class, name: 'event_settings'),
                new Relationship(domainObject: EventOccurrenceDomainObject::class, name: 'event_occurrences'),
            ], name: 'event'))
            ->loadRelation(new Relationship(domainObject: EventOccurrenceDomainObject::class, name: 'event_occurrence'))
            ->loadRelation(new Relationship(domainObject: CheckInListDomainObject::class, name: 'check_in_list'))
            ->findFirstWhere(['short_id' => $shortId]);

        if ($boxOffice === null) {
            throw new ResourceNotFoundException(__('Box office not found'));
        }

        return $boxOffice;
    }

    public function canSwitchOccurrence(BoxOfficeDomainObject $boxOffice): bool
    {
        return $boxOffice->getEventOccurrenceId() === null
            && $boxOffice->getEvent()->getType() === EventType::RECURRING->name;
    }

    /**
     * @throws ValidationException
     */
    public function resolve(BoxOfficeDomainObject $boxOffice, ?int $requestedOccurrenceId): BoxOfficeSessionScopeDTO
    {
        $event = $boxOffice->getEvent();
        $occurrence = $this->resolveOccurrence($boxOffice, $event, $requestedOccurrenceId);
        $checkInList = $boxOffice->getCheckInList() ?? $this->findSystemDefaultCheckInList($event->getId());
        $reason = $this->checkInUnavailableReason($checkInList, $occurrence);

        return new BoxOfficeSessionScopeDTO(
            event_occurrence: $occurrence,
            check_in_list_short_id: $checkInList?->getShortId(),
            check_in_available: $reason === null && $checkInList !== null,
            check_in_unavailable_reason: $reason,
        );
    }

    /**
     * @throws ValidationException
     */
    private function resolveOccurrence(
        BoxOfficeDomainObject $boxOffice,
        EventDomainObject $event,
        ?int $requestedOccurrenceId,
    ): ?EventOccurrenceDomainObject {
        $occurrences = $event->getEventOccurrences() ?? collect();

        if ($boxOffice->getEventOccurrence() !== null) {
            return $boxOffice->getEventOccurrence();
        }

        if ($event->getType() === EventType::SINGLE->name) {
            return $occurrences->first();
        }

        $occurrence = $occurrences->first(
            fn (EventOccurrenceDomainObject $occurrence) => $occurrence->getId() === $requestedOccurrenceId
                && ! $occurrence->isCancelled(),
        );

        if ($occurrence === null) {
            throw ValidationException::withMessages([
                'event_occurrence_id' => __('Choose a date to sell tickets for'),
            ]);
        }

        return $occurrence;
    }

    private function findSystemDefaultCheckInList(int $eventId): ?CheckInListDomainObject
    {
        return $this->checkInListRepository->findFirstWhere([
            'event_id' => $eventId,
            'is_system_default' => true,
        ]);
    }

    private function checkInUnavailableReason(
        ?CheckInListDomainObject $checkInList,
        ?EventOccurrenceDomainObject $occurrence,
    ): ?string {
        if ($checkInList === null) {
            return __('This event has no check-in list');
        }

        try {
            $this->checkInListActivityValidator->assertActive($checkInList);
        } catch (CannotCheckInException $exception) {
            return $exception->getMessage();
        }

        if ($checkInList->getEventOccurrenceId() !== null
            && $occurrence !== null
            && $checkInList->getEventOccurrenceId() !== $occurrence->getId()) {
            return __('The linked check-in list is for a different date');
        }

        return null;
    }
}
