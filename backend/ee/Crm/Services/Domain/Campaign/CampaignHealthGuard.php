<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\Enterprise\Crm\Jobs\NotifyCampaignPausedJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;

class CampaignHealthGuard
{
    public const MINIMUM_SENDS = 50;

    public const MAX_COMPLAINT_RATE = 0.003;

    public const MAX_BOUNCE_RATE = 0.08;

    public const REASON_COMPLAINTS = 'COMPLAINT_RATE';

    public const REASON_BOUNCES = 'BOUNCE_RATE';

    public const CHECK_INTERVAL_SECONDS = 60;

    public function __construct(
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly CacheRepository $cache,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function stopIfUnhealthy(int $campaignId): bool
    {
        $throttleKey = 'crm:campaign:'.$campaignId.':health-checked';

        if (! $this->cache->add($throttleKey, true, self::CHECK_INTERVAL_SECONDS)) {
            return false;
        }

        $stats = $this->campaignRepository->getHealthStats($campaignId);

        if ((int) $stats->sent < self::MINIMUM_SENDS) {
            $this->cache->forget($throttleKey);

            return false;
        }

        $reason = match (true) {
            (int) $stats->complained / (int) $stats->sent > self::MAX_COMPLAINT_RATE => self::REASON_COMPLAINTS,
            (int) $stats->bounced / (int) $stats->sent > self::MAX_BOUNCE_RATE => self::REASON_BOUNCES,
            default => null,
        };

        if ($reason === null) {
            return false;
        }

        $stopped = $this->databaseManager->transaction(function () use ($campaignId, $reason): bool {
            $stopped = $this->campaignRepository->transitionStatus($campaignId, [CrmCampaignStatus::SENDING->value], [
                CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::PAUSED->value,
                CrmCampaignDomainObjectAbstract::STATUS_REASON => $reason,
            ]);

            if ($stopped) {
                $this->campaignRepository->skipPendingRecipients($campaignId);
            }

            return $stopped;
        });

        if ($stopped) {
            NotifyCampaignPausedJob::dispatch($campaignId);
        }

        return $stopped;
    }
}
