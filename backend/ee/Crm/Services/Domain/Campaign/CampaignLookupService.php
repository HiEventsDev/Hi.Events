<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Exceptions\ResourceNotFoundException;

class CampaignLookupService
{
    public function __construct(
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function findForOrganizer(int $organizerId, int $campaignId): CrmCampaignDomainObject
    {
        $campaign = $this->campaignRepository->findFirstWhere([
            CrmCampaignDomainObjectAbstract::ID => $campaignId,
            CrmCampaignDomainObjectAbstract::ORGANIZER_ID => $organizerId,
        ]);

        if ($campaign === null) {
            throw new ResourceNotFoundException(__('Campaign not found'));
        }

        return $campaign;
    }
}
