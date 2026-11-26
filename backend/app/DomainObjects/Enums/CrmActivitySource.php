<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmActivitySource: string
{
    use BaseEnum;

    case ORDER = 'ORDER';
    case ATTENDEE = 'ATTENDEE';
    case CHECK_IN = 'CHECK_IN';
    case WAITLIST_ENTRY = 'WAITLIST_ENTRY';
    case CAMPAIGN_RECIPIENT = 'CAMPAIGN_RECIPIENT';
    case IMPORT = 'IMPORT';
}
