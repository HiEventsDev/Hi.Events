<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLookupService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignReportService;
use HiEvents\Exceptions\ResourceNotFoundException;

class GetCampaignHandler
{
    public function __construct(
        private readonly CampaignLookupService $lookupService,
        private readonly CampaignReportService $reportService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $organizerId, int $campaignId, bool $canViewFinancials): CrmCampaignDomainObject
    {
        $campaign = $this->lookupService->findForOrganizer($organizerId, $campaignId);
        $this->reportService->attachDetail($campaign, $canViewFinancials);

        return $campaign;
    }
}
