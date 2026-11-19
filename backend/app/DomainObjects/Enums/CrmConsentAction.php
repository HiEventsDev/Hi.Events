<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmConsentAction: string
{
    use BaseEnum;

    case OPT_IN = 'OPT_IN';
    case OPT_OUT = 'OPT_OUT';
    case BOUNCED = 'BOUNCED';
    case COMPLAINED = 'COMPLAINED';
    case BOUNCE_CLEARED = 'BOUNCE_CLEARED';
}
