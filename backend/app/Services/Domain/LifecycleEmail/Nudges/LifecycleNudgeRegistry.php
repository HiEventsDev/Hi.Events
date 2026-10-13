<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\LifecycleEmail\Nudges;

use HiEvents\DomainObjects\Enums\LifecycleNudgeType;

class LifecycleNudgeRegistry
{
    public function __construct(
        private readonly NoEventCreatedNudge $noEventCreated,
        private readonly StripeOnboardingIncompleteNudge $stripeOnboardingIncomplete,
        private readonly EventDraftStaleNudge $eventDraftStale,
        private readonly LiveEventNoSalesNudge $liveEventNoSales,
    ) {}

    public function for(LifecycleNudgeType $type): LifecycleNudgeInterface
    {
        return match ($type) {
            LifecycleNudgeType::NO_EVENT_CREATED => $this->noEventCreated,
            LifecycleNudgeType::STRIPE_ONBOARDING_INCOMPLETE => $this->stripeOnboardingIncomplete,
            LifecycleNudgeType::EVENT_DRAFT_STALE => $this->eventDraftStale,
            LifecycleNudgeType::LIVE_EVENT_NO_SALES => $this->liveEventNoSales,
        };
    }

    /**
     * @return array<int, LifecycleNudgeInterface>
     */
    public function all(): array
    {
        return array_map(fn (LifecycleNudgeType $type) => $this->for($type), LifecycleNudgeType::cases());
    }
}
