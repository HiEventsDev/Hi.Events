<?php

declare(strict_types=1);

namespace HiEvents\Resources\LifecycleEmail;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Application\Handlers\Admin\LifecycleEmails\DTO\AdminLifecycleEmailDTO;
use Illuminate\Http\Request;

/**
 * @mixin AdminLifecycleEmailDTO
 */
class AdminLifecycleEmailResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            /** @var 'NO_EVENT_CREATED'|'STRIPE_ONBOARDING_INCOMPLETE'|'EVENT_DRAFT_STALE'|'LIVE_EVENT_NO_SALES' */
            'nudge_type' => $this->template->nudgeType->name,
            'subject' => $this->template->subject,
            'body' => $this->template->body,
            'cta_label' => $this->template->ctaLabel,
            'enabled_at' => $this->template->enabledAt,
            'is_customised' => $this->template->isCustomised,
            'sent_total' => $this->sentTotal,
            'sent_last_30_days' => $this->sentLastThirtyDays,
            'delivered_total' => $this->delivered,
            'bounced_total' => $this->bounced,
            'opened_total' => $this->opened,
            'clicked_total' => $this->clicked,
            'tokens' => $this->tokens,
        ];
    }
}
