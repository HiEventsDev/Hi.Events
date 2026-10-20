<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\DTO\GetBoxOfficesDTO;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetBoxOfficesHandler
{
    public function __construct(
        private readonly BoxOfficeRepositoryInterface $boxOfficeRepository,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    public function handle(GetBoxOfficesDTO $dto): LengthAwarePaginator
    {
        $boxOffices = $this->boxOfficeRepository
            ->loadRelation(ProductDomainObject::class)
            ->loadRelation(new Relationship(domainObject: EventDomainObject::class, name: 'event'))
            ->loadRelation(new Relationship(domainObject: EventOccurrenceDomainObject::class, name: 'event_occurrence'))
            ->loadRelation(new Relationship(domainObject: CheckInListDomainObject::class, name: 'check_in_list'))
            ->findByEventId(
                eventId: $dto->event_id,
                params: $dto->query_params,
            );

        if ($boxOffices->isEmpty()) {
            return $boxOffices;
        }

        $salesCounts = $this->orderRepository->getBoxOfficeSalesCountsByIds(
            $boxOffices->map(fn (BoxOfficeDomainObject $boxOffice) => $boxOffice->getId())->toArray(),
        );

        $boxOffices->each(function (BoxOfficeDomainObject $boxOffice) use ($salesCounts) {
            $salesCount = $salesCounts->firstWhere('boxOfficeId', $boxOffice->getId());

            $boxOffice->setSalesCount($salesCount?->salesCount ?? 0);
            $boxOffice->setGrossSales($salesCount?->grossSales ?? 0.0);
        });

        return $boxOffices;
    }
}
