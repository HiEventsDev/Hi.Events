<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Enterprise\BoxOffice\Repository\DTO\BoxOfficeSummaryRowDTO;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\DTO\BoxOfficeStatsDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\DTO\GetBoxOfficeStatsDTO;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Support\Collection;

class GetBoxOfficeStatsHandler
{
    public function __construct(
        private readonly BoxOfficeRepositoryInterface $boxOfficeRepository,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(GetBoxOfficeStatsDTO $dto): BoxOfficeStatsDTO
    {
        $boxOffice = $this->boxOfficeRepository
            ->loadRelation(new Relationship(domainObject: EventDomainObject::class, name: 'event'))
            ->findFirstWhere([
                'event_id' => $dto->event_id,
                'id' => $dto->box_office_id,
            ]);

        if ($boxOffice === null) {
            throw new ResourceNotFoundException(__('Box office not found'));
        }

        $timezone = $boxOffice->getEvent()->getTimezone();

        $rows = $this->orderRepository->getBoxOfficeSummary(
            boxOfficeId: $boxOffice->getId(),
            timezone: $timezone,
            from: $dto->from ? Carbon::parse($dto->from, $timezone)->startOfDay()->utc()->toDateTimeString() : null,
            to: $dto->to ? Carbon::parse($dto->to, $timezone)->endOfDay()->utc()->toDateTimeString() : null,
        );

        return new BoxOfficeStatsDTO(
            currency: $boxOffice->getEvent()->getCurrency(),
            orders: $rows->sum(fn (BoxOfficeSummaryRowDTO $row) => $row->orders),
            gross: round($rows->sum(fn (BoxOfficeSummaryRowDTO $row) => $row->gross), 2),
            refunded: round($rows->sum(fn (BoxOfficeSummaryRowDTO $row) => $row->refunded), 2),
            by_tender: $this->groupRows($rows, 'tender'),
            by_operator: $this->groupRows($rows, 'operatorName', 'operator_name'),
            by_day: $this->groupRows($rows, 'day'),
        );
    }

    private function groupRows(Collection $rows, string $property, ?string $key = null): array
    {
        return $rows
            ->groupBy(fn (BoxOfficeSummaryRowDTO $row) => $row->{$property})
            ->map(fn (Collection $group, string $value) => [
                ($key ?? $property) => $value,
                'orders' => $group->sum(fn (BoxOfficeSummaryRowDTO $row) => $row->orders),
                'gross' => round($group->sum(fn (BoxOfficeSummaryRowDTO $row) => $row->gross), 2),
                'refunded' => round($group->sum(fn (BoxOfficeSummaryRowDTO $row) => $row->refunded), 2),
            ])
            ->values()
            ->all();
    }
}
