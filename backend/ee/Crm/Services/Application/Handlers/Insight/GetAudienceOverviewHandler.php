<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\Enums\CrmSegmentPreset;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmInsightsRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight\DTO\AudienceOverviewDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentCountService;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;

class GetAudienceOverviewHandler
{
    private const MONTHS = 12;

    private const CACHE_SECONDS = 300;

    private const NAMED_EVENTS = 5;

    public function __construct(
        private readonly CrmInsightsRepositoryInterface $insightsRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly CrmCacheVersion $cacheVersion,
        private readonly SegmentCountService $segmentCountService,
    ) {}

    public function handle(int $organizerId, bool $canViewFinancials): AudienceOverviewDTO
    {
        $organizer = $this->organizerRepository
            ->loadRelation(OrganizerSettingDomainObject::class)
            ->findById($organizerId);
        $timezone = $organizer->getTimezone() ?? 'UTC';
        $firstMonth = now()->startOfMonth()->subMonths(self::MONTHS - 1);

        $overview = $this->cacheVersion->remember(
            $organizerId,
            'overview:'.$timezone.':'.$firstMonth->toDateString(),
            self::CACHE_SECONDS,
            fn (): array => $this->computeOverview($organizerId, $timezone, $firstMonth),
        );

        $optIn = $this->insightsRepository->getLiveEventOptInCounts($organizerId, now());

        return new AudienceOverviewDTO(
            ...$overview,
            marketing_opt_in_enabled: (bool) $organizer->getOrganizerSettings()?->getDefaultShowMarketingOptIn(),
            live_events_count: (int) $optIn->live_events_count,
            live_events_collecting_opt_in: (int) $optIn->collecting_count,
            live_events_not_collecting_opt_in: (int) $optIn->live_events_count === (int) $optIn->collecting_count
                ? []
                : $this->insightsRepository
                    ->findLiveEventsNotCollectingOptIn($organizerId, now(), self::NAMED_EVENTS)
                    ->map(static fn (object $event) => ['id' => (int) $event->id, 'title' => $event->title])
                    ->all(),
            revenue_concentration: $canViewFinancials
                ? $this->cacheVersion->remember(
                    $organizerId,
                    'overview-revenue-concentration',
                    self::CACHE_SECONDS,
                    fn (): array => $this->computeRevenueConcentration($organizerId),
                )
                : null,
        );
    }

    private function computeOverview(int $organizerId, string $timezone, CarbonInterface $firstMonth): array
    {
        $totals = $this->insightsRepository->getAudienceTotals($organizerId, now());
        $buyers = (int) $totals->buyers_count;
        $repeatBuyers = $this->segmentCountService->count($organizerId, CrmSegmentPreset::REPEAT_BUYERS->definition())->contacts_count;

        return [
            'contacts_count' => (int) $totals->contacts_count,
            'mailable_count' => (int) $totals->mailable_count,
            'buyers_count' => $buyers,
            'repeat_buyers_count' => $repeatBuyers,
            'repeat_rate' => $buyers === 0 ? 0.0 : round($repeatBuyers / $buyers * 100, 1),
            'new_contacts_count' => (int) $totals->new_contacts_count,
            'orders_count' => (int) $totals->orders_count,
            'tickets_count' => (int) $totals->tickets_count,
            'average_events_per_buyer' => round((float) $totals->average_events_per_buyer, 2),
            'monthly_buyers' => $this->insightsRepository
                ->getMonthlyBuyers($organizerId, $timezone, $firstMonth)
                ->map(static fn (object $row) => [
                    'month' => $row->month,
                    'new_buyers' => (int) $row->new_buyers,
                    'returning_buyers' => (int) $row->returning_buyers,
                ])
                ->all(),
        ];
    }

    private function computeRevenueConcentration(int $organizerId): array
    {
        return $this->insightsRepository->getRevenueConcentration($organizerId)->map(static fn (object $row) => [
            'currency' => $row->currency,
            'total_net' => (float) $row->total_net,
            'top_decile_net' => (float) $row->top_decile_net,
            'top_decile_share' => (float) $row->total_net > 0 ? round((float) $row->top_decile_net / (float) $row->total_net * 100, 1) : 0.0,
        ])->all();
    }
}
