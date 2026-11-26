<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use HiEvents\Enterprise\Crm\Mail\CampaignPendingReviewMail;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\Review\CampaignSupportInbox;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyCampaignPendingReviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        private readonly int $campaignId,
        private readonly int $recipientsCount,
        private readonly string $reason,
    ) {}

    public function handle(
        CrmCampaignRepositoryInterface $campaignRepository,
        AccountRepositoryInterface $accountRepository,
        Mailer $mailer,
        CampaignSupportInbox $supportInbox,
    ): void {
        $supportEmail = $supportInbox->address();
        $campaign = $campaignRepository->findFirstWhere(['id' => $this->campaignId]);

        if ($supportEmail === null || $campaign === null) {
            return;
        }

        $account = $accountRepository->findById($campaign->getAccountId());

        $mailer->to($supportEmail)->send(new CampaignPendingReviewMail(
            campaignId: $campaign->getId(),
            campaignName: $campaign->getName(),
            campaignSubject: (string) $campaign->getSubject(),
            accountName: $account->getName(),
            accountId: $account->getId(),
            recipientsCount: $this->recipientsCount,
            reason: $this->reason,
            automaticCheckFindings: $campaign->aiReviewVerdict()['reasons'] ?? [],
        ));
    }
}
