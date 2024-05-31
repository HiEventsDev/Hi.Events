<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum EventLifecycleStatus
{
    use BaseEnum;

    case UPCOMING;
    case ENDED;
    case ONGOING;
}
