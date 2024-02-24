<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum MessageStatus
{
    use BaseEnum;

    case PROCESSING;
    case SENT;
    case FAILED;
}
