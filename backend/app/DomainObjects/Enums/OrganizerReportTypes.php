<?php

namespace HiEvents\DomainObjects\Enums;

enum OrganizerReportTypes: string
{
    use BaseEnum;

    case REVENUE_SUMMARY = 'revenue_summary';
    case EVENTS_PERFORMANCE = 'events_performance';
    case TAX_SUMMARY = 'tax_summary';
    case CHECK_IN_SUMMARY = 'check_in_summary';
    case PLATFORM_FEES = 'platform_fees';
}
