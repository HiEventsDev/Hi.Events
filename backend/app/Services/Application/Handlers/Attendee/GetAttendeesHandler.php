<?php

namespace HiEvents\Services\Application\Handlers\Attendee;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class GetAttendeesHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
    )
    {
    }

    public function handle(int $eventId, QueryParamsDTO $queryParams): LengthAwarePaginator
    {
        return $this->attendeeRepository
            ->loadRelation(new Relationship(
                domainObject: OrderDomainObject::class,
                name: 'order'
            ))
            ->loadRelation(new Relationship(
                domainObject: AttendeeCheckInDomainObject::class,
                name: 'check_ins'
            ))
            ->findByEventId($eventId, $queryParams);
    }
}
