<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum ProductStatus
{
    use BaseEnum;

    case ACTIVE;
    case INACTIVE;
}
