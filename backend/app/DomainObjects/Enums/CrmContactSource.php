<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmContactSource: string
{
    use BaseEnum;

    case ORDER = 'ORDER';
    case ATTENDEE = 'ATTENDEE';
    case WAITLIST = 'WAITLIST';
    case IMPORT = 'IMPORT';
    case MANUAL = 'MANUAL';
    case FORM = 'FORM';
}
