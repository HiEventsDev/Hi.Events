<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignAudienceService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLookupService;
use HiEvents\Exceptions\PermissionRequiredException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

class GetCampaignRecipientsHandler
{
    public function __construct(
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly CampaignLookupService $lookupService,
        private readonly CampaignAudienceService $audienceService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws PermissionRequiredException
     */
    public function handle(int $organizerId, int $campaignId, ?string $filter, int $page, bool $canViewFinancials): LengthAwarePaginator
    {
        $campaign = $this->lookupService->findForOrganizer($organizerId, $campaignId);

        if (! $canViewFinancials && $this->audienceService->needsFinancialAccess($campaign)) {
            throw new PermissionRequiredException(__('This campaign was sent to a segment that filters by spend, so only members who can view financials can see its recipients.'));
        }

        return $this->campaignRepository->findRecipients($campaign->getId(), $filter, $page, 50);
    }
}
