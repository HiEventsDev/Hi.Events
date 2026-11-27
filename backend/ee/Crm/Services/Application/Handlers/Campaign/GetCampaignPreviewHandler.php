<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign;

use HiEvents\Enterprise\Crm\Exceptions\CampaignOrganizerDeletedException;
use HiEvents\Enterprise\Crm\Mail\CampaignMail;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLookupService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignMailFactory;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignRecipientDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\CampaignSmsPreviewService;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsPreviewDTO;
use HiEvents\Exceptions\ResourceNotFoundException;

class GetCampaignPreviewHandler
{
    public function __construct(
        private readonly CampaignLookupService $lookupService,
        private readonly CampaignMailFactory $mailFactory,
        private readonly CampaignSmsPreviewService $smsPreviewService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws CampaignOrganizerDeletedException
     */
    public function handle(int $organizerId, int $campaignId, CampaignRecipientDTO $viewerName): CampaignMail|SmsPreviewDTO
    {
        $campaign = $this->lookupService->findForOrganizer($organizerId, $campaignId);

        return $campaign->isSms()
            ? $this->smsPreviewService->preview($campaign, $viewerName)
            : $this->mailFactory->make($campaign, null, $viewerName);
    }
}
