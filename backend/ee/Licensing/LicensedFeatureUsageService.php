<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing;

use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\StripeTerminalReaderRepositoryInterface;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapRepositoryInterface;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatMapRepositoryInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class LicensedFeatureUsageService
{
    private const CACHE_KEY = 'licensing.features_in_use';

    private const GENERATION_CACHE_KEY = 'licensing.features_in_use.generation';

    private const CACHE_TTL_SECONDS = 600;

    public function __construct(
        private readonly SeatMapRepositoryInterface $seatMapRepository,
        private readonly EventSeatMapRepositoryInterface $eventSeatMapRepository,
        private readonly BoxOfficeRepositoryInterface $boxOfficeRepository,
        private readonly StripeTerminalReaderRepositoryInterface $stripeTerminalReaderRepository,
        private readonly LicenceService $licenceService,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * @return LicensedFeature[]
     */
    public function featuresInUse(?int $accountId = null): array
    {
        $values = $this->cache->remember(
            $this->cacheKey($accountId),
            self::CACHE_TTL_SECONDS,
            fn () => array_map(
                static fn (LicensedFeature $feature) => $feature->value,
                $this->detectFeaturesInUse($accountId),
            ),
        );

        $features = array_values(array_filter(array_map(
            static fn (string $value) => LicensedFeature::tryFrom($value),
            $values,
        )));

        if (in_array(LicensedFeature::WHITE_LABEL, $this->licenceService->state()->features, true)) {
            $features[] = LicensedFeature::WHITE_LABEL;
        }

        return $features;
    }

    public function forget(): void
    {
        $this->cache->forever(self::GENERATION_CACHE_KEY, $this->generation() + 1);
    }

    /**
     * @return LicensedFeature[]
     */
    private function detectFeaturesInUse(?int $accountId): array
    {
        $features = [];

        if ($this->seatMapRepository->existsForLiveOrganizer($accountId)
            || $this->eventSeatMapRepository->existsForUpcomingEvent($accountId)) {
            $features[] = LicensedFeature::SEATING;
        }

        if ($this->boxOfficeRepository->existsInUseForUpcomingEvent($accountId)
            || $this->stripeTerminalReaderRepository->existsForLiveOrganizer($accountId)) {
            $features[] = LicensedFeature::BOX_OFFICE;
        }

        return $features;
    }

    private function cacheKey(?int $accountId): string
    {
        return sprintf('%s.%d.%s', self::CACHE_KEY, $this->generation(), $accountId ?? 'install');
    }

    private function generation(): int
    {
        return (int) $this->cache->get(self::GENERATION_CACHE_KEY, 0);
    }
}
