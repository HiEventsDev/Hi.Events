<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignDispatchService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use HiEvents\Enterprise\Licensing\LicenceService;
use HiEvents\Enterprise\Licensing\LicensedFeature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendScheduledCampaignsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const STALLED_AFTER_MINUTES = 15;

    private const MAX_RESTARTS_PER_RUN = 100;

    private const MAX_EXPIRED_REVIEWS_PER_RUN = 50;

    private const MAX_LAUNCHES_PER_RUN = 500;

    public int $timeout = 40;

    public function handle(
        CrmCampaignRepositoryInterface $campaignRepository,
        CampaignDispatchService $dispatchService,
        CampaignLaunchService $launchService,
        LicenceService $licenceService,
    ): void {
        $now = now();
        $stalledBefore = $now->copy()->subMinutes(self::STALLED_AFTER_MINUTES);
        $campaignRepository->failStalledRecipients($stalledBefore);

        foreach ($campaignRepository->findStalledCampaignIds($stalledBefore, self::MAX_RESTARTS_PER_RUN) as $campaignId) {
            if ($campaignRepository->touchIfStatus($campaignId, CrmCampaignStatus::SENDING)) {
                $dispatchService->restart($campaignId);
            }
        }

        $missedSendWindowBefore = $now->copy()->subHours(CampaignLaunchService::SCHEDULED_SEND_WINDOW_HOURS);
        $staleReviews = $campaignRepository->findStaleReviews(
            $missedSendWindowBefore,
            $now->copy()->subHours(CampaignLaunchService::AUTOMATION_REVIEW_WINDOW_HOURS),
            self::MAX_EXPIRED_REVIEWS_PER_RUN,
        );

        foreach ($staleReviews as $campaign) {
            $launchService->expireStaleReview($campaign, $now);
        }

        $dueBy = $licenceService->allows(LicensedFeature::CRM) ? $now : $missedSendWindowBefore;

        foreach ($campaignRepository->findDueScheduledIds($dueBy, self::MAX_LAUNCHES_PER_RUN) as $campaignId) {
            LaunchScheduledCampaignJob::dispatch($campaignId);
        }
    }
}
