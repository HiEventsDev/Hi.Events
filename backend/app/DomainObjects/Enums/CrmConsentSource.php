<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmConsentSource: string
{
    use BaseEnum;

    case ORDER = 'ORDER';
    case IMPORT = 'IMPORT';
    case MANUAL = 'MANUAL';
    case UNSUBSCRIBE_LINK = 'UNSUBSCRIBE_LINK';
    case CAMPAIGN = 'CAMPAIGN';
    case CONTACT = 'CONTACT';
    case FORM = 'FORM';
    case SMS_REPLY = 'SMS_REPLY';
}
