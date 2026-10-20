<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;

class GetBoxOfficeHandler
{
    public function __construct(
        private readonly BoxOfficeRepositoryInterface $boxOfficeRepository,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $boxOfficeId, int $eventId): BoxOfficeDomainObject
    {
        $boxOffice = $this->boxOfficeRepository
            ->loadRelation(ProductDomainObject::class)
            ->loadRelation(new Relationship(domainObject: EventDomainObject::class, name: 'event'))
            ->loadRelation(new Relationship(domainObject: EventOccurrenceDomainObject::class, name: 'event_occurrence'))
            ->loadRelation(new Relationship(domainObject: CheckInListDomainObject::class, name: 'check_in_list'))
            ->findFirstWhere([
                'event_id' => $eventId,
                'id' => $boxOfficeId,
            ]);

        if ($boxOffice === null) {
            throw new ResourceNotFoundException(__('Box office not found'));
        }

        $salesCount = $this->orderRepository
            ->getBoxOfficeSalesCountsByIds([$boxOffice->getId()])
            ->first();

        $boxOffice->setSalesCount($salesCount?->salesCount ?? 0);
        $boxOffice->setGrossSales($salesCount?->grossSales ?? 0.0);

        return $boxOffice;
    }
}
