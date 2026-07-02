<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\CheckInListDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Helper\DateHelper;
use HiEvents\Http\DTO\FilterFieldDTO;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use Illuminate\Contracts\Pagination\Paginator;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetCheckInListAttendeesPublicHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly CheckInListRepositoryInterface $checkInListRepository,
    ) {}

    /**
     * @throws CannotCheckInException
     */
    public function handle(string $shortId, QueryParamsDTO $queryParams): Paginator
    {
        $checkInList = $this->checkInListRepository
            ->loadRelation(ProductDomainObject::class)
            ->loadRelation(new Relationship(EventDomainObject::class, name: 'event'))
            ->findFirstWhere([
                CheckInListDomainObjectAbstract::SHORT_ID => $shortId,
            ]);

        if (! $checkInList) {
            throw new ResourceNotFoundException(__('Check-in list not found'));
        }

        $this->validateCheckInListIsActive($checkInList);

        $queryParams = $this->applyCheckInListOccurrenceScope($checkInList, $queryParams);

        $attendees = $this->attendeeRepository->getAttendeesByCheckInShortId($shortId, $queryParams);

        // Set the check-in for each attendee
        $attendees->getCollection()->transform(function (AttendeeDomainObject $attendee) use ($checkInList) {
            $attendee->setCheckIn($attendee->getCheckIns()?->first(fn ($checkIn) => $checkIn->getCheckInListId() === $checkInList->getId()));

            return $attendee;
        });

        return $attendees;
    }

    /**
     * Force the attendee query to the list's own occurrence when set; otherwise
     * honour the client's filter (that's how the optional filter pill works).
     */
    private function applyCheckInListOccurrenceScope(
        CheckInListDomainObject $checkInList,
        QueryParamsDTO $queryParams,
    ): QueryParamsDTO {
        $scopedOccurrenceId = $checkInList->getEventOccurrenceId();
        if ($scopedOccurrenceId === null) {
            return $queryParams;
        }

        $filterFields = ($queryParams->filter_fields ?? collect())
            ->reject(fn (FilterFieldDTO $f) => $f->field === 'event_occurrence_id')
            ->push(new FilterFieldDTO(
                field: 'event_occurrence_id',
                operator: 'eq',
                value: (string) $scopedOccurrenceId,
            ))
            ->values();

        return new QueryParamsDTO(
            page: $queryParams->page,
            per_page: $queryParams->per_page,
            sort_by: $queryParams->sort_by,
            sort_direction: $queryParams->sort_direction,
            query: $queryParams->query,
            filter_fields: $filterFields,
            includes: $queryParams->includes,
            query_params: $queryParams->query_params,
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
            throw new CannotCheckInException(__('Check-in list is not active yet'));
        }
    }
}
