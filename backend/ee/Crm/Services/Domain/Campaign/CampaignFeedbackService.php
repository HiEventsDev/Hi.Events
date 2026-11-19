<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;

class CampaignFeedbackService
{
    private const BATCH_SIZE = 500;

    private const MAX_BATCHES_PER_RUN = 10;

    private const CHECKED_UNTIL_CACHE_KEY = 'crm:campaign-feedback:checked-until';

    private const OVERLAP_MINUTES = 10;

    private const LOOKBACK_DAYS_WITHOUT_WATERMARK = 30;

    public function __construct(
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly CrmProjectionRepositoryInterface $projectionRepository,
        private readonly CrmCacheVersion $cacheVersion,
        private readonly CacheRepository $cache,
        private readonly CampaignHealthGuard $healthGuard,
        private readonly DatabaseManager $databaseManager,
        private readonly CampaignReportService $reportService,
    ) {}

    public function recordBouncesAndComplaints(): void
    {
        $startedAt = now();
        $checkedUntil = $this->cache->get(self::CHECKED_UNTIL_CACHE_KEY);
        $recordedSince = $checkedUntil === null
            ? $startedAt->copy()->subDays(self::LOOKBACK_DAYS_WITHOUT_WATERMARK)
            : Carbon::createFromTimestamp((int) $checkedUntil)->subMinutes(self::OVERLAP_MINUTES);

        for ($batch = 0; $batch < self::MAX_BATCHES_PER_RUN; $batch++) {
            if ($this->recordBatch($recordedSince) < self::BATCH_SIZE) {
                $this->cache->forever(self::CHECKED_UNTIL_CACHE_KEY, $startedAt->getTimestamp());

                return;
            }
        }
    }

    private function recordBatch(CarbonInterface $recordedSince): int
    {
        $feedback = $this->campaignRepository->findUnrecordedFeedback($recordedSince, self::BATCH_SIZE);

        $feedback
            ->groupBy('organizer_id')
            ->each(function ($rows, $organizerId): void {
                $this->databaseManager->transaction(function () use ($rows, $organizerId): void {
                    $this->projectionRepository->lockOrganizer((int) $organizerId);
                    $stillPresent = $this->contactRepository->findExistingIds((int) $organizerId, $rows->pluck('contact_id')->filter()->map(static fn ($id) => (int) $id)->all());

                    foreach ($rows as $row) {
                        if (! in_array((int) $row->contact_id, $stillPresent, true)) {
                            $this->contactRepository->strengthenDeletedRecipientSuppression(
                                (int) $organizerId,
                                (int) $row->recipient_id,
                                $row->complained_at !== null ? CrmEmailMarketingStatus::COMPLAINED : CrmEmailMarketingStatus::BOUNCED,
                            );

                            continue;
                        }

                        $this->contactRepository->insertConsentEvent(
                            organizerId: (int) $organizerId,
                            contactId: (int) $row->contact_id,
                            email: $row->email,
                            action: $row->complained_at !== null ? CrmConsentAction::COMPLAINED : CrmConsentAction::BOUNCED,
                            source: CrmConsentSource::CAMPAIGN,
                            sourceId: (int) $row->recipient_id,
                            userId: null,
                        );
                    }

                    $this->projectionRepository->refreshContacts((int) $organizerId, $stillPresent);
                });

                $this->cacheVersion->bump((int) $organizerId);
            });

        $campaignIds = $feedback->pluck('campaign_id')->unique()->map(static fn ($campaignId) => (int) $campaignId)->values()->all();
        $this->reportService->forgetDeliveryStats($campaignIds);

        foreach ($campaignIds as $campaignId) {
            $this->healthGuard->stopIfUnhealthy($campaignId);
        }

        return $feedback->count();
    }
}
