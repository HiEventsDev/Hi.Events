<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign;

use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLookupService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;

class DeleteCampaignHandler
{
    public function __construct(
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly CampaignLookupService $lookupService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     */
    public function handle(int $organizerId, int $campaignId): void
    {
        $campaign = $this->lookupService->findForOrganizer($organizerId, $campaignId);

        if ($campaign->getAutomationId() !== null) {
            throw new ResourceConflictException(__('Campaigns sent by an automation can\'t be deleted. Switch the automation off to stop it sending.'));
        }

        if ($this->campaignRepository->hasRecipients($campaign->getId())) {
            throw new ResourceConflictException(__('A campaign that has been sent can\'t be deleted, because its unsubscribe links must keep working.'));
        }

        $this->campaignRepository->deleteWhere([
            CrmCampaignDomainObjectAbstract::ID => $campaign->getId(),
            CrmCampaignDomainObjectAbstract::ORGANIZER_ID => $organizerId,
            CrmCampaignDomainObjectAbstract::STATUS => $campaign->getStatus(),
        ]);
    }
}
