<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\CrmActivityType;
use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use HiEvents\DomainObjects\Generated\CrmSegmentDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmCampaignRecipientStatus;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSegmentRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\DTO\SegmentCountDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationConfigDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\CompiledSegmentDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentReferenceDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentDefinitionNormalizer;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentQueryCompiler;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentResolver;
use HiEvents\Exceptions\ResourceNotFoundException;

class CampaignAudienceService
{
    public const NON_BUYER_REMINDER_REPEAT_DAYS = 7;

    private const REACHED_STATUSES = [
        CrmCampaignRecipientStatus::SENDING->value,
        CrmCampaignRecipientStatus::SENT->value,
    ];

    public function __construct(
        private readonly SegmentResolver $segmentResolver,
        private readonly SegmentQueryCompiler $compiler,
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly CrmAutomationRepositoryInterface $automationRepository,
        private readonly CrmSegmentRepositoryInterface $segmentRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     */
    public function compile(CrmCampaignDomainObject $campaign): ?CompiledSegmentDTO
    {
        $definition = $this->resolveDefinition($campaign);

        $segment = $definition === null ? null : $this->compiler->compile($definition, $campaign->getOrganizerId(), now());

        return $this->withoutRecentAutomationRecipients(
            $campaign,
            $this->withinExplicitAudience($campaign, $this->withinOccurrence($campaign, $segment)),
        );
    }

    public function needsFinancialAccess(CrmCampaignDomainObject $campaign): bool
    {
        if ($campaign->getSegmentId() !== null) {
            $segment = $this->segmentRepository->findFirstWhere([
                CrmSegmentDomainObjectAbstract::ID => $campaign->getSegmentId(),
                CrmSegmentDomainObjectAbstract::ORGANIZER_ID => $campaign->getOrganizerId(),
            ]);

            return $segment !== null && SegmentDefinitionNormalizer::hasFinancialRules($segment->getDefinition());
        }

        return $campaign->getPresetSegment() === null
            && SegmentDefinitionNormalizer::hasFinancialRules($campaign->getSegmentDefinition());
    }

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     */
    public function count(CrmCampaignDomainObject $campaign): SegmentCountDTO
    {
        return $this->countCompiled($campaign->getOrganizerId(), $this->compile($campaign));
    }

    public function countCompiled(int $organizerId, ?CompiledSegmentDTO $audience): SegmentCountDTO
    {
        $counts = $this->contactRepository->countSegment($organizerId, $audience);

        return new SegmentCountDTO(
            contacts_count: (int) $counts->contacts_count,
            mailable_count: (int) $counts->mailable_count,
        );
    }

    private function withinOccurrence(CrmCampaignDomainObject $campaign, ?CompiledSegmentDTO $segment): ?CompiledSegmentDTO
    {
        if ($campaign->getEventOccurrenceId() === null) {
            return $segment;
        }

        return new CompiledSegmentDTO(
            sql: '('.($segment?->sql ?? 'TRUE').' AND '.($campaign->getExcludesOccurrenceHolders() ? 'NOT ' : '').'EXISTS ('
                .'SELECT 1 FROM crm_contact_activities occurrence_ticket'
                .' WHERE occurrence_ticket.contact_id = crm_contacts.id'
                ." AND occurrence_ticket.type = '".CrmActivityType::TICKET_HELD->value."'"
                .' AND (occurrence_ticket.event_occurrence_id = ? OR ('
                .'occurrence_ticket.event_occurrence_id IS NULL'
                .' AND occurrence_ticket.event_id = (SELECT only_date.event_id FROM event_occurrences only_date WHERE only_date.id = ?)'
                .' AND NOT EXISTS (SELECT 1 FROM event_occurrences other_date WHERE other_date.event_id = occurrence_ticket.event_id AND other_date.id <> ? AND other_date.deleted_at IS NULL)'
                .'))))',
            bindings: [...($segment?->bindings ?? []), ...array_fill(0, 3, $campaign->getEventOccurrenceId())],
        );
    }

    private function withinExplicitAudience(CrmCampaignDomainObject $campaign, ?CompiledSegmentDTO $segment): ?CompiledSegmentDTO
    {
        if (! $campaign->getAudienceIsExplicit()) {
            return $segment;
        }

        return new CompiledSegmentDTO(
            sql: '('.($segment?->sql ?? 'TRUE').' AND EXISTS ('
                .'SELECT 1 FROM crm_campaign_audience_contacts listed'
                .' WHERE listed.campaign_id = ? AND listed.contact_id = crm_contacts.id))',
            bindings: [...($segment?->bindings ?? []), $campaign->getId()],
        );
    }

    private function withoutRecentAutomationRecipients(CrmCampaignDomainObject $campaign, ?CompiledSegmentDTO $segment): ?CompiledSegmentDTO
    {
        $automation = $campaign->getAutomationId() === null
            ? null
            : $this->automationRepository->findFirstWhere(['id' => $campaign->getAutomationId()]);
        $repeatAfterDays = $automation === null ? null : $this->repeatAfterDays($automation);

        if ($automation === null || $repeatAfterDays === null) {
            return $segment;
        }

        $trigger = CrmAutomationTrigger::from($automation->getTrigger());
        $newSinceSwitchedOn = $trigger === CrmAutomationTrigger::WELCOME && $automation->getEnabledAt() !== null;

        $exclusion = 'NOT EXISTS ('
            .'SELECT 1 FROM crm_campaign_recipients earlier'
            .' JOIN crm_campaigns earlier_campaign ON earlier_campaign.id = earlier.campaign_id'
            .' WHERE earlier_campaign.automation_id = ? AND earlier_campaign.id <> ?'
            .' AND earlier.contact_id = crm_contacts.id AND earlier.created_at >= ?'
            ." AND (earlier.status IN ('".implode("', '", self::REACHED_STATUSES)."')"
            ." OR (earlier.status = '".CrmCampaignRecipientStatus::PENDING->value."'"
            ." AND earlier_campaign.status IN ('".implode("', '", CrmCampaignStatus::valuesOf(CrmCampaignStatus::holdingPendingRecipients()))."'))"
            ." OR (earlier.status = '".CrmCampaignRecipientStatus::SKIPPED->value."'"
            ." AND earlier_campaign.status = '".CrmCampaignStatus::CANCELLED->value."'))"
            .')'
            .($newSinceSwitchedOn ? ' AND crm_contacts.first_seen_at >= ?' : '');

        return new CompiledSegmentDTO(
            sql: '('.($segment?->sql ?? 'TRUE').' AND '.$exclusion.')',
            bindings: [
                ...($segment?->bindings ?? []),
                $automation->getId(),
                $campaign->getId(),
                now()->subDays($repeatAfterDays)->toDateTimeString(),
                ...($newSinceSwitchedOn ? [$automation->getEnabledAt()] : []),
            ],
        );
    }

    private function repeatAfterDays(CrmAutomationDomainObject $automation): ?int
    {
        $trigger = CrmAutomationTrigger::from($automation->getTrigger());

        if ($trigger === CrmAutomationTrigger::BEFORE_EVENT && AutomationConfigDTO::forAutomation($automation)->targetsNonBuyers()) {
            return self::NON_BUYER_REMINDER_REPEAT_DAYS;
        }

        return $trigger->repeatAfterDays();
    }

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     */
    private function resolveDefinition(CrmCampaignDomainObject $campaign): ?array
    {
        return $this->segmentResolver->resolveDefinition(
            $campaign->getOrganizerId(),
            new SegmentReferenceDTO(
                segment_id: $campaign->getSegmentId(),
                preset: $campaign->getPresetSegment(),
                definition: $campaign->getSegmentDefinition(),
            ),
            canViewFinancials: true,
        );
    }
}
