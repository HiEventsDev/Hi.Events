<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum OrganizerStatus: string
{
    use BaseEnum;

    case DRAFT = 'DRAFT';
    case LIVE = 'LIVE';
    case ARCHIVED = 'ARCHIVED';
}
