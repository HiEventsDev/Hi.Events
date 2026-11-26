<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\SmsCountry;
use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\Enterprise\Crm\Jobs\DispatchCampaignJob;
use HiEvents\Enterprise\Crm\Jobs\SendCampaignEmailJob;
use HiEvents\Enterprise\Crm\Jobs\SendCampaignSmsJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Account\AccountSuspensionService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;

class CampaignDispatchService
{
    public const REASON_ORGANIZER_DELETED = 'ORGANIZER_DELETED';

    public const REASON_ACCOUNT_SUSPENDED = 'ACCOUNT_SUSPENDED';

    public const WAVE_SIZE = 500;

    public const WAVE_DELAY_SECONDS = 15;

    private const MAX_IN_FLIGHT_BEFORE_NEXT_WAVE = 100;

    private const MIN_SECONDS_BETWEEN_IDLE_RUNS = 10;

    private const RUN_LOCK_SECONDS = 60;

    private const PROGRESS_CACHE_SECONDS = 86400;

    public const MAX_SENDING_HOURS_WAIT_MINUTES = 10;

    private const WAKE_GUARD_ENDS_SECONDS_EARLY = 5;

    private const NO_PROGRESS = ['wave_start' => 0, 'frontier' => 0, 'checked_at' => 0];

