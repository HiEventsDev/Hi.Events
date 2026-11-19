<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\CrmSegmentPreset;
use HiEvents\DomainObjects\Enums\SmsCountry;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSegmentRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentDefinitionNormalizer;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Infrastructure\Email\Delivery\EmailDeliveryWebhookReceiverResolver;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;

class CampaignReportService
{
    private const SENDING_STATS_CACHE_SECONDS = 10;

    private const STATS_CACHE_SECONDS = 300;

    private const REVENUE_CACHE_SECONDS = 300;

    public function __construct(
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly CrmSegmentRepositoryInterface $segmentRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly PromoCodeRepositoryInterface $promoCodeRepository,
        private readonly CacheRepository $cache,
        private readonly CrmCacheVersion $cacheVersion,
        private readonly EmailDeliveryWebhookReceiverResolver $deliveryWebhookResolver,
    ) {}

    /**
     * @param  Collection<int, CrmCampaignDomainObject>  $campaigns
     */
    public function attachSummaries(Collection $campaigns, bool $withRevenue): void
    {
        if ($campaigns->isEmpty()) {
            return;
        }

        $stats = $this->deliveryStats($campaigns);
        $emailReportsAvailable = $this->deliveryWebhookResolver->anyConfigured();

        $events = $this->eventRepository->findWhereIn(
            'id',
            $campaigns->map(static fn (CrmCampaignDomainObject $campaign) => $campaign->getEventId())->filter()->unique()->values()->all(),
        )->keyBy(static fn ($event) => $event->getId());

        $segments = $this->segmentRepository->findWhereIn(
            'id',
            $campaigns->map(static fn (CrmCampaignDomainObject $campaign) => $campaign->getSegmentId())->filter()->unique()->values()->all(),
        )->keyBy(static fn ($segment) => $segment->getId());

        $textsWaiting = $this->textsWaitingForSendingHours($campaigns);

        $campaigns->each(function (CrmCampaignDomainObject $campaign) use ($stats, $events, $segments, $withRevenue, $emailReportsAvailable, $textsWaiting): void {
            $campaign
                ->setDeliveryReportsAvailable($campaign->isSms() || $emailReportsAvailable)
                ->setSmsWaiting($textsWaiting->get($campaign->getId(), []))
                ->setDeliveryStats($stats->get($campaign->getId()))
                ->setEventTitle($events->get($campaign->getEventId())?->getTitle())
                ->setSegmentName($segments->get($campaign->getSegmentId())?->getName()
                    ?? CrmSegmentPreset::tryFrom((string) $campaign->getPresetSegment())?->value)
                ->setHasFinancialRules(SegmentDefinitionNormalizer::hasFinancialRules(
                    $segments->get($campaign->getSegmentId())?->getDefinition() ?? $campaign->getSegmentDefinition(),
                ));

            if ($withRevenue) {
                $campaign->setRevenue($this->revenue($campaign));
            }
        });
    }

    /**
     * @param  Collection<int, CrmCampaignDomainObject>  $campaigns
     * @return Collection<int, array<int, array{country: string, pending_count: int, next_sending_at: string}>>
     */
    private function textsWaitingForSendingHours(Collection $campaigns): Collection
    {
        $sendingTexts = $campaigns
            ->filter(static fn (CrmCampaignDomainObject $campaign) => $campaign->isSms() && $campaign->getStatus() === CrmCampaignStatus::SENDING->value)
            ->map(static fn (CrmCampaignDomainObject $campaign) => $campaign->getId())
            ->values()
            ->all();
        $now = now();

        return $this->campaignRepository
            ->countPendingRecipientsByCountry($sendingTexts)
            ->filter(static fn (object $row) => SmsCountry::tryFrom($row->phone_country)?->allowsSendingAt($now) === false)
            ->groupBy('campaign_id')
            ->map(static fn (Collection $rows) => $rows->map(static fn (object $row) => [
                'country' => $row->phone_country,
                'pending_count' => (int) $row->pending_count,
                'next_sending_at' => SmsCountry::from($row->phone_country)->nextSendingTime($now)->toIso8601String(),
            ])->values()->all());
    }

    /**
     * @param  Collection<int, CrmCampaignDomainObject>  $campaigns
     * @return Collection<int, object>
     */
    private function deliveryStats(Collection $campaigns): Collection
    {
        $campaigns = $campaigns->keyBy(static fn (CrmCampaignDomainObject $campaign) => $campaign->getId());
        $cached = $this->cache->many($campaigns->map(fn (CrmCampaignDomainObject $campaign) => $this->statsKey($campaign))->values()->all());
        $stats = $campaigns
            ->map(fn (CrmCampaignDomainObject $campaign) => $cached[$this->statsKey($campaign)] ?? null)
            ->filter();
        $missing = $campaigns->keys()->diff($stats->keys())->values()->all();

        if ($missing !== []) {
            $this->campaignRepository->getDeliveryStats($missing)->each(function (object $row) use ($campaigns, $stats): void {
                $campaignId = (int) $row->campaign_id;
                $stats->put($campaignId, (array) $row);
                $this->cache->put(
                    $this->statsKey($campaigns->get($campaignId)),
                    (array) $row,
                    $campaigns->get($campaignId)->getStatus() === CrmCampaignStatus::SENDING->value
                        ? self::SENDING_STATS_CACHE_SECONDS
                        : self::STATS_CACHE_SECONDS,
                );
            });
        }

        return $stats->map(static fn (array $row) => (object) $row);
    }

    /**
     * @param  int[]  $campaignIds
     */
    public function forgetDeliveryStats(array $campaignIds): void
    {
        $keys = [];

        foreach ($campaignIds as $campaignId) {
            foreach (CrmCampaignStatus::cases() as $status) {
                $keys[] = self::statsKeyFor($campaignId, $status->value);
            }
        }

        $this->cache->deleteMultiple($keys);
    }

    private function statsKey(CrmCampaignDomainObject $campaign): string
    {
        return self::statsKeyFor($campaign->getId(), $campaign->getStatus());
    }

    private static function statsKeyFor(int $campaignId, string $status): string
    {
        return 'crm:campaign:'.$campaignId.':delivery-stats:'.$status;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function revenue(CrmCampaignDomainObject $campaign): array
    {
        return $this->cacheVersion->remember(
            $campaign->getOrganizerId(),
            'campaign-revenue:'.$campaign->getId().':'.$campaign->getSentAt(),
            self::REVENUE_CACHE_SECONDS,
            fn (): array => $this->campaignRepository->getAttributedRevenue($campaign)->map(static fn (object $row) => [
                'currency' => $row->currency,
                'attributed_orders' => (int) $row->attributed_orders,
                'attributed_revenue' => (float) $row->attributed_revenue,
                'influenced_orders' => (int) $row->influenced_orders,
                'influenced_revenue' => (float) $row->influenced_revenue,
            ])->all(),
        );
    }

    public function attachDetail(CrmCampaignDomainObject $campaign, bool $withRevenue): void
    {
        $this->attachSummaries(collect([$campaign]), $withRevenue);

        if ($campaign->getPromoCodeId() !== null) {
            $campaign->setPromoCode($this->promoCodeRepository->findFirstWhere(['id' => $campaign->getPromoCodeId()])?->getCode());
        }
    }
}
