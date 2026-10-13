<?php

namespace HiEvents\DomainObjects\Enums;

enum LifecycleNudgeType
{
    use BaseEnum;

    case NO_EVENT_CREATED;
    case STRIPE_ONBOARDING_INCOMPLETE;
    case EVENT_DRAFT_STALE;
    case LIVE_EVENT_NO_SALES;
}