    public function __construct(
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly AccountSuspensionService $accountSuspensionService,
        private readonly CacheRepository $cache,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function dispatch(int $campaignId): void
    {
        $campaign = $this->campaignRepository->findFirstWhere(['id' => $campaignId]);

        if ($campaign === null || $campaign->getStatus() !== CrmCampaignStatus::SENDING->value) {
            return;
        }

        if ($this->organizerRepository->findFirstWhere(['id' => $campaign->getOrganizerId()]) === null) {
            $this->cancelForDeletedOrganizer($campaignId);

            return;
        }

        if ($this->pauseIfAccountSuspended($campaign)) {
            return;
        }

        if ($campaign->getSentAt() === null) {
            $this->campaignRepository->updateWhere(
                attributes: [CrmCampaignDomainObjectAbstract::SENT_AT => now()->toDateTimeString()],
                where: [CrmCampaignDomainObjectAbstract::ID => $campaignId],
            );
        }

        $lock = $this->cache->lock('crm:campaign:'.$campaignId.':dispatching', self::RUN_LOCK_SECONDS);

        if (! $lock->get()) {
            return;
        }

        try {
            $runAgain = $this->queueNextWave($campaignId, $campaign->isSms());
        } finally {
            $lock->release();
        }

        if ($runAgain) {
            DispatchCampaignJob::dispatch($campaignId)->delay(now()->addSeconds(self::WAVE_DELAY_SECONDS));
        }
    }

    public function cancelForDeletedOrganizer(int $campaignId): void
    {
        $this->databaseManager->transaction(function () use ($campaignId): void {
            $this->campaignRepository->transitionStatus($campaignId, [CrmCampaignStatus::SENDING->value], [
                CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::CANCELLED->value,
                CrmCampaignDomainObjectAbstract::STATUS_REASON => self::REASON_ORGANIZER_DELETED,
            ]);
            $this->campaignRepository->skipPendingRecipients($campaignId);
        });
    }

    public function pauseIfAccountSuspended(CrmCampaignDomainObject $campaign): bool
    {
        if (! $this->accountSuspensionService->isAccountSuspended($campaign->getAccountId())) {
            return false;
        }

        $this->databaseManager->transaction(function () use ($campaign): void {
            $this->campaignRepository->transitionStatus($campaign->getId(), [CrmCampaignStatus::SENDING->value], [
                CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::PAUSED->value,
                CrmCampaignDomainObjectAbstract::STATUS_REASON => self::REASON_ACCOUNT_SUSPENDED,
            ]);
            $this->campaignRepository->skipPendingRecipients($campaign->getId());
        });

        return true;
    }

    public function restart(int $campaignId): void
    {
        $this->cache->forget($this->progressKey($campaignId));

        DispatchCampaignJob::dispatch($campaignId);
    }

    public function markSentIfFinished(int $campaignId): void
    {
        if ($this->campaignRepository->hasPendingRecipients($campaignId)) {
            return;
        }

        $this->campaignRepository->transitionStatus($campaignId, [CrmCampaignStatus::SENDING->value], [
            CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::SENT->value,
        ]);
    }

    private function queueNextWave(int $campaignId, bool $isSms): bool
    {
        $sendableCountries = $isSms ? SmsCountry::sendableNow(SmsCountry::valuesArray(), now()) : null;
        $progress = $this->cache->get($this->progressKey($campaignId)) ?? self::NO_PROGRESS;

        if ($isSms && array_diff($sendableCountries, $progress['countries'] ?? $sendableCountries) !== []) {
            $progress = self::NO_PROGRESS;
        }

        $inFlight = $this->campaignRepository->countPendingRecipientsBetween($campaignId, $progress['wave_start'], $progress['frontier'], $sendableCountries);

        $recipientIds = $inFlight > self::MAX_IN_FLIGHT_BEFORE_NEXT_WAVE
            ? []
            : $this->campaignRepository->findPendingRecipientIds($campaignId, $progress['frontier'], self::WAVE_SIZE, $sendableCountries);

        if ($recipientIds === []) {
            if (! $this->campaignRepository->hasPendingRecipients($campaignId)) {
                $this->cache->forget($this->progressKey($campaignId));
                $this->markSentIfFinished($campaignId);

                return false;
            }

            if ($isSms && $inFlight === 0 && $this->campaignRepository->findPendingRecipientIds($campaignId, 0, 1, $sendableCountries) === []) {
                $this->cache->forget($this->progressKey($campaignId));
                $this->wakeForSendingHours($campaignId);

                return false;
            }

            if (now()->getTimestamp() - $progress['checked_at'] < self::MIN_SECONDS_BETWEEN_IDLE_RUNS) {
                return false;
            }

            $this->saveProgress($campaignId, $inFlight === 0 ? 0 : $progress['wave_start'], $inFlight === 0 ? 0 : $progress['frontier'], $sendableCountries);

            return true;
        }

        foreach ($recipientIds as $recipientId) {
            $isSms ? SendCampaignSmsJob::dispatch($recipientId) : SendCampaignEmailJob::dispatch($recipientId);
        }

        $this->saveProgress($campaignId, $progress['frontier'], end($recipientIds), $sendableCountries);

        return true;
    }

    private function wakeForSendingHours(int $campaignId): void
    {
        $wakeAt = min($this->nextSendingWindow($campaignId), now()->addMinutes(self::MAX_SENDING_HOURS_WAIT_MINUTES));
        $secondsUntilWake = (int) now()->diffInSeconds($wakeAt);
        $isFirstToQueueThisWake = $this->cache->add(
            'crm:campaign:'.$campaignId.':sending-hours-wake',
            true,
            max($secondsUntilWake - self::WAKE_GUARD_ENDS_SECONDS_EARLY, 1),
        );

        if ($isFirstToQueueThisWake) {
            DispatchCampaignJob::dispatch($campaignId)->delay($wakeAt);
        }
    }

    private function nextSendingWindow(int $campaignId): CarbonInterface
    {
        $now = now();
        $waiting = array_filter(array_map(
            static fn (string $country) => SmsCountry::tryFrom($country)?->nextSendingTime($now),
            $this->campaignRepository->findPendingRecipientCountries($campaignId),
        ));

        return $waiting === [] ? $now->copy()->addMinutes(self::MAX_SENDING_HOURS_WAIT_MINUTES) : min($waiting);
    }

    /**
     * @param  string[]|null  $sendableCountries
     */
    private function saveProgress(int $campaignId, int $waveStart, int $frontier, ?array $sendableCountries): void
    {
        $this->cache->put(
            $this->progressKey($campaignId),
            ['wave_start' => $waveStart, 'frontier' => $frontier, 'checked_at' => now()->getTimestamp(), 'countries' => $sendableCountries],
            self::PROGRESS_CACHE_SECONDS,
        );
    }

    private function progressKey(int $campaignId): string
    {
        return 'crm:campaign:'.$campaignId.':dispatch-progress';
    }
}
