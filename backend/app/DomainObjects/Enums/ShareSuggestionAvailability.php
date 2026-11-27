<?php

namespace HiEvents\DomainObjects\Enums;

enum ShareSuggestionAvailability: string
{
    case AVAILABLE = 'AVAILABLE';
    case NOT_CONFIGURED = 'NOT_CONFIGURED';
    case EMAIL_NOT_CONFIRMED = 'EMAIL_NOT_CONFIRMED';
    case PAYMENTS_NOT_CONNECTED = 'PAYMENTS_NOT_CONNECTED';
    case EVENT_UNDER_REVIEW = 'EVENT_UNDER_REVIEW';
}
