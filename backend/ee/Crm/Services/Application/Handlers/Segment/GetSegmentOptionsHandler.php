<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmInsightsRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\DTO\SegmentOptionsDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\DTO\SegmentOptionsQueryDTO;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;

class GetSegmentOptionsHandler
{
    private const DEFAULT_EVENTS = 200;

    private const SEARCH_RESULTS = 50;

    public function __construct(
        private readonly CrmInsightsRepositoryInterface $insightsRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
    ) {}

    public function handle(int $organizerId, SegmentOptionsQueryDTO $query): SegmentOptionsDTO
    {
        $currencies = $this->insightsRepository->getCurrencies($organizerId);
        $organizerCurrency = $this->organizerRepository->findById($organizerId)->getCurrency();

        return new SegmentOptionsDTO(
            events: $this->insightsRepository->getEventOptions(
                $organizerId,
                $query->event_query,
                $query->event_ids,
                $query->event_query === null ? self::DEFAULT_EVENTS : self::SEARCH_RESULTS,
            )->map(static fn (object $event) => [
                'id' => (int) $event->id,
                'title' => $event->title,
                'status' => $event->status,
                'timezone' => $event->timezone,
                'starts_at' => $event->next_start ?? $event->last_start,
            ]),
            products: $query->with_products
                ? $this->insightsRepository->getProductOptions($organizerId, $query->product_query, $query->product_ids, self::SEARCH_RESULTS)
                : collect(),
            locations: $this->insightsRepository->getLocationOptions($organizerId),
            currencies: array_values(array_unique([$organizerCurrency, ...$currencies])),
        );
    }
}
