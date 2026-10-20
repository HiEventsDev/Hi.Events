<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum AnnouncementStatus
{
    use BaseEnum;

    case DRAFT;
    case PUBLISHED;
}
