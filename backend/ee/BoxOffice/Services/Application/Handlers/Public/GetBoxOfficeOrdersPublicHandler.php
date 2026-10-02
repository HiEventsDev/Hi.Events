<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetBoxOfficeOrdersPublicHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    public function handle(BoxOfficeDomainObject $boxOffice, QueryParamsDTO $params): LengthAwarePaginator
    {
        return $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->loadRelation(new Relationship(
                domainObject: AttendeeDomainObject::class,
                nested: [new Relationship(domainObject: AttendeeCheckInDomainObject::class, name: 'check_ins')],
            ))
            ->findByBoxOfficeId($boxOffice->getId(), $params);
    }
}
