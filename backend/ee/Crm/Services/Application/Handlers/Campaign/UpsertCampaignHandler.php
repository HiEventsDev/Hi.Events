<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\CrmMarketingChannel;
use HiEvents\DomainObjects\Enums\CrmSegmentPreset;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\Enterprise\Crm\Exceptions\InvalidEarlyAccessTicketsException;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignTemplateRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Campaign\DTO\UpsertCampaignDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignAudienceService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignHtmlSanitizer;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLookupService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignReportService;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentReferenceDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentDefinitionNormalizer;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentResolver;
use HiEvents\Exceptions\FeatureNotEnabledException;
use HiEvents\Exceptions\PermissionRequiredException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;

class UpsertCampaignHandler
{
    public function __construct(
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly SegmentResolver $segmentResolver,
        private readonly CampaignLookupService $lookupService,
        private readonly CampaignReportService $reportService,
        private readonly CampaignHtmlSanitizer $sanitizer,
        private readonly CrmCampaignTemplateRepositoryInterface $templateRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CampaignAudienceService $audienceService,
        private readonly SegmentDefinitionNormalizer $normalizer,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws InvalidSegmentDefinitionException
     * @throws PermissionRequiredException
     * @throws InvalidEarlyAccessTicketsException
     * @throws FeatureNotEnabledException
     */
    public function handle(UpsertCampaignDTO $dto): CrmCampaignDomainObject
    {
        $existing = $dto->campaign_id === null ? null : $this->lookupService->findForOrganizer($dto->organizer_id, $dto->campaign_id);
        $keepsHiddenAudience = $existing !== null && ! $dto->can_view_financials && $this->audienceService->needsFinancialAccess($existing);

        if ($keepsHiddenAudience && ! $this->isUnchangedHiddenAudience($existing, $dto->segment)) {
            throw new PermissionRequiredException(__('This campaign is sent to people filtered by spend, so only members who can view financials can change who receives it.'));
        }

        $isSms = ($existing === null ? $dto->channel->value : $existing->getChannel()) === CrmMarketingChannel::SMS->value;

        if (! $isSms && $dto->segment->preset === CrmSegmentPreset::SMS_SUBSCRIBERS->value) {
            throw InvalidSegmentDefinitionException::textSubscribersByEmail();
        }

        $audience = $keepsHiddenAudience ? [] : $this->audienceAttributes($dto);

        if ($dto->event_id !== null && $this->eventRepository->findFirstWhere([
            EventDomainObjectAbstract::ID => $dto->event_id,
            EventDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
        ]) === null) {
            throw new ResourceNotFoundException(__('Event not found'));
        }

        $attributes = [
            CrmCampaignDomainObjectAbstract::NAME => trim($dto->name),
            CrmCampaignDomainObjectAbstract::SUBJECT => $isSms || $dto->subject === null ? null : trim($dto->subject),
            CrmCampaignDomainObjectAbstract::PREHEADER => $isSms || $dto->preheader === null || trim($dto->preheader) === '' ? null : trim($dto->preheader),
            CrmCampaignDomainObjectAbstract::BODY => $isSms ? $this->smsBody($dto->body) : $this->sanitizer->sanitize($dto->body),
            ...$audience,
            CrmCampaignDomainObjectAbstract::EVENT_ID => $dto->event_id,
            CrmCampaignDomainObjectAbstract::EARLY_ACCESS_PRODUCT_IDS => $this->earlyAccessProductIds($dto),
        ];

        if ($existing === null) {
            if ($isSms) {
                $this->featureFlagService->assertEnabled(FeatureFlag::CRM_SMS, $dto->account_id);
            }

            $campaign = $this->campaignRepository->create([
                ...$attributes,
                CrmCampaignDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
                CrmCampaignDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
                CrmCampaignDomainObjectAbstract::CREATED_BY_USER_ID => $dto->user_id,
                CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::DRAFT->value,
                CrmCampaignDomainObjectAbstract::CHANNEL => $dto->channel->value,
            ]);

            if ($dto->template_id !== null && ! $isSms) {
                $this->templateRepository->markUsed($dto->organizer_id, $dto->template_id);
            }
        } else {
            if (! $existing->isEditable()) {
                throw new ResourceConflictException(__('This campaign has already been sent and can no longer be edited.'));
            }

            if (! $this->campaignRepository->updateIfEditable($existing->getId(), $attributes)) {
                throw new ResourceConflictException(__('This campaign has already been sent and can no longer be edited.'));
            }

            $campaign = $this->lookupService->findForOrganizer($dto->organizer_id, $existing->getId());
        }

        $this->reportService->attachDetail($campaign, $dto->can_view_financials);

        return $campaign;
    }

    private function smsBody(?string $body): ?string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $body));

        return $text === '' ? null : $text;
    }

    /**
     * @return int[]|null
     *
     * @throws InvalidEarlyAccessTicketsException
     */
    private function earlyAccessProductIds(UpsertCampaignDTO $dto): ?array
    {
        $ids = array_values(array_unique($dto->early_access_product_ids));

        if ($dto->event_id === null || $ids === []) {
            return null;
        }

        $found = $this->productRepository->findWhereIn(ProductDomainObjectAbstract::ID, $ids, [
            ProductDomainObjectAbstract::EVENT_ID => $dto->event_id,
        ]);

        if ($found->count() !== count($ids)) {
            throw new InvalidEarlyAccessTicketsException(__('Choose early access tickets from the linked event.'));
        }

        sort($ids);

        return $ids;
    }

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     */
    private function audienceAttributes(UpsertCampaignDTO $dto): array
    {
        $definition = $dto->segment->definition === null
            ? null
            : $this->segmentResolver->resolveDefinition($dto->organizer_id, $dto->segment, $dto->can_view_financials);

        if ($dto->segment->segment_id !== null || $dto->segment->preset !== null) {
            $this->segmentResolver->resolveDefinition($dto->organizer_id, $dto->segment, $dto->can_view_financials);
        }

        return [
            CrmCampaignDomainObjectAbstract::SEGMENT_ID => $dto->segment->segment_id,
            CrmCampaignDomainObjectAbstract::PRESET_SEGMENT => $dto->segment->segment_id === null ? $dto->segment->preset : null,
            CrmCampaignDomainObjectAbstract::SEGMENT_DEFINITION => $dto->segment->segment_id === null && $dto->segment->preset === null
                ? $definition
                : null,
        ];
    }

    private function isUnchangedHiddenAudience(CrmCampaignDomainObject $campaign, SegmentReferenceDTO $submitted): bool
    {
        if ($campaign->getSegmentId() !== null) {
            return $submitted->segment_id === $campaign->getSegmentId();
        }

        return $submitted->segment_id === null
            && $submitted->preset === null
            && $this->normalizer->isStrippedCopyOf($submitted->definition, $campaign->getSegmentDefinition());
    }
}
