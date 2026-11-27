<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger;

use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use HiEvents\DomainObjects\Generated\CrmAutomationDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Exceptions\AutomationNotRunnableException;
use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\AutomationDefaults;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationRunDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignSendGate;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\CompiledSegmentDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentQueryCompiler;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentResolver;
use HiEvents\Exceptions\ResourceNotFoundException;

class SegmentEnteredTrigger extends BaseAutomationTrigger
{
    public const MAX_ENTRANTS_PER_RUN = 5000;

    private const LAUNCHED_RUN_RECOVERY_MINUTES = 60;

    public function __construct(
        private readonly CrmAutomationRepositoryInterface $automationRepository,
        private readonly SegmentResolver $segmentResolver,
        private readonly SegmentQueryCompiler $segmentCompiler,
        private readonly AutomationDefaults $defaults,
        private readonly CampaignSendGate $sendGate,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     */
    public function baseline(CrmAutomationDomainObject $automation): void
    {
        $definition = $this->resolveDefinition($automation);

        $this->replaceBaseline($automation, $this->compile($automation, $definition), $this->hash($definition));
    }

    public function dueRuns(CrmAutomationDomainObject $automation): array
    {
        try {
            $definition = $this->resolveDefinition($automation);
        } catch (ResourceNotFoundException|InvalidSegmentDefinitionException) {
            throw new AutomationNotRunnableException(CampaignNotSendableException::REASON_SEGMENT_DELETED);
        }

        $segment = $this->compile($automation, $definition);
        $hash = $this->hash($definition);

        if ($automation->getSegmentDefinitionHash() !== $hash) {
            $this->replaceBaseline($automation, $segment, $hash);

            return [];
        }

        $this->automationRepository->addMembersFromLatestLaunchedCampaign(
            $automation->getId(),
            now()->subMinutes(self::LAUNCHED_RUN_RECOVERY_MINUTES),
        );

        $entrants = $this->automationRepository->findEntrants(
            $automation->getId(),
            $automation->getOrganizerId(),
            $segment,
            min(self::MAX_ENTRANTS_PER_RUN, $this->sendGate->maxRecipientsPerSend($automation->getAccountId())),
        );

        if ($entrants === []) {
            return [];
        }

        return [new AutomationRunDTO(
            run_key: 'entered:'.$this->runSlot(),
            label: null,
            event: null,
            attributes: [
                ...$this->audienceAttributes($automation),
                CrmCampaignDomainObjectAbstract::AUDIENCE_IS_EXPLICIT => true,
            ],
            contact_ids: $entrants,
        )];
    }

    public function settled(CrmAutomationDomainObject $automation, AutomationRunDTO $run): void
    {
        $this->automationRepository->addMembers($automation->getId(), $run->contact_ids ?? []);
    }

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     */
    private function resolveDefinition(CrmAutomationDomainObject $automation): array
    {
        return $this->segmentResolver->resolveDefinition(
            $automation->getOrganizerId(),
            $this->defaults->audience(CrmAutomationTrigger::SEGMENT_ENTERED, $automation->getSegmentId(), $automation->getPresetSegment()),
            true,
        ) ?? ['match' => 'all', 'rules' => []];
    }

    private function compile(CrmAutomationDomainObject $automation, array $definition): CompiledSegmentDTO
    {
        return $this->segmentCompiler->compile($definition, $automation->getOrganizerId(), now());
    }

    private function hash(array $definition): string
    {
        return hash('sha256', (string) json_encode($definition));
    }

    private function replaceBaseline(CrmAutomationDomainObject $automation, CompiledSegmentDTO $segment, string $hash): void
    {
        $this->automationRepository->replaceMembers($automation->getId(), $automation->getOrganizerId(), $segment);
        $this->automationRepository->updateWhere(
            attributes: [CrmAutomationDomainObjectAbstract::SEGMENT_DEFINITION_HASH => $hash],
            where: [CrmAutomationDomainObjectAbstract::ID => $automation->getId()],
        );
        $automation->setSegmentDefinitionHash($hash);
    }
}
