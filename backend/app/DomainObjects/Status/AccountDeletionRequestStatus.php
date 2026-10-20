<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum AccountDeletionRequestStatus
{
    use BaseEnum;

    case REQUESTED;
    case CANCELLED;
    case COMPLETED;
}
