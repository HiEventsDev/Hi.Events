<?php

namespace HiEvents\Services\Application\Handlers\Waitlist;

use HiEvents\Constants;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Services\Application\Handlers\Waitlist\DTO\WaitlistProductStatsDTO;
use HiEvents\Services\Application\Handlers\Waitlist\DTO\WaitlistStatsDTO;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;

class GetWaitlistStatsHandler
{
    public function __construct(
        private readonly WaitlistEntryRepositoryInterface       $waitlistEntryRepository,
        private readonly AvailableProductQuantitiesFetchService $availableQuantitiesService,
    )
    {
    }

    public function handle(int $eventId): WaitlistStatsDTO
    {
        $stats = $this->waitlistEntryRepository->getStatsByEventId($eventId);
        $productRows = $this->waitlistEntryRepository->getProductStatsByEventId($eventId);

        $quantities = $this->availableQuantitiesService->getAvailableProductQuantities($eventId, ignoreCache: true);

        $products = $productRows->map(function ($row) use ($quantities) {
            $actualAvailable = $this->getAvailableCountForPrice($quantities, (int) $row->product_price_id);
            $offeredCount = (int) $row->offered;

            if ($actualAvailable === Constants::INFINITE) {
                $available = null;
            } else {
                $available = max(0, $actualAvailable - $offeredCount);
            }

            return new WaitlistProductStatsDTO(
                product_price_id: (int) $row->product_price_id,
                product_title: $row->product_title,
                waiting: (int) $row->waiting,
                offered: $offeredCount,
                available: $available,
            );
        })->all();

        $stats->products = $products;

        return $stats;
    }

    private function getAvailableCountForPrice(object $quantities, int $priceId): int
    {
        foreach ($quantities->productQuantities as $productQuantity) {
            if ($productQuantity->price_id === $priceId) {
                $available = max(0, $productQuantity->quantity_available);
                if ($available === Constants::INFINITE) {
                    return Constants::INFINITE;
                }
                return $available;
            }
        }

        return 0;
    }
}
