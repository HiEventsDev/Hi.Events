<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\CheckInListDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Http\DTO\FilterFieldDTO;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Services\Domain\CheckInList\CheckInListActivityValidator;
use Illuminate\Contracts\Pagination\Paginator;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetCheckInListAttendeesPublicHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly CheckInListRepositoryInterface $checkInListRepository,
        private readonly CheckInListActivityValidator $checkInListActivityValidator,
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

        $this->checkInListActivityValidator->assertActive($checkInList);

        $queryParams = $this->applyCheckInListOccurrenceScope($checkInList, $queryParams);

        $attendees = $this->attendeeRepository->getAttendeesByCheckInShortId($shortId, $queryParams);

        // Set the check-in for each attendee
        $attendees->getCollection()->transform(function (AttendeeDomainObject $attendee) use ($checkInList) {
            $attendee->setCheckIn($attendee->getCheckIns()?->first(fn ($checkIn) => $checkIn->getCheckInListId() === $checkInList->getId()));

            return $attendee;
        });

        return $attendees;
    }

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
}
