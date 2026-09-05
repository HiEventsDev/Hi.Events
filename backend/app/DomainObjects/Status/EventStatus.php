<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum EventStatus
{
    use BaseEnum;

    case DRAFT;
    case LIVE;
    case ARCHIVED;
    case PENDING_MANUAL_REVIEW;
}
