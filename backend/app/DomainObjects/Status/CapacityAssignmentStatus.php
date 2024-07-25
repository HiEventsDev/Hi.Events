<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum CapacityAssignmentStatus
{
    use BaseEnum;

    case ACTIVE;
    case INACTIVE;
}
