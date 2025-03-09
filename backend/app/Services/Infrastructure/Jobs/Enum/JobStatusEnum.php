<?php

namespace HiEvents\Services\Infrastructure\Jobs\Enum;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum JobStatusEnum
{
    use BaseEnum;

    case IN_PROGRESS;
    case FINISHED;
    case FAILED;
    case NOT_FOUND;
}
