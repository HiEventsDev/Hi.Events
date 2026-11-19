<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum CrmCampaignRecipientStatus: string
{
    use BaseEnum;

    case PENDING = 'PENDING';
    case SENDING = 'SENDING';
    case SENT = 'SENT';
    case FAILED = 'FAILED';
    case SKIPPED = 'SKIPPED';
}
