<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation;

use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use HiEvents\DomainObjects\Enums\CrmSegmentPreset;
use HiEvents\DomainObjects\Generated\CrmAutomationDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Exceptions\InvalidPlaceholderException;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation\DTO\UpsertAutomationDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\AutomationDefaults;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignHtmlSanitizer;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignTokenCatalog;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentResolver;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;

class UpsertAutomationHandler
{
    public const MAX_AUTOMATIONS_PER_ORGANIZER = 50;

    public function __construct(
        private readonly CrmAutomationRepositoryInterface $automationRepository,
        private readonly SegmentResolver $segmentResolver,
        private readonly CampaignHtmlSanitizer $sanitizer,
        private readonly AutomationDefaults $defaults,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     * @throws ResourceConflictException
     * @throws InvalidPlaceholderException
     */
    public function handle(UpsertAutomationDTO $dto): CrmAutomationDomainObject
    {
        $existing = $dto->automation_id === null ? null : $this->automationRepository->findFirstWhere([
            CrmAutomationDomainObjectAbstract::ID => $dto->automation_id,
            CrmAutomationDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
        ]);

        if ($dto->automation_id !== null && $existing === null) {
            throw new ResourceNotFoundException(__('Automation not found'));
        }

        $trigger = $dto->trigger;
        $audience = $this->defaults->audience($trigger, $dto->segment_id, $dto->preset);

        if ($audience->preset === CrmSegmentPreset::SMS_SUBSCRIBERS->value) {
            throw InvalidSegmentDefinitionException::textSubscribersByEmail();
        }

        $this->segmentResolver->resolveDefinition($dto->organizer_id, $audience, $dto->can_view_financials);

        foreach (['subject' => $dto->subject, 'preheader' => $dto->preheader, 'body' => $dto->body] as $field => $value) {
            $error = CampaignTokenCatalog::firstError((string) $value, $trigger->isEventScoped());
            if ($error !== null) {
                throw new InvalidPlaceholderException($field, $error);
            }
        }

        $config = $dto->config;
        $attributes = [
            CrmAutomationDomainObjectAbstract::NAME => trim($dto->name),
            CrmAutomationDomainObjectAbstract::SUBJECT => trim($dto->subject),
            CrmAutomationDomainObjectAbstract::PREHEADER => $dto->preheader === null || trim($dto->preheader) === '' ? null : trim($dto->preheader),
            CrmAutomationDomainObjectAbstract::BODY => (string) $this->sanitizer->sanitize($dto->body),
            CrmAutomationDomainObjectAbstract::SEGMENT_ID => $audience->segment_id,
            CrmAutomationDomainObjectAbstract::PRESET_SEGMENT => $audience->preset,
            CrmAutomationDomainObjectAbstract::DELAY_DAYS => $trigger === CrmAutomationTrigger::AFTER_EVENT ? $config->delay_days : 0,
            CrmAutomationDomainObjectAbstract::CONFIG => $config->forStorage($trigger),
        ];

        if ($existing !== null) {
            return $this->automationRepository->updateFromArray($existing->getId(), $attributes);
        }

        if ($this->automationRepository->countWhere([CrmAutomationDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id]) >= self::MAX_AUTOMATIONS_PER_ORGANIZER) {
            throw new ResourceConflictException(__('You have reached the maximum number of automations.'));
        }

        return $this->automationRepository->create([
            ...$attributes,
            CrmAutomationDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
            CrmAutomationDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
            CrmAutomationDomainObjectAbstract::TRIGGER => $trigger->value,
            CrmAutomationDomainObjectAbstract::IS_ENABLED => false,
        ]);
    }
}
