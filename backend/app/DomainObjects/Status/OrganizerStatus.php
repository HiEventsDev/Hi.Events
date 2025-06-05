<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum OrganizerStatus
{
    use BaseEnum;

    case DRAFT;
    case LIVE;
    case ARCHIVED;
}
