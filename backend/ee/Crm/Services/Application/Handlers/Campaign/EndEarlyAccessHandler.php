<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLookupService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignReportService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\EarlyAccessService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;

class EndEarlyAccessHandler
{
    public function __construct(
        private readonly CampaignLookupService $lookupService,
        private readonly EarlyAccessService $earlyAccessService,
        private readonly CampaignReportService $reportService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     */
    public function handle(int $organizerId, int $campaignId, bool $canViewFinancials): CrmCampaignDomainObject
    {
        $campaign = $this->lookupService->findForOrganizer($organizerId, $campaignId);

        if ($campaign->getEventId() === null || $campaign->getPromoCodeId() === null || $campaign->earlyAccessProductIds() === []) {
            throw new ResourceConflictException(__('This campaign hasn\'t given anyone early access.'));
        }

        $this->earlyAccessService->makePublic($campaign->getEventId(), $campaign->earlyAccessProductIds());
        $this->reportService->attachDetail($campaign, $canViewFinancials);

        return $campaign;
    }
}
