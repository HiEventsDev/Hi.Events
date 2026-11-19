<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentDefinitionNormalizer;
use HiEvents\Resources\BaseResource;

/**
 * @mixin CrmCampaignDomainObject
 */
class CampaignResource extends BaseResource
{
    public function toArray($request): array
    {
        $stats = $this->getDeliveryStats();
        $definition = $this->getSegmentDefinition();
        $hasRestrictedRules = ! $this->isPermitted(Permission::VIEW_FINANCIALS)
            && ($this->getHasFinancialRules() || SegmentDefinitionNormalizer::hasFinancialRules($definition));

        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            /** @var 'EMAIL'|'SMS' */
            'channel' => $this->getChannel(),
            'subject' => $this->getSubject(),
            'preheader' => $this->getPreheader(),
            'body' => $this->getBody(),
            /** @var 'DRAFT'|'SCHEDULED'|'PENDING_REVIEW'|'SENDING'|'SENT'|'PAUSED'|'CANCELLED'|'REJECTED' */
            'status' => $this->getStatus(),
            'status_reason' => $this->getStatusReason(),
            /** @var array{hours: int}|null */
            'status_reason_params' => $this->getStatusReason() === CampaignLaunchService::REASON_SCHEDULED_SEND_EXPIRED
                ? ['hours' => CampaignLaunchService::SCHEDULED_SEND_WINDOW_HOURS]
                : null,
            'review_note' => $this->getReviewNote(),
            'is_editable' => $this->isEditable(),
            'segment_id' => $this->getSegmentId(),
            'preset' => $this->getPresetSegment(),
            'definition' => $hasRestrictedRules && $definition !== null ? SegmentDefinitionNormalizer::withoutFinancialRules($definition) : $definition,
            'has_restricted_rules' => $hasRestrictedRules,
            'segment_name' => $this->getSegmentName(),
            'automation_id' => $this->getAutomationId(),
            'event_id' => $this->getEventId(),
            'event_title' => $this->getEventTitle(),
            /** @var int[] */
            'early_access_product_ids' => $this->earlyAccessProductIds(),
            'promo_code' => $this->getPromoCode(),
            'attribution_window_days' => $this->getAttributionWindowDays(),
            'recipients_count' => $this->getRecipientsCount(),
            'scheduled_at' => $this->getScheduledAt(),
            'sent_at' => $this->getSentAt(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
            'delivery_reports_available' => $this->getDeliveryReportsAvailable(),
            /** @var array<int, array{country: 'US'|'GB'|'IE'|'AU', pending_count: int, next_sending_at: string}> */
            'sms_waiting' => $this->getSmsWaiting(),
            'stats' => [
                'sent' => (int) ($stats->sent ?? 0),
                'pending' => (int) ($stats->pending ?? 0),
                'failed' => (int) ($stats->failed ?? 0),
                'skipped' => (int) ($stats->skipped ?? 0),
                'delivered' => (int) ($stats->delivered ?? 0),
                'opened' => (int) ($stats->opened ?? 0),
                'clicked' => (int) ($stats->clicked ?? 0),
                'bounced' => (int) ($stats->bounced ?? 0),
                'complained' => (int) ($stats->complained ?? 0),
            ],
            $this->mergeWhen($this->isPermitted(Permission::VIEW_FINANCIALS), fn () => [
                'revenue' => $this->getRevenue() ?? [],
            ]),
        ];
    }
}
