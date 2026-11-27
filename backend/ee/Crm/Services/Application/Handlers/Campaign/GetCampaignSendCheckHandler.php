<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign;

use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignAudienceService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLookupService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignSendCheckDTO;
use HiEvents\Exceptions\PermissionRequiredException;
use HiEvents\Exceptions\ResourceNotFoundException;

class GetCampaignSendCheckHandler
{
    public function __construct(
        private readonly CampaignLookupService $lookupService,
        private readonly CampaignAudienceService $audienceService,
        private readonly CampaignLaunchService $launchService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws PermissionRequiredException
     */
    public function handle(int $organizerId, int $campaignId, bool $canViewFinancials, bool $isScheduled): CampaignSendCheckDTO
    {
        $campaign = $this->lookupService->findForOrganizer($organizerId, $campaignId);

        if (! $canViewFinancials && $this->audienceService->needsFinancialAccess($campaign)) {
            throw new PermissionRequiredException(__('This campaign filters by spend, so only members who can view financials can send it.'));
        }

        return $this->launchService->check($campaign, $isScheduled);
    }
}
