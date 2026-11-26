<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationConfigDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationFailureDTO;
use HiEvents\Resources\BaseResource;

/**
 * @mixin CrmAutomationDomainObject
 */
class AutomationResource extends BaseResource
{
    public function toArray($request): array
    {
        $config = AutomationConfigDTO::forAutomation($this->resource);
        $failure = AutomationFailureDTO::decode($this->getLastFailureReason());

        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            /** @var 'EVENT_PUBLISHED'|'AFTER_EVENT'|'BEFORE_EVENT'|'ABANDONED_CHECKOUT'|'SEGMENT_ENTERED'|'WIN_BACK'|'WELCOME' */
            'trigger' => $this->getTrigger(),
            'is_enabled' => $this->getIsEnabled(),
            'enabled_at' => $this->getEnabledAt(),
            'subject' => $this->getSubject(),
            'preheader' => $this->getPreheader(),
            'body' => $this->getBody(),
            'segment_id' => $this->getSegmentId(),
            'preset' => $this->getPresetSegment(),
            'delay_days' => $config->delay_days,
            'wait_minutes' => $config->wait_minutes,
            'days_before' => $config->days_before,
            /** @var 'not_purchased'|'purchased' */
            'occurrence_audience' => $config->occurrence_audience,
            'sent_count' => $this->getSentCount(),
            'last_sent_at' => $this->getLastSentAt(),
            /** @var 'NOT_SENDABLE'|'ACCOUNT_NOT_VERIFIED'|'ACCOUNT_NOT_APPROVED'|'TOO_MANY_RECIPIENTS'|'LINKS_NOT_ALLOWED'|'EMPTY_CONTENT'|'INVALID_PLACEHOLDER'|'SEGMENT_DELETED'|'EVENT_AWAITING_REVIEW'|'REVIEW_EXPIRED'|null */
            'last_failure_reason' => $failure?->code,
            /** @var array<string, int|string>|null */
            'last_failure_reason_params' => $failure === null ? null : (object) $failure->params,
        ];
    }
}
