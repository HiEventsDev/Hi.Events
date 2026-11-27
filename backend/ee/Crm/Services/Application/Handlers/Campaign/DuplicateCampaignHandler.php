<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\DTO\DuplicateCampaignResultDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignAudienceService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLookupService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignReportService;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentReferenceDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentResolver;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;

class DuplicateCampaignHandler
{
    private const NAME_MAX_LENGTH = 150;

    public function __construct(
        private readonly CampaignLookupService $lookupService,
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly CampaignAudienceService $audienceService,
        private readonly SegmentResolver $segmentResolver,
        private readonly CampaignReportService $reportService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $organizerId, int $campaignId, int $userId, bool $canViewFinancials): DuplicateCampaignResultDTO
    {
        $original = $this->lookupService->findForOrganizer($organizerId, $campaignId);
        $warnings = [];

        [$audience, $audienceWarning] = $this->audience($original, $canViewFinancials);
        if ($audienceWarning !== null) {
            $warnings[] = $audienceWarning;
        }

        $eventExists = $original->getEventId() !== null && $this->eventRepository->findFirstWhere([
            EventDomainObjectAbstract::ID => $original->getEventId(),
            EventDomainObjectAbstract::ORGANIZER_ID => $organizerId,
        ]) !== null;

        if ($original->getEventId() !== null && ! $eventExists) {
            $warnings[] = __('The event the original linked to has been deleted, so the copy isn\'t linked to an event.');
        }

        $copy = $this->campaignRepository->create([
            CrmCampaignDomainObjectAbstract::ACCOUNT_ID => $original->getAccountId(),
            CrmCampaignDomainObjectAbstract::ORGANIZER_ID => $organizerId,
            CrmCampaignDomainObjectAbstract::CREATED_BY_USER_ID => $userId,
            CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::DRAFT->value,
            CrmCampaignDomainObjectAbstract::CHANNEL => $original->getChannel(),
            CrmCampaignDomainObjectAbstract::NAME => mb_substr(__('Copy of :name', ['name' => $original->getName()]), 0, self::NAME_MAX_LENGTH),
            CrmCampaignDomainObjectAbstract::SUBJECT => $original->getSubject(),
            CrmCampaignDomainObjectAbstract::PREHEADER => $original->getPreheader(),
            CrmCampaignDomainObjectAbstract::BODY => $original->getBody(),
            CrmCampaignDomainObjectAbstract::EVENT_ID => $eventExists ? $original->getEventId() : null,
            CrmCampaignDomainObjectAbstract::EARLY_ACCESS_PRODUCT_IDS => $eventExists && $original->getAutomationId() === null && $original->earlyAccessProductIds() !== []
                ? $original->earlyAccessProductIds()
                : null,
            ...$audience,
        ]);

        $campaign = $this->lookupService->findForOrganizer($organizerId, $copy->getId());
        $this->reportService->attachDetail($campaign, $canViewFinancials);

        return new DuplicateCampaignResultDTO(campaign: $campaign, warnings: $warnings);
    }

    /**
     * @return array{0: array<string, mixed>, 1: string|null}
     */
    private function audience(CrmCampaignDomainObject $original, bool $canViewFinancials): array
    {
        $everyone = [
            CrmCampaignDomainObjectAbstract::SEGMENT_ID => null,
            CrmCampaignDomainObjectAbstract::PRESET_SEGMENT => null,
            CrmCampaignDomainObjectAbstract::SEGMENT_DEFINITION => null,
        ];

        if ($original->getAutomationId() !== null) {
            return [$everyone, __('The original was sent by an automation, so the copy goes to everyone who opted in. Choose who should get it before sending.')];
        }

        if (! $canViewFinancials && $this->audienceService->needsFinancialAccess($original)) {
            return [$everyone, __('The original\'s audience filters by spend, which your role can\'t see, so the copy goes to everyone who opted in. Choose who should get it before sending.')];
        }

        try {
            $this->segmentResolver->resolveDefinition($original->getOrganizerId(), new SegmentReferenceDTO(
                segment_id: $original->getSegmentId(),
                preset: $original->getPresetSegment(),
                definition: $original->getSegmentDefinition(),
            ), true);
        } catch (ResourceNotFoundException|InvalidSegmentDefinitionException) {
            return [$everyone, __('The segment the original used no longer exists, so the copy goes to everyone who opted in. Choose who should get it before sending.')];
        }

        return [[
            CrmCampaignDomainObjectAbstract::SEGMENT_ID => $original->getSegmentId(),
            CrmCampaignDomainObjectAbstract::PRESET_SEGMENT => $original->getPresetSegment(),
            CrmCampaignDomainObjectAbstract::SEGMENT_DEFINITION => $original->getSegmentDefinition(),
        ], null];
    }
}
