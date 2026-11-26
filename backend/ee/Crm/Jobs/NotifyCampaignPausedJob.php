<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use HiEvents\Enterprise\Crm\Mail\CampaignPausedMail;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignOwnerNotifier;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\Review\CampaignSupportInbox;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyCampaignPausedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        private readonly int $campaignId,
    ) {}

    public function handle(
        CrmCampaignRepositoryInterface $campaignRepository,
        AccountRepositoryInterface $accountRepository,
        CampaignOwnerNotifier $ownerNotifier,
        Mailer $mailer,
        Repository $config,
        CampaignSupportInbox $supportInbox,
    ): void {
        $campaign = $campaignRepository->findFirstWhere(['id' => $this->campaignId]);

        if ($campaign === null) {
            return;
        }

        $ownerNotifier->notifyPausedForHealth($campaign);

        $supportEmail = $supportInbox->address();

        if ($supportEmail === null || ! $config->get('app.saas_mode_enabled')) {
            return;
        }

        $account = $accountRepository->findById($campaign->getAccountId());

        $mailer->to($supportEmail)->send(new CampaignPausedMail(
            campaignId: $campaign->getId(),
            campaignName: $campaign->getName(),
            accountName: $account->getName(),
            accountId: $account->getId(),
            reason: (string) $campaign->getStatusReason(),
        ));
    }
}
