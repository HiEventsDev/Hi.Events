<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum CrmSignupFormStatus: string
{
    use BaseEnum;

    case ACTIVE = 'ACTIVE';
    case PAUSED = 'PAUSED';
}
