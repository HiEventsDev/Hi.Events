<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLookupService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignReportService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Database\DatabaseManager;

class CancelCampaignHandler
{
    public function __construct(
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly CampaignLookupService $lookupService,
        private readonly CampaignReportService $reportService,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     */
    public function handle(int $organizerId, int $campaignId, bool $canViewFinancials): CrmCampaignDomainObject
    {
        $campaign = $this->lookupService->findForOrganizer($organizerId, $campaignId);

        $stopped = $this->databaseManager->transaction(fn (): bool => $this->withdraw($campaign) || $this->stop($campaign));

        if (! $stopped) {
            throw new ResourceConflictException(__('This campaign can\'t be cancelled.'));
        }

        $updated = $this->lookupService->findForOrganizer($organizerId, $campaignId);
        $this->reportService->attachDetail($updated, $canViewFinancials);

        return $updated;
    }

    private function withdraw(CrmCampaignDomainObject $campaign): bool
    {
        $withdrawn = $this->campaignRepository->transitionStatus(
            $campaign->getId(),
            CrmCampaignStatus::valuesOf(CrmCampaignStatus::withdrawable()),
            [
                CrmCampaignDomainObjectAbstract::STATUS => $campaign->getAutomationId() === null
                    ? CrmCampaignStatus::DRAFT->value
                    : CrmCampaignStatus::CANCELLED->value,
                CrmCampaignDomainObjectAbstract::SCHEDULED_AT => null,
                CrmCampaignDomainObjectAbstract::STATUS_REASON => null,
                CrmCampaignDomainObjectAbstract::LAUNCHED_AT => null,
                CrmCampaignDomainObjectAbstract::APPROVED_AT => null,
            ],
        );

        if ($withdrawn) {
            $this->campaignRepository->withdrawRecipients($campaign->getId(), keepAsSkipped: $campaign->getAutomationId() !== null);
        }

        return $withdrawn;
    }

    private function stop(CrmCampaignDomainObject $campaign): bool
    {
        $stopped = $this->campaignRepository->transitionStatus(
            $campaign->getId(),
            CrmCampaignStatus::valuesOf(CrmCampaignStatus::stoppable()),
            [CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::CANCELLED->value],
        );

        if ($stopped) {
            $this->campaignRepository->skipPendingRecipients($campaign->getId());
        }

        return $stopped;
    }
}
