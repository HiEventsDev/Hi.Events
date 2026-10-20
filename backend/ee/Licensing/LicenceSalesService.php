<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing;

use Carbon\CarbonImmutable;
use HiEvents\Enterprise\Licensing\DTO\LicenceSalesPeriodDTO;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;

class LicenceSalesService
{
    public function __construct(
        private readonly LicenceService $licenceService,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    /**
     * @return LicenceSalesPeriodDTO[]
     */
    public function salesByLicenceYear(): array
    {
        $licence = $this->licenceService->state()->licence;

        if ($licence?->covered_sales === null) {
            return [];
        }

        $now = CarbonImmutable::now('UTC');
        $latestYearEndsAt = $this->latestYearEndsAt($licence->expires_at, $now);

        return array_map(
            fn (CarbonImmutable $endsAt) => $this->period($endsAt->subYearNoOverflow(), $endsAt, $now),
            [$latestYearEndsAt, $latestYearEndsAt->subYearNoOverflow()],
        );
    }

    private function period(CarbonImmutable $startsAt, CarbonImmutable $endsAt, CarbonImmutable $now): LicenceSalesPeriodDTO
    {
        return new LicenceSalesPeriodDTO(
            starts_at: $startsAt,
            ends_at: $endsAt,
            is_current: $now->greaterThanOrEqualTo($startsAt) && $now->lessThan($endsAt),
            sales: $this->orderRepository->getNetSalesByCurrency($startsAt, $endsAt),
        );
    }

    private function latestYearEndsAt(CarbonImmutable $expiresAt, CarbonImmutable $now): CarbonImmutable
    {
        $endsAt = $expiresAt;

        while ($endsAt->subYearNoOverflow()->greaterThan($now)) {
            $endsAt = $endsAt->subYearNoOverflow();
        }

        return $endsAt;
    }
}
