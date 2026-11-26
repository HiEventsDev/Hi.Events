<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\CrmMarketingChannel;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Exceptions\CampaignOrganizerDeletedException;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms\DTO\PreviewSmsCampaignDraftDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignAudienceService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLookupService;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\CampaignSmsPreviewService;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsCampaignEstimateDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsCampaignEstimator;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsPreviewDTO;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;

class PreviewSmsCampaignDraftHandler
{
    public function __construct(
        private readonly CampaignSmsPreviewService $previewService,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly CampaignLookupService $lookupService,
        private readonly CampaignAudienceService $audienceService,
        private readonly SmsCampaignEstimator $estimator,
    ) {}

    /**
     * @throws CampaignOrganizerDeletedException
     * @throws ResourceNotFoundException
     */
    public function handle(PreviewSmsCampaignDraftDTO $dto): SmsPreviewDTO
    {
        $event = $dto->event_id === null ? null : $this->eventRepository->findFirstWhere([
            EventDomainObjectAbstract::ID => $dto->event_id,
            EventDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
        ]);

        $preview = $this->previewService->preview(
            (new CrmCampaignDomainObject)
                ->setAccountId($dto->account_id)
                ->setOrganizerId($dto->organizer_id)
                ->setChannel(CrmMarketingChannel::SMS->value)
                ->setBody($dto->body)
                ->setEventId($event?->getId())
                ->setEarlyAccessProductIds($event === null || $dto->early_access_product_ids === [] ? null : $dto->early_access_product_ids),
            $dto->viewer,
        );

        return new SmsPreviewDTO(
            variants: $preview->variants,
            uses_placeholders: $preview->uses_placeholders,
            estimate: $this->estimate($dto, $preview),
        );
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function estimate(PreviewSmsCampaignDraftDTO $dto, SmsPreviewDTO $preview): ?SmsCampaignEstimateDTO
    {
        if ($dto->campaign_id === null) {
            return null;
        }

        $campaign = $this->lookupService->findForOrganizer($dto->organizer_id, $dto->campaign_id);

        if (! $campaign->isSms()) {
            throw new ResourceNotFoundException(__('Campaign not found'));
        }

        return ! $dto->can_view_financials && $this->audienceService->needsFinancialAccess($campaign)
            ? null
            : $this->estimator->estimate($campaign, $preview->variants);
    }
}
