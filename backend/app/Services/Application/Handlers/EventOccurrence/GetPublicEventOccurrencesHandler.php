<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Exceptions\InvalidOccurrenceDatesException;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\GetPublicEventHandler;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\GetPublicEventOccurrencesDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\GetPublicEventOccurrencesResultDTO;
use HiEvents\Services\Domain\EventOccurrence\PublicOccurrenceVisibilityService;

class GetPublicEventOccurrencesHandler
{
    private const MAX_RANGE_DAYS = 45;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly PublicOccurrenceVisibilityService $occurrenceVisibilityService,
    ) {}

    /**
     * @throws InvalidOccurrenceDatesException
     */
    public function handle(GetPublicEventOccurrencesDTO $dto): GetPublicEventOccurrencesResultDTO
    {
        [$startDateFrom, $startDateTo] = $this->validateRange($dto);

        $event = $this->eventRepository
            ->loadRelation(new Relationship(ProductCategoryDomainObject::class, [
                new Relationship(ProductDomainObject::class),
            ]))
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->findById($dto->eventId);

        $where = $this->occurrenceVisibilityService->buildWhereConditions(
            eventId: $dto->eventId,
            isRecurring: $event->getType() === EventType::RECURRING->name,
            hideSoldOutOccurrences: $this->occurrenceVisibilityService->shouldHideSoldOutOccurrences($event),
        );

        $where[] = [EventOccurrenceDomainObjectAbstract::START_DATE, '>=', $startDateFrom->toDateTimeString()];
        $where[] = [EventOccurrenceDomainObjectAbstract::START_DATE, '<=', $startDateTo->toDateTimeString()];

        $occurrences = $this->occurrenceRepository
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ]))
            ->findWhere(
                where: $where,
                orderAndDirections: [
                    new OrderAndDirection(EventOccurrenceDomainObjectAbstract::START_DATE, 'asc'),
                ],
                limit: GetPublicEventHandler::MAX_PUBLIC_OCCURRENCES,
            );

        return new GetPublicEventOccurrencesResultDTO(
            event: $event,
            occurrences: $occurrences,
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     *
     * @throws InvalidOccurrenceDatesException
     */
    private function validateRange(GetPublicEventOccurrencesDTO $dto): array
    {
        if ($dto->startDateFrom === null || $dto->startDateTo === null) {
            throw new InvalidOccurrenceDatesException(
                __('Both start_date_from and start_date_to are required.')
            );
        }

        try {
            $startDateFrom = Carbon::parse($dto->startDateFrom, 'UTC');
            $startDateTo = Carbon::parse($dto->startDateTo, 'UTC');
        } catch (InvalidFormatException) {
            throw new InvalidOccurrenceDatesException(
                __('The date range is invalid.')
            );
        }

        if ($startDateFrom->greaterThan($startDateTo)
            || $startDateFrom->diffInDays($startDateTo) > self::MAX_RANGE_DAYS) {
            throw new InvalidOccurrenceDatesException(
                __('The date range must be valid and span at most :days days.', ['days' => self::MAX_RANGE_DAYS])
            );
        }

        return [$startDateFrom, $startDateTo];
    }
}
