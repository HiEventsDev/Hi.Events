<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation;

use HiEvents\DomainObjects\Generated\CrmAutomationDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use Illuminate\Database\DatabaseManager;

class DeleteAutomationHandler
{
    public function __construct(
        private readonly CrmAutomationRepositoryInterface $automationRepository,
        private readonly DatabaseManager $databaseManager,
        private readonly CampaignLaunchService $launchService,
    ) {}

    public function handle(int $organizerId, int $automationId): void
    {
        $automation = $this->automationRepository->findFirstWhere([
            CrmAutomationDomainObjectAbstract::ID => $automationId,
            CrmAutomationDomainObjectAbstract::ORGANIZER_ID => $organizerId,
        ]);

        if ($automation === null) {
            return;
        }

        $this->databaseManager->transaction(function () use ($automation): void {
            $this->automationRepository->clearMembers($automation->getId());
            $this->launchService->cancelRunsOfStoppedAutomation($automation->getId());
            $this->automationRepository->updateFromArray($automation->getId(), [
                CrmAutomationDomainObjectAbstract::IS_ENABLED => false,
                CrmAutomationDomainObjectAbstract::ENABLED_AT => null,
            ]);
            $this->automationRepository->deleteById($automation->getId());
        });
    }
}
