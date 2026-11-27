<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation;

use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use HiEvents\DomainObjects\Generated\CrmAutomationDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\AutomationDefaults;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger\SegmentEnteredTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentResolver;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Database\DatabaseManager;

class SetAutomationEnabledHandler
{
    public const MAX_ENABLED_SEGMENT_AUTOMATIONS = 20;

    public function __construct(
        private readonly CrmAutomationRepositoryInterface $automationRepository,
        private readonly SegmentResolver $segmentResolver,
        private readonly SegmentEnteredTrigger $segmentEnteredTrigger,
        private readonly AutomationDefaults $defaults,
        private readonly DatabaseManager $databaseManager,
        private readonly CampaignLaunchService $launchService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws InvalidSegmentDefinitionException
     */
    public function handle(int $organizerId, int $automationId, bool $isEnabled, bool $canViewFinancials): CrmAutomationDomainObject
    {
        $automation = $this->automationRepository->findFirstWhere([
            CrmAutomationDomainObjectAbstract::ID => $automationId,
            CrmAutomationDomainObjectAbstract::ORGANIZER_ID => $organizerId,
        ]) ?? throw new ResourceNotFoundException(__('Automation not found'));

        if ($automation->getIsEnabled() === $isEnabled) {
            return $automation;
        }

        return $isEnabled
            ? $this->enable($automation, $canViewFinancials)
            : $this->disable($automation);
    }

    /**
     * @throws ResourceConflictException
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     */
    private function enable(CrmAutomationDomainObject $automation, bool $canViewFinancials): CrmAutomationDomainObject
    {
        $trigger = CrmAutomationTrigger::from($automation->getTrigger());
        $this->segmentResolver->resolveDefinition(
            $automation->getOrganizerId(),
            $this->defaults->audience($trigger, $automation->getSegmentId(), $automation->getPresetSegment()),
            $canViewFinancials,
        );

        if ($trigger === CrmAutomationTrigger::SEGMENT_ENTERED && $this->enabledSegmentAutomations($automation->getOrganizerId()) >= self::MAX_ENABLED_SEGMENT_AUTOMATIONS) {
            throw new ResourceConflictException(trans_choice(
                '{1} You can have :count segment automation switched on at once.|[2,*] You can have up to :count segment automations switched on at once.',
                self::MAX_ENABLED_SEGMENT_AUTOMATIONS,
            ));
        }

        return $this->databaseManager->transaction(function () use ($automation, $trigger): CrmAutomationDomainObject {
            if ($trigger === CrmAutomationTrigger::SEGMENT_ENTERED) {
                $this->segmentEnteredTrigger->baseline($automation);
            }

            return $this->automationRepository->updateFromArray($automation->getId(), [
                CrmAutomationDomainObjectAbstract::IS_ENABLED => true,
                CrmAutomationDomainObjectAbstract::ENABLED_AT => now()->toDateTimeString(),
                CrmAutomationDomainObjectAbstract::LAST_FAILURE_REASON => null,
                CrmAutomationDomainObjectAbstract::BASELINE_EVENT_IDS => $trigger === CrmAutomationTrigger::EVENT_PUBLISHED
                    ? $this->automationRepository->findPublishedEventIds($automation->getOrganizerId())
                    : null,
            ]);
        });
    }

    private function disable(CrmAutomationDomainObject $automation): CrmAutomationDomainObject
    {
        return $this->databaseManager->transaction(function () use ($automation): CrmAutomationDomainObject {
            $this->automationRepository->clearMembers($automation->getId());
            $this->launchService->cancelRunsOfStoppedAutomation($automation->getId());

            return $this->automationRepository->updateFromArray($automation->getId(), [
                CrmAutomationDomainObjectAbstract::IS_ENABLED => false,
                CrmAutomationDomainObjectAbstract::ENABLED_AT => null,
                CrmAutomationDomainObjectAbstract::LAST_FAILURE_REASON => null,
                CrmAutomationDomainObjectAbstract::SEGMENT_DEFINITION_HASH => null,
                CrmAutomationDomainObjectAbstract::BASELINE_EVENT_IDS => null,
            ]);
        });
    }

    private function enabledSegmentAutomations(int $organizerId): int
    {
        return $this->automationRepository->countWhere([
            CrmAutomationDomainObjectAbstract::ORGANIZER_ID => $organizerId,
            CrmAutomationDomainObjectAbstract::TRIGGER => CrmAutomationTrigger::SEGMENT_ENTERED->value,
            CrmAutomationDomainObjectAbstract::IS_ENABLED => true,
        ]);
    }
}
