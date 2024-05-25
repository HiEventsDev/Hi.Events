<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum UserStatus
{
    use BaseEnum;

    case ACTIVE;
    case INVITED;
    case INACTIVE;
}
