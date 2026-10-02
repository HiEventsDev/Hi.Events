<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum SeatClaimStatus
{
    use BaseEnum;

    case HELD;
    case SOLD;
    case BLOCKED;
}
