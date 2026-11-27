<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmAutomationRunOutcome
{
    case SENT;
    case IN_REVIEW;
    case NOBODY_TO_SEND_TO;
    case ALREADY_LAUNCHED;
    case LEFT_FOR_NEXT_RUN;
    case REFUSED;

    public function isSettled(): bool
    {
        return ! in_array($this, [self::LEFT_FOR_NEXT_RUN, self::REFUSED], true);
    }
}
